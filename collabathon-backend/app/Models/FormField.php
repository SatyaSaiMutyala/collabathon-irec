<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** Which fields the mobile app renders on each form — the admin Settings toggles. */
#[Fillable(['form', 'field_key', 'label', 'enabled', 'required', 'is_core', 'sort_order'])]
class FormField extends Model
{
    public const FORM_BROKER = 'broker_registration';
    public const FORM_PROPERTY = 'property_listing';

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'required' => 'boolean',
            'is_core' => 'boolean',
        ];
    }
}
