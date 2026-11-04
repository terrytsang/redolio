/* REDO widgets runtime — progressive enhancement only; every widget works (statically) without this file. */
(function () {
    'use strict';
    // Testimonials carousel
    document.querySelectorAll('[data-bp-carousel]').forEach(function (root) {
        var vp = root.querySelector('.bpw-tcar__viewport'), slides = root.querySelectorAll('.bpw-tcar__slide'), nav = root.querySelector('.bpw-tcar__nav'), dots = root.querySelector('.bpw-tcar__dots');
        if (!vp || slides.length < 2) return;
        nav.hidden = false;
        slides.forEach(function () { dots.appendChild(document.createElement('i')); });
        var cur = 0;
        function mark() { var w = vp.clientWidth || 1; cur = Math.round(vp.scrollLeft / w); dots.querySelectorAll('i').forEach(function (d, i) { d.className = i === cur ? 'on' : ''; }); }
        function go(i) { cur = (i + slides.length) % slides.length; vp.scrollTo({ left: cur * vp.clientWidth, behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' }); }
        root.querySelectorAll('[data-dir]').forEach(function (b) { b.addEventListener('click', function () { go(cur + (+b.dataset.dir)); }); });
        vp.addEventListener('scroll', function () { window.requestAnimationFrame(mark); }, { passive: true });
        vp.addEventListener('keydown', function (e) { if (e.key === 'ArrowRight') go(cur + 1); if (e.key === 'ArrowLeft') go(cur - 1); });
        mark();
        var secs = +root.dataset.autoplay;
        if (secs && !matchMedia('(prefers-reduced-motion: reduce)').matches) {
            var timer = null, start = function () { stop(); timer = setInterval(function () { go(cur + 1); }, secs * 1000); }, stop = function () { if (timer) clearInterval(timer); timer = null; };
            ['mouseenter', 'focusin', 'touchstart'].forEach(function (ev) { root.addEventListener(ev, stop, { passive: true }); });
            ['mouseleave', 'focusout'].forEach(function (ev) { root.addEventListener(ev, start); });
            start();
        }
    });
    // Google Analytics (GA4) / Tag Manager: the loader <script> is in <head>; this configures it.
    var m = document.querySelector('meta[name="redo-analytics"]');
    if (m && m.content) {
        window.dataLayer = window.dataLayer || [];
        if (/^GTM-/.test(m.content)) { window.dataLayer.push({ 'gtm.start': Date.now(), event: 'gtm.js' }); }
        else { window.gtag = function () { window.dataLayer.push(arguments); }; window.gtag('js', new Date()); window.gtag('config', m.content); }
    }
})();
