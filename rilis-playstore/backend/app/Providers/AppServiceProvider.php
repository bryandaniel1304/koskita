<?php

namespace App\Providers;

use App\Models\Booking;
use App\Models\Kos;
use App\Models\Report;
use App\Observers\BookingObserver;
use App\Observers\KosObserver;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // App ini cuma load Bootstrap (bukan Tailwind), jadi pagination
        // view bawaan Laravel (Tailwind) perlu diganti supaya ->links()
        // tampil rapi -- sama seperti fix di research-dashboard.
        Paginator::useBootstrapFive();

        // Notifikasi "kamar tersedia lagi" untuk daftar tunggu -- dipasang
        // sebagai observer (bukan dipanggil manual dari tiap controller
        // yang mengubah status booking/total_rooms) supaya SEMUA jalur
        // (Admin, Owner web, Owner API, tenant batalkan booking) otomatis
        // ikut memicu tanpa risiko ada yang lupa. Lihat WaitlistService.
        Booking::observe(BookingObserver::class);
        Kos::observe(KosObserver::class);

        // Badge jumlah laporan menunggu di sidebar admin -- dipasang lewat
        // composer (bukan tiap controller kirim variabel sendiri-sendiri)
        // supaya otomatis tampil konsisten di semua halaman admin tanpa
        // perlu diingat-ingat tiap tambah controller baru.
        View::composer('layouts.admin', function ($view) {
            $view->with('pendingReportsCount', Report::where('status', 'pending')->count());
        });

        // Ganti isi email verifikasi bawaan Laravel (Inggris) ke Bahasa
        // Indonesia -- pakai hook resmi toMailUsing() supaya logika
        // pembuatan signed URL-nya (verification.verify, sudah dipakai di
        // routes/web.php) tetap dari Laravel sendiri, cuma teksnya yang
        // diganti. Tema warna & logo email sudah otomatis ikut brand
        // KosKita lewat resources/views/vendor/mail/html/themes/default.css.
        VerifyEmail::toMailUsing(function (object $notifiable, string $url) {
            return (new MailMessage)
                ->subject('Verifikasi Email KosKita')
                ->greeting('Halo, ' . $notifiable->name . '!')
                ->line('Terima kasih sudah mendaftar di KosKita. Konfirmasi dulu alamat emailmu supaya bisa mengajukan booking dan memberi ulasan.')
                ->action('Verifikasi Email', $url)
                ->line('Kalau kamu tidak merasa mendaftar akun KosKita, abaikan saja email ini.');
        });
    }
}
