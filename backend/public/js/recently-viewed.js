// Riwayat "Terakhir Dilihat" -- disimpan di localStorage BROWSER, bukan
// server, jadi tidak butuh endpoint/tabel baru. Pola & key sengaja identik
// konsepnya dengan versi mobile (SharedPreferences) supaya konsisten:
// terbaru selalu di depan, duplikat dihapus (bukan digandakan), maks 15.
window.KoskitaRecent = (function () {
    var KEY = 'koskita_recently_viewed_v1';
    var MAX = 15;

    function getAll() {
        try {
            var raw = localStorage.getItem(KEY);
            if (!raw) return [];
            var parsed = JSON.parse(raw);
            return Array.isArray(parsed) ? parsed : [];
        } catch (e) {
            return [];
        }
    }

    function track(kos) {
        try {
            var list = getAll().filter(function (k) { return k.id !== kos.id; });
            list.unshift(kos);
            if (list.length > MAX) list = list.slice(0, MAX);
            localStorage.setItem(KEY, JSON.stringify(list));
        } catch (e) {
            // "Nice to have" -- localStorage penuh/diblokir tidak boleh ganggu halaman.
        }
    }

    return { getAll: getAll, track: track };
})();
