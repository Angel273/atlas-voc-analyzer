<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pseudonym_vault', function (Blueprint $table) {
            $table->id();
            $table->string('scope_id')->index();
            $table->string('entity_type')->index(); // agent, supervisor, survey, etc.
            $table->string('entity_internal_id')->index();
            $table->string('pseudonym')->index();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('expires_at')->nullable();

            $table->unique(['scope_id', 'entity_type', 'entity_internal_id']);
            $table->unique(['scope_id', 'pseudonym']);
        });

        Schema::create('privacy_transformations', function (Blueprint $table) {
            $table->id();
            $table->uuid('ai_run_id')->nullable()->index();
            $table->string('transformation_type')->index(); // prompt_pseudonymization, verbatim_redaction, tool_minimization, reidentification
            $table->integer('tokens_count')->default(0);
            $table->json('redacted_types')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('privacy_transformations');
        Schema::dropIfExists('pseudonym_vault');
    }
};
