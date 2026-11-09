<?php

namespace App\Http\Controllers\Api;

use App\Models\Journal;

class JournalController extends CrudController
{
    protected string $model = Journal::class;

    protected function rules(): array
    {
        return [
            'no' => 'required|string',
            'date' => 'required|string',
            'desc' => 'required|string',
            'lines' => 'required|array|min:2',
        ];
    }
}
