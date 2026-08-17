{{-- "Online Nanny" -- asisten AI berbasis aturan yang sama persis dengan
     yang dipakai app Flutter (OnlineNannyService), sekarang tersedia juga
     di situs web supaya pengalamannya setara di kedua platform. --}}
<style>
    #nannyBubble {
        position: fixed; right: 22px; bottom: 22px; z-index: 9000;
        width: 58px; height: 58px; border-radius: 50%;
        background: linear-gradient(135deg, var(--primary-light), var(--primary));
        display: flex; align-items: center; justify-content: center;
        font-size: 26px; border: none; cursor: pointer;
        box-shadow: 0 10px 24px -6px rgba(53, 93, 219, 0.5);
        animation: nanny-pulse 2.2s ease-in-out infinite;
    }
    #nannyBubble .nanny-dot {
        position: absolute; top: 6px; right: 6px; width: 12px; height: 12px;
        border-radius: 50%; background: #10B981; border: 2px solid #fff;
    }
    @keyframes nanny-pulse {
        0%, 100% { transform: scale(1); }
        50% { transform: scale(1.06); }
    }
    #nannyPanel {
        position: fixed; right: 22px; bottom: 92px; z-index: 9001;
        width: 370px; max-width: calc(100vw - 32px);
        height: 560px; max-height: calc(100vh - 140px);
        background: var(--bg-light);
        border-radius: 22px;
        box-shadow: 0 20px 50px -12px rgba(15, 23, 42, 0.35);
        display: none; flex-direction: column; overflow: hidden;
    }
    #nannyPanel.open { display: flex; }
    .nanny-header {
        background: #fff; border-bottom: 1px solid #E2E8F0;
        padding: 14px 16px; display: flex; align-items: center; gap: 10px;
    }
    .nanny-avatar {
        width: 40px; height: 40px; border-radius: 50%; flex-shrink: 0;
        background: linear-gradient(135deg, var(--primary-light), var(--primary));
        display: flex; align-items: center; justify-content: center; font-size: 19px;
    }
    .nanny-messages { flex: 1; overflow-y: auto; padding: 14px; }
    .nanny-bubble { max-width: 82%; padding: 10px 14px; border-radius: 14px; font-size: 13.5px; line-height: 1.5; margin-bottom: 10px; white-space: pre-line; }
    .nanny-bubble.bot { background: #fff; color: var(--dark); border-bottom-left-radius: 4px; }
    .nanny-bubble.user { background: linear-gradient(135deg, var(--primary-light), var(--primary)); color: #fff; margin-left: auto; border-bottom-right-radius: 4px; }
    .nanny-quick { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 14px; }
    .nanny-quick button {
        border: 1.5px solid var(--primary-light); color: var(--primary);
        background: #fff; border-radius: 20px; padding: 6px 12px; font-size: 12px; font-weight: 600;
    }
    .nanny-suggestions { display: flex; gap: 8px; overflow-x: auto; padding-bottom: 12px; margin-top: -2px; }
    .nanny-suggestion-card { flex: 0 0 auto; width: 128px; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px -2px rgba(15,23,42,0.1); text-decoration: none; color: inherit; }
    .nanny-suggestion-card img { width: 100%; height: 64px; object-fit: cover; display: block; }
    .nanny-suggestion-card .body { padding: 6px 8px; }
    .nanny-suggestion-card .name { font-size: 10.5px; font-weight: 700; line-height: 1.2; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
    .nanny-suggestion-card .price { font-size: 10.5px; font-weight: 700; color: var(--primary); margin-top: 2px; }
    .nanny-typing { display: inline-flex; gap: 3px; background: #fff; padding: 10px 14px; border-radius: 14px; border-bottom-left-radius: 4px; margin-bottom: 10px; }
    .nanny-typing span { width: 6px; height: 6px; border-radius: 50%; background: #94A3B8; animation: nanny-bounce 1s infinite ease-in-out; }
    .nanny-typing span:nth-child(2) { animation-delay: 0.15s; }
    .nanny-typing span:nth-child(3) { animation-delay: 0.3s; }
    @keyframes nanny-bounce { 0%, 60%, 100% { transform: translateY(0); opacity: 0.5; } 30% { transform: translateY(-4px); opacity: 1; } }
    .nanny-input-bar { padding: 10px; background: #fff; border-top: 1px solid #E2E8F0; display: flex; gap: 8px; }
    .nanny-input-bar input { flex: 1; border: 1px solid #E2E8F0; border-radius: 22px; padding: 9px 16px; font-size: 13px; outline: none; }
    .nanny-input-bar input:focus { border-color: var(--primary-light); }
    .nanny-send { width: 40px; height: 40px; border-radius: 50%; border: none; flex-shrink: 0; background: linear-gradient(135deg, var(--primary-light), var(--primary)); color: #fff; display: flex; align-items: center; justify-content: center; }
    .nanny-send:disabled { opacity: 0.5; }

    /* Mode gelap -- panel-nya sendiri sudah adaptif (background: var(--bg-
       light)), tapi semua sub-komponen di dalamnya (header, bubble, tombol
       balasan cepat, kartu saran, input) sengaja pakai putih polos --
       tanpa blok ini, panelnya jadi gelap sedangkan isinya kotak-kotak
       putih random, dan teks yang warnanya adaptif (var(--dark)) jadi
       tidak kebaca di atas putih itu. */
    [data-bs-theme="dark"] .nanny-header { background: #1A2436; border-bottom-color: rgba(255,255,255,0.08); }
    [data-bs-theme="dark"] .nanny-bubble.bot,
    [data-bs-theme="dark"] .nanny-typing,
    [data-bs-theme="dark"] .nanny-quick button,
    [data-bs-theme="dark"] .nanny-suggestion-card { background: #1A2436; }
    [data-bs-theme="dark"] .nanny-quick button { border-color: rgba(255,255,255,0.16); }
    [data-bs-theme="dark"] .nanny-input-bar { background: #1A2436; border-top-color: rgba(255,255,255,0.08); }
    [data-bs-theme="dark"] .nanny-input-bar input { background: #141C2C; border-color: rgba(255,255,255,0.14); color: #E2E8F0; }
</style>

<button id="nannyBubble" type="button" aria-label="Buka chat Online Nanny">
    👵<span class="nanny-dot"></span>
</button>

<div id="nannyPanel" role="dialog" aria-label="Chat Online Nanny">
    <div class="nanny-header">
        <div class="nanny-avatar">👵</div>
        <div class="flex-grow-1">
            <div class="fw-bold" style="font-size: 14px;">Online Nanny</div>
            <div class="small text-muted" style="font-size: 11px;"><i class="bi bi-circle-fill text-success" style="font-size: 6px;"></i> Online, siap bantu</div>
        </div>
        <button type="button" class="btn btn-sm btn-link text-muted p-0" id="nannyClose" aria-label="Tutup chat"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="nanny-messages" id="nannyMessages"></div>
    <form class="nanny-input-bar" id="nannyForm">
        <input type="text" id="nannyInput" placeholder="Tanya Online Nanny..." maxlength="500" autocomplete="off">
        <button type="submit" class="nanny-send" id="nannySend" aria-label="Kirim pesan"><i class="bi bi-send-fill"></i></button>
    </form>
</div>

<script>
(function () {
    var bubble = document.getElementById('nannyBubble');
    var panel = document.getElementById('nannyPanel');
    var closeBtn = document.getElementById('nannyClose');
    var messagesEl = document.getElementById('nannyMessages');
    var form = document.getElementById('nannyForm');
    var input = document.getElementById('nannyInput');
    var sendBtn = document.getElementById('nannySend');
    var csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    var opened = false;
    var sentCount = 0;

    var QUICK_REPLIES = ['Rekomendasiin kos buat aku', 'Kos murah di BSD', 'Cara booking'];

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function scrollToBottom() {
        messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    function addBubble(text, isUser) {
        var div = document.createElement('div');
        div.className = 'nanny-bubble ' + (isUser ? 'user' : 'bot');
        div.textContent = text;
        messagesEl.appendChild(div);
        scrollToBottom();
    }

    function addSuggestions(kosList) {
        if (!kosList || !kosList.length) return;
        var wrap = document.createElement('div');
        wrap.className = 'nanny-suggestions';
        kosList.forEach(function (kos) {
            var a = document.createElement('a');
            a.className = 'nanny-suggestion-card';
            a.href = '/kos/' + kos.id;
            var price = kos.price >= 1000000 ? 'Rp ' + (kos.price / 1000000).toFixed(1) + ' jt' : 'Rp ' + kos.price;
            a.innerHTML = '<img src="' + kos.cover_image + '" alt="" loading="lazy">' +
                '<div class="body"><div class="name">' + escapeHtml(kos.name) + '</div><div class="price">' + price + '</div></div>';
            wrap.appendChild(a);
        });
        messagesEl.appendChild(wrap);
        scrollToBottom();
    }

    function addQuickReplies() {
        var wrap = document.createElement('div');
        wrap.className = 'nanny-quick';
        wrap.id = 'nannyQuick';
        QUICK_REPLIES.forEach(function (q) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.textContent = q;
            btn.addEventListener('click', function () { send(q); });
            wrap.appendChild(btn);
        });
        messagesEl.appendChild(wrap);
        scrollToBottom();
    }

    function removeQuickReplies() {
        var el = document.getElementById('nannyQuick');
        if (el) el.remove();
    }

    function showTyping() {
        var div = document.createElement('div');
        div.id = 'nannyTyping';
        div.className = 'nanny-typing';
        div.innerHTML = '<span></span><span></span><span></span>';
        messagesEl.appendChild(div);
        scrollToBottom();
    }

    function hideTyping() {
        var el = document.getElementById('nannyTyping');
        if (el) el.remove();
    }

    function send(text) {
        text = (text || input.value).trim();
        if (!text) return;
        removeQuickReplies();
        addBubble(text, true);
        input.value = '';
        sendBtn.disabled = true;
        sentCount++;
        showTyping();

        fetch('{{ route('web.chatbot') }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken },
            body: JSON.stringify({ message: text }),
        })
            .then(function (res) {
                if (!res.ok) throw new Error('Gagal memuat balasan');
                return res.json();
            })
            .then(function (data) {
                hideTyping();
                addBubble(data.reply || 'Maaf, aku belum bisa jawab itu.', false);
                addSuggestions(data.kos);
            })
            .catch(function () {
                hideTyping();
                addBubble('Waduh, koneksi ke Online Nanny lagi bermasalah. Coba lagi sebentar ya.', false);
            })
            .finally(function () {
                sendBtn.disabled = false;
            });
    }

    function openPanel() {
        panel.classList.add('open');
        opened = true;
        if (messagesEl.childElementCount === 0) {
            addBubble('Hai! 👋 Aku Online Nanny, siap bantu kamu nyari kos yang nyaman. Mau tanya soal harga, lokasi, fasilitas, atau langsung minta direkomendasikan aja?', false);
            addQuickReplies();
        }
        input.focus();
    }

    bubble.addEventListener('click', function () {
        if (panel.classList.contains('open')) {
            panel.classList.remove('open');
        } else {
            openPanel();
        }
    });
    closeBtn.addEventListener('click', function () { panel.classList.remove('open'); });

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        send();
    });
})();
</script>
