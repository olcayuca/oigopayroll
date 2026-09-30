<?php

namespace App\Enums;

/**
 * Audited events, grouped by the prefix before the dot.
 */
enum AuditEvent: string
{
    use HasLabels;

    // Authentication
    case Login = 'auth.login';
    case Logout = 'auth.logout';
    case LoginFailed = 'auth.failed';
    case Lockout = 'auth.lockout';
    case PasswordReset = 'auth.password_reset';
    case SessionTerminated = 'auth.session_terminated';
    case IdleLogout = 'auth.idle_logout';

    // Security
    case IpBlocked = 'security.ip_blocked';
    case SettingsChanged = 'security.settings_changed';
    case CredentialRevealed = 'security.credential_revealed';

    // Firms
    case FirmCreated = 'firm.created';
    case FirmUpdated = 'firm.updated';
    case FirmApproved = 'firm.approved';
    case FirmRejected = 'firm.rejected';
    case FirmDeactivated = 'firm.deactivated';
    case FirmReactivated = 'firm.reactivated';
    case FirmLinked = 'firm.linked';
    case FirmUnlinked = 'firm.unlinked';

    // Users and access
    case UserCreated = 'user.created';
    case UserUpdated = 'user.updated';
    case UserActivated = 'user.activated';
    case UserDeactivated = 'user.deactivated';
    case UserPasswordReset = 'user.password_reset';
    case AccessGranted = 'access.granted';
    case AccessRevoked = 'access.revoked';
    case TemplateChanged = 'access.template_changed';

    // Records
    case CompanyCreated = 'company.created';
    case CompanyUpdated = 'company.updated';
    case CompanyDeleted = 'company.deleted';
    case WorkplaceCreated = 'workplace.created';
    case WorkplaceUpdated = 'workplace.updated';
    case WorkplaceDeleted = 'workplace.deleted';
    case ImportCompleted = 'import.completed';

    // System
    case SystemSettingsChanged = 'system.settings_changed';
    case WebsiteChanged = 'system.website_changed';
    case ParameterChanged = 'system.parameter_changed';

    // KVKK
    case PolicyPublished = 'kvkk.policy_published';
    case ConsentGiven = 'kvkk.consent_given';
    case ConsentRefused = 'kvkk.consent_refused';
    case ConsentRevoked = 'kvkk.consent_revoked';
    case KvkkRequestCreated = 'kvkk.request_created';
    case KvkkRequestUpdated = 'kvkk.request_updated';
    case PersonalDataExported = 'kvkk.data_exported';
    case UserAnonymized = 'kvkk.user_anonymized';

    /**
     * Get the Turkish display label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Login => 'Giriş',
            self::Logout => 'Çıkış',
            self::LoginFailed => 'Başarısız giriş',
            self::Lockout => 'Giriş kilidi (çok fazla deneme)',
            self::PasswordReset => 'Şifre sıfırlandı (e-posta ile)',
            self::SessionTerminated => 'Oturum sonlandırıldı',
            self::IdleLogout => 'Hareketsizlik nedeniyle çıkış',
            self::IpBlocked => 'İzinsiz IP engellendi',
            self::SettingsChanged => 'Güvenlik ayarları değişti',
            self::CredentialRevealed => 'İşyeri şifresi görüntülendi',
            self::FirmCreated => 'Firma oluşturuldu',
            self::FirmUpdated => 'Firma güncellendi',
            self::FirmApproved => 'Firma onaylandı',
            self::FirmRejected => 'Firma reddedildi',
            self::FirmDeactivated => 'Firma pasife alındı',
            self::FirmReactivated => 'Firma aktifleştirildi',
            self::FirmLinked => 'Firmalar arası yetki verildi',
            self::FirmUnlinked => 'Firmalar arası yetki kaldırıldı',
            self::UserCreated => 'Kullanıcı oluşturuldu',
            self::UserUpdated => 'Kullanıcı güncellendi',
            self::UserActivated => 'Kullanıcı aktifleştirildi',
            self::UserDeactivated => 'Kullanıcı pasife alındı',
            self::UserPasswordReset => 'Kullanıcı şifresi sıfırlandı',
            self::AccessGranted => 'Yetki verildi / değişti',
            self::AccessRevoked => 'Yetki kaldırıldı',
            self::TemplateChanged => 'Yetki şablonu değişti',
            self::CompanyCreated => 'Şirket oluşturuldu',
            self::CompanyUpdated => 'Şirket güncellendi',
            self::CompanyDeleted => 'Şirket silindi',
            self::WorkplaceCreated => 'İşyeri oluşturuldu',
            self::WorkplaceUpdated => 'İşyeri güncellendi',
            self::WorkplaceDeleted => 'İşyeri silindi',
            self::ImportCompleted => 'Excel aktarımı tamamlandı',
            self::SystemSettingsChanged => 'Sistem ayarları değişti',
            self::WebsiteChanged => 'Web sitesi içeriği değişti',
            self::ParameterChanged => 'Yasal parametre değişti',
            self::PolicyPublished => 'KVKK metni yayımlandı',
            self::ConsentGiven => 'KVKK metni onaylandı',
            self::ConsentRefused => 'Açık rıza reddedildi',
            self::ConsentRevoked => 'Açık rıza geri alındı',
            self::KvkkRequestCreated => 'KVKK başvurusu yapıldı',
            self::KvkkRequestUpdated => 'KVKK başvurusu güncellendi',
            self::PersonalDataExported => 'Kişisel veri dökümü alındı',
            self::UserAnonymized => 'Kullanıcı anonimleştirildi',
        };
    }

    /**
     * Event group for filtering.
     */
    public function group(): string
    {
        return explode('.', $this->value)[0];
    }

    /**
     * @return array<string, string>
     */
    public static function groups(): array
    {
        return [
            'auth' => 'Giriş / Oturum',
            'security' => 'Güvenlik',
            'firm' => 'Firma',
            'user' => 'Kullanıcı',
            'access' => 'Yetki',
            'company' => 'Şirket',
            'workplace' => 'İşyeri',
            'import' => 'Aktarım',
            'system' => 'Sistem',
            'kvkk' => 'KVKK',
        ];
    }

    /**
     * Badge colour for the event list.
     */
    public function color(): string
    {
        return match ($this) {
            self::LoginFailed, self::Lockout, self::IpBlocked => 'red',
            self::CredentialRevealed, self::SettingsChanged, self::SessionTerminated, self::IdleLogout,
            self::UserDeactivated, self::FirmDeactivated, self::FirmRejected, self::AccessRevoked,
            self::CompanyDeleted, self::WorkplaceDeleted, self::UserPasswordReset, self::ConsentRevoked,
            self::PersonalDataExported, self::UserAnonymized => 'amber',
            self::Login, self::Logout => 'zinc',
            default => 'sky',
        };
    }
}
