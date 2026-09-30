<?php

namespace App\Kvkk;

use App\Enums\AuditEvent;
use App\Models\AccessGrant;
use App\Models\AuditLog;
use App\Models\Consent;
use App\Models\KvkkRequest;
use App\Models\User;
use App\Support\Audit;

/**
 * Everything the system holds about a user, for a "veri kopyası" application (KVKK md. 11/1-b).
 * Secrets (password hash, 2FA secrets, passkey keys) are never included.
 */
class PersonalDataExport
{
    /**
     * @return array<string, mixed>
     */
    public function for(User $user, ?User $actor = null): array
    {
        $data = [
            'olusturma_tarihi' => now()->toIso8601String(),
            'kullanici' => [
                'ad_soyad' => $user->name,
                'e_posta' => $user->email,
                'kullanici_tipi' => $user->type->label(),
                'aktif' => $user->is_active,
                'firma' => $user->homeFirm?->name,
                'e_posta_dogrulama' => $user->email_verified_at?->toIso8601String(),
                'iki_adimli_dogrulama' => $user->two_factor_confirmed_at !== null,
                'kayit_tarihi' => $user->created_at?->toIso8601String(),
            ],
            'yetkiler' => AccessGrant::query()->where('user_id', $user->id)->get()
                ->map(fn (AccessGrant $grant) => [
                    'kapsam' => $grant->scopeModel()?->getAttribute('name') ?? $grant->scopeModel()?->getAttribute('title'),
                    'izinler' => $grant->permissions,
                    'tarih' => $grant->created_at?->toIso8601String(),
                ])->all(),
            'kvkk_onaylari' => Consent::query()->with('document')->where('user_id', $user->id)->get()
                ->map(fn (Consent $consent) => [
                    'metin' => $consent->document->type->label().' v'.$consent->document->version,
                    'karar' => $consent->accepted ? 'onay' : 'ret',
                    'tarih' => $consent->decided_at->toIso8601String(),
                    'geri_alma' => $consent->revoked_at?->toIso8601String(),
                    'ip' => $consent->ip_address,
                ])->all(),
            'kvkk_basvurulari' => KvkkRequest::query()->where('user_id', $user->id)->get()
                ->map(fn (KvkkRequest $request) => [
                    'no' => $request->id,
                    'konu' => $request->type->shortLabel(),
                    'durum' => $request->status->label(),
                    'aciklama' => $request->message,
                    'yanit' => $request->response,
                    'tarih' => $request->created_at?->toIso8601String(),
                ])->all(),
            'islem_kayitlari' => AuditLog::query()->where('user_id', $user->id)->latest('id')->limit(1000)->get()
                ->map(fn (AuditLog $log) => [
                    'tarih' => $log->created_at->toIso8601String(),
                    'islem' => $log->event->label(),
                    'aciklama' => $log->description,
                    'ip' => $log->ip_address,
                    'tarayici' => $log->user_agent,
                ])->all(),
        ];

        Audit::log(AuditEvent::PersonalDataExported, "Kişisel veri dökümü alındı: {$user->email}", $user, [], $actor);

        return $data;
    }
}
