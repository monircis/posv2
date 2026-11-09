<?php

namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Api\CrudController;
use App\Models\TaxRate;

/** Settings > Tax > Tax rates. */
class TaxRateController extends CrudController
{
    protected string $model = TaxRate::class;

    protected ?string $orderBy = 'id';

    protected string $direction = 'asc';

    protected function rules(): array
    {
        return [
            'name' => 'required|string|max:60',
            'rate' => 'required|numeric|min:0|max:100',
            'code' => 'nullable|string|max:10',
            'active' => 'boolean',
        ];
    }
}
