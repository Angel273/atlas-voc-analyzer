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
        Schema::create('performance_cases', function (Blueprint $table) {
            $table->id();
            $table->string('case_number')->unique();
            $table->foreignId('workforce_member_id')->constrained('workforce_members')->cascadeOnDelete();
            $table->string('target_type')->default('agent'); // agent, supervisor
            $table->string('type')->index(); // nps_improvement, csat_recovery, quality_compliance, general
            $table->text('reason');
            $table->string('priority')->default('medium')->index(); // low, medium, high, critical
            $table->string('status')->default('open')->index(); // open, monitoring, action_plan, improving, resolved, closed
            $table->date('opened_at')->index();
            $table->date('closed_at')->nullable()->index();
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('baseline')->nullable();
            $table->json('objectives')->nullable();
            $table->date('next_review_at')->nullable()->index();
            $table->timestamps();

            $table->index(['workforce_member_id', 'status']);
        });

        Schema::create('performance_case_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('performance_case_id')->constrained('performance_cases')->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('previous_status');
            $table->string('resulting_status');
            $table->text('summary');
            $table->text('observations')->nullable();
            $table->text('actions')->nullable();
            $table->text('commitments')->nullable();
            $table->date('next_review_at')->nullable();
            $table->json('metrics_snapshot')->nullable();
            $table->longText('disciplinary_details')->nullable(); // Encrypted at application layer
            $table->timestamps();

            $table->index(['performance_case_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('performance_case_updates');
        Schema::dropIfExists('performance_cases');
    }
};
