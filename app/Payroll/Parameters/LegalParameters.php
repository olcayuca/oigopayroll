<?php

namespace App\Payroll\Parameters;

use App\Enums\AuditEvent;
use App\Models\LegalParameter;
use App\Models\User;
use App\Support\Audit;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Read and maintain legal payroll parameters by effective date.
 *
 * The payroll engine asks: "what was X on date D?" → value(X, D).
 */
class LegalParameters
{
    /**
     * @var array<string, Collection<int, LegalParameter>>
     */
    private array $history = [];

    /**
     * Value in force on the given date (null if none was in force yet).
     *
     * @return string|list<array{up_to: string|null, rate: string}>|null
     */
    public function value(string $key, DateTimeInterface|string $date): string|array|null
    {
        return $this->entryAt($key, $date)?->value;
    }

    /**
     * Value in force on the given date; throws if the parameter has no value for that date.
     *
     * @return string|list<array{up_to: string|null, rate: string}>
     */
    public function require(string $key, DateTimeInterface|string $date): string|array
    {
        $value = $this->value($key, $date);

        if ($value === null) {
            $label = ParameterCatalog::find($key)->label ?? $key;

            throw new InvalidArgumentException("\"{$label}\" parametresi ".Carbon::parse($date)->format('d.m.Y').' için tanımlı değil.');
        }

        return $value;
    }

    public function entryAt(string $key, DateTimeInterface|string $date): ?LegalParameter
    {
        $day = Carbon::parse($date)->startOfDay();

        return $this->history($key)->first(fn (LegalParameter $entry) => $entry->effective_from->lte($day));
    }

    /**
     * All entries of a parameter, newest first.
     *
     * @return Collection<int, LegalParameter>
     */
    public function history(string $key): Collection
    {
        return $this->history[$key] ??= LegalParameter::where('key', $key)->orderByDesc('effective_from')->get();
    }

    /**
     * Create or replace the value that starts on $effectiveFrom.
     */
    public function set(string $key, DateTimeInterface|string $effectiveFrom, mixed $value, ?string $source = null, ?string $note = null, ?User $user = null): LegalParameter
    {
        $definition = ParameterCatalog::find($key) ?? throw ValidationException::withMessages(['key' => 'Bilinmeyen parametre.']);
        $normalized = $definition->normalize($value);
        $from = Carbon::parse($effectiveFrom)->startOfDay();

        // whereDate: portable across MySQL DATE and SQLite text dates.
        $entry = LegalParameter::where('key', $key)->whereDate('effective_from', $from->toDateString())->first()
            ?? new LegalParameter(['key' => $key, 'effective_from' => $from->toDateString()]);

        $entry->fill(['value' => $normalized, 'source' => $source ?: null, 'note' => $note ?: null, 'updated_by' => $user?->id])->save();

        unset($this->history[$key]);

        Audit::log(AuditEvent::ParameterChanged, "{$definition->label}: {$from->format('d.m.Y')} itibarıyla {$definition->format($normalized)}", $entry, [
            'key' => $key,
            'effective_from' => $from->toDateString(),
            'value' => $normalized,
        ], $user);

        return $entry;
    }

    public function delete(LegalParameter $entry, ?User $user = null): void
    {
        $definition = $entry->definition();
        $entry->delete();
        unset($this->history[$entry->key]);

        Audit::log(AuditEvent::ParameterChanged, ($definition->label ?? $entry->key).": {$entry->effective_from->format('d.m.Y')} dönemi silindi", null, [
            'key' => $entry->key,
            'effective_from' => $entry->effective_from->toDateString(),
        ], $user);
    }
}
