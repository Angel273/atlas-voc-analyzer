<?php

namespace App\Services\Ai\Providers;

use App\Models\KpiGoal;
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
            $goals = KpiGoal::getGoalsMap();
            $npsTarget = sprintf('%+.2f (%+.1f%%)', $goals['nps']['target_value'], $goals['nps']['target_percentage']);
            $csatTarget = sprintf('%.2f (%.1f%%)', $goals['csat']['target_value'], $goals['csat']['target_percentage']);
            $profTarget = sprintf('%.2f (%.1f%%)', $goals['professionalism']['target_value'], $goals['professionalism']['target_percentage']);

            $structuredContent = "## Diagnóstico Analítico y Evaluación frente a Metas\n\n"
                ."A partir de los datos auditados en ATLAS VOC y las metas operacionales vigentes, se presenta el análisis consolidado de desempeño:\n\n"
                .'| Dimensión / Grupo | Muestra ($N$) | NPS (Meta: '.$npsTarget.') | CSAT (Meta: '.$csatTarget.') | Profesionalismo (Meta: '.$profTarget.') |'."\n"
                ."| :--- | :---: | :---: | :---: | :---: |\n"
                ."| **Segmento Evaluado** | 165 | +0.50 | 0.77 | 0.85 |\n"
                ."| **Promedio General** | 766 | +0.48 | 0.75 | 0.84 |\n\n"
                ."### Cumplimiento frente a Metas Operacionales\n"
                ."- **NPS**: En meta (+0.50 alcanzado vs meta {$npsTarget}).\n"
                ."- **CSAT**: En zona de atención (0.77 vs meta {$csatTarget}, brecha de -0.03).\n"
                ."- **Profesionalismo**: En meta (0.85 cumpliendo el objetivo fijado).\n\n"
                ."### Recomendaciones Accionables\n"
                ."1. Priorizar retroalimentación dirigida a los casos con puntuaciones atípicas de satisfacción.\n"
                .'2. Monitorear los verbatims entrantes para anticipar fricciones en políticas y facturación.';

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

            if (str_contains($lower, 'meta') || str_contains($lower, 'target') || str_contains($lower, 'objetivo') || str_contains($lower, 'benchmark')) {
                return [
                    'content' => null,
                    'tool_calls' => [
                        [
                            'id' => 'call_'.substr(md5($content), 0, 8),
                            'name' => 'get_kpi_goals',
                            'arguments' => [
                                'metric' => str_contains($lower, 'csat') ? 'csat' : (str_contains($lower, 'profesionalismo') ? 'professionalism' : (str_contains($lower, 'nps') ? 'nps' : 'all')),
                            ],
                        ],
                    ],
                    'tokens_used' => 60,
                    'model' => 'mock-model-v1',
                ];
            }

            if (str_contains($lower, 'raw') || str_contains($lower, 'crudo') || str_contains($lower, 'verbatim') || str_contains($lower, 'comentario')) {
                return [
                    'content' => null,
                    'tool_calls' => [
                        [
                            'id' => 'call_'.substr(md5($content), 0, 8),
                            'name' => 'query_raw_data',
                            'arguments' => [
                                'limit' => 10,
                                'include_all' => false,
                            ],
                        ],
                    ],
                    'tokens_used' => 85,
                    'model' => 'mock-model-v1',
                ];
            }

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
