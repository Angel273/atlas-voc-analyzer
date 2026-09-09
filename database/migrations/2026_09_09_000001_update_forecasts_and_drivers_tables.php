<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forecasts', function (Blueprint $table) {
            $table->string('status')->default('completed')->after('model')->index();
            $table->string('reliability')->nullable()->after('status');
            $table->text('selection_reason')->nullable()->after('reliability');
        });

        Schema::table('forecast_results', function (Blueprint $table) {
            $table->decimal('raw_forecast_value', 8, 4)->nullable()->after('actual_value');
            $table->boolean('was_bounded')->default(false)->after('confidence_high');
        });

        Schema::create('driver_analyses', function (Blueprint $table) {
            $table->id();
            $table->string('metric')->index(); // nps, csat, professionalism
            $table->json('filters')->nullable();
            $table->unsignedInteger('sample_size')->default(0);
            $table->json('controlled_variables');
            $table->json('reference_categories');
            $table->json('drivers_data');
            $table->json('diagnostics')->nullable();
            $table->text('ai_interpretation')->nullable();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_analyses');

        Schema::table('forecast_results', function (Blueprint $table) {
            $table->dropColumn(['raw_forecast_value', 'was_bounded']);
        });

        Schema::table('forecasts', function (Blueprint $table) {
            $table->dropColumn(['status', 'reliability', 'selection_reason']);
        });
    }
};
