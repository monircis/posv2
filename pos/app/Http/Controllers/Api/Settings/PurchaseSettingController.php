<?php

namespace App\Http\Controllers\Api\Settings;

use App\Models\PurchaseSetting;

/** Settings > Purchases. */
class PurchaseSettingController extends SettingPageController
{
    protected string $model = PurchaseSetting::class;

    protected function rules(): array
    {
        return [
            'po_prefix' => 'nullable|string|max:10',
            'approval_over' => 'required|integer|min:0',
            'default_supplier_terms' => 'required|in:On delivery,7 days,14 days,30 days',
            'allow_partial_receiving' => 'required|boolean',
            'cost_method' => 'required|in:Last cost,Weighted average',
        ];
    }
}
