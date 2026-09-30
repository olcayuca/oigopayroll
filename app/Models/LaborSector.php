<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * ÇSGB işkolu. The id is the official işkolu number (1-20).
 *
 * @property int $id
 * @property string $name
 */
#[Fillable(['id', 'name'])]
class LaborSector extends Model
{
    public $incrementing = false;

    public $timestamps = false;
}
