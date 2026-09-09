<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->json('grounding_context')->nullable()->after('display_content');
            $table->integer('tokens_used')->default(0)->after('grounding_context');
        });

        Schema::table('ai_tool_calls', function (Blueprint $table) {
            $table->integer('duration_ms')->default(0)->after('status');
            $table->json('citations')->nullable()->after('duration_ms');
        });
    }

    public function down(): void
    {
        Schema::table('ai_tool_calls', function (Blueprint $table) {
            $table->dropColumn(['duration_ms', 'citations']);
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn(['grounding_context', 'tokens_used']);
        });
    }
};
