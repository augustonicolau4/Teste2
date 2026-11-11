<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // -----------------------------------------------------------------
        // Clientes
        // -----------------------------------------------------------------
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('promoter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('code', 30)->nullable();
            $table->string('type', 20)->default('individual'); // individual|company|fleet
            $table->string('name');
            $table->string('trade_name')->nullable();
            $table->string('nif', 20)->nullable()->index();
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->string('country', 2)->default('AO');
            $table->string('contact_person')->nullable();
            $table->decimal('credit_limit', 16, 2)->default(0);
            $table->decimal('balance', 16, 2)->default(0); // saldo conta corrente
            $table->string('payment_terms', 30)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['company_id', 'name']);
        });

        // -----------------------------------------------------------------
        // Frotas (agrupamento de viaturas de um cliente)
        // -----------------------------------------------------------------
        Schema::create('fleets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('code', 30)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // -----------------------------------------------------------------
        // Viaturas
        // -----------------------------------------------------------------
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fleet_id')->nullable()->constrained()->nullOnDelete();
            $table->string('plate', 20)->index();       // matrícula
            $table->string('vin', 40)->nullable();      // número de quadro
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('color')->nullable();
            $table->string('fuel_type', 20)->nullable(); // gasolina|gasoleo|hibrido|eletrico
            $table->unsignedInteger('current_km')->default(0);
            $table->date('last_service_at')->nullable();
            $table->unsignedInteger('next_service_km')->nullable();
            $table->date('insurance_expiry')->nullable();
            $table->date('inspection_expiry')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'plate']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
        Schema::dropIfExists('fleets');
        Schema::dropIfExists('customers');
    }
};
