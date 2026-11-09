<?php

namespace App\Models;

use Illuminate\Support\Facades\Storage;

/** Settings > Business. One row: use BusinessSetting::current(). */
class BusinessSetting extends SingleRowSetting
{
    protected $table = 'business_settings';

    protected $fillable = [
        'business_name', 'legal_name', 'abn', 'phone', 'email', 'address', 'logo', 'brand_colour',
    ];

    // Sent with the row so the frontend can show the image.
    protected $appends = ['logo_url'];

    /** Full address of the logo file, e.g. http://127.0.0.1:8001/storage/logos/abc.png, or null. */
    public function getLogoUrlAttribute()
    {
        if (! $this->logo) {
            return null;
        }

        return Storage::disk('public')->url($this->logo);
    }
}
