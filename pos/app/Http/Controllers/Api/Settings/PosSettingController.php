<?php

namespace App\Http\Controllers\Api\Settings;

use App\Models\PosSetting;

/** Settings > Sales & POS. */
class PosSettingController extends SettingPageController
{
    protected string $model = PosSetting::class;

    protected function rules(): array
    {
        return [
            'default_customer' => 'required|in:Walk-in customer,Ask every sale',
            'allow_below_stock' => 'required|boolean',
            'max_discount_percent' => 'required|integer|min:0|max:100',
            'refund_needs_pin' => 'required|boolean',
            'use_scale' => 'required|boolean',
            'search_keypad' => 'required|boolean',
            'require_register' => 'required|boolean',
            'print_receipt' => 'required|in:Always,Ask,Never',
            'clear_held_sales_after' => 'required|in:2 hours,End of day,Never',
        ];
    }
}
