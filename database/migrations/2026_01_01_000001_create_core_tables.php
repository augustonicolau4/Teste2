<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // -----------------------------------------------------------------
        // Empresas (emitentes das faturas)
        // -----------------------------------------------------------------
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('trade_name')->nullable();
            $table->string('nif', 20)->unique();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->string('country', 2)->default('AO');
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('website')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('certificate_number')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // -----------------------------------------------------------------
        // Filiais / Estabelecimentos
        // -----------------------------------------------------------------
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name');
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('phone', 30)->nullable();
            $table->boolean('is_headquarters')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['company_id', 'code']);
        });

        // -----------------------------------------------------------------
        // Configurações genéricas (chave/valor)
        // -----------------------------------------------------------------
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('group', 60)->default('general');
            $table->string('key', 120);
            $table->text('value')->nullable();
            $table->string('type', 30)->default('string'); // string|int|float|bool|json
            $table->timestamps();
            $table->unique(['company_id', 'group', 'key']);
        });

        // -----------------------------------------------------------------
        // Séries de faturação (numeração sequencial por tipo/ano)
        // -----------------------------------------------------------------
        Schema::create('series', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('document_type', 4); // FT|FR|FP|RC|NC|ND|GR
            $table->string('code', 20);
            $table->string('prefix', 10);
            $table->string('format', 60)->default('{PREFIXO} {ANO}/{NUMERO}');
            $table->unsignedSmallInteger('year');
            $table->unsignedBigInteger('current_sequence')->default(0);
            $table->unsignedBigInteger('last_number')->default(0);
            $table->unsignedSmallInteger('padding')->default(5);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'document_type', 'code', 'year'], 'series_unique');
        });

        // -----------------------------------------------------------------
        // Impostos
        // -----------------------------------------------------------------
        Schema::create('taxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('code', 20);
            $table->string('name');
            $table->string('type', 10)->default('IVA14'); // TaxType enum value
            $table->decimal('rate', 6, 4)->default(0);    // 0.1400 = 14%
            $table->string('exemption_code', 6)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // -----------------------------------------------------------------
        // Unidades de medida
        // -----------------------------------------------------------------
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name');
            $table->string('symbol', 10)->nullable();
            $table->timestamps();
        });

        // -----------------------------------------------------------------
        // Auditoria (log de alterações)
        // -----------------------------------------------------------------
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event', 40);
            $table->nullableMorphs('auditable');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();
            $table->index(['event', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('units');
        Schema::dropIfExists('taxes');
        Schema::dropIfExists('series');
        Schema::dropIfExists('settings');
        Schema::dropIfExists('branches');
        Schema::dropIfExists('companies');
    }
};
