<?php

namespace App\Notifications;

use App\Enums\HasLabels;

/**
 * Groups of notifications a user can switch on or off per channel (Panel → Ayarlar → Bildirimler).
 * "System" (KVKK answers, admin alerts) cannot be turned off.
 */
enum NotificationCategory: string
{
    use HasLabels;

    case Setup = 'setup';
    case Documents = 'documents';
    case Support = 'support';
    case Reminders = 'reminders';
    case System = 'system';

    public function label(): string
    {
        return match ($this) {
            self::Setup => 'Kurulum ve firma durumu',
            self::Documents => 'Belge süreleri',
            self::Support => 'Destek talepleri',
            self::Reminders => 'Hatırlatıcılarım',
            self::System => 'Sistem bildirimleri',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Setup => 'Kurulum onayı, firma başvurusu ve sorumlu uzman ataması',
            self::Documents => 'Firma belgelerinin süresi dolarken ve dolduğunda',
            self::Support => 'Talep alındı, yanıtlandı ve durum değişiklikleri',
            self::Reminders => 'Asistandan eklediğiniz hatırlatıcıların zamanı geldiğinde',
            self::System => 'KVKK başvuruları ve zorunlu bilgilendirmeler',
        };
    }

    /**
     * @return list<self>
     */
    public static function configurable(): array
    {
        return [self::Setup, self::Documents, self::Support, self::Reminders];
    }
}
