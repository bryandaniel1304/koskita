<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
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
        // Seluruh layout dashboard ini pakai Bootstrap (bukan Tailwind) --
        // tanpa ini, {{ $x->links() }} render kelas utility Tailwind yang
        // tidak berefek apapun karena Tailwind CSS-nya tidak pernah dimuat,
        // jadinya tombol paginasi tampil mentah/berantakan tanpa styling.
        Paginator::useBootstrapFive();
    }
}
