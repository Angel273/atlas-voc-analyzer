<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->text('examples')->nullable();
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('verbatim_analyses', function (Blueprint $table) {
            $table->id();
            $table->string('survey_id')->index();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->decimal('confidence', 5, 4)->nullable();
            $table->string('provider')->default('gemini');
            $table->string('model')->default('gemini-2.5-flash');
            $table->string('prompt_version')->default('v1');
            $table->string('status')->default('completed')->index();
            $table->timestamp('processed_at')->nullable()->index();
            $table->timestamps();

            $table->foreign('survey_id')->references('survey_id')->on('surveys')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verbatim_analyses');
        Schema::dropIfExists('categories');
    }
};
