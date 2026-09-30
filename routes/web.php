<?php

use Illuminate\Support\Facades\Route;

/*
| Each portal lives on its own host (config/portals.php). Auth (Fortify) and settings
| routes are host-agnostic and served on both panels; EnforcePortal decides who may
| use which host.
*/

// siteadi.com: landing page
Route::domain(config('portals.landing'))->group(function () {
    Route::view('/', 'welcome')->name('home');
});

// admin.siteadi.com: HRD
Route::domain(config('portals.admin'))
    ->name('admin.')
    ->middleware(['auth', 'verified'])
    ->group(function () {
        Route::livewire('/', 'pages::admin.dashboard')->name('dashboard');

        Route::livewire('firmalar', 'pages::admin.firms.index')->name('firms.index');
        Route::livewire('firmalar/{firm}', 'pages::admin.firms.show')->name('firms.show');
    });

// panel.siteadi.com: client firms
Route::domain(config('portals.panel'))
    ->middleware(['auth', 'verified'])
    ->group(function () {
        Route::view('/', 'dashboard')->name('dashboard');
    });

require __DIR__.'/settings.php';
