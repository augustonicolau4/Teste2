<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // -----------------------------------------------------------------
        // Ordens de Serviço (oficina)
        // -----------------------------------------------------------------
        Schema::create('service_orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 40);
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('vehicle_id')->constrained()->restrictOnDelete();
            $table->foreignId('fleet_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('advisor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('technician_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('promoter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('document_id')->nullable()->constrained('documents')->nullOnDelete();

            $table->string('status', 30)->default('received')->index();
            $table->string('priority', 20)->default('normal'); // low|normal|high|urgent
            $table->string('type', 20)->default('repair');      // repair|maintenance|inspection|bodywork

            // Entrada da viatura
            $table->unsignedInteger('mileage_in')->nullable();
            $table->unsignedInteger('mileage_out')->nullable();
            $table->string('fuel_level', 20)->nullable(); // vazio|1/4|1/2|3/4|cheio
            $table->text('customer_complaint')->nullable();
            $table->text('diagnosis')->nullable();
            $table->text('work_performed')->nullable();
            $table->text('observations')->nullable();

            // Datas
            $table->dateTime('entry_at');
            $table->dateTime('promised_at')->nullable();
            $table->dateTime('finished_at')->nullable();
            $table->dateTime('delivered_at')->nullable();

            // Valores (consolidados)
            $table->decimal('labor_total', 16, 2)->default(0);
            $table->decimal('parts_total', 16, 2)->default(0);
            $table->decimal('discount_total', 16, 2)->default(0);
            $table->decimal('net_total', 16, 2)->default(0);
            $table->decimal('iva_total', 16, 2)->default(0);
            $table->decimal('retention_total', 16, 2)->default(0);
            $table->decimal('grand_total', 16, 2)->default(0);

            $table->string('quote_status', 20)->default('pending'); // pending|sent|approved|rejected
            $table->timestamp('quote_sent_at')->nullable();
            $table->timestamp('quote_approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'number']);
            $table->index(['vehicle_id', 'status']);
        });

        // Linhas da OS (peças e mão de obra)
        Schema::create('service_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_order_id')->constrained()->cascadeOnDelete();
            $table->string('line_type', 20); // product|service
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('technician_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('description');
            $table->decimal('quantity', 16, 3)->default(1);
            $table->decimal('unit_price', 16, 2)->default(0);
            $table->decimal('cost_price', 16, 2)->default(0);
            $table->decimal('discount_rate', 6, 4)->default(0);
            $table->string('tax_type', 10)->default('IVA14');
            $table->decimal('iva_rate', 6, 4)->default(0.14);
            $table->boolean('retention_applicable')->default(false);
            $table->decimal('retention_rate', 6, 4)->default(0);
            $table->decimal('total', 16, 2)->default(0);
            $table->decimal('labor_hours', 6, 2)->default(0);
            $table->boolean('is_approved')->default(true);
            $table->boolean('stock_deducted')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Checklist de entrada / inspeção
        Schema::create('service_order_checklists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_order_id')->constrained()->cascadeOnDelete();
            $table->string('category', 40)->default('exterior'); // exterior|interior|mecanica|eletrica|documentos
            $table->string('item');
            $table->boolean('is_ok')->default(true);
            $table->string('severity', 20)->nullable(); // low|medium|high
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Histórico de estados da OS
        Schema::create('service_order_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_order_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['service_order_id', 'created_at']);
        });

        // Atribuição de materiais da OS (ligação stock<->OS)
        Schema::create('service_order_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_order_item_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('quantity', 16, 3);
            $table->decimal('unit_cost', 16, 2)->default(0);
            $table->timestamp('issued_at');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        // Concluir o ciclo: FK de documents.service_order_id -> service_orders
        Schema::table('documents', function (Blueprint $table) {
            $table->foreign('service_order_id')->references('id')->on('service_orders')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropForeign(['service_order_id']);
        });

        Schema::dropIfExists('service_order_materials');
        Schema::dropIfExists('service_order_status_histories');
        Schema::dropIfExists('service_order_checklists');
        Schema::dropIfExists('service_order_items');
        Schema::dropIfExists('service_orders');
    }
};
