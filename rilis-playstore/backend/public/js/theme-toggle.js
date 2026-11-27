(function () {
    var btn = document.getElementById('themeToggle');
    var icon = document.getElementById('themeToggleIcon');
    if (!btn || !icon) return;

    function applyIcon(theme) {
        icon.className = theme === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-stars-fill';
    }

    applyIcon(document.documentElement.getAttribute('data-bs-theme') || 'light');

    btn.addEventListener('click', function () {
        var current = document.documentElement.getAttribute('data-bs-theme') || 'light';
        var next = current === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-bs-theme', next);
        localStorage.setItem('koskita-theme', next);
        applyIcon(next);
    });
})();
