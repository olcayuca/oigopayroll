<?php

use App\Enums\PolicyType;
use App\Http\Controllers\ShowPolicyDocument;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Route::livewire('settings/profile', 'pages::settings.profile')->name('profile.edit');

    // RequirePolicyConsent sends everyone here until the current KVKK texts are decided on.
    Route::livewire('kvkk/onay', 'pages::kvkk.consent')->name('kvkk.consent');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('settings/appearance', 'pages::settings.appearance')->name('appearance.edit');
    Route::livewire('settings/kvkk', 'pages::settings.kvkk')->name('kvkk.edit');

    Route::livewire('settings/security', 'pages::settings.security')
        ->middleware(
            when(
                Features::canManageTwoFactorAuthentication()
                && Features::optionEnabled(Features::twoFactorAuthentication(), 'confirmPassword'),
                ['password.confirm'],
                [],
            ),
        )
        ->name('security.edit');
});

// Public: current KVKK texts (linked from the landing page, login and the consent screen).
Route::get('kvkk/metin/{type}', ShowPolicyDocument::class)
    ->whereIn('type', array_column(PolicyType::cases(), 'value'))
    ->name('kvkk.document');

Route::get('.well-known/passkey-endpoints', function () {
    return response()->json([
        'enroll' => route('security.edit'),
        'manage' => route('security.edit'),
    ]);
})->name('well-known.passkeys');
