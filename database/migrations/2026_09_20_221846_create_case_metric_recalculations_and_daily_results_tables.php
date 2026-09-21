<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('case_metric_recalculations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('performance_case_id')->constrained('performance_cases')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->dateTime('recalculated_at');
            $table->foreignId('triggered_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason')->default('manual'); // case_creation, manual, post_import_sync
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['performance_case_id', 'version_number']);
        });

        Schema::create('case_daily_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_metric_recalculation_id')->constrained('case_metric_recalculations')->cascadeOnDelete();
            $table->foreignId('performance_case_id')->constrained('performance_cases')->cascadeOnDelete();
            $table->date('date');
            $table->decimal('nps_score', 6, 4)->nullable(); // null when no sample
            $table->decimal('csat_score', 6, 4)->nullable(); // null when no sample
            $table->decimal('professionalism_score', 6, 4)->nullable(); // null when no sample
            $table->unsignedInteger('survey_volume')->default(0);
            $table->boolean('has_sample')->default(false);
            $table->json('applicable_goals')->nullable();
            $table->foreignId('effective_team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->string('effective_team_name')->nullable();
            $table->foreignId('effective_supervisor_id')->nullable()->constrained('workforce_members')->nullOnDelete();
            $table->string('effective_supervisor_name')->nullable();
            $table->timestamps();

            $table->index(['case_metric_recalculation_id', 'date'], 'idx_recalc_date');
            $table->index(['performance_case_id', 'date'], 'idx_case_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_daily_results');
        Schema::dropIfExists('case_metric_recalculations');
    }
};
