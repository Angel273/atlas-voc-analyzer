<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kpi_goals', function (Blueprint $table) {
            $table->id();
            $table->string('metric')->unique(); // 'nps', 'csat', 'professionalism'
            $table->decimal('target_value', 8, 4); // E.g., 0.5000 for NPS (scale -1 to 1), 0.8000 for CSAT
            $table->decimal('warning_threshold', 8, 4)->nullable(); // E.g., 0.2000 for NPS, 0.7000 for CSAT
            $table->string('unit')->default('score'); // 'score' (-1 a 1) or '%'
            $table->text('description')->nullable();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Seed default initial goals with NPS explicitly in the -1 to 1 scale
        DB::table('kpi_goals')->insert([
            [
                'metric' => 'nps',
                'target_value' => 0.5000,
                'warning_threshold' => 0.2000,
                'unit' => 'score',
                'description' => 'Meta de lealtad neta NPS (+0.50 en escala -1 a 1, equiv. +50%).',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'metric' => 'csat',
                'target_value' => 0.8000,
                'warning_threshold' => 0.7000,
                'unit' => 'score',
                'description' => 'Meta de satisfacción general CSAT (0.80 en escala -1 a 1 / 0 a 1, equiv. 80%).',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'metric' => 'professionalism',
                'target_value' => 0.8500,
                'warning_threshold' => 0.7500,
                'unit' => 'score',
                'description' => 'Meta de profesionalismo de agentes (0.85 en escala -1 a 1 / 0 a 1, equiv. 85%).',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_goals');
    }
};
