<?php

namespace App\Enums;

use App\Support\Text;

/**
 * Shared helpers for enums that expose a Turkish display label.
 */
trait HasLabels
{
    abstract public function label(): string;

    /**
     * Get all labels keyed by value.
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    /**
     * Resolve a case from its value or its label (case-insensitive, Turkish aware).
     */
    public static function fromLabelOrValue(?string $input): ?self
    {
        if ($input === null || trim($input) === '') {
            return null;
        }

        $needle = Text::key($input);

        foreach (self::cases() as $case) {
            if ($needle === Text::key($case->value) || $needle === Text::key($case->label())) {
                return $case;
            }
        }

        return null;
    }
}
