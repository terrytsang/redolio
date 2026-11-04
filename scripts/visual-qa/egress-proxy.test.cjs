/**
 * Proves the capture browser cannot reach internal services. Run: node --test scripts/visual-qa/egress-proxy.test.cjs
 * (also run by tests/Feature/SourceCaptureTest.php). A "secret" server on loopback counts every request that
 * reaches it — the assertion in every test is that the count stays at zero.
 */
const test = require('node:test');
const assert = require('node:assert');
const http = require('http');
const net = require('net');
const { startProxy, ipPublic, validatedAddress } = require('./egress-proxy.cjs');

async function secretServer() {
    const hits = [];
    for (const port of [8080, 8443, 80]) { // proxy only permits web ports, so the secret must live on one
        const srv = http.createServer((q, r) => { hits.push(q.url); r.end('INTERNAL SECRET'); });
        const ok = await new Promise((res) => { srv.once('error', () => res(false)); srv.listen(port, '127.0.0.1', () => res(true)); });
        if (ok) return { srv, port, hits };
    }
    return null;
}

function viaProxy(proxyPort, method, rawTarget, hostHeader) {
    return new Promise((resolve) => {
        const s = net.connect(proxyPort, '127.0.0.1', () => {
            s.write(method === 'CONNECT'
                ? `CONNECT ${rawTarget} HTTP/1.1\r\nHost: ${rawTarget}\r\n\r\n`
                : `GET ${rawTarget} HTTP/1.1\r\nHost: ${hostHeader}\r\nConnection: close\r\n\r\n`);
        });
        let buf = '';
        s.on('data', (d) => { buf += d.toString(); });
        s.on('close', () => resolve(buf));
        s.on('error', () => resolve(buf));
        setTimeout(() => { s.destroy(); }, 4000);
    });
}

test('address policy blocks every internal and special-purpose range, allows ordinary public addresses', () => {
    for (const ip of ['127.0.0.1', '10.0.0.5', '172.16.0.1', '192.168.1.1', '169.254.169.254', '100.100.100.200', '192.0.0.192', '198.18.0.1', '224.0.0.1', '0.0.0.0',
        '::1', '::', 'fe80::1', 'fc00::1', 'fd00:ec2::254', 'ff02::1', '::ffff:127.0.0.1', '::ffff:7f00:1', '64:ff9b::7f00:1', '2002:7f00:1::1', '2001:db8::1', 'garbage', '']) {
        assert.strictEqual(ipPublic(ip), false, `${ip} must be blocked`);
    }
    for (const ip of ['8.8.8.8', '1.1.1.1', '93.184.216.34', '2606:4700:4700::1111']) assert.strictEqual(ipPublic(ip), true, `${ip} must be allowed`);
});

test('names and numeric tricks that point inward are refused before any connection', async () => {
    for (const host of ['localhost', 'foo.localhost', 'printer.local', 'db.internal', 'x.localdomain', '127.0.0.1', '[::1]', '2130706433', '0x7f000001', '017700000001', '127.1', '0']) {
        assert.strictEqual(await validatedAddress(host), null, `${host} must be refused`);
    }
});

test('the proxy never lets a client reach an internal server (CONNECT and plain HTTP, every spelling)', async () => {
    const secret = await secretServer();
    const proxy = await startProxy();
    try {
        const sp = secret ? secret.port : 8080;
        const attempts = [
            ['CONNECT', `127.0.0.1:${sp}`], ['CONNECT', `localhost:${sp}`], ['CONNECT', `[::1]:${sp}`], ['CONNECT', `2130706433:${sp}`], ['CONNECT', `0x7f000001:${sp}`],
            ['CONNECT', '169.254.169.254:80'], ['CONNECT', '10.0.0.1:80'], ['CONNECT', '100.100.100.200:80'], ['CONNECT', `127.0.0.1:22`], ['CONNECT', `127.0.0.1:6379`],
            ['GET', `http://127.0.0.1:${sp}/secret`, `127.0.0.1:${sp}`], ['GET', `http://localhost:${sp}/secret`, `localhost:${sp}`], ['GET', 'http://169.254.169.254/latest/meta-data/', '169.254.169.254'],
            ['GET', `http://[::1]:${sp}/secret`, `[::1]:${sp}`], ['GET', 'file:///etc/passwd', 'x'], ['GET', 'gopher://127.0.0.1:6379/_INFO', 'x'],
        ];
        for (const [method, target, host] of attempts) {
            const out = await viaProxy(proxy.port, method, target, host);
            assert.match(out, /^HTTP\/1\.1 (403|400)/, `${method} ${target} must be refused, got: ${out.slice(0, 40)}`);
            assert.ok(!out.includes('INTERNAL SECRET'), `${target} leaked the internal response`);
        }
        if (secret) assert.deepStrictEqual(secret.hits, [], 'the internal server must never have been contacted');
        assert.ok(proxy.stats.blocked.length >= 10, 'blocked attempts are recorded');
    } finally { proxy.close(); secret && secret.srv.close(); }
});

test('only ordinary web ports are reachable', async () => {
    const proxy = await startProxy();
    try {
        for (const port of [22, 25, 3306, 5432, 6379, 9200, 11211]) {
            const out = await viaProxy(proxy.port, 'CONNECT', `93.184.216.34:${port}`);
            assert.match(out, /^HTTP\/1\.1 403/, `port ${port} must be refused`);
        }
    } finally { proxy.close(); }
});

test('a real browser behind the proxy cannot reach internal servers: navigation, images, fetch, iframes, redirects', async (t) => {
    let chromium;
    try { ({ chromium } = require('playwright')); } catch { return t.skip('playwright not installed'); }
    const secret = await secretServer();
    if (!secret) return t.skip('no web port free on loopback for the secret server');
    // A page that tries every internal route; served from a data: URL so only the PROXY policy is under test.
    const proxy = await startProxy();
    let browser;
    try {
        browser = await chromium.launch({ proxy: { server: `http://127.0.0.1:${proxy.port}` }, args: ['--proxy-bypass-list=<-loopback>', '--disable-quic', '--host-resolver-rules=MAP * ~NOTFOUND , EXCLUDE 127.0.0.1'] });
        const ctx = await browser.newContext({ serviceWorkers: 'block' });
        const page = await ctx.newPage();
        const target = `http://127.0.0.1:${secret.port}`;
        await page.goto(target + '/nav', { timeout: 8000 }).catch(() => {});
        await page.goto(`http://localhost:${secret.port}/nav2`, { timeout: 8000 }).catch(() => {});
        await page.setContent(`<img src="${target}/img"><iframe src="${target}/frame"></iframe><link rel="stylesheet" href="${target}/css"><script>fetch("${target}/xhr").catch(()=>{});new Image().src="${target}/beacon";</script>`);
        await page.waitForTimeout(1500);
        assert.deepStrictEqual(secret.hits, [], 'internal server must never be contacted by the browser: ' + JSON.stringify(secret.hits));
        assert.ok(proxy.stats.blocked.length > 0, 'the proxy saw and refused the attempts');
    } finally { if (browser) await browser.close(); proxy.close(); secret.srv.close(); }
});

test('public destinations still work through the proxy (skipped offline)', async (t) => {
    const addr = await validatedAddress('example.com');
    if (!addr) return t.skip('offline');
    const proxy = await startProxy();
    try {
        const out = await viaProxy(proxy.port, 'CONNECT', 'example.com:443');
        assert.match(out, /^HTTP\/1\.1 200 Connection Established/);
    } finally { proxy.close(); }
});
