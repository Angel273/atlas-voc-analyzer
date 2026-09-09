export type WidgetType =
    | 'metric'
    | 'kpi_card'
    | 'trend'
    | 'line_chart'
    | 'comparison'
    | 'bar_chart'
    | 'category_breakdown'
    | 'area_chart'
    | 'distribution'
    | 'table'
    | 'text';

export interface WidgetGrid {
    x: number;
    y: number;
    w: number;
    h: number;
}

export interface ComputedColumnConfig {
    enabled: boolean;
    name: string;
    calculationType: 'percent_of_target' | 'diff_from_target' | 'multiply_100' | 'custom';
    customMultiplier?: number;
}

export interface ConditionalFormattingConfig {
    enabled: boolean;
    greenThreshold?: number;
    redThreshold?: number;
    mode: 'badge' | 'background' | 'bar';
}

export interface WidgetConfig {
    metric?: string;
    metrics?: string[];
    dimension?: string;
    aggregation?: string;
    color?: string;
    showTargetLine?: boolean;
    targetLineValue?: number;
    targetLineLabel?: string;
    computedColumn?: ComputedColumnConfig;
    conditionalFormatting?: ConditionalFormattingConfig;
    textContent?: string;
    comparison?: boolean;
    limit?: number;
    [key: string]: any;
}

export interface Widget {
    id: number;
    title: string;
    type: WidgetType;
    x: number;
    y: number;
    w: number;
    h: number;
    configuration: WidgetConfig;
    sort_order: number;
}

export interface Dashboard {
    id: number;
    name: string;
    description: string;
    widgets: Widget[];
    is_default?: boolean;
    global_filters?: Record<string, any>;
}
