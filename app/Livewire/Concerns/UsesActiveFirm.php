<?php

namespace App\Livewire\Concerns;

use App\Models\Company;
use App\Models\Firm;
use App\Models\Workplace;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;

/**
 * Panel pages work on the user's active firm. Pages that change data require it to be active.
 */
trait UsesActiveFirm
{
    #[Computed]
    public function firm(): Firm
    {
        $firm = Auth::user()->activeFirm();

        abort_if($firm === null, 403, 'Yetkili olduğunuz bir firma bulunmuyor.');

        return $firm;
    }

    /**
     * Open a record of another visible firm by switching the panel to that firm.
     */
    protected function followCompanyFirm(Company $company): void
    {
        $this->authorize('view', $company);

        $this->switchToFirmOf($company);
    }

    protected function followWorkplaceFirm(Workplace $workplace): void
    {
        $this->authorize('view', $workplace);

        $this->switchToFirmOf($workplace->company);
    }

    private function switchToFirmOf(Company $company): void
    {
        if ($company->firm_id !== Auth::user()->current_firm_id) {
            Auth::user()->switchFirm($company->firm);
            unset($this->firm);
        }
    }
}
