<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // -----------------------------------------------------------------
        // Documentos fiscais (FT, FR, FP, RC, NC, ND, GR)
        // -----------------------------------------------------------------
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('series_id')->constrained('series');
            $table->string('document_type', 4)->index();
            $table->unsignedBigInteger('sequence');
            $table->string('number', 60);            // ex.: FT 2026/00001
            $table->foreignId('customer_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('service_order_id')->nullable()->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('promoter_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('status', 20)->default('draft')->index();
            $table->string('currency', 3)->default('AOA');
            $table->decimal('exchange_rate', 16, 6)->default(1);

            // Datas
            $table->date('issue_date')->index();
            $table->date('due_date')->nullable();
            $table->dateTime('system_entry_date');
            $table->dateTime('delivered_at')->nullable();

            // Valores
            $table->decimal('gross_total', 16, 2)->default(0);
            $table->decimal('discount_total', 16, 2)->default(0);
            $table->decimal('net_total', 16, 2)->default(0);       // base tributável
            $table->decimal('iva_total', 16, 2)->default(0);
            $table->decimal('retention_total', 16, 2)->default(0);
            $table->decimal('grand_total', 16, 2)->default(0);
            $table->decimal('paid_total', 16, 2)->default(0);
            $table->decimal('balance', 16, 2)->default(0);

            // Fiscal / assinatura
            $table->boolean('is_fiscal')->default(true);
            $table->string('hash', 172)->nullable()->index();
            $table->string('hash_control', 172)->nullable();      // hash do documento anterior
            $table->text('signature')->nullable();                // assinatura RSA (base64)
            $table->string('signature_algorithm', 30)->nullable();
            $table->string('software_certificate', 60)->nullable();
            $table->text('qr_payload')->nullable();

            // Anulação / relações
            $table->unsignedBigInteger('related_document_id')->nullable()->index();
            $table->string('cancel_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('saft_exported_at')->nullable();

            $table->unsignedInteger('printed_count')->default(0);
            $table->text('notes')->nullable();
            $table->text('internal_notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['series_id', 'sequence'], 'documents_series_sequence_unique');
            $table->index(['company_id', 'document_type', 'issue_date']);
            $table->index(['customer_id', 'status']);
        });

        // -----------------------------------------------------------------
        // Linhas do documento
        // -----------------------------------------------------------------
        Schema::create('document_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('line_number');
            $table->string('line_type', 20)->default('service'); // product|service|other
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('service_order_item_id')->nullable();
            $table->string('description');
            $table->string('unit_code', 10)->nullable();
            $table->decimal('quantity', 16, 4)->default(1);
            $table->decimal('unit_price', 16, 4)->default(0);
            $table->decimal('discount_rate', 6, 4)->default(0);
            $table->decimal('discount_amount', 16, 2)->default(0);
            $table->decimal('gross_amount', 16, 2)->default(0);
            $table->decimal('net_amount', 16, 2)->default(0);
            $table->string('tax_type', 10)->default('IVA14');
            $table->decimal('iva_rate', 6, 4)->default(0.14);
            $table->decimal('iva_amount', 16, 2)->default(0);
            $table->string('exemption_code', 6)->nullable();
            $table->boolean('retention_applicable')->default(false);
            $table->decimal('retention_rate', 6, 4)->default(0);
            $table->decimal('retention_amount', 16, 2)->default(0);
            $table->decimal('total_line', 16, 2)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // -----------------------------------------------------------------
        // Resumo de impostos por documento (agrupado por taxa)
        // -----------------------------------------------------------------
        Schema::create('document_taxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->string('tax_type', 10);
            $table->string('tax_code', 30)->nullable();
            $table->string('description')->nullable();
            $table->string('country_region', 5)->default('AO');
            $table->decimal('base_amount', 16, 2)->default(0);
            $table->decimal('rate', 6, 4)->default(0);
            $table->decimal('amount', 16, 2)->default(0);
            $table->string('exemption_code', 6)->nullable();
            $table->timestamps();
        });

        // -----------------------------------------------------------------
        // Assinaturas digitais (cadeia de hash)
        // -----------------------------------------------------------------
        Schema::create('document_signatures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('signature_number')->default(1);
            $table->string('hash', 172);
            $table->string('previous_hash', 172)->nullable();
            $table->text('signature');
            $table->string('algorithm', 30)->default('RSA-SHA256');
            $table->string('certificate_number', 60)->nullable();
            $table->string('software_certificate', 60)->nullable();
            $table->timestamp('signed_at');
            $table->timestamps();
            $table->unique(['document_id', 'signature_number']);
        });

        // -----------------------------------------------------------------
        // Relações entre documentos (NC->FT, referências, guias de remessa)
        // -----------------------------------------------------------------
        Schema::create('document_relations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_document_id')->constrained('documents')->cascadeOnDelete();
            $table->foreignId('target_document_id')->constrained('documents')->cascadeOnDelete();
            $table->string('relation_type', 20); // credit|reference|delivery
            $table->decimal('amount', 16, 2)->default(0);
            $table->string('reason')->nullable();
            $table->timestamps();
            $table->unique(['source_document_id', 'target_document_id', 'relation_type'], 'doc_relations_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_relations');
        Schema::dropIfExists('document_signatures');
        Schema::dropIfExists('document_taxes');
        Schema::dropIfExists('document_lines');
        Schema::dropIfExists('documents');
    }
};
