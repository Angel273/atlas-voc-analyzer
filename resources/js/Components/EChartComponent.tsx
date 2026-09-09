import React, { useEffect, useRef, useState } from 'react';
import * as echarts from 'echarts';

interface Props {
    options: echarts.EChartsOption;
    height?: string | number;
    className?: string;
}

export default function EChartComponent({ options, height = 300, className = '' }: Props) {
    const chartRef = useRef<HTMLDivElement>(null);
    const chartInstance = useRef<echarts.ECharts | null>(null);
    const [isDark, setIsDark] = useState<boolean>(() => {
        if (typeof document === 'undefined') return false;
        return document.documentElement.classList.contains('dark');
    });

    useEffect(() => {
        const handleThemeChange = () => {
            setIsDark(document.documentElement.classList.contains('dark'));
        };

        window.addEventListener('atlas-theme-changed', handleThemeChange);

        // Also observe mutation on documentElement class attribute
        const observer = new MutationObserver(() => {
            handleThemeChange();
        });
        observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });

        return () => {
            window.removeEventListener('atlas-theme-changed', handleThemeChange);
            observer.disconnect();
        };
    }, []);

    useEffect(() => {
        if (!chartRef.current) return;

        // Initialize with flat clean aesthetic
        if (!chartInstance.current) {
            chartInstance.current = echarts.init(chartRef.current, undefined, {
                renderer: 'svg',
            });
        }

        const textColor = isDark ? '#f0f4f1' : '#18221d';
        const subTextColor = isDark ? '#8e9e94' : '#687169';
        const borderColor = isDark ? '#29382f' : '#ccd1ca';

        const defaultPalette = isDark
            ? ['#d7f45b', '#7ee2a8', '#f29b91', '#ebd67a', '#74b9ff', '#f0f4f1']
            : ['#18221d', '#aac69c', '#d7f45b', '#687169', '#e1a89e', '#d7bf70'];

        // Apply theme overrides consistent with Atlas Tools editorial style
        const styledOptions: echarts.EChartsOption = {
            ...options,
            backgroundColor: 'transparent',
            textStyle: {
                fontFamily: 'DM Sans, sans-serif',
                color: textColor,
                ...((options.textStyle as any) || {}),
            },
            color: options.color || defaultPalette,
            tooltip: {
                backgroundColor: isDark ? '#141b18' : '#ffffff',
                borderColor: borderColor,
                textStyle: {
                    color: textColor,
                    fontFamily: 'DM Sans, sans-serif',
                },
                ...((options.tooltip as any) || {}),
            },
        };

        chartInstance.current.setOption(styledOptions, true);
        chartInstance.current.resize();

        const handleResize = () => {
            chartInstance.current?.resize();
        };

        window.addEventListener('resize', handleResize);

        const resizeObserver = new ResizeObserver(() => {
            chartInstance.current?.resize();
        });
        resizeObserver.observe(chartRef.current);

        return () => {
            window.removeEventListener('resize', handleResize);
            resizeObserver.disconnect();
        };
    }, [options, height, isDark]);

    useEffect(() => {
        return () => {
            chartInstance.current?.dispose();
            chartInstance.current = null;
        };
    }, []);

    return (
        <div
            ref={chartRef}
            style={{ width: '100%', height: typeof height === 'number' ? `${height}px` : height }}
            className={`w-full ${className}`}
        />
    );
}
