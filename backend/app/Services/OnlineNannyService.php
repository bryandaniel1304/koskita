<?php

namespace App\Services;

use App\Models\User;
use App\Models\Kos;

/**
 * "Online Nanny" -- asisten chat berbasis aturan (rule-based) yang menjawab
 * pertanyaan seputar kos, booking, dan rekomendasi dengan mencocokkan kata
 * kunci dari pesan pengguna, lalu menariknya dari data kos & rekomendasi
 * yang sungguhan (bukan LLM/API eksternal berbayar).
 */
class OnlineNannyService
{
    protected array $facilityKeywords = [
        'kamar mandi dalam' => 'KM Dalam',
        'km dalam' => 'KM Dalam',
        'kamar mandi' => 'KM Dalam',
        'ac' => 'AC',
        'wifi' => 'WiFi',
        'internet' => 'WiFi',
        'dapur' => 'Dapur',
        'masak' => 'Dapur',
        'parkir' => 'Parkir',
        'motor' => 'Parkir',
        'mobil' => 'Parkir',
        'laundry' => 'Laundry',
        'cuci baju' => 'Laundry',
    ];

    protected array $locationKeywords = ['karawaci', 'bsd', 'serpong'];

    public function respond(User $user, string $message): array
    {
        $text = strtolower(trim($message));

        // Pemilik kos punya kebutuhan yang beda total dari penyewa (kelola
        // listing & booking masuk, bukan cari/booking kos) -- dialihkan ke
        // jalur pertanyaan khusus pemilik.
        if ($user->role === 'owner') {
            return $this->respondToOwner($user, $text);
        }

        if ($text === '') {
            return $this->reply('Hai! Ada yang bisa aku bantu soal kos? Coba tanya "rekomendasi kos" atau "kos murah di BSD" ya 😊');
        }

        if ($this->matchesAny($text, ['halo', 'hai', 'hi', 'hei', 'pagi', 'siang', 'sore', 'malam', 'permisi'])) {
            return $this->greeting($user);
        }

        if ($this->matchesAny($text, ['terima kasih', 'makasih', 'thanks', 'thx', 'mksh'])) {
            return $this->reply('Sama-sama! 🥰 Kalau butuh apa-apa lagi soal kos, tinggal panggil aku ya~');
        }

        if ($this->matchesAny($text, ['siapa kamu', 'kamu siapa', 'online nanny', 'kamu bot', 'kamu ai'])) {
            return $this->reply("Aku Online Nanny 👵💙, asisten kecil KOSKITA yang senang bantu kamu cari kos yang pas! Tanya-tanya aja soal harga, lokasi, fasilitas, cara booking, atau minta direkomendasikan langsung.");
        }

        if ($this->matchesAny($text, ['rekomendasi', 'rekomendasiin', 'cocok buat aku', 'pilihin', 'sesuai preferensi', 'sesuai profil'])) {
            return $this->recommend($user);
        }

        if ($this->matchesAny($text, ['cara booking', 'cara pesan', 'cara sewa', 'gimana booking', 'gimana cara booking'])) {
            return $this->reply("Gampang banget! 📅\n1. Buka detail kos yang kamu suka\n2. Ketuk tombol \"Ajukan Booking\"\n3. Isi tanggal mulai & durasi sewa\n4. Kirim, tunggu dikonfirmasi admin\n\nStatus booking-mu bisa dicek kapan aja di tab Booking ya!");
        }

        if ($this->matchesAny($text, ['favorit', 'simpan kos', 'wishlist', 'cara favorit'])) {
            return $this->reply('Tinggal ketuk ikon ❤️ di pojok kanan atas halaman detail kos. Semua kos favoritmu ngumpul rapi di tab Favorit 💙');
        }

        $wantsBudget = $this->matchesAny($text, ['murah', 'budget', 'harga', 'mahal', 'hemat', 'juta', 'jt', 'rp']);
        $location = null;
        foreach ($this->locationKeywords as $loc) {
            if (str_contains($text, $loc)) {
                $location = $loc;
                break;
            }
        }
        $facilityName = null;
        foreach ($this->facilityKeywords as $keyword => $name) {
            if (str_contains($text, $keyword)) {
                $facilityName = $name;
                break;
            }
        }
        $gender = null;
        if ($this->matchesAny($text, ['putra', 'cowok', 'pria', 'laki'])) {
            $gender = 'putra';
        } elseif ($this->matchesAny($text, ['putri', 'cewek', 'wanita', 'perempuan'])) {
            $gender = 'putri';
        }

        // Kalau ada lebih dari satu sinyal (mis. "kos murah di Serpong yang ada WiFi"),
        // gabungkan semua filter yang terdeteksi jadi satu pencarian.
        if ($wantsBudget || $location || $facilityName || $gender) {
            return $this->searchCombined($text, $wantsBudget, $location, $facilityName, $gender);
        }

        return $this->fallback();
    }

