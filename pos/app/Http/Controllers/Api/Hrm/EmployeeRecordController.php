<?php

namespace App\Http\Controllers\Api\Hrm;

use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\EmployeeEducation;
use App\Models\EmployeeExperience;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * HRM > an employee's documents, education and experience.
 * Documents are saved on the private disk (storage/app/private/employee-documents) and downloaded through the API.
 */
#[Group('HRM', weight: 18)]
class EmployeeRecordController extends HrmController
{
    // ---- documents ----

    /** List documents. Needs `hrm.view`. */
    public function documents(Request $request, Employee $employee)
    {
        if (! $this->canSeeEmployee($request, $employee)) {
            return $this->notYourBranch();
        }

        return $employee->documents()->get();
    }

    /**
     * Upload document
     *
     * Form fields: file (PDF, image, Word; up to 10 MB), title, type, expires_on. Needs `hrm.edit`.
     */
    public function storeDocument(Request $request, Employee $employee)
    {
        $data = $request->validate([
            'file' => 'required|file|mimes:pdf,png,jpg,jpeg,webp,doc,docx|max:10240',
            'title' => 'required|string|max:120',
            'type' => 'nullable|string|max:40',
            'expires_on' => 'nullable|date',
        ]);
        if (! $this->canSeeEmployee($request, $employee)) {
            return $this->notYourBranch();
        }
        $file = $request->file('file');
        $document = $employee->documents()->create([
            'title' => $data['title'], 'type' => $data['type'] ?? null, 'expires_on' => $data['expires_on'] ?? null,
            'file' => $file->store('employee-documents/'.$employee->id, 'local'), 'file_name' => $file->getClientOriginalName(),
            'uploaded_by' => $this->by($request),
        ]);

        return response()->json($document, 201);
    }

    /** Download document. Needs `hrm.view`. */
    public function downloadDocument(Request $request, EmployeeDocument $document)
    {
        $employee = Employee::withTrashed()->find($document->employee_id);
        if (! $this->canSeeEmployee($request, $employee)) {
            return $this->notYourBranch();
        }

        return Storage::disk('local')->download($document->file, $document->file_name ?: basename($document->file));
    }

    /** Delete document. Needs `hrm.edit`. */
    public function destroyDocument(Request $request, EmployeeDocument $document)
    {
        $employee = Employee::withTrashed()->find($document->employee_id);
        if (! $this->canSeeEmployee($request, $employee)) {
            return $this->notYourBranch();
        }
        Storage::disk('local')->delete($document->file);
        $document->delete();

        return response()->noContent();
    }

    // ---- education and experience ----

    private const KINDS = [
        'educations' => [EmployeeEducation::class, [
            'degree' => 'required|string|max:120', 'institution' => 'nullable|string|max:160', 'year' => 'nullable|string|max:10', 'result' => 'nullable|string|max:40',
        ]],
        'experiences' => [EmployeeExperience::class, [
            'company' => 'required|string|max:160', 'position' => 'nullable|string|max:120', 'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from', 'notes' => 'nullable|string|max:1000',
        ]],
    ];

    /** List education or experience (kind = educations or experiences). Needs `hrm.view`. */
    public function index(Request $request, Employee $employee, string $kind)
    {
        if (! $this->canSeeEmployee($request, $employee)) {
            return $this->notYourBranch();
        }

        return $employee->$kind()->get();
    }

    /** Add a line of education or experience. Needs `hrm.edit`. */
    public function store(Request $request, Employee $employee, string $kind)
    {
        [, $rules] = self::KINDS[$kind];
        $data = $request->validate($rules);
        if (! $this->canSeeEmployee($request, $employee)) {
            return $this->notYourBranch();
        }

        return response()->json($employee->$kind()->create($data), 201);
    }

    /** Change a line. Needs `hrm.edit`. */
    public function update(Request $request, string $kind, int $id)
    {
        [$class, $rules] = self::KINDS[$kind];
        $record = $class::findOrFail($id);
        $data = $request->validate($rules);
        if (! $this->canSeeEmployee($request, Employee::withTrashed()->find($record->employee_id))) {
            return $this->notYourBranch();
        }
        $record->update($data);

        return $record->fresh();
    }

    /** Delete a line. Needs `hrm.edit`. */
    public function destroy(Request $request, string $kind, int $id)
    {
        [$class] = self::KINDS[$kind];
        $record = $class::findOrFail($id);
        if (! $this->canSeeEmployee($request, Employee::withTrashed()->find($record->employee_id))) {
            return $this->notYourBranch();
        }
        $record->delete();

        return response()->noContent();
    }
}
