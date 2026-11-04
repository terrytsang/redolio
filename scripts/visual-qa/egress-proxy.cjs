/**
 * Policy-enforcing egress proxy for the source-capture browser.
 *
 * WHY A PROXY: Playwright's route() does not see redirect hops, and Chromium resolves DNS itself, so
 * filtering requests inside the browser cannot stop "public page → 302 → internal address" or DNS
 * rebinding. Instead Chromium is forced (--proxy-server, loopback NOT bypassed, QUIC off) to send ALL
 * traffic through this proxy. For every connection the proxy:
 *   1. refuses non-web ports and obviously internal names,
 *   2. resolves the hostname ONCE,
 *   3. requires EVERY resolved address to be public (see ipPublic — mirrors IpRangeChecker.php),
 *   4. connects to the address it validated (so the connection cannot be rebound after the check).
 * HTTPS is tunnelled end-to-end (CONNECT) — nothing is decrypted or rewritten. Redirects, sub-resources,
 * fetch/XHR and WebSockets all pass through the same gate.
 */
const http = require('http');
const net = require('net');
const dns = require('dns').promises;

// ---------------------------------------------------------------- address policy
const V4_DENY = ['0.0.0.0/8', '10.0.0.0/8', '100.64.0.0/10', '127.0.0.0/8', '169.254.0.0/16', '172.16.0.0/12', '192.0.0.0/24', '192.0.2.0/24', '192.168.0.0/16', '198.18.0.0/15', '198.51.100.0/24', '203.0.113.0/24', '224.0.0.0/4', '240.0.0.0/4'];
const V6_DENY = ['::/128', '::1/128', '::ffff:0:0/96', '64:ff9b::/96', '64:ff9b:1::/48', '100::/64', '2001::/32', '2001:db8::/32', '2002::/16', 'fc00::/7', 'fe80::/10', 'ff00::/8'];
const ALLOWED_PORTS = new Set([80, 443, 8080, 8443]);
const BLOCKED_NAMES = /^(localhost|.*\.localhost|.*\.local|.*\.internal|.*\.localdomain|.*\.lan|.*\.home\.arpa)$/;

function v4ToBig(ip) { return ip.split('.').reduce((a, o) => (a << 8n) + BigInt(parseInt(o, 10)), 0n); }
function v6ToBig(ip) {
    if (ip.includes('.')) { // embedded IPv4 (::ffff:1.2.3.4)
        const i = ip.lastIndexOf(':');
        const v4 = v4ToBig(ip.slice(i + 1));
        ip = ip.slice(0, i + 1) + ((v4 >> 16n) & 0xffffn).toString(16) + ':' + (v4 & 0xffffn).toString(16);
    }
    const [head, rest] = ip.split('::');
    const h = head ? head.split(':') : [];
    const t = rest !== undefined ? (rest ? rest.split(':') : []) : null;
    const groups = t === null ? h : [...h, ...Array(8 - h.length - t.length).fill('0'), ...t];
    return groups.reduce((a, g) => (a << 16n) + BigInt(parseInt(g || '0', 16)), 0n);
}
function inCidr(big, bits, cidr) {
    const [base, len] = cidr.split('/');
    const shift = BigInt(bits - parseInt(len, 10));
    return (big >> shift) === ((bits === 32 ? v4ToBig(base) : v6ToBig(base)) >> shift);
}
function ipPublic(ip) {
    const kind = net.isIP(ip);
    if (kind === 4) { const b = v4ToBig(ip); return !V4_DENY.some((c) => inCidr(b, 32, c)); }
    if (kind === 6) { const b = v6ToBig(ip); return !V6_DENY.some((c) => inCidr(b, 128, c)); }
    return false;
}

/** Resolve once and require every address to be public. @returns {Promise<string|null>} the address to connect to */
async function validatedAddress(host) {
    host = host.replace(/^\[|\]$/g, '').toLowerCase();
    if (BLOCKED_NAMES.test(host)) return null;
    if (net.isIP(host)) return ipPublic(host) ? host : null;
    try {
        const addrs = await dns.lookup(host, { all: true });
        if (!addrs.length || !addrs.every((a) => ipPublic(a.address))) return null;
        return (addrs.find((a) => a.family === 4) || addrs[0]).address;
    } catch { return null; }
}

/**
 * @param {{maxBytes?: number, onBlock?: (what: string) => void}} opts
 * @returns {Promise<{port: number, close: () => void, stats: {bytes: number, blocked: string[]}}>}
 */
function startProxy(opts = {}) {
    const maxBytes = opts.maxBytes || 120 * 1024 * 1024;
    const stats = { bytes: 0, blocked: [] };
    const block = (what) => { if (stats.blocked.length < 50) stats.blocked.push(what); opts.onBlock && opts.onBlock(what); };
    const count = (sock) => sock.on('data', (c) => { stats.bytes += c.length; if (stats.bytes > maxBytes) sock.destroy(); });

    const server = http.createServer(async (req, res) => {
        // Plain-HTTP forward proxy (absolute-URI request line).
        let target;
        try { target = new URL(req.url); } catch { res.writeHead(400).end(); return; }
        const port = parseInt(target.port || '80', 10);
        if (target.protocol !== 'http:' || !ALLOWED_PORTS.has(port)) { block(`${target.hostname}:${port}`); res.writeHead(403).end('blocked'); return; }
        const addr = await validatedAddress(target.hostname);
        if (!addr) { block(target.hostname); res.writeHead(403).end('blocked'); return; }
        const headers = { ...req.headers };
        delete headers['proxy-connection']; delete headers['proxy-authorization'];
        const up = http.request({ host: addr, port, method: req.method, path: target.pathname + target.search, headers: { ...headers, host: target.host }, timeout: 30000 }, (ur) => {
            res.writeHead(ur.statusCode, ur.headers);
            count(ur);
            ur.pipe(res);
        });
        up.on('error', () => { if (!res.headersSent) res.writeHead(502); res.end(); });
        up.on('timeout', () => up.destroy());
        req.pipe(up);
    });

    server.on('connect', async (req, clientSocket, head) => {
        const m = /^(\[[^\]]+\]|[^:]+):(\d+)$/.exec(req.url || '');
        const deny = (what) => { block(what); clientSocket.end('HTTP/1.1 403 Forbidden\r\n\r\n'); };
        if (!m) return deny('malformed');
        const port = parseInt(m[2], 10);
        if (!ALLOWED_PORTS.has(port)) return deny(`${m[1]}:${port}`);
        const addr = await validatedAddress(m[1]);
        if (!addr) return deny(m[1]);
        const upstream = net.connect({ host: addr, port, timeout: 30000 }, () => {
            clientSocket.write('HTTP/1.1 200 Connection Established\r\n\r\n');
            if (head && head.length) upstream.write(head);
            upstream.pipe(clientSocket); clientSocket.pipe(upstream);
        });
        count(upstream);
        upstream.on('error', () => clientSocket.destroy());
        upstream.on('timeout', () => upstream.destroy());
        clientSocket.on('error', () => upstream.destroy());
    });

    return new Promise((resolve) => server.listen(0, '127.0.0.1', () => resolve({
        port: server.address().port,
        close: () => { server.close(); server.closeAllConnections && server.closeAllConnections(); },
        stats,
    })));
}

module.exports = { startProxy, validatedAddress, ipPublic, ALLOWED_PORTS };
