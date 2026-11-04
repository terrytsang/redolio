//

// Light / dark theme. The initial value is applied in <head> (partials/theme-head) before paint.
document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-theme-toggle]');
    if (!btn) return;
    const root = document.documentElement;
    const next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    root.classList.add('theme-ready');
    root.setAttribute('data-theme', next);
    root.style.colorScheme = next;
    try { localStorage.setItem('redo-theme', next); } catch (err) { /* private mode: the choice just lasts for this page */ }
    document.dispatchEvent(new CustomEvent('redo:theme', { detail: next }));
});
