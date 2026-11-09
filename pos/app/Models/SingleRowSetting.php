<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Base for a Settings page that is saved in one row (business_settings, tax_settings ...).
 * The first time the page is read, the row is made with the column defaults.
 */
abstract class SingleRowSetting extends Model
{
    protected $hidden = ['id', 'created_at', 'updated_at'];

    /** The one settings row. */
    public static function current(): static
    {
        $setting = static::first();

        if (! $setting) {
            $setting = static::create();
            $setting = $setting->fresh(); // load the column defaults
        }

        return $setting;
    }
}
