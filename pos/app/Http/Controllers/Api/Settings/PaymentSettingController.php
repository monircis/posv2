<?php

namespace App\Http\Controllers\Api\Settings;

use App\Models\PaymentSetting;

/** Settings > Payment methods > Rules. */
class PaymentSettingController extends SettingPageController
{
    protected string $model = PaymentSetting::class;

    protected function rules(): array
    {
        return [
            'card_surcharge' => 'required|numeric|min:0|max:3',
            'round_cash_5c' => 'required|boolean',
            'allow_split_payments' => 'required|boolean',
        ];
    }
}
