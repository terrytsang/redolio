{{-- Runs before first paint so there is never a flash of the wrong theme. Light is the default. --}}
<script>
    (function () {
        var t = 'light';
        try { t = localStorage.getItem('redo-theme') === 'dark' ? 'dark' : 'light'; } catch (e) {}
        document.documentElement.setAttribute('data-theme', t);
        document.documentElement.style.colorScheme = t;
    })();
</script>
<meta name="color-scheme" content="light dark">
