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
    public int $timeout = 600;

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

        $pendingSurveys = [];
        foreach ($surveys as $survey) {
            if (isset($alreadyCompleted[$survey->survey_id])) {
                continue;
            }

            $verbatim = trim((string) $survey->verbatim);
            if ($verbatim === '') {
                continue;
            }

            $pendingSurveys[] = $survey;
        }

        if (empty($pendingSurveys)) {
            return;
        }

        // Process verbatims in batches of 100 per LLM prompt
        $batches = array_chunk($pendingSurveys, 100);

        foreach ($batches as $batch) {
            $this->processBatch(
                $batch,
                $activeCategories,
                $categoriesMap,
                $categoryNames,
                $categoryContext,
                $aiProvider,
                $piiScrubber
            );
        }
    }

    /**
     * Process a batch of up to 100 verbatims in a single AI prompt.
     */
    protected function processBatch(
        array $batch,
        $activeCategories,
        $categoriesMap,
        array $categoryNames,
        string $categoryContext,
        AiProvider $aiProvider,
        PiiScrubberService $piiScrubber
    ): void {
        $items = [];
        $surveysById = [];

        foreach ($batch as $survey) {
            $surveysById[$survey->survey_id] = $survey;
            $sanitized = $piiScrubber->scrubText(trim((string) $survey->verbatim), "cat_{$survey->survey_id}");
            $items[] = [
                'id' => (string) $survey->survey_id,
                'text' => mb_substr($sanitized, 0, 400),
            ];
        }

        $systemInstruction = "You are an expert customer feedback classifier for Voice of Customer analytics.\n"
            ."You MUST classify each verbatim into EXACTLY ONE of the allowed categories:\n"
            .$categoryContext."\n\n"
            ."Rules:\n"
            ."1. Do NOT invent new categories.\n"
            ."2. Return ONLY a valid JSON array of objects with keys: \"id\", \"category\", and \"confidence\" (number between 0.0 and 1.0).\n"
            ."3. Provide an entry for each verbatim in the input.\n"
            ."Format example:\n"
            .'[{"id": "ID_HERE", "category": "EXACT_CATEGORY_NAME", "confidence": 0.95}]';

        $prompt = 'Classify these '.count($items)." customer verbatims:\n".json_encode($items, JSON_UNESCAPED_UNICODE);

        try {
            $response = $aiProvider->generate([
                ['role' => 'user', 'content' => $prompt],
            ], [], [
                'system_instruction' => $systemInstruction,
                'prompt_version' => 'cat_v2_batch100',
            ]);

            $content = $response['content'] ?? '';
            $startPos = strpos($content, '[');
            $endPos = strrpos($content, ']');
            $processedIds = [];

            if ($startPos !== false && $endPos !== false) {
                $jsonString = substr($content, $startPos, $endPos - $startPos + 1);
                $parsed = json_decode($jsonString, true);

                if (is_array($parsed)) {
                    foreach ($parsed as $item) {
                        $sId = (string) ($item['id'] ?? '');
                        if (! isset($surveysById[$sId])) {
                            continue;
                        }

                        $catName = $item['category'] ?? '';
                        $matchedCategory = $categoriesMap[$catName] ?? null;

                        if (! $matchedCategory) {
                            foreach ($categoryNames as $cName) {
                                if (stripos($catName, $cName) !== false) {
                                    $matchedCategory = $categoriesMap[$cName];
                                    break;
                                }
                            }
                        }

                        if (! $matchedCategory) {
                            $matchedCategory = $activeCategories->first();
                        }

                        $confidence = isset($item['confidence']) ? (float) $item['confidence'] : 0.85;

                        VerbatimAnalysis::updateOrCreate(
                            ['survey_id' => $sId],
                            [
                                'category_id' => $matchedCategory->id,
                                'confidence' => $confidence,
                                'provider' => $aiProvider->providerName(),
                                'model' => $response['model'] ?? config('services.gemini.model', env('GEMINI_MODEL', 'gemini-flash-latest')),
                                'prompt_version' => 'cat_v2_batch100',
                                'status' => 'completed',
                                'processed_at' => now(),
                            ]
                        );

                        $processedIds[$sId] = true;
                    }
                }
            }

            // Fallback for any items missed by the LLM in this batch
            foreach ($batch as $survey) {
                if (! isset($processedIds[$survey->survey_id])) {
                    $this->fallbackSingle(
                        $survey,
                        $activeCategories,
                        $categoriesMap,
                        $categoryNames,
                        $categoryContext,
                        $aiProvider,
                        $piiScrubber
                    );
                }
            }
        } catch (\Throwable $e) {
            // If the whole batch call failed, process each survey individually or record failure
            foreach ($batch as $survey) {
                $this->fallbackSingle(
                    $survey,
                    $activeCategories,
                    $categoriesMap,
                    $categoryNames,
                    $categoryContext,
                    $aiProvider,
                    $piiScrubber
                );
            }
        }
    }

    /**
     * Fallback for a single survey if batch classification missed it or failed.
     */
    protected function fallbackSingle(
        Survey $survey,
        $activeCategories,
        $categoriesMap,
        array $categoryNames,
        string $categoryContext,
        AiProvider $aiProvider,
        PiiScrubberService $piiScrubber
    ): void {
        $verbatim = trim((string) $survey->verbatim);
        if ($verbatim === '') {
            return;
        }

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
                'prompt_version' => 'cat_v2_fallback',
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

            if (! $matchedCategory) {
                foreach ($categoryNames as $cName) {
                    if (stripos($content, $cName) !== false) {
                        $matchedCategory = $categoriesMap[$cName];
                        break;
                    }
                }
            }

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
                    'prompt_version' => 'cat_v2_fallback',
                    'status' => 'completed',
                    'processed_at' => now(),
                ]
            );
        } catch (\Throwable $e) {
            VerbatimAnalysis::updateOrCreate(
                ['survey_id' => $survey->survey_id],
                [
                    'category_id' => $activeCategories->first()->id,
                    'confidence' => 0.0,
                    'provider' => $aiProvider->providerName(),
                    'model' => 'failed',
                    'prompt_version' => 'cat_v2_fallback',
                    'status' => 'failed',
                    'processed_at' => now(),
                ]
            );
        }
    }
}
