<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_mapping_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('import_mapping_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('import_mapping_templates')->cascadeOnDelete();
            $table->string('internal_field');
            $table->string('source_column');
            $table->json('transformations')->nullable();
            $table->timestamps();
        });

        Schema::create('imports', function (Blueprint $table) {
            $table->id();
            $table->string('original_filename');
            $table->string('file_hash', 64)->index();
            $table->string('sheet_name');
            $table->unsignedInteger('header_row')->default(1);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('mapping_template_id')->nullable()->constrained('import_mapping_templates')->nullOnDelete();
            $table->json('used_mapping');
            $table->unsignedInteger('row_count')->default(0);
            $table->unsignedInteger('accepted_rows')->default(0);
            $table->unsignedInteger('rejected_rows')->default(0);
            $table->unsignedInteger('duplicate_rows')->default(0);
            $table->json('errors')->nullable();
            $table->string('status')->default('queued')->index(); // queued, processing, completed, failed
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imports');
        Schema::dropIfExists('import_mapping_fields');
        Schema::dropIfExists('import_mapping_templates');
    }
};
