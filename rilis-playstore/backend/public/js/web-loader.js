(function () {
    var loader = document.getElementById('koskitaLoader');
    if (!loader) return;

    var shownAt = Date.now();
    var MIN_VISIBLE_MS = 350; // supaya tidak "kedip" kalau halaman sudah kepakai cache browser

    function hide() {
        var elapsed = Date.now() - shownAt;
        var wait = Math.max(0, MIN_VISIBLE_MS - elapsed);
        setTimeout(function () {
            loader.classList.add('koskita-loader-hidden');
        }, wait);
    }

    function show() {
        shownAt = Date.now();
        loader.classList.remove('koskita-loader-hidden');
    }

    window.addEventListener('load', hide);

    // Kalau load event entah kenapa tidak pernah tembak (jarang, tapi jaga-jaga),
    // jangan biarkan overlay nyangkut selamanya menutupi halaman.
    setTimeout(hide, 4000);

    // Tampilkan lagi overlay pas pindah ke halaman lain di situs yang sama,
    // supaya transisi antar halaman terasa mulus (bukan cuma di kunjungan pertama).
    document.addEventListener('click', function (e) {
        var a = e.target.closest('a');
        if (!a || !a.href || a.target || a.hasAttribute('download')) return;
        if (a.origin !== window.location.origin) return;
        if (a.getAttribute('href').startsWith('#')) return;
        show();
    });

    document.addEventListener('submit', function (e) {
        if (e.target.matches('form')) show();
    });

    // Halaman yang dipulihkan dari bfcache (tombol back/forward) tidak
    // memicu 'load' lagi -- pastikan overlay tidak nyangkut kalau begitu.
    window.addEventListener('pageshow', function (e) {
        if (e.persisted) loader.classList.add('koskita-loader-hidden');
    });

    // Skeleton shimmer utk <img class="img-shimmer"> -- lepas begitu gambar
    // selesai dimuat (atau langsung kalau sudah ada di cache browser).
    document.querySelectorAll('img.img-shimmer').forEach(function (img) {
        function clear() { img.classList.remove('img-shimmer'); }
        if (img.complete && img.naturalWidth > 0) {
            clear();
        } else {
            img.addEventListener('load', clear);
            img.addEventListener('error', clear);
        }
    });

    // Tombol submit form dengan class "js-loading-submit" otomatis nonaktif
    // + ganti jadi spinner pas ditekan -- mencegah submit dobel & kasih
    // feedback instan (booking/review/login/daftar).
    document.querySelectorAll('form.js-loading-submit').forEach(function (form) {
        form.addEventListener('submit', function () {
            var btn = form.querySelector('button[type="submit"]');
            if (!btn || btn.disabled) return;
            btn.dataset.originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Memproses...';
        });
    });
})();
