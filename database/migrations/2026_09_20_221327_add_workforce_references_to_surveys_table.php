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
        Schema::table('surveys', function (Blueprint $table) {
            $table->foreignId('agent_id')->nullable()->after('agent_name')->constrained('workforce_members')->nullOnDelete();
            $table->foreignId('supervisor_id')->nullable()->after('supervisor')->constrained('workforce_members')->nullOnDelete();
            $table->foreignId('team_id')->nullable()->after('supervisor_id')->constrained('teams')->nullOnDelete();

            $table->index(['agent_id', 'survey_date'], 'idx_surveys_agent_date');
            $table->index(['supervisor_id', 'survey_date'], 'idx_surveys_supervisor_date');
            $table->index(['team_id', 'survey_date'], 'idx_surveys_team_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('surveys', function (Blueprint $table) {
            $table->dropIndex('idx_surveys_agent_date');
            $table->dropIndex('idx_surveys_supervisor_date');
            $table->dropIndex('idx_surveys_team_date');
            $table->dropConstrainedForeignId('team_id');
            $table->dropConstrainedForeignId('supervisor_id');
            $table->dropConstrainedForeignId('agent_id');
        });
    }
};
