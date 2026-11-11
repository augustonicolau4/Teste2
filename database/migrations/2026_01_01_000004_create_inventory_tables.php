<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // -----------------------------------------------------------------
        // Categorias de produtos
        // -----------------------------------------------------------------
        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('product_categories')->nullOnDelete();
            $table->string('name');
            $table->string('code', 30)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // -----------------------------------------------------------------
        // Armazéns
        // -----------------------------------------------------------------
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 20);
            $table->string('name');
            $table->string('location')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'code']);
        });

        // -----------------------------------------------------------------
        // Produtos / Peças (com código OEM)
        // -----------------------------------------------------------------
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('product_categories')->nullOnDelete();
            $table->foreignId('unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->foreignId('tax_id')->nullable()->constrained('taxes')->nullOnDelete();
            $table->string('sku', 60)->nullable();
            $table->string('oem_code', 80)->nullable()->index();  // código original do fabricante
            $table->string('barcode', 80)->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('brand')->nullable();
            $table->enum('type', ['product', 'service', 'part', 'consumable'])->default('part');
            $table->decimal('cost_price', 16, 2)->default(0);
            $table->decimal('sale_price', 16, 2)->default(0);
            $table->decimal('iva_rate', 6, 4)->default(0.14);
            $table->boolean('retention_applicable')->default(false);
            $table->decimal('stock_min', 16, 3)->default(0);
            $table->decimal('stock_max', 16, 3)->default(0);
            $table->decimal('reorder_level', 16, 3)->default(0);
            $table->boolean('track_stock')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'sku']);
            $table->index(['company_id', 'name']);
        });

        // -----------------------------------------------------------------
        // Movimentos de stock (kardex)
        // -----------------------------------------------------------------
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20); // in|out|adjust|transfer
            $table->decimal('quantity', 16, 3);
            $table->decimal('unit_cost', 16, 2)->default(0);
            $table->decimal('total_cost', 16, 2)->default(0);
            $table->decimal('stock_before', 16, 3)->default(0);
            $table->decimal('stock_after', 16, 3)->default(0);
            $table->nullableMorphs('source'); // ServiceOrder, Document, PurchaseOrder...
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reason')->nullable();
            $table->timestamp('moved_at');
            $table->timestamps();
            $table->index(['product_id', 'moved_at']);
        });

        // -----------------------------------------------------------------
        // Fornecedores
        // -----------------------------------------------------------------
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('trade_name')->nullable();
            $table->string('nif', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('country', 2)->default('AO');
            $table->string('contact_person')->nullable();
            $table->decimal('balance', 16, 2)->default(0);
            $table->string('payment_terms', 30)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // -----------------------------------------------------------------
        // Ordens de Compra
        // -----------------------------------------------------------------
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number', 40);
            $table->string('status', 20)->default('draft'); // draft|sent|partial|received|cancelled
            $table->date('order_date');
            $table->date('expected_date')->nullable();
            $table->decimal('subtotal', 16, 2)->default(0);
            $table->decimal('iva_total', 16, 2)->default(0);
            $table->decimal('total', 16, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'number']);
        });

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('description');
            $table->decimal('quantity', 16, 3)->default(0);
            $table->decimal('received_quantity', 16, 3)->default(0);
            $table->decimal('unit_cost', 16, 2)->default(0);
            $table->decimal('iva_rate', 6, 4)->default(0.14);
            $table->decimal('total', 16, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('products');
        Schema::dropIfExists('warehouses');
        Schema::dropIfExists('product_categories');
    }
};
