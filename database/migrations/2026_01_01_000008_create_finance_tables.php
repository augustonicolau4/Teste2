<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // -----------------------------------------------------------------
        // Contas bancárias / tesouraria
        // -----------------------------------------------------------------
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('bank_name')->nullable();
            $table->string('iban', 40)->nullable();
            $table->string('account_number', 40)->nullable();
            $table->string('currency', 3)->default('AOA');
            $table->string('type', 20)->default('bank'); // bank|cash|tpa
            $table->decimal('opening_balance', 16, 2)->default(0);
            $table->decimal('current_balance', 16, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // -----------------------------------------------------------------
        // Sessões de caixa (abertura/fecho)
        // -----------------------------------------------------------------
        Schema::create('cash_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('bank_account_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->decimal('opening_balance', 16, 2)->default(0);
            $table->decimal('total_in', 16, 2)->default(0);
            $table->decimal('total_out', 16, 2)->default(0);
            $table->decimal('expected_balance', 16, 2)->default(0);
            $table->decimal('closing_balance', 16, 2)->nullable();
            $table->decimal('difference', 16, 2)->default(0);
            $table->string('status', 20)->default('open'); // open|closed
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'status']);
        });

        // -----------------------------------------------------------------
        // Pagamentos e recebimentos
        // -----------------------------------------------------------------
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('cash_session_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('bank_account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('direction', 10)->default('in'); // in|out
            $table->string('method', 4)->default('NU');     // PaymentMethod
            $table->decimal('amount', 16, 2);
            $table->string('currency', 3)->default('AOA');
            $table->string('reference')->nullable();
            $table->dateTime('paid_at');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['company_id', 'direction', 'paid_at']);
        });

        // -----------------------------------------------------------------
        // Conta corrente (razão de clientes/fornecedores)
        // -----------------------------------------------------------------
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('account_type', 20); // customer|supplier
            $table->unsignedBigInteger('account_id');
            $table->foreignId('document_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('entry_type', 10); // debit|credit
            $table->decimal('amount', 16, 2);
            $table->decimal('balance_after', 16, 2)->default(0);
            $table->string('description')->nullable();
            $table->date('entry_date');
            $table->timestamps();
            $table->index(['account_type', 'account_id', 'entry_date'], 'ledger_account_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('cash_sessions');
        Schema::dropIfExists('bank_accounts');
    }
};
