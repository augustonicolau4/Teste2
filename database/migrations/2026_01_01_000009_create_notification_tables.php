<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // -----------------------------------------------------------------
        // Mensagens WhatsApp (envio/receção e estado de entrega)
        // -----------------------------------------------------------------
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->nullableMorphs('related'); // Document, ServiceOrder, Customer...
            $table->string('direction', 10)->default('outbound'); // outbound|inbound
            $table->string('phone', 30)->index();
            $table->string('message_type', 20)->default('text'); // text|document|image|audio
            $table->text('message')->nullable();
            $table->string('media_path')->nullable();
            $table->string('media_caption')->nullable();
            $table->string('status', 20)->default('pending'); // pending|sent|delivered|read|failed
            $table->string('external_id')->nullable()->index();
            $table->string('job_id')->nullable();
            $table->text('error')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        // -----------------------------------------------------------------
        // Conversas com o assistente de IA
        // -----------------------------------------------------------------
        Schema::create('ai_conversations', function (Blueprint $table) {
            $table->id();
            $table->string('session_id')->index();
            $table->string('phone', 30)->index();
            $table->string('role', 12); // user|assistant|system
            $table->text('message');
            $table->json('context')->nullable();
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->timestamps();
        });

        // -----------------------------------------------------------------
        // Fila de impressão (ponte térmica via Node.js)
        // -----------------------------------------------------------------
        Schema::create('print_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->nullableMorphs('printable');
            $table->string('format', 10)->default('ticket'); // ticket|a4
            $table->string('printer')->nullable();
            $table->string('status', 20)->default('queued'); // queued|sent|printed|failed
            $table->unsignedInteger('copies')->default(1);
            $table->text('error')->nullable();
            $table->timestamp('printed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('print_jobs');
        Schema::dropIfExists('ai_conversations');
        Schema::dropIfExists('whatsapp_messages');
    }
};
