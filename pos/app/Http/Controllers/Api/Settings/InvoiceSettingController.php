<?php

namespace App\Http\Controllers\Api\Settings;

use App\Models\InvoiceSetting;

/** Settings > Invoice & receipt. */
class InvoiceSettingController extends SettingPageController
{
    protected string $model = InvoiceSetting::class;

    protected function rules(): array
    {
        return [
            'invoice_prefix' => 'nullable|string|max:10',
            'next_invoice_number' => 'required|integer|min:1',
            'receipt_prefix' => 'nullable|string|max:10',
            'paper' => 'required|in:80mm roll,A4',
            'show_logo' => 'required|boolean',
            'show_abn' => 'required|boolean',
            'payment_terms' => 'required|in:Due on receipt,7 days,14 days,30 days',
            'footer_text' => 'nullable|string|max:500',
        ];
    }
}
