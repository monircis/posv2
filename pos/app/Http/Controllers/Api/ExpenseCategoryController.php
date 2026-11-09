<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExpenseCategory;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Expenses > Categories. */
#[Group('Expense Categories', 'The categories an expense can have.', weight: 14)]
class ExpenseCategoryController extends Controller
{
    /**
     * List categories
     *
     * Sorted by name. `expenses_count` = expenses using it. Also returns the `colors` to choose from.
     * Needs `expenses.view`.
     */
    public function index()
    {
        return [
            'data' => ExpenseCategory::withCount('expenses')->orderBy('name')->get(),
            'colors' => ExpenseCategory::COLORS,
        ];
    }

    /**
     * Add category
     *
     * Names must be unique. Needs `expenses.create`.
     */
    public function store(Request $request)
    {
        $category = ExpenseCategory::create($request->validate($this->rules()));

        return response()->json($category->fresh(), 201);
    }

    /**
     * Update category
     *
     * A new name also shows on the expenses that use it. Send all fields. Needs `expenses.edit`.
     */
    public function update(Request $request, ExpenseCategory $expenseCategory)
    {
        $expenseCategory->update($request->validate($this->rules($expenseCategory)));

        return $expenseCategory->fresh();
    }

    private function rules(?ExpenseCategory $category = null): array
    {
        return [
            'name' => ['required', 'string', 'max:80', Rule::unique('expense_categories')->ignore($category)],
            'color' => ['required', Rule::in(ExpenseCategory::COLORS)],
            'active' => 'boolean',
        ];
    }
}
