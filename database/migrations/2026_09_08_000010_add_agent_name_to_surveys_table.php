<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('surveys') && !Schema::hasColumn('surveys', 'agent_name')) {
            Schema::table('surveys', function (Blueprint $table) {
                $table->string('agent_name')->nullable()->after('agent_bms')->index();
                $table->index(['agent_name', 'survey_date']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('surveys') && Schema::hasColumn('surveys', 'agent_name')) {
            Schema::table('surveys', function (Blueprint $table) {
                $table->dropIndex(['agent_name', 'survey_date']);
                $table->dropColumn('agent_name');
            });
        }
    }
};
