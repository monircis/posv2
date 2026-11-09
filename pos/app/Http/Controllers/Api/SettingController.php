<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use Illuminate\Http\Request;

/** JSON documents stored by key: the Settings pages, the HRM pay run and Accounting's locked periods. */
class SettingController extends Controller
{
    /** Which screen's permission each key needs. */
    private const MODULE = ['settings' => 'settings', 'payroll' => 'hrm', 'accounting.periods' => 'accounting'];

    public function show(Request $request, string $key)
    {
        $this->authorizeKey($request, $key, 'view');

        return ['key' => $key, 'value' => AppSetting::find($key)?->value];
    }

    public function update(Request $request, string $key)
    {
        $this->authorizeKey($request, $key, 'edit');
        $request->validate(['value' => 'present|array']);
        $setting = AppSetting::updateOrCreate(['key' => $key], ['value' => $request->input('value')]);

        return ['key' => $key, 'value' => $setting->value];
    }

    private function authorizeKey(Request $request, string $key, string $action): void
    {
        abort_unless(isset(self::MODULE[$key]), 404);
        abort_unless($request->user()->hasPermission(self::MODULE[$key].'.'.$action), 403, 'You do not have permission to '.$action.' '.$key.'.');
    }
}
