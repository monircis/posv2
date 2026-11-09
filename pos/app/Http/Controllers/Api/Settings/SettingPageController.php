<?php

namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * Show / update for one Settings page that is saved in one row.
 * A page controller only names its model and its validation rules.
 * Permissions are checked by the routes (settings.view, settings.edit).
 */
abstract class SettingPageController extends Controller
{
    /** @var class-string<\App\Models\SingleRowSetting> */
    protected string $model;

    protected function rules(): array
    {
        return [];
    }

    public function show()
    {
        return $this->model::current();
    }

    public function update(Request $request)
    {
        $data = $request->validate($this->rules());

        $setting = $this->model::current();
        $setting->update($data);

        return $setting->fresh();
    }
}
