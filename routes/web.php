<?php

use App\Http\Controllers\DownloadFirmTemplate;
use App\Http\Controllers\DownloadImportTemplate;
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
        Route::livewire('firmalar/excel', 'pages::admin.firms.import')->name('firms.import');
        Route::get('firmalar/excel/sablon', DownloadFirmTemplate::class)->name('firms.template');
        Route::livewire('firmalar/{firm}', 'pages::admin.firms.show')->name('firms.show');

        Route::livewire('kullanicilar', 'pages::admin.users.index')->name('users.index');
        Route::livewire('kullanicilar/{user}', 'pages::admin.users.show')->name('users.show');
        Route::livewire('yetki-sablonlari', 'pages::admin.permission-templates.index')->name('permission-templates.index');

        Route::livewire('ayarlar', 'pages::admin.settings.index')->name('settings.index');
        Route::livewire('web-sitesi', 'pages::admin.website.index')->name('website.index');
    });

// panel.siteadi.com: client firms
Route::domain(config('portals.panel'))
    ->middleware(['auth', 'verified'])
    ->group(function () {
        Route::livewire('/', 'pages::panel.dashboard')->name('dashboard');

        Route::livewire('sirketler', 'pages::panel.companies.index')->name('companies.index');
        Route::livewire('sirketler/yeni', 'pages::panel.companies.form')->name('companies.create');
        Route::livewire('sirketler/{company}', 'pages::panel.companies.show')->name('companies.show');
        Route::livewire('sirketler/{company}/duzenle', 'pages::panel.companies.form')->name('companies.edit');

        Route::livewire('isyerleri', 'pages::panel.workplaces.index')->name('workplaces.index');
        Route::livewire('isyerleri/yeni', 'pages::panel.workplaces.form')->name('workplaces.create');
        Route::livewire('isyerleri/{workplace}', 'pages::panel.workplaces.show')->name('workplaces.show');
        Route::livewire('isyerleri/{workplace}/duzenle', 'pages::panel.workplaces.form')->name('workplaces.edit');

        Route::livewire('kullanicilar', 'pages::panel.users.index')->name('users.index');
        Route::livewire('firma-erisimleri', 'pages::panel.firm-access.index')->name('firm-access.index');

        Route::livewire('aktarim/{type}', 'pages::panel.imports.upload')->name('imports.create');
        Route::get('aktarim/{type}/sablon', DownloadImportTemplate::class)->name('imports.template');
    });

require __DIR__.'/settings.php';
