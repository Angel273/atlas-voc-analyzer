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
        Schema::create('team_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->date('period_from')->index();
            $table->date('period_to')->index();
            $table->date('cutoff_date');
            $table->string('data_version', 64)->index();
            $table->string('prompt_version', 50)->default('team_report_v1');
            $table->string('model', 100)->default('gemini-flash-latest');
            $table->json('parameters')->nullable();
            $table->string('status', 30)->default('pending')->index(); // pending, processing, completed, failed
            $table->string('file_path')->nullable();
            $table->string('file_hash', 64)->nullable()->index();
            $table->unsignedInteger('file_size')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('tokens_used')->default(0);
            $table->text('error_message')->nullable();
            $table->json('narrative')->nullable();
            $table->json('metrics_data')->nullable();
            $table->foreignId('previous_report_id')->nullable()->constrained('team_reports')->nullOnDelete();
            $table->timestamps();

            $table->index(['team_id', 'period_from', 'period_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_reports');
    }
};
