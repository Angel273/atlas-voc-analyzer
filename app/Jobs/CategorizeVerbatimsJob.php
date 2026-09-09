<?php

namespace App\Jobs;

use App\Models\Category;
use App\Models\Survey;
use App\Models\VerbatimAnalysis;
use App\Services\Ai\Contracts\AiProvider;
use App\Services\Privacy\PiiScrubberService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CategorizeVerbatimsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 300;

    public function __construct(
        public array $surveyIds = []
    ) {}

    public function handle(AiProvider $aiProvider, PiiScrubberService $piiScrubber): void
    {
        $activeCategories = Category::where('active', true)->get();
        if ($activeCategories->isEmpty()) {
            return;
        }

        $categoryNames = $activeCategories->pluck('name')->toArray();
        $categoriesMap = $activeCategories->keyBy('name');

        $categoryDescriptions = [];
        foreach ($activeCategories as $cat) {
            $categoryDescriptions[] = "- {$cat->name}: {$cat->description}";
        }
        $categoryContext = implode("\n", $categoryDescriptions);

        // Pre-fetch already completed analyses in this batch to ensure idempotency
        $alreadyCompleted = VerbatimAnalysis::whereIn('survey_id', $this->surveyIds)
            ->where('status', 'completed')
            ->pluck('survey_id')
            ->flip()
            ->toArray();

        $surveys = Survey::whereIn('survey_id', $this->surveyIds)
            ->whereNotNull('verbatim')
            ->get();

        foreach ($surveys as $survey) {
            if (isset($alreadyCompleted[$survey->survey_id])) {
                continue;
            }

            $verbatim = trim((string) $survey->verbatim);
            if ($verbatim === '') {
                continue;
            }

            // Scrub verbatim for privacy
            $sanitizedVerbatim = $piiScrubber->scrubText($verbatim, "cat_{$survey->survey_id}");

            $systemInstruction = 'You are a customer feedback classifier for Voice of Customer analytics. '
                ."You MUST classify the feedback into EXACTLY ONE of the following allowed categories:\n"
                .$categoryContext."\n\n"
                .'Do NOT invent new categories. You must respond in valid JSON with format: '
                .'{"category": "EXACT_CATEGORY_NAME", "confidence": 0.95}';

            $prompt = "Classify this customer verbatim:\n\"{$sanitizedVerbatim}\"";

            try {
                $response = $aiProvider->generate([
                    ['role' => 'user', 'content' => $prompt],
                ], [], [
                    'system_instruction' => $systemInstruction,
                    'prompt_version' => 'cat_v1',
                ]);

                $content = $response['content'] ?? '';
                $jsonStart = strpos($content, '{');
                $jsonEnd = strrpos($content, '}');
                $matchedCategory = null;
                $confidence = 0.85;

                if ($jsonStart !== false && $jsonEnd !== false) {
                    $parsed = json_decode(substr($content, $jsonStart, $jsonEnd - $jsonStart + 1), true);
                    if (! empty($parsed['category']) && isset($categoriesMap[$parsed['category']])) {
                        $matchedCategory = $categoriesMap[$parsed['category']];
                        $confidence = (float) ($parsed['confidence'] ?? 0.85);
                    }
                }

                // Fallback: match by substring if JSON didn't parse or had slight variation
                if (! $matchedCategory) {
                    foreach ($categoryNames as $cName) {
                        if (stripos($content, $cName) !== false) {
                            $matchedCategory = $categoriesMap[$cName];
                            break;
                        }
                    }
                }

                // Default to first category if still unassigned
                if (! $matchedCategory) {
                    $matchedCategory = $activeCategories->first();
                }

                VerbatimAnalysis::updateOrCreate(
                    ['survey_id' => $survey->survey_id],
                    [
                        'category_id' => $matchedCategory->id,
                        'confidence' => $confidence,
                        'provider' => $aiProvider->providerName(),
                        'model' => $response['model'] ?? config('services.gemini.model', env('GEMINI_MODEL', 'gemini-flash-latest')),
                        'prompt_version' => 'cat_v1',
                        'status' => 'completed',
                        'processed_at' => now(),
                    ]
                );
            } catch (\Throwable $e) {
                // Log failure status for this survey
                VerbatimAnalysis::updateOrCreate(
                    ['survey_id' => $survey->survey_id],
                    [
                        'category_id' => $activeCategories->first()->id,
                        'confidence' => 0.0,
                        'provider' => $aiProvider->providerName(),
                        'model' => 'failed',
                        'prompt_version' => 'cat_v1',
                        'status' => 'failed',
                        'processed_at' => now(),
                    ]
                );
            }
        }
    }
}
