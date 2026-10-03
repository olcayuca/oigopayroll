<?php

namespace App\Support;

use App\Models\AccessGrant;
use App\Models\Company;
use App\Models\DataImport;
use App\Models\Definition;
use App\Models\Employee;
use App\Models\Firm;
use App\Models\FirmContract;
use App\Models\FirmDocument;
use App\Models\Workplace;
use Illuminate\Database\Eloquent\Model;

/**
 * Where an audited action belongs: firm / company (şirket) / workplace (şube).
 */
final class AuditScope
{
    /**
     * @return array{firm_id: int|null, company_id: int|null, workplace_id: int|null}
     */
    public static function of(?Model $model): array
    {
        $none = ['firm_id' => null, 'company_id' => null, 'workplace_id' => null];

        return match (true) {
            $model instanceof Firm => [...$none, 'firm_id' => $model->id],
            $model instanceof Company => [...$none, 'firm_id' => $model->firm_id, 'company_id' => $model->id],
            $model instanceof Workplace => [
                'firm_id' => $model->company()->withTrashed()->value('firm_id'),
                'company_id' => $model->company_id,
                'workplace_id' => $model->id,
            ],
            $model instanceof Employee => ['firm_id' => $model->firm_id, 'company_id' => $model->company_id, 'workplace_id' => $model->workplace_id],
            $model instanceof Definition, $model instanceof DataImport, $model instanceof FirmContract => [...$none, 'firm_id' => $model->firm_id],
            $model instanceof FirmDocument => [...$none, 'firm_id' => $model->firm_id, 'company_id' => $model->company_id],
            $model instanceof AccessGrant => self::of($model->scopeModel()),
            default => $none,
        };
    }

    public static function resolves(?Model $model): bool
    {
        return self::of($model)['firm_id'] !== null;
    }
}
