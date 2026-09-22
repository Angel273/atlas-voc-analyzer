<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Desempeño - {{ $data['team']['name'] }}</title>
    <style>
        @page {
            margin: 25px 30px 40px 30px;
            @bottom-right {
                content: "Página " counter(page);
                font-family: 'DejaVu Sans', sans-serif;
                font-size: 8px;
                color: #64748b;
            }
        }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 9px;
            line-height: 1.35;
            color: #1e293b;
            background-color: #ffffff;
            margin: 0;
            padding: 0;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            background-color: #18221d;
            color: #ffffff;
            padding: 12px 16px;
            margin-bottom: 14px;
            border-radius: 4px;
        }
        .header-title {
            font-size: 16px;
            font-weight: bold;
            color: #ffffff;
            margin: 0 0 2px 0;
        }
        .header-subtitle {
            font-size: 9px;
            color: #94a3b8;
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .badge-report-id {
            background-color: #26382e;
            color: #d7f45b;
            padding: 4px 8px;
            font-size: 9px;
            font-weight: bold;
            border-radius: 3px;
            display: inline-block;
        }
        .meta-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
        }
        .meta-table td {
            padding: 6px 10px;
            font-size: 8.5px;
            border-bottom: 1px solid #edf2f7;
        }
        .meta-label {
            font-weight: bold;
            color: #475569;
            width: 18%;
        }
        .meta-val {
            color: #0f172a;
            width: 32%;
        }
        .alert-low-sample {
            background-color: #fffbeb;
            border: 1px solid #fde68a;
            color: #92400e;
            padding: 7px 10px;
            border-radius: 4px;
            margin-bottom: 12px;
            font-size: 8.5px;
        }
        .section-title {
            font-size: 11px;
            font-weight: bold;
            color: #18221d;
            border-bottom: 2px solid #18221d;
            padding-bottom: 3px;
            margin-top: 14px;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .exec-summary-box {
            background-color: #f0fdf4;
            border-left: 3px solid #16a34a;
            padding: 8px 12px;
            font-size: 9px;
            line-height: 1.45;
            color: #14532d;
            margin-bottom: 12px;
            border-radius: 0 4px 4px 0;
        }
        .metrics-grid-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }
        .metrics-grid-table td {
            width: 25%;
            padding: 4px;
            vertical-align: top;
        }
        .metric-card {
            border: 1px solid #e2e8f0;
            background-color: #ffffff;
            border-radius: 4px;
            padding: 8px;
            text-align: center;
        }
        .metric-card-title {
            font-size: 8px;
            text-transform: uppercase;
            color: #64748b;
            font-weight: bold;
            margin-bottom: 4px;
        }
        .metric-card-val {
            font-size: 16px;
            font-weight: bold;
            color: #0f172a;
            margin-bottom: 2px;
        }
        .metric-card-target {
            font-size: 7.5px;
            color: #475569;
        }
        .metric-card-badge {
            display: inline-block;
            padding: 2px 6px;
            font-size: 7.5px;
            font-weight: bold;
            border-radius: 3px;
            margin-top: 3px;
        }
        .badge-success { background-color: #dcfce7; color: #166534; }
        .badge-danger { background-color: #fee2e2; color: #991b1b; }
        .badge-warning { background-color: #fef3c7; color: #92400e; }
        .badge-info { background-color: #e0f2fe; color: #0369a1; }
        .badge-secondary { background-color: #f1f5f9; color: #475569; }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
            font-size: 8px;
        }
        .data-table th {
            background-color: #1e293b;
            color: #ffffff;
            font-weight: bold;
            text-align: left;
            padding: 5px 6px;
            border: 1px solid #1e293b;
        }
        .data-table td {
            padding: 4px 6px;
            border: 1px solid #e2e8f0;
            vertical-align: middle;
        }
        .data-table tr:nth-child(even) {
            background-color: #f8fafc;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .font-bold { font-weight: bold; }

        .two-column-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }
        .two-column-table td {
            width: 50%;
            vertical-align: top;
            padding: 0 4px;
        }
        .column-box {
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 8px 10px;
            background-color: #ffffff;
            min-height: 100px;
        }
        .column-box-title {
            font-size: 9px;
            font-weight: bold;
            margin-bottom: 6px;
            padding-bottom: 3px;
            border-bottom: 1px solid #cbd5e1;
        }
        .column-box ul {
            margin: 0;
            padding-left: 14px;
            font-size: 8px;
            line-height: 1.4;
            color: #334155;
        }
        .column-box li {
            margin-bottom: 4px;
        }
        .page-break {
            page-break-before: always;
        }
        .avoid-break {
            page-break-inside: avoid;
        }
        .agent-dossier {
            margin-bottom: 14px;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            background-color: #ffffff;
            page-break-inside: auto;
        }
        .agent-dossier-header-table {
            width: 100%;
            border-collapse: collapse;
            background-color: #1e293b;
            color: #ffffff;
            border-radius: 3px 3px 0 0;
        }
        .agent-dossier-header-table td {
            padding: 5px 8px;
            vertical-align: middle;
        }
        .agent-dossier-title {
            font-size: 10px;
            font-weight: bold;
            color: #ffffff;
        }
        .agent-dossier-bms {
            font-size: 7.5px;
            color: #94a3b8;
            font-family: monospace;
        }
        .agent-metrics-table {
            width: 100%;
            border-collapse: collapse;
            background-color: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }
        .agent-metrics-table td {
            width: 25%;
            padding: 5px 6px;
            text-align: center;
            border-right: 1px solid #e2e8f0;
            border-bottom: 1px solid #e2e8f0;
        }
        .agent-metrics-table td:last-child {
            border-right: none;
        }
        .agent-metric-label {
            font-size: 6.5px;
            text-transform: uppercase;
            color: #64748b;
            font-weight: bold;
        }
        .agent-metric-val {
            font-size: 11px;
            font-weight: bold;
            color: #0f172a;
            margin-top: 1px;
        }
        .agent-metric-sub {
            font-size: 6.5px;
            color: #64748b;
            margin-top: 1px;
        }
        .agent-analysis-body {
            padding: 6px 8px;
        }
        .agent-section-subtitle {
            font-size: 8px;
            font-weight: bold;
            color: #18221d;
            text-transform: uppercase;
            margin-top: 5px;
            margin-bottom: 3px;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 2px;
        }
        .agent-narrative-text {
            font-size: 8px;
            line-height: 1.35;
            color: #334155;
            margin-bottom: 5px;
        }
        .agent-coaching-box {
            background-color: #eff6ff;
            border-left: 3px solid #3b82f6;
            padding: 5px 7px;
            margin-top: 4px;
            margin-bottom: 6px;
            font-size: 7.5px;
            color: #1e3a8a;
            line-height: 1.35;
            border-radius: 0 3px 3px 0;
        }
        .verbatim-list {
            margin-top: 5px;
        }
        .verbatim-card {
            border: 1px solid #e2e8f0;
            border-radius: 3px;
            padding: 5px 7px;
            margin-bottom: 4px;
            background-color: #ffffff;
            page-break-inside: avoid;
        }
        .verbatim-promoter {
            border-left: 3px solid #16a34a;
            background-color: #f0fdf4;
        }
        .verbatim-detractor {
            border-left: 3px solid #dc2626;
            background-color: #fef2f2;
        }
        .verbatim-passive {
            border-left: 3px solid #d97706;
            background-color: #fffbeb;
        }
        .verbatim-header {
            font-size: 7px;
            color: #64748b;
            margin-bottom: 2px;
        }
        .verbatim-text {
            font-size: 7.5px;
            font-style: italic;
            color: #1e293b;
            line-height: 1.35;
        }
        .footer-note {
            margin-top: 15px;
            border-top: 1px solid #e2e8f0;
            padding-top: 6px;
            font-size: 7.5px;
            color: #64748b;
            text-align: center;
        }
    </style>
</head>
<body>
    @php
        $goals = $data['goals'] ?? \App\Models\KpiGoal::getGoalsMap();
        $npsTarget = (float) ($goals['nps']['target_value'] ?? 0.50);
        $npsTargetPct = (float) ($goals['nps']['target_percentage'] ?? 50.0);
        $csatTarget = (float) ($goals['csat']['target_value'] ?? 0.80);
        $csatTargetPct = (float) ($goals['csat']['target_percentage'] ?? 80.0);
        $profTarget = (float) ($goals['professionalism']['target_value'] ?? 0.85);
        $profTargetPct = (float) ($goals['professionalism']['target_percentage'] ?? 85.0);
    @endphp

    {{-- HEADER BANNER --}}
    <table class="header-table">
        <tr>
            <td style="vertical-align: middle;">
                <div class="header-title">ATLAS VOC ANALYZER</div>
                <div class="header-subtitle">Informe Ejecutivo de Desempeño Operacional y Voz del Cliente</div>
            </td>
            <td style="text-align: right; vertical-align: middle; width: 220px;">
                <div class="badge-report-id">REPORTE #{{ $report->id }}</div>
                <div style="font-size: 7.5px; color: #cbd5e1; margin-top: 3px;">
                    Corte: {{ $data['period']['cutoff_date'] }} | Generado: {{ now()->format('d/m/Y H:i') }}
                </div>
            </td>
        </tr>
    </table>

    {{-- METADATA OVERVIEW --}}
    <table class="meta-table">
        <tr>
            <td class="meta-label">Equipo:</td>
            <td class="meta-val font-bold">{{ $data['team']['name'] }} ({{ $data['team']['code'] }})</td>
            <td class="meta-label">Período Evaluado:</td>
            <td class="meta-val">{{ $data['period']['from'] }} al {{ $data['period']['to'] }}</td>
        </tr>
        <tr>
            <td class="meta-label">Supervisor:</td>
            <td class="meta-val">{{ $data['team']['supervisor_name'] }}</td>
            <td class="meta-label">Data Version Hash:</td>
            <td class="meta-val" style="font-family: monospace; font-size: 7px;">{{ substr($data['data_version'], 0, 24) }}...</td>
        </tr>
        <tr>
            <td class="meta-label">Total Encuestas:</td>
            <td class="meta-val font-bold">{{ $data['metrics']['survey_volume'] }} interacciones</td>
            <td class="meta-label">Motor de Análisis:</td>
            <td class="meta-val">{{ $report->model ?? 'gemini-flash' }} ({{ !empty($narrative['is_fallback']) ? 'Reglas Determinísticas' : 'IA Asistida' }})</td>
        </tr>
        <tr>
            <td class="meta-label">Metas Operacionales:</td>
            <td class="meta-val" colspan="3" style="font-size: 7.5px;">
                <strong>NPS:</strong> &ge; {{ sprintf('%+.2f', $npsTarget) }} ({{ sprintf('%.1f%%', $npsTargetPct) }}) &bull;
                <strong>CSAT:</strong> &ge; {{ sprintf('%.1f%%', $csatTargetPct) }} Top-Box &bull;
                <strong>Profesionalismo:</strong> &ge; {{ sprintf('%.1f%%', $profTargetPct) }} Top-Box
            </td>
        </tr>
    </table>

    @if($data['is_low_sample'])
        <div class="alert-low-sample">
            <strong>ADVERTENCIA DE REPRESENTATIVIDAD:</strong> La muestra analizada en este período cuenta con 
            <strong>{{ $data['metrics']['survey_volume'] }} encuestas</strong>, ubicándose por debajo del umbral mínimo recomendado (15 encuestas).
            Las métricas porcentuales deben evaluarse como indicativas y complementarse con monitoreo cualitativo directo.
        </div>
    @endif

    {{-- EXECUTIVE SUMMARY --}}
    <div class="section-title">Resumen Ejecutivo de Gestión</div>
    <div class="exec-summary-box">
        {{ $narrative['executive_summary'] ?? 'Durante el período evaluado se consolidó el desempeño operativo del equipo.' }}
    </div>

    {{-- KEY METRICS CARDS --}}
    <div class="section-title">Indicadores Clave vs Metas Operacionales</div>
    <table class="metrics-grid-table">
        <tr>
            <td>
                <div class="metric-card">
                    <div class="metric-card-title">Muestra Total</div>
                    <div class="metric-card-val">{{ $data['metrics']['survey_volume'] }}</div>
                    <div class="metric-card-target">Auditadas en período</div>
                    <span class="metric-card-badge {{ $data['is_low_sample'] ? 'badge-warning' : 'badge-info' }}">
                        {{ $data['is_low_sample'] ? 'Muestra Frágil' : 'Válida' }}
                    </span>
                </div>
            </td>
            <td>
                @php
                    $npsMet = $data['metrics']['goals_comparison']['nps']['meets_goal'] ?? false;
                    $npsScore = $data['metrics']['nps_score'];
                @endphp
                <div class="metric-card">
                    <div class="metric-card-title">Net Promoter Score</div>
                    <div class="metric-card-val" style="color: {{ $npsMet ? '#166534' : '#991b1b' }};">
                        {{ $npsScore !== null ? sprintf('%+.2f', $npsScore) : 'N/D' }}
                    </div>
                    <div class="metric-card-target">Meta: &ge; {{ sprintf('%+.2f', $npsTarget) }} ({{ sprintf('%.1f%%', $npsTargetPct) }})</div>
                    <span class="metric-card-badge {{ $npsMet ? 'badge-success' : 'badge-danger' }}">
                        {{ $npsMet ? 'Meta Cumplida' : 'Bajo Meta' }}
                    </span>
                </div>
            </td>
            <td>
                @php
                    $csatMet = $data['metrics']['goals_comparison']['csat']['meets_goal'] ?? false;
                    $csatScore = $data['metrics']['csat_score'];
                @endphp
                <div class="metric-card">
                    <div class="metric-card-title">CSAT (Satisfacción)</div>
                    <div class="metric-card-val" style="color: {{ $csatMet ? '#166534' : '#991b1b' }};">
                        {{ $csatScore !== null ? sprintf('%.1f%%', $csatScore * 100) : 'N/D' }}
                    </div>
                    <div class="metric-card-target">Meta: &ge; {{ sprintf('%.1f%%', $csatTargetPct) }} Top-Box</div>
                    <span class="metric-card-badge {{ $csatMet ? 'badge-success' : 'badge-danger' }}">
                        {{ $csatMet ? 'Meta Cumplida' : 'En Atención' }}
                    </span>
                </div>
            </td>
            <td>
                @php
                    $profMet = $data['metrics']['goals_comparison']['professionalism']['meets_goal'] ?? false;
                    $profScore = $data['metrics']['professionalism_score'];
                @endphp
                <div class="metric-card">
                    <div class="metric-card-title">Profesionalismo</div>
                    <div class="metric-card-val" style="color: {{ $profMet ? '#166534' : '#991b1b' }};">
                        {{ $profScore !== null ? sprintf('%.1f%%', $profScore * 100) : 'N/D' }}
                    </div>
                    <div class="metric-card-target">Meta: &ge; {{ sprintf('%.1f%%', $profTargetPct) }} Top-Box</div>
                    <span class="metric-card-badge {{ $profMet ? 'badge-success' : 'badge-danger' }}">
                        {{ $profMet ? 'Meta Cumplida' : 'Bajo Meta' }}
                    </span>
                </div>
            </td>
        </tr>
    </table>

    {{-- COMPARISON WITH PREVIOUS PERIOD IF APPLICABLE --}}
    @if(!empty($data['comparison']))
        <div class="section-title">Comparativa frente al Período Anterior</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Métrica Evaluada</th>
                    <th class="text-center">Período Previo ({{ $data['comparison']['previous_period']['from'] }} al {{ $data['comparison']['previous_period']['to'] }})</th>
                    <th class="text-center">Período Actual ({{ $data['period']['from'] }} al {{ $data['period']['to'] }})</th>
                    <th class="text-center">Variación Absoluta</th>
                    <th class="text-center">Tendencia</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $prev = $data['comparison']['previous_metrics'];
                    $curr = $data['metrics'];
                    $deltas = $data['comparison']['deltas'];
                @endphp
                <tr>
                    <td class="font-bold">Volumen de Encuestas</td>
                    <td class="text-center">{{ $prev['survey_volume'] }}</td>
                    <td class="text-center font-bold">{{ $curr['survey_volume'] }}</td>
                    <td class="text-center">{{ sprintf('%+d', $deltas['volume_delta']) }}</td>
                    <td class="text-center">
                        <span class="metric-card-badge {{ $deltas['volume_delta'] >= 0 ? 'badge-success' : 'badge-warning' }}">
                            {{ $deltas['volume_delta'] >= 0 ? 'Incremento' : 'Disminución' }}
                        </span>
                    </td>
                </tr>
                <tr>
                    <td class="font-bold">NPS (Net Promoter Score)</td>
                    <td class="text-center">{{ $prev['nps_score'] !== null ? sprintf('%+.2f (%+.1f%%)', $prev['nps_score'], $prev['nps_score'] * 100) : 'N/D' }}</td>
                    <td class="text-center font-bold">{{ $curr['nps_score'] !== null ? sprintf('%+.2f (%+.1f%%)', $curr['nps_score'], $curr['nps_score'] * 100) : 'N/D' }}</td>
                    <td class="text-center">{{ $deltas['nps_delta'] !== null ? sprintf('%+.2f pts', $deltas['nps_delta']) : 'N/D' }}</td>
                    <td class="text-center">
                        @if($deltas['nps_delta'] !== null)
                            <span class="metric-card-badge {{ $deltas['nps_delta'] >= 0 ? 'badge-success' : 'badge-danger' }}">
                                {{ $deltas['nps_delta'] >= 0 ? 'Mejora' : 'Caída' }}
                            </span>
                        @else
                            <span class="metric-card-badge badge-secondary">N/D</span>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td class="font-bold">CSAT (Satisfacción del Cliente)</td>
                    <td class="text-center">{{ $prev['csat_score'] !== null ? sprintf('%.1f%%', $prev['csat_score'] * 100) : 'N/D' }}</td>
                    <td class="text-center font-bold">{{ $curr['csat_score'] !== null ? sprintf('%.1f%%', $curr['csat_score'] * 100) : 'N/D' }}</td>
                    <td class="text-center">{{ $deltas['csat_delta'] !== null ? sprintf('%+.1f%%', $deltas['csat_delta'] * 100) : 'N/D' }}</td>
                    <td class="text-center">
                        @if($deltas['csat_delta'] !== null)
                            <span class="metric-card-badge {{ $deltas['csat_delta'] >= 0 ? 'badge-success' : 'badge-danger' }}">
                                {{ $deltas['csat_delta'] >= 0 ? 'Favorable' : 'Desfavorable' }}
                            </span>
                        @else
                            <span class="metric-card-badge badge-secondary">N/D</span>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td class="font-bold">Profesionalismo</td>
                    <td class="text-center">{{ $prev['professionalism_score'] !== null ? sprintf('%.1f%%', $prev['professionalism_score'] * 100) : 'N/D' }}</td>
                    <td class="text-center font-bold">{{ $curr['professionalism_score'] !== null ? sprintf('%.1f%%', $curr['professionalism_score'] * 100) : 'N/D' }}</td>
                    <td class="text-center">{{ $deltas['professionalism_delta'] !== null ? sprintf('%+.1f%%', $deltas['professionalism_delta'] * 100) : 'N/D' }}</td>
                    <td class="text-center">
                        @if($deltas['professionalism_delta'] !== null)
                            <span class="metric-card-badge {{ $deltas['professionalism_delta'] >= 0 ? 'badge-success' : 'badge-danger' }}">
                                {{ $deltas['professionalism_delta'] >= 0 ? 'Favorable' : 'Desfavorable' }}
                            </span>
                        @else
                            <span class="metric-card-badge badge-secondary">N/D</span>
                        @endif
                    </td>
                </tr>
            </tbody>
        </table>
    @endif

    {{-- STRENGTHS AND RISKS (SIDE BY SIDE) --}}
    <table class="two-column-table avoid-break">
        <tr>
            <td>
                <div class="column-box" style="border-left: 3px solid #16a34a;">
                    <div class="column-box-title" style="color: #166534;">Fortalezas y Logros Operacionales</div>
                    <ul>
                        @foreach($narrative['team_strengths'] ?? [] as $st)
                            <li>{{ $st }}</li>
                        @endforeach
                    </ul>
                </div>
            </td>
            <td>
                <div class="column-box" style="border-left: 3px solid #dc2626;">
                    <div class="column-box-title" style="color: #991b1b;">Riesgos y Brechas Críticas</div>
                    <ul>
                        @foreach($narrative['team_risks'] ?? [] as $rk)
                            <li>{{ $rk }}</li>
                        @endforeach
                    </ul>
                </div>
            </td>
        </tr>
    </table>

    {{-- DAILY TRENDS & RUNCHART (PAGE 2 IF NEEDED) --}}
    @if(!empty($data['daily_trends']))
        <div class="avoid-break" style="margin-bottom: 12px;">
            <div class="section-title">Secuencia Temporal y Tendencia de Desempeño (Runchart del Equipo)</div>
            <div style="margin-bottom: 6px; background-color: #ffffff; border: 1px solid #cbd5e1; border-radius: 4px; padding: 4px;">
                {!! \App\Services\Reports\RunChartSvgService::renderImg($data['daily_trends'], $npsTarget, $csatTarget, 520, 105, false) !!}
            </div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th class="text-center">Volumen</th>
                        <th class="text-center">NPS</th>
                        <th class="text-center">CSAT (Satisfacción)</th>
                        <th class="text-center">Profesionalismo</th>
                        <th class="text-center">Diagnóstico Diario</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($data['daily_trends'] as $day)
                        @php
                            $dMet = ($day['nps'] !== null && $day['nps'] >= $npsTarget) && ($day['csat'] !== null && $day['csat'] >= $csatTarget);
                        @endphp
                        <tr>
                            <td class="font-bold">{{ $day['date'] }}</td>
                            <td class="text-center">{{ $day['volume'] }}</td>
                            <td class="text-center">{{ $day['nps'] !== null ? sprintf('%+.2f', $day['nps']) : 'N/D' }}</td>
                            <td class="text-center">{{ $day['csat'] !== null ? sprintf('%.1f%%', $day['csat'] * 100) : 'N/D' }}</td>
                            <td class="text-center">{{ $day['professionalism'] !== null ? sprintf('%.1f%%', $day['professionalism'] * 100) : 'N/D' }}</td>
                            <td class="text-center">
                                @if($day['volume'] < 3)
                                    <span class="metric-card-badge badge-secondary">Baja Muestra</span>
                                @elseif($dMet)
                                    <span class="metric-card-badge badge-success">En Meta</span>
                                @else
                                    <span class="metric-card-badge badge-warning">Oportunidad</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- AGENT PERFORMANCE TABLE --}}
    <div class="page-break"></div>
    <div class="section-title">Evaluación Individual de Agentes del Equipo</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Agente / Colaborador</th>
                <th class="text-center">BMS ID</th>
                <th class="text-center">Muestra</th>
                <th class="text-center">NPS</th>
                <th class="text-center">CSAT</th>
                <th class="text-center">Profesionalismo</th>
                <th class="text-center">Estado Operacional</th>
            </tr>
        </thead>
        <tbody>
            @forelse($data['agent_reviews'] as $agent)
                <tr>
                    <td class="font-bold">{{ $agent['agent_name'] }}</td>
                    <td class="text-center" style="font-family: monospace;">{{ $agent['agent_bms'] }}</td>
                    <td class="text-center">{{ $agent['volume'] }}</td>
                    <td class="text-center">{{ $agent['nps'] !== null ? sprintf('%+.2f', $agent['nps']) : 'N/D' }}</td>
                    <td class="text-center">{{ $agent['csat'] !== null ? sprintf('%.1f%%', $agent['csat'] * 100) : 'N/D' }}</td>
                    <td class="text-center">{{ $agent['professionalism'] !== null ? sprintf('%.1f%%', $agent['professionalism'] * 100) : 'N/D' }}</td>
                    <td class="text-center">
                        @if($agent['status'] === 'critical')
                            <span class="metric-card-badge badge-danger">Crítico</span>
                        @elseif($agent['status'] === 'warning')
                            <span class="metric-card-badge badge-warning">Atención</span>
                        @elseif($agent['status'] === 'low_sample')
                            <span class="metric-card-badge badge-secondary">Muestra Baja</span>
                        @else
                            <span class="metric-card-badge badge-success">En Meta</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center">No se registraron agentes asignados con encuestas en este período.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{-- DETAILED INDIVIDUAL AGENT DOSSIERS (METRICS, VOC VERBATIMS & COACHING) --}}
    @php
        $narrativeReviews = $narrative['agent_reviews'] ?? [];

        $findAgentNar = function ($agent, $reviews) {
            // 1. Exact name match
            foreach ($reviews as $r) {
                if (isset($r['agent_name']) && trim(mb_strtolower((string)$r['agent_name'])) === trim(mb_strtolower((string)$agent['agent_name']))) {
                    return $r;
                }
            }
            // 2. BMS match
            if (!empty($agent['agent_bms'])) {
                foreach ($reviews as $r) {
                    if (!empty($r['agent_bms']) && trim((string)$r['agent_bms']) === trim((string)$agent['agent_bms'])) {
                        return $r;
                    }
                }
            }
            // 3. Word token set match
            $tokenize = function ($str) {
                $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $str) ?: $str;
                $words = preg_split('/\s+/', preg_replace('/[^a-zA-Z0-9]/', ' ', mb_strtolower($ascii)));
                $words = array_filter($words, fn($w) => strlen((string)$w) > 0);
                sort($words);
                return implode(' ', $words);
            };
            $agToken = $tokenize($agent['agent_name']);
            if (!empty($agToken)) {
                foreach ($reviews as $r) {
                    if (!empty($r['agent_name']) && $tokenize((string)$r['agent_name']) === $agToken) {
                        return $r;
                    }
                }
            }
            return null;
        };
    @endphp

    <div class="page-break"></div>
    <div class="section-title">Diagnóstico Individual y Voz del Cliente por Colaborador</div>

    @forelse($data['agent_reviews'] as $agent)
        @php
            $nar = $findAgentNar($agent, $narrativeReviews);
            $agentVerbatims = $agent['verbatims'] ?? [];
            $vol = $agent['volume'];
            $promCount = $agent['promoters_count'] ?? 0;
            $detCount = $agent['detractors_count'] ?? 0;
            $pasCount = $agent['passives_count'] ?? 0;
            $promPct = $vol > 0 ? round(($promCount / $vol) * 100, 1) : 0;
            $detPct = $vol > 0 ? round(($detCount / $vol) * 100, 1) : 0;
            $pasPct = $vol > 0 ? round(($pasCount / $vol) * 100, 1) : 0;

            // Guaranteed analysis fallback on the fly: never leaves an agent without analysis!
            if (empty($nar) || empty($nar['assessment'])) {
                $npsFmt = $agent['nps'] !== null ? sprintf('%+.2f', $agent['nps']) : 'N/D';
                $csatFmt = $agent['csat'] !== null ? sprintf('%.1f%%', $agent['csat'] * 100) : 'N/D';
                $profFmt = $agent['professionalism'] !== null ? sprintf('%.1f%%', $agent['professionalism'] * 100) : 'N/D';
                $stat = $agent['status'] ?? 'on_target';

                $ass = "Volumen: {$vol} encuestas. NPS: {$npsFmt}, CSAT: {$csatFmt}, Profesionalismo: {$profFmt}. ";
                $act = 'Mantener acompañamiento y seguimiento rutinario de interacciones.';
                if ($stat === 'critical') {
                    $ass .= 'Desempeño en rango crítico con oportunidades prioritarias de satisfacción.';
                    $act = 'Programar sesión urgente 1 a 1 de calibración de llamadas y plan de acompañamiento intensivo.';
                } elseif ($stat === 'warning') {
                    $ass .= 'Desempeño con oportunidad de mejora frente a metas de satisfacción.';
                    $act = 'Reforzar técnicas de resolución en primer contacto y empatía.';
                } elseif ($stat === 'low_sample') {
                    $ass .= 'Muestra reducida para concluir tendencia estadística definitiva.';
                    $act = 'Priorizar monitoreo adicional de llamadas para evaluar calidad de manera representativa.';
                } else {
                    $ass .= 'Rendimiento alineado con las metas operacionales de calidad.';
                    $act = 'Reconocer buen desempeño e incentivar como referente en mejores prácticas.';
                }

                $promQuotes = array_filter($agentVerbatims, fn ($v) => ($v['sentiment'] ?? '') === 'promoter');
                $detQuotes = array_filter($agentVerbatims, fn ($v) => ($v['sentiment'] ?? '') === 'detractor');
                $vCnt = count($agentVerbatims);

                $topC = !empty($agent['top_categories']) ? ' con concentración en: '.implode(', ', $agent['top_categories']) : '';
                $vbAnal = $vCnt > 0
                    ? "Se registraron {$vCnt} comentarios de clientes{$topC}. ".(!empty($promQuotes) ? count($promQuotes)." menciones promotoras favorables. " : "").(!empty($detQuotes) ? count($detQuotes)." menciones con oportunidad de resolución." : "")
                    : "No se registraron comentarios textuales de clientes en este corte evaluado.";

                $stList = !empty($promQuotes) ? ['Reconocimiento explícito de clientes por trato cordial, disposición y cortesía.'] : ['Atención continua y registro consistente de interacciones con usuarios.'];
                $fpList = !empty($detQuotes) ? ['Comentarios de clientes señalando inconformidad con tiempos de resolución o respuesta.'] : ['Mantener consistencia operativa en la gestión de casos atípicos.'];

                $nar = [
                    'agent_name' => $agent['agent_name'],
                    'agent_bms' => $agent['agent_bms'] ?? '',
                    'assessment' => $ass,
                    'action' => $act,
                    'verbatim_analysis' => $vbAnal,
                    'strengths' => $stList,
                    'friction_points' => $fpList,
                ];
            }
        @endphp

        <div class="agent-dossier">
            {{-- Header --}}
            <table class="agent-dossier-header-table">
                <tr>
                    <td>
                        <span class="agent-dossier-title">{{ $agent['agent_name'] }}</span>
                        <span class="agent-dossier-bms">&nbsp;|&nbsp;BMS: {{ $agent['agent_bms'] }}</span>
                    </td>
                    <td class="text-right" style="width: 240px;">
                        <span style="font-size: 7.5px; color: #cbd5e1; margin-right: 6px;">Muestra: {{ $vol }} encuestas</span>
                        @if($agent['status'] === 'critical')
                            <span class="metric-card-badge badge-danger">Crítico</span>
                        @elseif($agent['status'] === 'warning')
                            <span class="metric-card-badge badge-warning">Atención</span>
                        @elseif($agent['status'] === 'low_sample')
                            <span class="metric-card-badge badge-secondary">Muestra Baja</span>
                        @else
                            <span class="metric-card-badge badge-success">En Meta</span>
                        @endif
                    </td>
                </tr>
            </table>

            {{-- Scorecard Metrics --}}
            @php
                $agentNpsMet = $agent['goals_comparison']['nps']['meets_goal'] ?? ($agent['nps'] !== null && $agent['nps'] >= $npsTarget);
                $agentCsatMet = $agent['goals_comparison']['csat']['meets_goal'] ?? ($agent['csat'] !== null && $agent['csat'] >= $csatTarget);
                $agentProfMet = $agent['goals_comparison']['professionalism']['meets_goal'] ?? ($agent['professionalism'] !== null && $agent['professionalism'] >= $profTarget);
            @endphp
            <table class="agent-metrics-table">
                <tr>
                    <td>
                        <div class="agent-metric-label">Net Promoter Score</div>
                        <div class="agent-metric-val" style="color: {{ $agentNpsMet ? '#166534' : '#991b1b' }};">
                            {{ $agent['nps'] !== null ? sprintf('%+.2f', $agent['nps']) : 'N/D' }}
                        </div>
                        <div class="agent-metric-sub">Meta: &ge; {{ sprintf('%+.2f', $npsTarget) }}</div>
                    </td>
                    <td>
                        <div class="agent-metric-label">CSAT (Satisfacción)</div>
                        <div class="agent-metric-val" style="color: {{ $agentCsatMet ? '#166534' : '#991b1b' }};">
                            {{ $agent['csat'] !== null ? sprintf('%.1f%%', $agent['csat'] * 100) : 'N/D' }}
                        </div>
                        <div class="agent-metric-sub">Meta: &ge; {{ sprintf('%.1f%%', $csatTargetPct) }}</div>
                    </td>
                    <td>
                        <div class="agent-metric-label">Profesionalismo</div>
                        <div class="agent-metric-val" style="color: {{ $agentProfMet ? '#166534' : '#991b1b' }};">
                            {{ $agent['professionalism'] !== null ? sprintf('%.1f%%', $agent['professionalism'] * 100) : 'N/D' }}
                        </div>
                        <div class="agent-metric-sub">Meta: &ge; {{ sprintf('%.1f%%', $profTargetPct) }}</div>
                    </td>
                    <td>
                        <div class="agent-metric-label">Distribución de Votos</div>
                        <div class="agent-metric-val" style="font-size: 8.5px; margin-top: 3px;">
                            <span style="color: #166534;">{{ $promCount }} Prom ({{ $promPct }}%)</span><br>
                            <span style="color: #991b1b;">{{ $detCount }} Det ({{ $detPct }}%)</span>
                        </div>
                        <div class="agent-metric-sub">{{ $pasCount }} Neutros ({{ $pasPct }}%)</div>
                    </td>
                </tr>
            </table>

            {{-- Agent Run Chart --}}
            @if(!empty($agent['daily_trends']) && count($agent['daily_trends']) > 0)
                <div style="margin-top: 4px; margin-bottom: 5px; background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 3px; padding: 3px 5px;">
                    <div style="font-size: 7px; font-weight: bold; color: #475569; margin-bottom: 2px;">
                        Secuencia Temporal Diaria (Runchart del Colaborador)
                    </div>
                    {!! \App\Services\Reports\RunChartSvgService::renderImg($agent['daily_trends'], $npsTarget, $csatTarget, 500, 70, true) !!}
                </div>
            @endif

            {{-- Analysis & Coaching --}}
            <div class="agent-analysis-body">
                <div class="avoid-break">
                    <div class="agent-section-subtitle">Diagnóstico Operativo y Análisis de la Voz del Cliente</div>
                    <div class="agent-narrative-text">
                        <strong>Evaluación Numérica:</strong> {{ $nar['assessment'] ?? 'Evaluación cuantitativa registrada conforme a la muestra auditada.' }}
                    </div>
                    @if(!empty($nar['verbatim_analysis']))
                        <div class="agent-narrative-text">
                            <strong>Voz del Cliente & Percepción:</strong> {{ $nar['verbatim_analysis'] }}
                        </div>
                    @endif

                    @if(!empty($nar['strengths']) || !empty($nar['friction_points']))
                        <table style="width: 100%; border-collapse: collapse; margin-bottom: 6px;">
                            <tr>
                                @if(!empty($nar['strengths']))
                                    <td style="width: 50%; vertical-align: top; padding-right: 4px;">
                                        <div style="font-size: 7.5px; font-weight: bold; color: #166534; margin-bottom: 2px;">Fortalezas Reconocidas por Clientes:</div>
                                        <ul style="margin: 0; padding-left: 12px; font-size: 7.5px; color: #1e293b; line-height: 1.35;">
                                            @foreach($nar['strengths'] as $st)
                                                <li>{{ $st }}</li>
                                            @endforeach
                                        </ul>
                                    </td>
                                @endif
                                @if(!empty($nar['friction_points']))
                                    <td style="width: 50%; vertical-align: top; padding-left: 4px;">
                                        <div style="font-size: 7.5px; font-weight: bold; color: #991b1b; margin-bottom: 2px;">Causas Raíz de Inconformidad:</div>
                                        <ul style="margin: 0; padding-left: 12px; font-size: 7.5px; color: #1e293b; line-height: 1.35;">
                                            @foreach($nar['friction_points'] as $fp)
                                                <li>{{ $fp }}</li>
                                            @endforeach
                                        </ul>
                                    </td>
                                @endif
                            </tr>
                        </table>
                    @endif

                    <div class="agent-coaching-box">
                        <strong>Plan de Acción / Coaching Recomendado:</strong> {{ $nar['action'] ?? 'Mantener monitoreo continuo de interacciones y calibración semanal.' }}
                    </div>
                </div>

                {{-- All Customer Verbatims for this Agent --}}
                <div class="agent-section-subtitle" style="margin-top: 6px;">
                    Registro de Comentarios Textuales de Clientes ({{ count($agentVerbatims) }} verbatims registrados)
                </div>

                @if(empty($agentVerbatims))
                    <div style="font-size: 7.5px; font-style: italic; color: #64748b; padding: 4px 0;">
                        No se registraron comentarios textuales de clientes (verbatims) para este colaborador en el corte evaluado.
                    </div>
                @else
                    <div class="verbatim-list">
                        @foreach($agentVerbatims as $vb)
                            <div class="verbatim-card verbatim-{{ $vb['sentiment'] }}">
                                <div class="verbatim-header">
                                    <span class="metric-card-badge {{ ($vb['sentiment'] ?? '') === 'promoter' ? 'badge-success' : (($vb['sentiment'] ?? '') === 'detractor' ? 'badge-danger' : 'badge-warning') }}">
                                        {{ ucfirst($vb['sentiment'] ?? 'opinion') }} &bull; NPS: {{ ($vb['nps_score'] ?? null) !== null ? $vb['nps_score'] : 'N/D' }}
                                    </span>
                                    @if(($vb['csat_score'] ?? null) !== null)
                                        <span style="margin-left: 5px; font-weight: bold; color: #334155;">CSAT: {{ $vb['csat_score'] }}</span>
                                    @endif
                                    @if(($vb['professionalism_score'] ?? null) !== null)
                                        <span style="margin-left: 5px; font-weight: bold; color: #334155;">Prof: {{ $vb['professionalism_score'] }}</span>
                                    @endif
                                    @if(!empty($vb['category']) && $vb['category'] !== 'Sin categoría')
                                        <span style="margin-left: 5px; color: #475569; background-color: #e2e8f0; padding: 1px 4px; border-radius: 2px;">
                                            {{ $vb['category'] }}
                                        </span>
                                    @endif
                                    @if(!empty($vb['date']))
                                        <span style="float: right; color: #94a3b8;">{{ $vb['date'] }}</span>
                                    @endif
                                </div>
                                <div class="verbatim-text">
                                    &ldquo;{{ $vb['verbatim'] }}&rdquo;
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    @empty
        <div style="font-size: 8px; font-style: italic; color: #64748b; margin-bottom: 12px;">
            No se identificaron agentes evaluados en este período.
        </div>
    @endforelse

    {{-- VERBATIMS / TOP CATEGORIES --}}
    @if(!empty($data['verbatim_categories']))
        <div class="avoid-break">
            <div class="section-title">Distribución de Motivos de Contacto y Categorías de Clientes</div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Categoría / Motivo Detectado</th>
                        <th class="text-center" style="width: 20%;">Menciones Totales</th>
                        <th class="text-center" style="width: 20%;">Participación sobre Muestra</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($data['verbatim_categories'] as $cat)
                        <tr>
                            <td class="font-bold">{{ $cat['category'] }}</td>
                            <td class="text-center">{{ $cat['count'] }}</td>
                            <td class="text-center">{{ $cat['percentage'] }}%</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- OPEN PERFORMANCE CASES TRACKING (SANITIZED - ZERO DISCIPLINARY DATA) --}}
    @if(!empty($data['open_cases']))
        <div class="avoid-break">
            <div class="section-title">Casos de Desempeño Abiertos en Seguimiento</div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>N° Caso</th>
                        <th>Colaborador</th>
                        <th>Rol</th>
                        <th class="text-center">Tipo</th>
                        <th class="text-center">Prioridad</th>
                        <th class="text-center">Estado</th>
                        <th class="text-center">Apertura</th>
                        <th class="text-center">Próxima Revisión</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($data['open_cases'] as $oc)
                        <tr>
                            <td class="font-bold" style="font-family: monospace;">{{ $oc['case_number'] }}</td>
                            <td>{{ $oc['member_name'] }}</td>
                            <td>{{ ucfirst($oc['member_role']) }}</td>
                            <td class="text-center">{{ ucfirst($oc['type']) }}</td>
                            <td class="text-center">
                                <span class="metric-card-badge {{ $oc['priority'] === 'high' ? 'badge-danger' : ($oc['priority'] === 'medium' ? 'badge-warning' : 'badge-info') }}">
                                    {{ ucfirst($oc['priority']) }}
                                </span>
                            </td>
                            <td class="text-center">
                                <span class="metric-card-badge {{ $oc['status'] === 'under_review' ? 'badge-warning' : 'badge-info' }}">
                                    {{ ucfirst(str_replace('_', ' ', $oc['status'])) }}
                                </span>
                            </td>
                            <td class="text-center">{{ $oc['opened_at'] }}</td>
                            <td class="text-center">{{ $oc['next_review_at'] ?? 'Pendiente' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div style="font-size: 7.5px; color: #64748b; font-style: italic; margin-top: -6px; margin-bottom: 10px;">
                * Conforme a la política de protección de datos laborales, las anotaciones disciplinarias y detalles confidenciales no forman parte de este informe operativo.
            </div>
        </div>
    @endif

    {{-- RECOMMENDED ACTIONS & DATA QUALITY --}}
    <div class="avoid-break">
        <div class="section-title">Plan de Acción Operacional Prioritario</div>
        <ul style="padding-left: 16px; font-size: 8.5px; line-height: 1.45; color: #1e293b;">
            @foreach($narrative['recommended_actions'] ?? [] as $act)
                <li style="margin-bottom: 4px;">{{ $act }}</li>
            @endforeach
        </ul>

        <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; padding: 6px 10px; border-radius: 4px; margin-top: 8px;">
            <strong style="font-size: 8px; color: #475569;">Nota de Calidad y Gobierno del Dato:</strong>
            <span style="font-size: 8px; color: #334155;">{{ $narrative['data_quality_notes'] ?? 'Datos validados conforme a las reglas metodológicas de la organización.' }}</span>
        </div>
    </div>

    {{-- METHODOLOGY & FOOTER --}}
    <div class="footer-note avoid-break">
        <strong>Marco Metodológico:</strong> NPS calculado en escala (-1.00 a +1.00) con fórmula Top-Box Promotores (9-10) menos Detractores (1-6). 
        CSAT y Profesionalismo calculados como % Top-Box de satisfacción favorable.
        <br>
        Documento emitido y autenticado por <strong>ATLAS VOC ANALYZER</strong>. Confidencial - Uso Interno Exclusivo de Operaciones y Calidad.
    </div>

</body>
</html>
