<?php

namespace App\Services\Ai\Providers;

use App\Services\Ai\Contracts\AiProvider;

class MockAiProvider implements AiProvider
{
    public function providerName(): string
    {
        return 'mock';
    }

    public function generate(array $messages, array $tools = [], array $options = []): array
    {
        $lastMessage = end($messages) ?: ['role' => 'user', 'content' => ''];
        $lastRole = $lastMessage['role'] ?? 'user';
        $content = (string) ($lastMessage['content'] ?? '');

        // If the last message was a tool result, synthesize a final answer
        if ($lastRole === 'function' || $lastRole === 'tool') {
            $structuredContent = "### Hechos observados\n"
                ."Se han consultado y verificado las métricas y registros de encuestas en la base de datos de ATLAS VOC.\n\n"
                ."### Cálculos y métricas\n"
                ."Los valores calculados reflejan la distribución actual de satisfacción, NPS y desempeño de los equipos evaluados conforme a los parámetros de la consulta.\n\n"
                ."### Interpretación / Recomendaciones\n"
                .'Se observa una tendencia consistente en los segmentos analizados. Se sugiere profundizar en las causas raíz de aquellos supervisores o agentes que presenten mayor variabilidad en sus calificaciones.';

            return [
                'content' => $structuredContent,
                'tool_calls' => [],
                'tokens_used' => 140,
                'model' => 'mock-model-v1',
            ];
        }

        // If tools are provided and user is asking an analytical question, trigger a tool call
        if (! empty($tools)) {
            $lower = strtolower($content);

            if (str_contains($lower, 'nps') || str_contains($lower, 'csat') || str_contains($lower, 'supervisor') || str_contains($lower, 'agent') || str_contains($lower, 'compare')) {
                return [
                    'content' => null,
                    'tool_calls' => [
                        [
                            'id' => 'call_'.substr(md5($content), 0, 8),
                            'name' => 'query_data',
                            'arguments' => [
                                'metric' => str_contains($lower, 'csat') ? 'csat' : 'nps',
                                'aggregation' => 'avg',
                                'group_by' => str_contains($lower, 'supervisor') ? ['supervisor'] : ['agent'],
                            ],
                        ],
                    ],
                    'tokens_used' => 85,
                    'model' => 'mock-model-v1',
                ];
            }

            if (str_contains($lower, 'forecast')) {
                return [
                    'content' => null,
                    'tool_calls' => [
                        [
                            'id' => 'call_'.substr(md5($content), 0, 8),
                            'name' => 'run_forecast',
                            'arguments' => [
                                'metric' => 'nps',
                                'model' => 'linear_trend',
                                'horizon' => 7,
                            ],
                        ],
                    ],
                    'tokens_used' => 90,
                    'model' => 'mock-model-v1',
                ];
            }
        }

        // Default direct response
        return [
            'content' => 'Atlas VOC Assistant ready. I can analyze NPS, CSAT, professionalism, team performance, verbatim categories, and forecasts.',
            'tool_calls' => [],
            'tokens_used' => 50,
            'model' => 'mock-model-v1',
        ];
    }
}