    /**
     * Jalur pertanyaan khusus akun Penyedia Kos -- fokus ke cara kelola
     * listing, booking masuk, dan fitur rekomendasi penyewa, bukan
     * pencarian/booking kos seperti penyewa.
     */
    protected function respondToOwner(User $user, string $text): array
    {
        $name = explode(' ', $user->name)[0];

        if ($text === '') {
            return $this->reply("Hai, {$name}! 👋 Aku Online Nanny, siap bantu kamu kelola kos di KOSKITA. Mau tanya soal cara tambah kos, respon booking, atau lihat rekomendasi penyewa?");
        }

        if ($this->matchesAny($text, ['halo', 'hai', 'hi', 'hei', 'pagi', 'siang', 'sore', 'malam', 'permisi'])) {
            return $this->reply("Hai, {$name}! 👋 Aku Online Nanny, siap bantu kamu kelola kos di KOSKITA. Mau tanya soal cara tambah kos, respon booking, atau lihat rekomendasi penyewa?");
        }

        if ($this->matchesAny($text, ['terima kasih', 'makasih', 'thanks', 'thx', 'mksh'])) {
            return $this->reply('Sama-sama! 🥰 Kalau ada yang mau ditanyain lagi soal kelola kos, panggil aku ya~');
        }

        if ($this->matchesAny($text, ['siapa kamu', 'kamu siapa', 'online nanny', 'kamu bot', 'kamu ai'])) {
            return $this->reply('Aku Online Nanny 👵💙, asisten kecil KOSKITA yang bantu kamu sebagai pemilik kos -- dari nambah listing, pantau booking, sampai kasih tahu penyewa mana yang paling cocok sama kosmu.');
        }

        if ($this->matchesAny($text, ['belum verifikasi', 'verifikasi email', 'email belum', 'gak bisa tambah', 'tidak bisa tambah', 'terkunci'])) {
            return $this->reply('Beberapa aksi (tambah kos, kelola booking) memang dikunci sampai email kamu diverifikasi dulu, demi keamanan. Cek inbox (atau folder Spam) buat link verifikasinya, atau ketuk "Kirim Ulang" di halaman Profil kalau belum dapat emailnya.');
        }

        if ($this->matchesAny($text, ['tambah kos', 'nambah kos', 'upload kos', 'kos baru', 'daftarin kos', 'daftar kos', 'cara tambah'])) {
            return $this->reply("Gampang! 🏠\n1. Buka tab \"Kos Saya\"\n2. Ketuk tombol \"Tambah Kos\"\n3. Isi nama, harga, tipe, lokasi, fasilitas & aturan\n4. Jangan lupa upload foto kosnya biar menarik!\n5. Ketuk \"Tambah Kos\" buat simpan\n\nKos langsung muncul di daftar kamu dan bisa dilihat calon penyewa.");
        }

        if ($this->matchesAny($text, ['edit kos', 'ubah kos', 'ganti foto', 'hapus foto', 'update kos'])) {
            return $this->reply('Buka detail kos yang mau diubah dari tab "Kos Saya", ketuk ikon titik tiga → Edit. Di situ kamu bisa ganti data, tambah foto baru, atau hapus foto lama.');
        }

        if ($this->matchesAny($text, ['booking', 'pesanan', 'penyewa mau sewa', 'konfirmasi', 'tolak', 'respon'])) {
            return $this->reply("Booking yang masuk bisa kamu lihat di tab \"Booking\" (semua kos) atau di tab \"Booking Masuk\" pas buka detail kos tertentu. Untuk booking berstatus \"Menunggu\", ada tombol Konfirmasi ✅ atau Tolak ❌. Kalau sudah dikonfirmasi, nanti bisa ditandai \"Selesai\" kalau masa sewanya sudah habis.");
        }

        if ($this->matchesAny($text, ['rekomendasi penyewa', 'penyewa cocok', 'cocok sama kos', 'siapa yang cocok', 'calon penyewa'])) {
            return $this->reply('Fitur "Rekomendasi Penyewa" ada di tab kedua pas kamu buka detail salah satu kosmu (setelah "Booking Masuk"). Di situ ditampilkan profil penyewa yang preferensinya (budget, lokasi, fasilitas) paling cocok sama kos kamu, lengkap dengan persentase kecocokannya 🎯');
        }

        return $this->reply("Hmm, aku belum ngerti maksudnya 🤔 Coba tanya soal:\n• \"cara tambah kos\"\n• \"cara respon booking\"\n• \"rekomendasi penyewa\"\n• \"cara edit kos\"");
    }

