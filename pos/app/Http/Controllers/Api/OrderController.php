<?php

namespace App\Http\Controllers\Api;

use App\Models\Order;

class OrderController extends CrudController
{
    protected string $model = Order::class;

    protected function rules(): array
    {
        return [
            'no' => 'required|string',
            'ch' => 'required|string',
            'cust' => 'required|string',
            'items' => 'required|array',
        ];
    }
}
