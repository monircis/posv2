<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\BusinessSetting;
use App\Models\InvoiceSetting;
use App\Models\NotificationSetting;
use App\Models\PaymentMethod;
use App\Models\ProductSetting;
use App\Models\StockSetting;
use App\Models\TaxRate;
use Illuminate\Database\Seeder;

/**
 * Demo values for Settings. Pages not listed here keep their column defaults.
 * Tax rates and payment methods are only added when the table is empty.
 */
class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        BusinessSetting::current()->update([
            'business_name' => 'Madinah Butchery',
            'legal_name' => 'Madinah Halal Meats Pty Ltd',
            'abn' => '51 824 753 556',
            'phone' => '02 9759 4410',
            'email' => 'accounts@madinahbutchery.com.au',
            'address' => "118 Haldon St\nLakemba NSW 2195",
        ]);

        StockSetting::current()->update([
            'default_branch_id' => Branch::where('code', 'main')->value('id'),
        ]);

        InvoiceSetting::current()->update([
            'next_invoice_number' => 3311,
            'footer_text' => "Thank you for shopping with us.\nAll meat is certified halal.",
        ]);

        ProductSetting::current()->update(['sku_prefix' => '66']);

        NotificationSetting::current()->update(['alert_email' => 'aminul@madinahbutchery.com.au']);

        if (TaxRate::count() === 0) {
            TaxRate::create(['name' => 'GST', 'rate' => 10, 'code' => 'GST', 'active' => true]);
            TaxRate::create(['name' => 'GST free', 'rate' => 0, 'code' => 'FRE', 'active' => true]);
            TaxRate::create(['name' => 'Input taxed', 'rate' => 0, 'code' => 'ITS', 'active' => false]);
        }

        if (PaymentMethod::count() === 0) {
            PaymentMethod::create(['name' => 'Cash', 'pays_into' => 'Cash drawer', 'active' => true]);
            PaymentMethod::create(['name' => 'Card · Tyro', 'pays_into' => 'CBA Business', 'active' => true]);
            PaymentMethod::create(['name' => 'Bank transfer', 'pays_into' => 'CBA Business', 'active' => true]);
            PaymentMethod::create(['name' => 'Store credit', 'pays_into' => 'Customer balances', 'active' => true]);
            PaymentMethod::create(['name' => 'Afterpay', 'pays_into' => 'CBA Business', 'active' => false]);
        }
    }
}
