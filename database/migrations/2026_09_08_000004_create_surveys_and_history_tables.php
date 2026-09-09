<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('surveys', function (Blueprint $table) {
            $table->id();
            $table->string('survey_id')->unique()->index();
            $table->decimal('nps_score', 6, 4); // -1.0000 to 1.0000
            $table->decimal('csat_score', 6, 4); // 0.0000 to 1.0000
            $table->decimal('professionalism_score', 6, 4); // 0.0000 to 1.0000
            $table->text('verbatim')->nullable();
            $table->string('agent_bms')->index();
            $table->string('agent_name')->nullable()->index();
            $table->string('supervisor')->index();
            $table->date('survey_date')->index();
            $table->string('wave')->nullable()->index();
            $table->unsignedInteger('tenure_days')->nullable()->index();
            $table->string('record_hash', 64)->index();
            $table->foreignId('import_id')->constrained('imports')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['supervisor', 'survey_date']);
            $table->index(['agent_bms', 'survey_date']);
            $table->index(['agent_name', 'survey_date']);
        });

        Schema::create('survey_versions', function (Blueprint $table) {
            $table->id();
            $table->string('survey_id')->index();
            $table->string('previous_hash', 64);
            $table->string('new_hash', 64);
            $table->foreignId('import_id')->constrained('imports')->cascadeOnDelete();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('diff');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_versions');
        Schema::dropIfExists('surveys');
    }
};
