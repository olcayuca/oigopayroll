<?php

namespace App\Livewire\Concerns;

use Illuminate\Validation\ValidationException;

/**
 * Show validation errors thrown by actions (keyed "tax_number") on form fields bound as "firmForm.tax_number".
 */
trait MapsValidationErrors
{
    /**
     * Run the callback; on validation failure add its errors under the prefix and return null.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @param  list<string>  $fields  error keys that belong to the prefixed form
     * @return T|null
     */
    protected function mappingErrors(string $prefix, array $fields, callable $callback): mixed
    {
        $this->resetErrorBag();

        try {
            return $callback();
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->addError(in_array($field, $fields, true) ? "{$prefix}.{$field}" : $field, $messages[0]);
            }

            return null;
        }
    }
}
