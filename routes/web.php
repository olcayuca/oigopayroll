<?php

use App\Exports\ExportType;
use App\Http\Controllers\DownloadFirmDocument;
use App\Http\Controllers\DownloadFirmTemplate;
use App\Http\Controllers\DownloadImportTemplate;
use App\Http\Controllers\DownloadPersonalData;
use App\Http\Controllers\DownloadReport;
use App\Http\Controllers\ExportAuditLog;
use App\Http\Controllers\ExportFirmRecords;
use App\Http\Controllers\ImpersonationController;
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

        Route::livewire('belge-takibi', 'pages::admin.documents.index')->name('documents.index');
        Route::livewire('sozlesmeler', 'pages::admin.contracts.index')->name('contracts.index');
        Route::livewire('kullanicilar', 'pages::admin.users.index')->name('users.index');
        Route::livewire('kullanicilar/{user}', 'pages::admin.users.show')->name('users.show');
        Route::livewire('uzman-dagilimi', 'pages::admin.specialists.index')->name('specialists.index');
        Route::livewire('yetki-sablonlari', 'pages::admin.permission-templates.index')->name('permission-templates.index');

        Route::livewire('ayarlar', 'pages::admin.settings.index')->name('settings.index');
        Route::livewire('web-sitesi', 'pages::admin.website.index')->name('website.index');
        Route::livewire('duyurular', 'pages::admin.announcements.index')->name('announcements.index');
        Route::livewire('guvenlik', 'pages::admin.security.index')->name('security.index');
        Route::livewire('sistem-sagligi', 'pages::admin.system.health')->name('system.health');
        Route::livewire('yasal-parametreler', 'pages::admin.parameters.index')->name('parameters.index');
        Route::livewire('bordro-kodlari', 'pages::admin.codes.index')->name('codes.index');
        Route::livewire('calisma-takvimi', 'pages::admin.holidays.index')->name('holidays.index');
        Route::livewire('kvkk', 'pages::admin.kvkk.index')->name('kvkk.index');
        Route::livewire('cop-kutusu', 'pages::trash.index')->name('trash.index');
        Route::livewire('raporlar', 'pages::admin.reports.index')->name('reports.index');
        Route::get('raporlar/indir/{report}', DownloadReport::class)
            ->whereIn('report', array_column(ExportType::adminReports(), 'value'))->name('reports.download');
        Route::get('kvkk/basvurular/{kvkkRequest}/veri', DownloadPersonalData::class)->name('kvkk.export');
    });

// panel.siteadi.com: destek görünümü hand-over (token from the admin portal) and its end
Route::domain(config('portals.panel'))->group(function () {
    Route::get('destek/{token}', [ImpersonationController::class, 'start'])->middleware('throttle:20,1')->name('impersonation.start');
    Route::post('destek/bitir', [ImpersonationController::class, 'stop'])->middleware('auth')->name('impersonation.stop');
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

        Route::livewire('kurulum', 'pages::panel.setup.wizard')->name('setup.wizard');
        Route::livewire('islem-gecmisi', 'pages::panel.audit.index')->name('audit.index');
        Route::get('islem-gecmisi/excel', ExportAuditLog::class)->name('audit.export');
        Route::livewire('tanimlar', 'pages::panel.definitions.index')->name('definitions.index');

        Route::livewire('personel', 'pages::panel.employees.index')->name('employees.index');
        Route::livewire('personel/yeni', 'pages::panel.employees.form')->name('employees.create');
        Route::livewire('personel/{employee}', 'pages::panel.employees.show')->name('employees.show');
        Route::livewire('personel/{employee}/duzenle', 'pages::panel.employees.form')->name('employees.edit');

        Route::livewire('cop-kutusu', 'pages::trash.index')->name('trash.index');
        Route::livewire('belgeler', 'pages::panel.documents.index')->name('documents.index');
        Route::get('disa-aktar/{type}', ExportFirmRecords::class)->whereIn('type', ['sirketler', 'isyerleri', 'personel'])->name('exports.download');

        Route::livewire('kullanicilar', 'pages::panel.users.index')->name('users.index');
        Route::livewire('firma-erisimleri', 'pages::panel.firm-access.index')->name('firm-access.index');

        Route::livewire('aktarim/{type}', 'pages::panel.imports.upload')->name('imports.create');
        Route::get('aktarim/{type}/sablon', DownloadImportTemplate::class)->name('imports.template');
    });

// Both panels: firm document download (FirmPolicy::viewDocuments, audited).
Route::get('belgeler/{document}/indir', DownloadFirmDocument::class)->middleware(['auth', 'verified'])->name('documents.download');

require __DIR__.'/settings.php';
