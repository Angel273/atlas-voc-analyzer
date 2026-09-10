<?php

namespace App\Http\Controllers;

use App\Models\Forecast;
use App\Models\Survey;
use App\Services\Ai\Contracts\AiProvider;
use App\Services\DriverAnalysis\DriverAnalysisService;
use App\Services\Forecasting\ForecastEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ForecastController extends Controller
{
    public function __construct(
        protected ForecastEngine $forecastEngine,
        protected DriverAnalysisService $driverAnalysisService,
        protected AiProvider $aiProvider
    ) {}

    public function index(Request $request): Response
    {
        $forecasts = Forecast::with(['results', 'user'])->orderBy('id', 'desc')->paginate(10);
        $supervisors = Survey::distinct()->whereNotNull('supervisor')->where('supervisor', '!=', '')->orderBy('supervisor')->pluck('supervisor')->toArray();
        $waves = Survey::distinct()->whereNotNull('wave')->where('wave', '!=', '')->orderBy('wave')->pluck('wave')->toArray();
        $categories = DB::table('categories')->orderBy('name')->pluck('name')->toArray();

        return Inertia::render('Forecast/Index', [
            'forecasts' => $forecasts,
            'supervisors' => $supervisors,
            'waves' => $waves,
            'categories' => $categories,
        ]);
    }

    /**
     * Calculate explainable forecast using automatic model selection via walk-forward validation.
     */
    public function calculate(Request $request): JsonResponse
    {
        // 1. Validate user inputs (manual model selection removed per design rules)
        $validated = $request->validate([
            'metric' => ['required', 'string', 'in:nps,csat,professionalism'],
            'horizon' => ['required', 'integer', 'min:1', 'max:60'],
            'dimension' => ['nullable', 'string', 'in:supervisor,wave'],
            'dimension_value' => ['nullable', 'string'],
        ]);

        $user = Auth::user();

        // 2. Calculate deterministic mathematical forecast
        $forecast = $this->forecastEngine->calculateForecast(
            metric: $validated['metric'],
            modelType: null, // Deterministic selection by Atlas
            horizon: (int) $validated['horizon'],
            dimension: $validated['dimension'] ?? null,
            dimensionValue: $validated['dimension_value'] ?? null,
            user: $user
        );

        // 3. AI Qualitative Interpretation (Governance: Gemini explains, does not alter)
        try {
            if ($forecast->status === 'insufficient_history') {
                $eligibility = $forecast->parameters['eligibility'] ?? [];
                $systemInstruction = "Eres un consultor analítico senior en ATLAS VOC.\n"
                    ."El sistema determinista ha emitido un estado de 'INSUFFICIENT_HISTORY' según el Data Quality Gate.\n"
                    ."Explica con honestidad estadística por qué se necesitan al menos 28 periodos diarios válidos con mínimo 20 encuestas por periodo para emitir un pronóstico de producción confiable.\n"
                    ."Menciona que las observaciones históricas actuales aún son útiles para diagnóstico descriptivo y sugiere utilizar 'Driver Analysis' a nivel transaccional.";

                $response = $this->aiProvider->generate([
                    ['role' => 'user', 'content' => "Explica este estado de datos insuficientes:\n".json_encode($eligibility, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)],
                ], [], [
                    'system_instruction' => $systemInstruction,
                    'prompt_version' => 'forecast_gate_v1',
                ]);

                $forecast->update([
                    'ai_interpretation' => $response['content'] ?? null,
                ]);
            } else {
                $promptData = [
                    'metrica' => strtoupper($forecast->metric),
                    'modelo_seleccionado' => $forecast->model,
                    'razon_seleccion' => $forecast->selection_reason,
                    'comparativa_modelos' => $forecast->parameters['candidate_comparison'] ?? [],
                    'fiabilidad' => $forecast->reliability,
                    'evaluacion_fiabilidad' => $forecast->parameters['reliability_evaluation'] ?? [],
                    'horizonte_dias' => $forecast->forecast_horizon,
                    'periodo_entrenamiento' => "{$forecast->training_period_start->format('Y-m-d')} a {$forecast->training_period_end->format('Y-m-d')}",
                    'metricas_evaluacion' => [
                        'mae' => $forecast->mae,
                        'rmse' => $forecast->rmse,
                        'r2' => $forecast->r2,
                    ],
                    'valores_proyectados' => $forecast->results->map(fn ($r) => [
                        'fecha' => $r->date->format('Y-m-d'),
                        'pronostico' => $r->forecast_value,
                        'pronostico_crudo' => $r->raw_forecast_value,
                        'fue_acotado' => $r->was_bounded,
                        'limite_inferior' => $r->confidence_low,
                        'limite_superior' => $r->confidence_high,
                    ])->toArray(),
                ];

                $systemInstruction = "Eres un consultor analítico senior especializado en Voice of the Customer (VOC) y analítica predictiva en ATLAS.\n"
                    ."Atlas ha ejecutado las matemáticas deterministas y seleccionado automáticamente el mejor modelo mediante validación out-of-sample (walk-forward).\n"
                    ."Tu rol es INTERPRETAR los resultados sin modificar ni recalcular ningún número.\n"
                    ."Estructura tu respuesta en Markdown claro con estas secciones:\n"
                    ."### 1. Resumen Ejecutivo de la Proyección\n"
                    ."- Explica la tendencia proyectada y la razón objetiva por la cual se seleccionó este modelo específico.\n"
                    ."### 2. Fiabilidad del Modelo y Diagnóstico Estadístico\n"
                    ."- Explica el MAE (Error Absoluto Medio) y el RMSE en la escala de la métrica.\n"
                    ."- Comenta la calificación de fiabilidad ({$forecast->reliability}) y las señales consideradas.\n"
                    ."### 3. Correlación Temporal vs. Causalidad Operativa\n"
                    ."- Enfatiza que el tiempo cronológico no genera satisfacción; identifica hipótesis operativas (procesos, turnos, canales) para investigar.\n"
                    ."### 4. Recomendaciones Operativas para Gestión\n"
                    ."- Sugerencias prácticas de seguimiento.\n"
                    .'Importante: No uses fórmulas LaTeX crudas ni inglés innecesario. Usa porcentajes claros (ej: 65.4% o +0.42).';

                $response = $this->aiProvider->generate([
                    ['role' => 'user', 'content' => "Interpreta este pronóstico validado por Atlas:\n".json_encode($promptData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)],
                ], [], [
                    'system_instruction' => $systemInstruction,
                    'prompt_version' => 'forecast_v3_explainable',
                ]);

                $forecast->update([
                    'ai_interpretation' => $response['content'] ?? null,
                ]);
            }
        } catch (\Throwable $e) {
            // Qualitative interpretation failed, but mathematical results remain valid
        }

        return response()->json([
            'success' => true,
            'forecast' => $forecast->load('results'),
        ]);
    }

    /**
     * Execute Driver Analysis independently on survey-level dataset.
     */
    public function drivers(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'metric' => ['required', 'string', 'in:nps,csat,professionalism'],
            'supervisor' => ['nullable', 'string'],
            'wave' => ['nullable', 'string'],
            'date_from' => ['nullable', 'string'],
            'date_to' => ['nullable', 'string'],
            'ref_category' => ['nullable', 'string'],
            'ref_supervisor' => ['nullable', 'string'],
            'ref_wave' => ['nullable', 'string'],
        ]);

        $user = Auth::user();

        $filters = array_filter([
            'supervisor' => $validated['supervisor'] ?? null,
            'wave' => $validated['wave'] ?? null,
            'date_from' => $validated['date_from'] ?? null,
            'date_to' => $validated['date_to'] ?? null,
        ]);

        $referenceCategories = array_filter([
            'category' => $validated['ref_category'] ?? null,
            'supervisor' => $validated['ref_supervisor'] ?? null,
            'wave' => $validated['ref_wave'] ?? null,
        ]);

        $result = $this->driverAnalysisService->execute(
            metric: $validated['metric'],
            filters: $filters,
            referenceCategories: $referenceCategories,
            user: $user
        );

        // AI Qualitative Interpretation of Drivers
        try {
            $promptData = [
                'metrica_objetivo' => strtoupper($result['target_metric']),
                'tamano_muestra_total' => $result['sample_size'],
                'variables_controladas' => $result['controlled_variables'] ?? [],
                'categorias_referencia' => $result['reference_categories'] ?? [],
                'asociaciones_clave' => array_slice($result['drivers'] ?? [], 0, 8),
                'diagnosticos' => $result['diagnostics'] ?? [],
            ];

            $systemInstruction = "Eres el Consultor Analítico de IA para ATLAS VOC, especializado en análisis estadístico de drivers VOC.\n"
                ."Atlas ha calculado determinísticamente los efectos estimados mediante regresión multivariada.\n"
                ."REGLAS CRÍTICAS DE GOBERNANZA:\n"
                ."- Distingue SIEMPRE asociación estadística de causalidad operativa. NUNCA utilices frases como 'Facturación causa baja satisfacción'. Usa 'está asociado con', 'muestra una relación', 'efecto estimado'.\n"
                ."- Si un driver tiene estado 'INSUFFICIENT' o 'LOW' sample, advierte explícitamente que la muestra es pequeña y no debe considerarse un driver confiable.\n"
                ."- Destaca las asociaciones con soporte muestral ('SUPPORTED').\n"
                .'- El chat soporta renderizado matemático LaTeX/KaTeX. Puedes usar sintaxis LaTeX `$ ... $` para métricas, valores estadísticos o variables (ej. `$N = 15$`, `$p < 0.01$`, `$R^2$`) y `$$ ... $$` para fórmulas destacadas. NUNCA envuelvas expresiones LaTeX entre comillas invertidas o backticks; escribe directamente `$N = 15$` sin comillas invertidas.'."\n"
                .'- Estructura en Markdown claro con viñetas concisas y recomendaciones de foco operativo.';

            $response = $this->aiProvider->generate([
                ['role' => 'user', 'content' => "Interpreta las siguientes asociaciones estadísticas de drivers VOC:\n".json_encode($promptData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)],
            ], [], [
                'system_instruction' => $systemInstruction,
                'prompt_version' => 'drivers_v1',
            ]);

            $result['ai_interpretation'] = $response['content'] ?? null;
        } catch (\Throwable $e) {
            // Qualitative interpretation failed, deterministic results remain valid
        }

        return response()->json([
            'success' => true,
            'analysis' => $result,
        ]);
    }

    public function chat(Request $request, Forecast $forecast): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:3000'],
            'history' => ['nullable', 'array'],
            'history.*.role' => ['required_with:history', 'string', 'in:user,assistant,model'],
            'history.*.content' => ['required_with:history', 'string'],
            'driver_context' => ['nullable', 'array'],
        ]);

        $userMessage = $validated['message'];
        $history = $validated['history'] ?? [];
        $driverContext = $validated['driver_context'] ?? null;

        // Build rich forecast context for Gemini
        $forecastContext = [
            'metrica' => strtoupper($forecast->metric),
            'dimension' => $forecast->dimension,
            'valor_dimension' => $forecast->dimension_value ?: 'Toda la operación',
            'estado_calidad' => $forecast->status,
            'modelo_seleccionado' => $forecast->model,
            'razon_seleccion' => $forecast->selection_reason,
            'fiabilidad' => $forecast->reliability,
            'horizonte' => "{$forecast->forecast_horizon} días adelante",
            'periodo_entrenamiento' => $forecast->training_period_start ? "{$forecast->training_period_start->format('Y-m-d')} a {$forecast->training_period_end->format('Y-m-d')}" : 'N/A',
            'comparativa_modelos' => $forecast->parameters['candidate_comparison'] ?? [],
            'evaluacion_ajuste' => [
                'mae' => $forecast->mae,
                'rmse' => $forecast->rmse,
                'r2' => $forecast->r2 ? ($forecast->r2 * 100).'%' : 'N/A',
            ],
            'datos_historicos' => $forecast->historical_points,
            'proyecciones' => $forecast->results->map(fn ($r) => [
                'fecha' => $r->date->format('Y-m-d'),
                'proyeccion' => $r->forecast_value,
                'fue_acotado' => $r->was_bounded,
                'limite_inferior_95' => $r->confidence_low,
                'limite_superior_95' => $r->confidence_high,
            ])->toArray(),
        ];

        if ($driverContext) {
            $forecastContext['analisis_de_drivers_disponible'] = [
                'metrica' => $driverContext['target_metric'] ?? null,
                'muestra' => $driverContext['sample_size'] ?? null,
                'asociaciones_principales' => array_slice($driverContext['drivers'] ?? [], 0, 5),
            ];
        }

        $systemInstruction = "Eres el Consultor Analítico de IA para ATLAS VOC, especializado en modelos predictivos y Voice of the Customer.\n"
            ."Estás asesorando a un usuario de negocio sobre los resultados matemáticos calculados por Atlas:\n"
            .json_encode($forecastContext, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n\n"
            ."Instrucciones críticas de gobernanza:\n"
            ."- Responde SIEMPRE en español profesional, objetivo y claro.\n"
            ."- Resuelve las dudas basándote estrictamente en los datos calculados. No alteres ni inventes números.\n"
            ."- Si se discuten drivers o factores asociados, distingue firmemente correlación de causalidad operativa (usa 'asociado con', no 'causa').\n"
            ."- Si se pregunta por la fiabilidad, sé transparente respecto al tamaño muestral y la ventana histórica.\n"
            .'- El chat soporta renderizado matemático LaTeX/KaTeX. Puedes utilizar notación LaTeX `$ ... $` para variables y métricas estadísticas (ej. `$N = 15$`, `$p < 0.01$`, `$R^2$`) o `$$ ... $$` para fórmulas. NUNCA envuelvas expresiones LaTeX entre comillas invertidas o backticks; escribe directamente `$N = 15$` sin comillas invertidas.'."\n"
            .'- Puedes sintetizar los resultados del pronóstico con los drivers si ambos están disponibles.';

        $messages = [];
        foreach ($history as $h) {
            $messages[] = [
                'role' => $h['role'] === 'assistant' ? 'model' : 'user',
                'content' => $h['content'],
            ];
        }
        $messages[] = [
            'role' => 'user',
            'content' => $userMessage,
        ];

        try {
            $response = $this->aiProvider->generate($messages, [], [
                'system_instruction' => $systemInstruction,
                'prompt_version' => 'forecast_chat_v2',
            ]);

            return response()->json([
                'success' => true,
                'reply' => $response['content'] ?? 'No se pudo generar una respuesta.',
                'tokens_used' => $response['tokens_used'] ?? 0,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al comunicarse con Gemini: '.$e->getMessage(),
            ], 500);
        }
    }
}
