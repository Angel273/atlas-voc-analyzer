<?php

use App\Models\AiRun;
use App\Models\Message;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_runs', function (Blueprint $table) {
            $table->text('user_prompt')->nullable()->after('conversation_id');
        });

        // Backfill user_prompt from existing messages
        $assistantMessages = Message::where('sender_type', 'assistant')
            ->whereNotNull('metadata')
            ->get();

        foreach ($assistantMessages as $msg) {
            $aiRunId = $msg->metadata['ai_run_id'] ?? null;
            if (!$aiRunId) {
                continue;
            }

            $userMessage = Message::where('conversation_id', $msg->conversation_id)
                ->where('sender_type', 'user')
                ->where('created_at', '<=', $msg->created_at)
                ->orderByDesc('created_at')
                ->first();

            if ($userMessage) {
                AiRun::where('id', $aiRunId)->whereNull('user_prompt')->update([
                    'user_prompt' => $userMessage->display_content,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('ai_runs', function (Blueprint $table) {
            $table->dropColumn('user_prompt');
        });
    }
};
