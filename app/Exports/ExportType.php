<?php

namespace App\Exports;

use App\Enums\AuditEvent;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Firm;
use App\Models\User;
use App\Models\Workplace;
use App\Support\Fields\CompanyFields;
use App\Support\Fields\Field;
use App\Support\Fields\WorkplaceFields;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Excel reports. Company / workplace columns follow the import templates, minus every
 * credential column: encrypted values never leave the system through an export.
 */
enum ExportType: string
{
    case Firms = 'firmalar';
    case Users = 'kullanicilar';
    case Companies = 'sirketler';
    case Workplaces = 'isyerleri';
    case AuditLogs = 'islem-kayitlari';

    public function label(): string
    {
        return match ($this) {
            self::Firms => 'Firmalar',
            self::Users => 'Kullanıcılar',
            self::Companies => 'Şirketler',
            self::Workplaces => 'İşyerleri',
            self::AuditLogs => 'İşlem Kayıtları',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Firms => 'Tüm firmalar: durum, sorumlu uzman, şirket ve işyeri sayıları.',
            self::Users => 'Tüm kullanıcılar: tip, firma, durum, iki adımlı doğrulama, son giriş.',
            self::Companies => 'Tüm şirketler, içe aktarma şablonundaki sütunlarla.',
            self::Workplaces => 'Tüm işyerleri, şifre alanları hariç.',
            self::AuditLogs => 'Seçilen tarih aralığındaki işlem ve güvenlik kayıtları.',
        };
    }

    public function filename(): string
    {
        return $this->value.'-'.now()->format('Y-m-d-His').'.xlsx';
    }

    /**
     * @param  bool  $withFirm  add the firm column (admin reports span every firm)
     * @return list<ExportColumn>
     */
    public function columns(bool $withFirm = true): array
    {
        $firm = $withFirm ? [new ExportColumn('Firma', fn ($record) => match (true) {
            $record instanceof Company => $record->firm->name,
            $record instanceof Workplace => $record->company->firm->name,
            default => null,
        })] : [];

        return match ($this) {
            self::Firms => [
                new ExportColumn('Firma Adı', fn (Firm $firm) => $firm->name),
                new ExportColumn('Unvan', fn (Firm $firm) => $firm->title),
                new ExportColumn('Vergi No', fn (Firm $firm) => $firm->tax_number),
                new ExportColumn('Vergi Dairesi', fn (Firm $firm) => $firm->tax_office),
                new ExportColumn('Durum', fn (Firm $firm) => $firm->status),
                new ExportColumn('Kaynak', fn (Firm $firm) => $firm->source),
                new ExportColumn('Üst Firma', fn (Firm $firm) => $firm->parent?->name),
                new ExportColumn('Sorumlu Uzman', fn (Firm $firm) => $firm->specialist?->name),
                new ExportColumn('Şirket Sayısı', fn (Firm $firm) => $firm->getAttribute('companies_count'), ExportColumn::NUMBER),
                new ExportColumn('İşyeri Sayısı', fn (Firm $firm) => $firm->getAttribute('workplaces_count'), ExportColumn::NUMBER),
                new ExportColumn('Yetkili Kişi', fn (Firm $firm) => $firm->contact_name),
                new ExportColumn('Telefon', fn (Firm $firm) => $firm->phone),
                new ExportColumn('E-posta', fn (Firm $firm) => $firm->email),
                new ExportColumn('Adres', fn (Firm $firm) => $firm->address),
                new ExportColumn('Oluşturulma', fn (Firm $firm) => $firm->created_at, ExportColumn::DATETIME),
            ],
            self::Users => [
                new ExportColumn('Ad Soyad', fn (User $user) => $user->name),
                new ExportColumn('E-posta', fn (User $user) => $user->email),
                new ExportColumn('Tip', fn (User $user) => $user->type),
                new ExportColumn('Firma', fn (User $user) => $user->homeFirm?->name),
                new ExportColumn('Durum', fn (User $user) => $user->is_active ? 'Aktif' : 'Pasif'),
                new ExportColumn('İki Adımlı Doğrulama', fn (User $user) => $user->two_factor_confirmed_at !== null),
                new ExportColumn('Yetki Sayısı', fn (User $user) => $user->getAttribute('access_grants_count'), ExportColumn::NUMBER),
                new ExportColumn('Son Giriş', fn (User $user) => $user->getAttribute('last_login_at') ? Carbon::parse($user->getAttribute('last_login_at')) : null, ExportColumn::DATETIME),
                new ExportColumn('Kayıt Tarihi', fn (User $user) => $user->created_at, ExportColumn::DATETIME),
            ],
            self::Companies => [...$firm, ...self::fieldColumns(CompanyFields::all(), fn (Company $company, string $key) => match ($key) {
                'sector' => $company->sector->name,
                default => $company->getAttribute($key),
            })],
            self::Workplaces => [...$firm, ...self::fieldColumns(WorkplaceFields::all(), fn (Workplace $workplace, string $key) => match ($key) {
                'company_no' => $workplace->company->company_no,
                'risk_class' => $workplace->riskClass?->name,
                'labor_sector' => $workplace->laborSector?->name,
                'province_name' => $workplace->provinceLabel(),
                'district_name' => $workplace->districtLabel(),
                default => $workplace->getAttribute($key),
            })],
            self::AuditLogs => [
                new ExportColumn('Tarih', fn (AuditLog $log) => $log->created_at, ExportColumn::DATETIME),
                new ExportColumn('Kullanıcı', fn (AuditLog $log) => $log->user?->name),
                new ExportColumn('E-posta', fn (AuditLog $log) => $log->user?->email),
                new ExportColumn('İşlem', fn (AuditLog $log) => $log->event),
                new ExportColumn('Açıklama', fn (AuditLog $log) => $log->description),
                new ExportColumn('Portal', fn (AuditLog $log) => $log->portal),
                new ExportColumn('IP', fn (AuditLog $log) => $log->ip_address),
            ],
        };
    }

