(function () {
    var bar = document.createElement('div');
    bar.id = 'koskita-loading-bar';
    bar.style.cssText =
        'position:fixed;top:0;left:0;height:3px;width:0%;' +
        'background:linear-gradient(90deg,#7091F9,#355DDB);z-index:9999;' +
        'transition:width 0.3s ease, opacity 0.3s ease;opacity:1;';
    document.documentElement.appendChild(bar);

    function setWidth(w) {
        bar.style.width = w + '%';
    }

    function show() {
        bar.style.opacity = '1';
        setWidth(0);
        requestAnimationFrame(function () {
            setWidth(35);
        });
    }

    function finish() {
        setWidth(100);
        setTimeout(function () {
            bar.style.opacity = '0';
            setTimeout(function () {
                setWidth(0);
            }, 300);
        }, 150);
    }

    document.addEventListener('DOMContentLoaded', show);
    window.addEventListener('load', finish);

    document.addEventListener('click', function (e) {
        var a = e.target.closest('a');
        if (a && a.href && !a.target && a.origin === window.location.origin && !a.hasAttribute('download')) {
            show();
        }
    });

    document.addEventListener('submit', function () {
        show();
    });
})();
