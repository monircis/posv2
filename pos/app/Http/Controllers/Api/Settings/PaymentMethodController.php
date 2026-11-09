<?php

namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Api\CrudController;
use App\Models\PaymentMethod;

/** Settings > Payment methods > Methods. */
class PaymentMethodController extends CrudController
{
    protected string $model = PaymentMethod::class;

    protected ?string $orderBy = 'id';

    protected string $direction = 'asc';

    protected function rules(): array
    {
        return [
            'name' => 'required|string|max:60',
            'pays_into' => 'nullable|string|max:120',
            'active' => 'boolean',
        ];
    }
}
