<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HRM step 1: employee management.
 * - departments and designations (job titles)
 * - more employee fields: code, sign-in user (for self-service), reporting manager, probation, exit, bank
 * - employee documents, education, experience and status history
 * Nothing is deleted: the old columns (role, dept, type ...) stay and keep working.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->foreignId('branch_id')->nullable()->constrained('branches')->restrictOnDelete(); // null = all branches
            $table->unsignedInteger('manager_id')->nullable(); // the department manager (an employee)
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['name', 'branch_id']);
        });

        Schema::create('designations', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['name', 'department_id']);
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->string('code', 20)->nullable()->unique()->after('id');           // EMP-0001
            $table->string('user_id')->nullable()->unique()->after('code');          // sign-in user (auth service) for self-service
            $table->foreignId('department_id')->nullable()->after('dept')->constrained('departments')->nullOnDelete();
            $table->foreignId('designation_id')->nullable()->after('department_id')->constrained('designations')->nullOnDelete();
            $table->unsignedInteger('reporting_manager_id')->nullable()->after('designation_id');
            $table->string('gender', 10)->nullable()->after('last');                  // Male, Female, Other
            $table->string('marital_status', 20)->nullable()->after('gender');
            $table->string('nationality', 60)->nullable()->after('marital_status');
            $table->string('address')->nullable()->after('email');
            $table->string('city', 80)->nullable()->after('address');
            $table->string('photo')->nullable()->after('city');                       // storage/app/public/employees
            $table->string('emergency_name', 120)->nullable()->after('emerg');
            $table->string('emergency_phone', 40)->nullable()->after('emergency_name');
            $table->string('emergency_relation', 40)->nullable()->after('emergency_phone');
            $table->text('job_description')->nullable()->after('role');
            $table->unsignedTinyInteger('probation_months')->default(0)->after('start');
            $table->date('probation_end')->nullable()->after('probation_months');
            $table->date('confirmation_date')->nullable()->after('probation_end');
            $table->date('notice_date')->nullable()->after('confirmation_date');      // resignation handed in
            $table->date('exit_date')->nullable()->after('notice_date');              // last day
            $table->string('exit_reason')->nullable()->after('exit_date');
            $table->string('bank_name', 80)->nullable();
            $table->string('bank_account_name', 120)->nullable();
            $table->string('bank_bsb', 20)->nullable();
            $table->string('bank_account_number', 40)->nullable();
            $table->softDeletes();
            $table->index(['branch_id', 'status']);
            $table->foreign('reporting_manager_id')->references('id')->on('employees')->nullOnDelete();
        });

        Schema::table('departments', function (Blueprint $table) {
            $table->foreign('manager_id')->references('id')->on('employees')->nullOnDelete();
        });

        // Codes for employees that are already there.
        foreach (DB::table('employees')->whereNull('code')->orderBy('id')->pluck('id') as $id) {
            DB::table('employees')->where('id', $id)->update(['code' => 'EMP-'.str_pad((string) $id, 4, '0', STR_PAD_LEFT)]);
        }

        Schema::create('employee_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('employee_id');
            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
            $table->string('title', 120);
            $table->string('type', 40)->nullable();       // ID, Visa, Contract, Certificate ...
            $table->string('file');                       // storage/app/private/employee-documents
            $table->string('file_name')->nullable();
            $table->date('expires_on')->nullable();
            $table->string('uploaded_by')->nullable();
            $table->timestamps();
        });

        Schema::create('employee_educations', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('employee_id');
            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
            $table->string('degree', 120);
            $table->string('institution', 160)->nullable();
            $table->string('year', 10)->nullable();
            $table->string('result', 40)->nullable();
            $table->timestamps();
        });

        Schema::create('employee_experiences', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('employee_id');
            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
            $table->string('company', 160);
            $table->string('position', 120)->nullable();
            $table->date('date_from')->nullable();
            $table->date('date_to')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('employee_status_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('employee_id');
            $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->date('date');
            $table->string('note')->nullable();
            $table->string('changed_by')->nullable();
            $table->timestamps();
            $table->index(['employee_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_status_histories');
        Schema::dropIfExists('employee_experiences');
        Schema::dropIfExists('employee_educations');
        Schema::dropIfExists('employee_documents');
        Schema::table('departments', function (Blueprint $table) {
            $table->dropForeign(['manager_id']);
        });
        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['reporting_manager_id']);
            $table->dropForeign(['department_id']);
            $table->dropForeign(['designation_id']);
            $table->dropIndex(['branch_id', 'status']);
            $table->dropColumn([
                'code', 'user_id', 'department_id', 'designation_id', 'reporting_manager_id', 'gender', 'marital_status',
                'nationality', 'address', 'city', 'photo', 'emergency_name', 'emergency_phone', 'emergency_relation',
                'job_description', 'probation_months', 'probation_end', 'confirmation_date', 'notice_date', 'exit_date',
                'exit_reason', 'bank_name', 'bank_account_name', 'bank_bsb', 'bank_account_number', 'deleted_at',
            ]);
        });
        Schema::dropIfExists('designations');
        Schema::dropIfExists('departments');
    }
};
