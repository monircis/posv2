<?php

namespace App\Http\Controllers\Api\Settings;

use App\Models\CoinSetting;

/** Settings > Loyalty coins. */
class CoinSettingController extends SettingPageController
{
    protected string $model = CoinSetting::class;

    protected function rules(): array
    {
        return [
            'enabled' => 'required|boolean',
            'earn_per_dollar' => 'required|numeric|min:0|max:100',
            'coin_value' => 'required|numeric|min:0.0001|max:100',
            'min_redeem' => 'required|integer|min:0',
            'max_redeem_percent' => 'required|integer|min:0|max:100',
        ];
    }
}
