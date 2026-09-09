<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title')->default('New Conversation');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('conversation_id')->constrained()->cascadeOnDelete();
            $table->string('sender_type')->index(); // user, assistant, system, tool
            $table->text('display_content'); // Authorized representation
            $table->text('ai_content'); // Sanitized / pseudonymized representation
            $table->json('tool_calls')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider')->default('gemini');
            $table->string('model')->default('gemini-2.5-flash');
            $table->string('prompt_version')->default('v1');
            $table->string('tool_schema_version')->default('v1');
            $table->string('privacy_policy_version')->default('v1');
            $table->json('sanitized_payload')->nullable(); // Exact payload sent to provider
            $table->string('status')->default('running')->index(); // running, completed, failed
            $table->integer('tokens_used')->nullable();
            $table->integer('latency_ms')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_tool_calls', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('ai_run_id')->constrained()->cascadeOnDelete();
            $table->string('tool_name')->index();
            $table->string('tool_version')->default('v1');
            $table->json('arguments_sanitized');
            $table->json('query_dsl')->nullable();
            $table->text('result_summary')->nullable();
            $table->string('status')->default('completed')->index();
            $table->timestamp('requested_at');
            $table->timestamp('executed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_tool_calls');
        Schema::dropIfExists('ai_runs');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversations');
    }
};
