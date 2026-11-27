(function () {
    var checkboxes = document.querySelectorAll('.compare-checkbox');
    var bar = document.getElementById('compareBar');
    var countEl = document.getElementById('compareCount');
    var btn = document.getElementById('compareBtn');
    var clearBtn = document.getElementById('compareClear');
    if (!checkboxes.length || !bar) return;

    var selected = [];

    function update() {
        if (selected.length >= 2) {
            bar.classList.remove('d-none');
            countEl.textContent = selected.length + ' kos dipilih';
            btn.href = '/bandingkan?ids=' + selected.join(',');
            btn.classList.remove('disabled');
        } else {
            bar.classList.add('d-none');
            countEl.textContent = selected.length + ' kos dipilih';
        }
    }

    checkboxes.forEach(function (cb) {
        cb.addEventListener('change', function () {
            if (cb.checked) {
                // Tidak ada batas jumlah lagi -- tabel perbandingan sudah
                // bisa discroll ke samping (.table-responsive), jadi pilih
                // sebanyak apa pun tetap aman ditampilkan.
                selected.push(cb.value);
            } else {
                selected = selected.filter(function (id) { return id !== cb.value; });
            }
            update();
        });
    });

    clearBtn.addEventListener('click', function () {
        selected = [];
        checkboxes.forEach(function (cb) { cb.checked = false; });
        update();
    });
})();
