<?php

namespace App\Providers;

use App\Domain\Pendaftaran\Models\PendaftaranTte;
use App\Domain\Pendaftaran\Observers\AuditObserver;
use App\Domain\Sertifikat\Models\SertifikatTte;
use App\Policies\PendaftaranTtePolicy;
use App\Policies\SertifikatTtePolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
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
        // Otomatis paksa HTTPS jika diakses lewat HTTPS Tunnel/Proxy, SSL termination, atau di production
        if (
            !$this->app->runningInConsole() && (
                app()->environment('production') ||
                (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
                (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ||
                (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
            )
        ) {
            URL::forceScheme('https');
        }

        // Observer Transaksi Pendaftaran TTE
        PendaftaranTte::observe(AuditObserver::class);

        // Binding Kebijakan Otorisasi (Policies)
        Gate::policy(PendaftaranTte::class, PendaftaranTtePolicy::class);
        Gate::policy(SertifikatTte::class, SertifikatTtePolicy::class);

        // Share active SKPDs to admin layout for export modal filters
        \Illuminate\Support\Facades\View::composer('layouts.admin', function ($view) {
            $view->with('sharedSkpdList', \App\Domain\Master\Models\Skpd::where('is_active', true)->orderBy('nama_skpd')->get());
        });
    }
}