    protected function greeting(User $user): array
    {
        $name = explode(' ', $user->name)[0];
        return $this->reply("Hai, {$name}! 👋 Aku Online Nanny, siap bantu kamu nyari kos yang nyaman. Mau tanya soal harga, lokasi, fasilitas, atau langsung minta direkomendasikan aja?");
    }

    protected function recommend(User $user): array
    {
        $service = new RecommendationService();
        $result = $service->getRecommendations($user->id, 3);
        $recs = $result['recommendations'];

        if (empty($recs)) {
            return $this->reply('Waduh, belum ada kos yang bisa aku rekomendasikan sekarang. Coba lengkapi dulu profil preferensimu di halaman Profil ya!');
        }

        $names = collect($recs)->map(fn ($r) => $r['kos']->name . ' (' . $r['match_percentage'] . '% cocok)')->implode(', ');
        $text = "Berdasarkan preferensimu, ini yang paling cocok: {$names}. Yuk cek detailnya! 😍";

        return [
            'reply' => $text,
            'kos' => collect($recs)->map(fn ($r) => $this->formatKos($r['kos']))->values()->all(),
        ];
    }

    /**
     * Gabungkan semua sinyal (budget, lokasi, fasilitas, gender) yang
     * terdeteksi dari satu pesan jadi satu query kos, supaya pertanyaan
     * seperti "kos murah di Serpong yang ada WiFi" kena semua filternya
     * sekaligus, bukan cuma salah satu.
     */
    protected function searchCombined(string $text, bool $wantsBudget, ?string $location, ?string $facilityName, ?string $gender): array
    {
        $query = Kos::with('facilities', 'rules', 'images');
        $descriptors = [];

        $max = null;
        $descending = false;
        if ($wantsBudget) {
            if (preg_match('/(\d+(?:[.,]\d+)?)\s*(?:juta|jt)/', $text, $m)) {
                $max = (float) str_replace(',', '.', $m[1]) * 1000000;
                $query->where('price', '<=', $max);
                $descriptors[] = 'di bawah Rp' . number_format($max, 0, ',', '.');
            } else {
                $descending = $this->matchesAny($text, ['mahal', 'premium', 'mewah']);
                $descriptors[] = $descending ? 'paling premium' : 'paling terjangkau';
            }
        }

        if ($location) {
            $query->where('location', 'like', "%{$location}%");
            $descriptors[] = 'di area ' . ucfirst($location);
        }

        if ($facilityName) {
            $query->whereHas('facilities', fn ($q) => $q->where('name', $facilityName));
            $descriptors[] = 'dengan fasilitas ' . $facilityName;
        }

        if ($gender) {
            $query->whereIn('gender_type', [$gender, 'campur']);
            $descriptors[] = 'tipe ' . $gender;
        }

        $query->orderBy('price', $descending ? 'desc' : 'asc');
        $koses = $query->take(3)->get();

        $description = implode(', ', $descriptors);

        if ($koses->isEmpty()) {
            return $this->reply("Waduh, belum nemu kos {$description} nih. Coba longgarkan sedikit kriterianya ya!");
        }

        return [
            'reply' => "Nih, kos {$description} yang aku temukan 🔎",
            'kos' => $koses->map(fn ($k) => $this->formatKos($k))->values()->all(),
        ];
    }

    protected function fallback(): array
    {
        return $this->reply("Hmm, aku belum ngerti maksudnya 🤔 Coba tanya soal:\n• \"rekomendasi kos buat aku\"\n• \"kos murah di Serpong\"\n• \"kos yang ada AC\"\n• \"cara booking\"");
    }

    protected function reply(string $text): array
    {
        return ['reply' => $text, 'kos' => []];
    }

    protected function formatKos(Kos $kos): array
    {
        return [
            'id' => $kos->id,
            'name' => $kos->name,
            'price' => $kos->price,
            'location' => $kos->location,
            'gender_type' => $kos->gender_type,
            'cover_image' => $kos->cover_image,
        ];
    }

    protected function matchesAny(string $text, array $keywords): bool
    {
        foreach ($keywords as $keyword) {
            if (str_contains($text, $keyword)) {
                return true;
            }
        }
        return false;
    }
}
