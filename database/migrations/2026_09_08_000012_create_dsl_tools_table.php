<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dsl_tools', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique()->index();
            $table->string('label');
            $table->text('description');
            $table->boolean('is_builtin')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->string('execution_mode')->default('dsl_query'); // dsl_query, multi_metric, system
            $table->json('parameters_schema');
            $table->json('dsl_template')->nullable();
            $table->integer('sort_order')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dsl_tools');
    }
};
