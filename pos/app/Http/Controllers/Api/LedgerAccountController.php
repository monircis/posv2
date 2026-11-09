<?php

namespace App\Http\Controllers\Api;

use App\Models\LedgerAccount;

class LedgerAccountController extends CrudController
{
    protected string $model = LedgerAccount::class;

    protected ?string $orderBy = 'code';

    protected string $direction = 'asc';

    protected function rules(): array
    {
        return [
            'code' => 'required|string|max:20',
            'name' => 'required|string',
            'type' => 'required|in:asset,liability,equity,income,expense',
        ];
    }
}
