<?php

namespace App\Concerns;

use App\Models\Firm;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The firm a user is working on in the panel. Everything in the panel
 * (companies, workplaces, payroll) happens in the context of this firm.
 */
trait HasCurrentFirm
{
    /**
     * @return BelongsTo<Firm, $this>
     */
    public function currentFirm(): BelongsTo
    {
        return $this->belongsTo(Firm::class, 'current_firm_id');
    }

    /**
     * Resolve the firm to work on: the remembered one if still visible, otherwise the first visible firm.
     */
    public function activeFirm(): ?Firm
    {
        $firm = $this->current_firm_id !== null
            ? Firm::visibleTo($this)->find($this->current_firm_id)
            : null;

        $firm ??= Firm::visibleTo($this)->orderBy('name')->first();

        if ($firm?->id !== $this->current_firm_id) {
            $this->forceFill(['current_firm_id' => $firm?->id])->save();
        }

        return $firm;
    }

    /**
     * Switch the panel to another firm the user can see.
     */
    public function switchFirm(Firm $firm): void
    {
        if (! $this->canSee($firm)) {
            throw new AuthorizationException('Bu firmaya erişim yetkiniz yok.');
        }

        $this->forceFill(['current_firm_id' => $firm->id])->save();
    }
}
