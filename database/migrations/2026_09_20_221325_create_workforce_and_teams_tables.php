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
        Schema::create('workforce_members', function (Blueprint $table) {
            $table->id();
            $table->string('external_id')->nullable()->index(); // agent_bms for agents or supervisor code
            $table->string('name')->index();
            $table->string('role')->default('agent')->index(); // agent, supervisor, both
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->foreignId('supervisor_id')->nullable()->constrained('workforce_members')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('team_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('workforce_member_id')->constrained('workforce_members')->cascadeOnDelete();
            $table->string('role')->default('agent'); // agent, supervisor, lead
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestamps();

            $table->index(['workforce_member_id', 'effective_from', 'effective_to'], 'idx_member_effective');
            $table->index(['team_id', 'effective_from', 'effective_to'], 'idx_team_effective');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('team_memberships');
        Schema::dropIfExists('teams');
        Schema::dropIfExists('workforce_members');
    }
};
