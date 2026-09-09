<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forecasts', function (Blueprint $table) {
            $table->id();
            $table->string('metric')->index(); // nps, csat, professionalism, survey_volume
            $table->string('dimension')->nullable()->index(); // supervisor, agent, wave, etc.
            $table->string('dimension_value')->nullable()->index();
            $table->string('model')->index(); // naive, sma, ema, linear_trend
            $table->json('parameters');
            $table->date('training_period_start');
            $table->date('training_period_end');
            $table->unsignedInteger('forecast_horizon')->default(7); // e.g. 7 or 30 days/periods
            $table->decimal('mae', 8, 4)->nullable();
            $table->decimal('rmse', 8, 4)->nullable();
            $table->decimal('r2', 6, 4)->nullable();
            $table->text('ai_interpretation')->nullable();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('forecast_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('forecast_id')->constrained()->cascadeOnDelete();
            $table->date('date')->index();
            $table->decimal('actual_value', 8, 4)->nullable();
            $table->decimal('forecast_value', 8, 4);
            $table->decimal('confidence_low', 8, 4)->nullable();
            $table->decimal('confidence_high', 8, 4)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forecast_results');
        Schema::dropIfExists('forecasts');
    }
};
