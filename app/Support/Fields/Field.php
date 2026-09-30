<?php

namespace App\Support\Fields;

use Closure;

/**
 * One column of a bulk-import spreadsheet.
 */
final class Field
{
    public const TEXT = 'text';

    public const SELECT = 'select';

    public const DATE = 'date';

    public const BOOLEAN = 'boolean';

    public const SECRET = 'secret';

    /**
     * @param  string  $key  Key in the mapped row (a model attribute, or a lookup key such as "sector").
     * @param  (Closure(): list<string>)|null  $options  Allowed values offered as a dropdown in Excel.
     * @param  list<string>  $aliases  Other header texts accepted for this column.
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly bool $required = false,
        public readonly string $type = self::TEXT,
        public readonly ?string $hint = null,
        public readonly ?Closure $options = null,
        public readonly array $aliases = [],
    ) {
        //
    }

    /**
     * Get the dropdown values, if any.
     *
     * @return list<string>
     */
    public function options(): array
    {
        return $this->options ? ($this->options)() : [];
    }

    /**
     * Keep only the string entries of a list (e.g. a plucked name column).
     *
     * @param  iterable<mixed>  $values
     * @return list<string>
     */
    public static function strings(iterable $values): array
    {
        $strings = [];

        foreach ($values as $value) {
            if (is_string($value)) {
                $strings[] = $value;
            }
        }

        return $strings;
    }

    /**
     * Get the header text as written in the template.
     */
    public function header(): string
    {
        return $this->required ? $this->label.' *' : $this->label;
    }
}
