<?php

namespace App\Livewire;

use App\Livewire\Concerns\MapsValidationErrors;
use App\Livewire\Concerns\UsesActiveFirm;
use Livewire\Component;

/**
 * Base for panel pages that work on the user's active firm.
 */
abstract class PanelComponent extends Component
{
    use MapsValidationErrors, UsesActiveFirm;
}
