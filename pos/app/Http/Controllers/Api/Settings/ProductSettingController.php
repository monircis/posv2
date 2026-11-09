<?php

namespace App\Http\Controllers\Api\Settings;

use App\Models\ProductSetting;

/** Settings > Products. */
class ProductSettingController extends SettingPageController
{
    protected string $model = ProductSetting::class;

    protected function rules(): array
    {
        return [
            'auto_sku' => 'required|boolean',
            'sku_prefix' => 'nullable|string|max:10',
            'default_unit' => 'required|in:kg,each,pack,box',
            'low_stock_alert' => 'required|integer|min:0',
            'barcode_type' => 'required|in:EAN-13,Code 128,QR',
            'track_expiry' => 'required|boolean',
            'track_batches' => 'required|boolean',
        ];
    }
}