    /**
     * The whole report: every record of the type.
     *
     * @return Builder<covariant \Illuminate\Database\Eloquent\Model>
     */
    public function query(): Builder
    {
        return match ($this) {
            self::Firms => $this->prepare(Firm::query()),
            self::Users => $this->prepare(User::query()),
            self::Companies => $this->prepare(Company::query()),
            self::Workplaces => $this->prepare(Workplace::query()),
            self::AuditLogs => $this->prepare(AuditLog::query()),
        };
    }

    /**
     * Eager load what the columns read, and order the rows.
     *
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function prepare(Builder $query): Builder
    {
        return match ($this) {
            self::Firms => $query->with(['parent', 'specialist'])->withCount(['companies', 'workplaces'])->orderBy('name'),
            self::Users => $query->with('homeFirm')->withCount('accessGrants')
                ->addSelect(['last_login_at' => AuditLog::query()->selectRaw('max(created_at)')
                    ->whereColumn('audit_logs.user_id', 'users.id')->where('event', AuditEvent::Login)])
                ->orderBy('name'),
            self::Companies => $query->with(['firm', 'sector'])->orderBy('company_no'),
            self::Workplaces => $query->with(['company.firm', 'riskClass', 'laborSector', 'province', 'district'])
                ->orderBy('company_id')->orderBy('workplace_no'),
            self::AuditLogs => $query->with('user')->latest('id'),
        };
    }

    /**
     * @param  list<Field>  $fields
     * @param  \Closure(mixed, string): mixed  $read
     * @return list<ExportColumn>
     */
    private static function fieldColumns(array $fields, \Closure $read): array
    {
        $columns = [];

        foreach ($fields as $field) {
            if ($field->type === Field::SECRET || in_array($field->key, Workplace::SECRET_FIELDS, true)) {
                continue;
            }

            $columns[] = new ExportColumn(
                $field->label,
                fn ($record) => $read($record, $field->key),
                $field->type === Field::DATE ? ExportColumn::DATE : ExportColumn::TEXT,
            );
        }

        return $columns;
    }
}
