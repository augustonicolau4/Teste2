<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // -----------------------------------------------------------------
        // Colaboradores
        // -----------------------------------------------------------------
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('code', 30)->nullable();
            $table->string('name');
            $table->string('nif', 20)->nullable();
            $table->string('inss_number', 30)->nullable();       // Nº de segurança social
            $table->string('position')->nullable();
            $table->string('department')->nullable();
            $table->string('role_type', 20)->default('staff');   // mechanic|promoter|staff|manager
            $table->date('hire_date')->nullable();
            $table->date('termination_date')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('gender', 15)->nullable();
            $table->string('marital_status', 20)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();

            // Remuneração
            $table->decimal('base_salary', 16, 2)->default(0);
            $table->decimal('meal_allowance', 16, 2)->default(0);      // subsídio de alimentação
            $table->decimal('transport_allowance', 16, 2)->default(0); // subsídio de transporte
            $table->decimal('other_allowances', 16, 2)->default(0);
            $table->decimal('inss_employee_rate', 6, 4)->default(0.03);
            $table->decimal('inss_employer_rate', 6, 4)->default(0.08);

            // Dados bancários
            $table->string('bank_name')->nullable();
            $table->string('iban', 40)->nullable();

            // Comissões
            $table->decimal('commission_rate', 6, 4)->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id', 'role_type']);
        });

        // -----------------------------------------------------------------
        // Assiduidade
        // -----------------------------------------------------------------
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->time('check_in')->nullable();
            $table->time('check_out')->nullable();
            $table->decimal('worked_hours', 5, 2)->default(0);
            $table->decimal('overtime_hours', 5, 2)->default(0);
            $table->string('status', 20)->default('present'); // present|absent|late|leave|holiday
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['employee_id', 'date']);
        });

        // -----------------------------------------------------------------
        // Férias e licenças
        // -----------------------------------------------------------------
        Schema::create('leaves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20)->default('annual'); // annual|sick|unpaid|maternity|paternity
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedSmallInteger('days')->default(1);
            $table->text('reason')->nullable();
            $table->string('status', 20)->default('pending'); // pending|approved|rejected
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        // -----------------------------------------------------------------
        // Processamento salarial
        // -----------------------------------------------------------------
        Schema::create('payrolls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('reference', 7); // YYYY-MM
            $table->decimal('base_salary', 16, 2)->default(0);
            $table->unsignedSmallInteger('worked_days')->default(30);
            $table->decimal('overtime_amount', 16, 2)->default(0);
            $table->decimal('meal_allowance', 16, 2)->default(0);
            $table->decimal('transport_allowance', 16, 2)->default(0);
            $table->decimal('other_earnings', 16, 2)->default(0);
            $table->decimal('gross_total', 16, 2)->default(0);
            $table->decimal('taxable_base', 16, 2)->default(0);
            $table->decimal('inss_employee', 16, 2)->default(0);
            $table->decimal('inss_employer', 16, 2)->default(0);
            $table->decimal('irt', 16, 2)->default(0);
            $table->decimal('other_deductions', 16, 2)->default(0);
            $table->decimal('net_salary', 16, 2)->default(0);
            $table->string('status', 20)->default('draft'); // draft|processed|paid|cancelled
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->unique(['employee_id', 'reference']);
        });

        Schema::create('payroll_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20); // earning|deduction
            $table->string('code', 30)->nullable();
            $table->string('description');
            $table->decimal('amount', 16, 2)->default(0);
            $table->boolean('is_taxable')->default(true);
            $table->timestamps();
        });

        // -----------------------------------------------------------------
        // Comissões (técnicos e promotores)
        // -----------------------------------------------------------------
        Schema::create('commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('role', 20); // technician|promoter
            $table->nullableMorphs('source'); // ServiceOrder | Document
            $table->foreignId('document_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('base_amount', 16, 2)->default(0);
            $table->decimal('rate', 6, 4)->default(0);
            $table->decimal('amount', 16, 2)->default(0);
            $table->string('reference_period', 7)->nullable(); // YYYY-MM
            $table->string('status', 20)->default('pending');   // pending|approved|paid|cancelled
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['reference_period', 'role', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commissions');
        Schema::dropIfExists('payroll_items');
        Schema::dropIfExists('payrolls');
        Schema::dropIfExists('leaves');
        Schema::dropIfExists('attendances');
        Schema::dropIfExists('employees');
    }
};
