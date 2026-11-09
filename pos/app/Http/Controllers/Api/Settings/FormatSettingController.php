<?php

namespace App\Http\Controllers\Api\Settings;

use App\Models\FormatSetting;

/** Settings > Currency & formats. */
class FormatSettingController extends SettingPageController
{
    protected string $model = FormatSetting::class;

    protected function rules(): array
    {
        return [
            'currency' => 'required|string|max:60',
            'symbol_position' => 'required|in:Before,After',
            'decimal_places' => 'required|in:0,2,3',
            'date_format' => 'required|in:DD/MM/YYYY,MM/DD/YYYY,YYYY-MM-DD',
            'time_24h' => 'required|boolean',
            'week_starts_on' => 'required|in:Monday,Sunday',
            'weight_unit' => 'required|in:kg,lb',
        ];
    }
}
