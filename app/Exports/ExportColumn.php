<?php

namespace App\Exports;

use Closure;

/**
 * One column of an Excel export.
 */
final class ExportColumn
{
    public const TEXT = 'text';

    public const NUMBER = 'number';

    public const DATE = 'date';

    public const DATETIME = 'datetime';

    /**
     * @param  Closure(mixed): mixed  $value  Reads the cell value from a record.
     */
    public function __construct(
        public readonly string $label,
        public readonly Closure $value,
        public readonly string $type = self::TEXT,
    ) {
        //
    }
}
