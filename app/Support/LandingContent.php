<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Landing page (siteadi.com) content, edited from Admin → Web Sitesi and stored in settings.
 */
final class LandingContent
{
    public const KEY = 'landing';

    public const MAX_SERVICES = 8;

    /**
     * @return array{
     *     hero_title: string, hero_subtitle: string, hero_cta: string,
     *     services_title: string, services: list<array{title: string, text: string}>,
     *     about_title: string, about_text: string,
     *     meta_title: string, meta_description: string
     * }
     */
    public static function defaults(): array
    {
        return [
            'hero_title' => 'Bordro süreçleriniz tek platformda',
            'hero_subtitle' => 'Şirket, işyeri ve çalışan bilgilerinizi güvenle yönetin; bordrolarınıza her an erişin.',
            'hero_cta' => 'Hemen Başvurun',
            'services_title' => 'Hizmetlerimiz',
            'services' => [
                ['title' => 'Bordro Hizmeti', 'text' => 'Uzman bordro ekibimizle aylık bordro hesaplama ve raporlama.'],
                ['title' => 'SGK ve Vergi Bildirgeleri', 'text' => 'Bildirge ve beyannamelerin zamanında ve eksiksiz hazırlanması.'],
                ['title' => 'Çalışan Portalı', 'text' => 'Çalışanlarınız bordrolarını ve kişisel bilgilerini online görüntüler.'],
            ],
            'about_title' => 'Hakkımızda',
            'about_text' => 'HRD, işletmelere uçtan uca bordro ve insan kaynakları hizmeti sunar.',
            'meta_title' => '',
            'meta_description' => '',
        ];
    }

    /**
     * Stored content merged over the defaults.
     *
     * @return array<string, mixed>
     */
    public static function get(): array
    {
        $stored = Setting::get(self::KEY, []);

        return array_merge(self::defaults(), is_array($stored) ? $stored : []);
    }

    /**
     * @param  array<string, mixed>  $content
     */
    public static function save(array $content): void
    {
        Setting::putMany([self::KEY => $content]);
    }
}
