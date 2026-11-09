<?php

namespace App\Http\Controllers\Api\Settings;

use App\Models\BusinessSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** Settings > Business. The logo is uploaded on its own (uploadLogo / deleteLogo). */
class BusinessSettingController extends SettingPageController
{
    protected string $model = BusinessSetting::class;

    protected function rules(): array
    {
        return [
            'business_name' => 'required|string|max:120',
            'legal_name' => 'nullable|string|max:160',
            'abn' => 'nullable|string|max:20',
            'phone' => 'nullable|string|max:40',
            'email' => 'nullable|email|max:190',
            'address' => 'nullable|string|max:500',
            'brand_colour' => 'required|in:Blue,Green,Maroon,Black',
        ];
    }

    /** POST /api/settings/business/logo with the file in the "logo" form field. The old file is deleted. */
    public function uploadLogo(Request $request)
    {
        $request->validate([
            'logo' => 'required|image|mimes:png,jpg,jpeg,webp|max:2048', // 2 MB
        ]);

        $setting = BusinessSetting::current();
        if ($setting->logo) {
            Storage::disk('public')->delete($setting->logo);
        }

        // Saved as storage/app/public/logos/<random name>.png
        $path = $request->file('logo')->store('logos', 'public');
        $setting->update(['logo' => $path]);

        return $setting->fresh();
    }

    /** DELETE /api/settings/business/logo */
    public function deleteLogo()
    {
        $setting = BusinessSetting::current();
        if ($setting->logo) {
            Storage::disk('public')->delete($setting->logo);
        }
        $setting->update(['logo' => null]);

        return $setting->fresh();
    }
}
