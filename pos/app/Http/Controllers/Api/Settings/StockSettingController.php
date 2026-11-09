<?php

namespace App\Http\Controllers\Api\Settings;

use App\Models\StockSetting;

/** Settings > Branches & locations > Defaults. */
class StockSettingController extends SettingPageController
{
    protected string $model = StockSetting::class;

    protected function rules(): array
    {
        return [
            'default_branch_id' => 'nullable|exists:branches,id',
            'track_stock_per_branch' => 'required|boolean',
        ];
    }
}
