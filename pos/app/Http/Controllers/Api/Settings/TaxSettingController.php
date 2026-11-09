<?php

namespace App\Http\Controllers\Api\Settings;

use App\Models\TaxSetting;

/** Settings > Tax > GST. */
class TaxSettingController extends SettingPageController
{
    protected string $model = TaxSetting::class;

    protected function rules(): array
    {
        return [
            'gst_registered' => 'required|boolean',
            'prices_include_tax' => 'required|boolean',
            'bas_period' => 'required|in:Monthly,Quarterly,Annually',
            'tax_rounding' => 'required|in:Per line,Per invoice',
        ];
    }
}
