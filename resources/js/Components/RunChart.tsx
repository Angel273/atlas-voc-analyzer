import React, { useState } from 'react';
import { TrendingUp, BarChart2 } from 'lucide-react';

export interface RunChartTrendItem {
    date: string;
    volume: number;
    nps: number | null;
    csat: number | null;
    professionalism?: number | null;
}

interface RunChartProps {
    trends: RunChartTrendItem[];
    npsTarget?: number;
    csatTarget?: number;
    height?: number;
    title?: string;
    isAgent?: boolean;
}

export default function RunChart({
    trends,
    npsTarget = 0.50,
    csatTarget = 0.80,
    height = 140,
    title,
    isAgent = false,
}: RunChartProps) {
    const [hoveredIdx, setHoveredIdx] = useState<number | null>(null);

    if (!trends || trends.length === 0) {
        return (
            <div className="p-4 bg-[#fafaf8] border border-dashed border-[#ccd1ca] rounded text-center text-xs text-[#687169]">
                Sin registros diarios suficientes para graficar la secuencia temporal.
            </div>
        );
    }

    // Sort chronologically
    const sorted = [...trends].sort((a, b) => a.date.localeCompare(b.date));
    const n = sorted.length;

    const hasNegative = sorted.some((t) => t.nps !== null && t.nps < -0.01);
    const maxVol = Math.max(1, ...sorted.map((t) => t.volume || 0));

    const minY = hasNegative ? -1.0 : 0.0;
    const maxY = 1.0;
    const rangeY = maxY - minY;

    const width = 600;
    const pL = 36;
    const pR = 20;
    const pT = 20;
    const pB = isAgent ? 22 : 26;
    const plotW = Math.max(50, width - pL - pR);
    const plotH = Math.max(40, height - pT - pB);

    // Target lines
    const npsTargetClamped = Math.min(maxY, Math.max(minY, npsTarget));
    const targetY = pT + (1.0 - (npsTargetClamped - minY) / rangeY) * plotH;
    const zeroY = hasNegative ? pT + (1.0 - (0.0 - minY) / rangeY) * plotH : null;

    // Date stepping
    let dateStep = 1;
    if (n > 16) dateStep = 3;
    else if (n > 9) dateStep = 2;

    // Coordinates calculation
    const npsCoords: { x: number; y: number; val: number }[] = [];
    const csatCoords: { x: number; y: number; val: number }[] = [];
    const pointsData = sorted.map((t, idx) => {
        const x = n === 1 ? pL + plotW / 2 : pL + (idx / (n - 1)) * plotW;
        
        let ny: number | null = null;
        if (t.nps !== null) {
            const clamped = Math.min(maxY, Math.max(minY, t.nps));
            ny = pT + (1.0 - (clamped - minY) / rangeY) * plotH;
            npsCoords.push({ x, y: ny, val: t.nps });
        }

        let cy: number | null = null;
        if (t.csat !== null) {
            const clamped = Math.min(maxY, Math.max(minY, t.csat));
            cy = pT + (1.0 - (clamped - minY) / rangeY) * plotH;
            csatCoords.push({ x, y: cy, val: t.csat });
        }

        const vol = t.volume || 0;
        const barH = maxVol > 0 ? (vol / maxVol) * (plotH * 0.32) : 0;
        const barY = pT + plotH - barH;
        const barW = Math.max(3, Math.min(14, (plotW / Math.max(1, n)) * 0.45));

        return {
            ...t,
            x,
            ny,
            cy,
            barH,
            barY,
            barW,
            idx,
        };
    });

    const npsPolyline = npsCoords.map((c) => `${c.x.toFixed(1)},${c.y.toFixed(1)}`).join(' ');
    const csatPolyline = csatCoords.map((c) => `${c.x.toFixed(1)},${c.y.toFixed(1)}`).join(' ');

    const activeItem = hoveredIdx !== null ? pointsData[hoveredIdx] : null;

    return (
        <div className="space-y-2">
            {title && (
                <div className="flex items-center justify-between">
                    <h4 className="text-xs font-bold uppercase tracking-wider text-[#18221d] flex items-center gap-1.5">
                        <TrendingUp className="w-3.5 h-3.5 text-emerald-800" />
                        {title}
                    </h4>
                    <div className="flex items-center gap-3 text-[10px] text-[#687169]">
                        <span className="flex items-center gap-1">
                            <span className="w-2 h-2 rounded-full bg-[#166534] inline-block" />
                            <strong className="text-[#18221d]">NPS</strong>
                        </span>
                        <span className="flex items-center gap-1">
                            <span className="w-2 h-2 rounded-full bg-[#0284c7] inline-block" />
                            <strong className="text-[#18221d]">CSAT</strong>
                        </span>
                        <span className="flex items-center gap-1">
                            <span className="w-2.5 h-0.5 border-b-2 border-dashed border-[#16a34a] inline-block" />
                            Meta ({npsTarget >= 0 ? `+${npsTarget.toFixed(2)}` : npsTarget.toFixed(2)})
                        </span>
                        <span className="flex items-center gap-1">
                            <span className="w-2 h-2 bg-[#cbd5e1] rounded-2xs inline-block" />
                            Volumen
                        </span>
                    </div>
                </div>
            )}

            <div className="relative bg-[#fafaf8] border border-[#ccd1ca] rounded-md p-2">
                <svg
                    viewBox={`0 0 ${width} ${height}`}
                    className="w-full overflow-visible"
                    style={{ maxHeight: height }}
                >
                    {/* Background & Plot Area */}
                    <rect
                        x={pL}
                        y={pT}
                        width={plotW}
                        height={plotH}
                        fill="#ffffff"
                        stroke="#e2e8f0"
                        strokeWidth="0.5"
                        rx="2"
                    />

                    {/* Zero baseline */}
                    {zeroY !== null && (
                        <line
                            x1={pL}
                            y1={zeroY}
                            x2={pL + plotW}
                            y2={zeroY}
                            stroke="#94a3b8"
                            strokeWidth="0.8"
                        />
                    )}

                    {/* Target NPS Line */}
                    <line
                        x1={pL}
                        y1={targetY}
                        x2={pL + plotW}
                        y2={targetY}
                        stroke="#16a34a"
                        strokeWidth="0.8"
                        strokeDasharray="3,2"
                    />
                    {!isAgent && (
                        <text
                            x={pL + 3}
                            y={Math.max(pT + 7, targetY - 2)}
                            fontSize="5.5"
                            fill="#16a34a"
                            fontWeight="bold"
                            className="select-none"
                        >
                            Meta NPS: {npsTarget >= 0 ? `+${npsTarget.toFixed(2)}` : npsTarget.toFixed(2)}
                        </text>
                    )}

                    {/* Y-Axis Labels */}
                    <text x={pL - 4} y={pT + 4} fontSize="6" textAnchor="end" fill="#64748b">
                        {hasNegative ? '+1.0' : '100%'}
                    </text>
                    {hasNegative && zeroY !== null ? (
                        <>
                            <text x={pL - 4} y={zeroY + 2} fontSize="6" textAnchor="end" fill="#64748b">
                                0.0
                            </text>
                            <text x={pL - 4} y={pT + plotH + 2} fontSize="6" textAnchor="end" fill="#64748b">
                                -1.0
                            </text>
                        </>
                    ) : (
                        <>
                            <text x={pL - 4} y={pT + plotH * 0.5 + 2} fontSize="6" textAnchor="end" fill="#64748b">
                                50%
                            </text>
                            <text x={pL - 4} y={pT + plotH + 2} fontSize="6" textAnchor="end" fill="#64748b">
                                0%
                            </text>
                        </>
                    )}

                    {/* Volume bars */}
                    {pointsData.map((pt) => (
                        <rect
                            key={`bar-${pt.idx}`}
                            x={pt.x - pt.barW / 2}
                            y={pt.barY}
                            width={pt.barW}
                            height={pt.barH}
                            fill={hoveredIdx === pt.idx ? '#94a3b8' : '#e2e8f0'}
                            stroke="#cbd5e1"
                            strokeWidth="0.5"
                            rx="1"
                        />
                    ))}

                    {/* Polylines */}
                    {npsCoords.length > 1 && (
                        <polyline
                            points={npsPolyline}
                            fill="none"
                            stroke="#166534"
                            strokeWidth="1.8"
                            strokeLinecap="round"
                            strokeLinejoin="round"
                        />
                    )}
                    {csatCoords.length > 1 && (
                        <polyline
                            points={csatPolyline}
                            fill="none"
                            stroke="#0284c7"
                            strokeWidth="1.3"
                            strokeDasharray="4,1.5"
                            strokeLinecap="round"
                            strokeLinejoin="round"
                        />
                    )}

                    {/* Hover vertical guideline */}
                    {activeItem && (
                        <line
                            x1={activeItem.x}
                            y1={pT}
                            x2={activeItem.x}
                            y2={pT + plotH}
                            stroke="#334139"
                            strokeWidth="0.8"
                            strokeDasharray="2,2"
                        />
                    )}

                    {/* Circles for Points */}
                    {pointsData.map((pt) => (
                        <g key={`pt-${pt.idx}`}>
                            {pt.ny !== null && (
                                <circle
                                    cx={pt.x}
                                    cy={pt.ny}
                                    r={hoveredIdx === pt.idx ? 3.5 : 2.2}
                                    fill="#166534"
                                    stroke="#ffffff"
                                    strokeWidth="0.8"
                                />
                            )}
                            {pt.cy !== null && (
                                <circle
                                    cx={pt.x}
                                    cy={pt.cy}
                                    r={hoveredIdx === pt.idx ? 3 : 1.8}
                                    fill="#0284c7"
                                    stroke="#ffffff"
                                    strokeWidth="0.8"
                                />
                            )}
                        </g>
                    ))}

                    {/* X-axis date labels */}
                    {pointsData.map((pt) => {
                        const isLast = pt.idx === n - 1;
                        if (pt.idx % dateStep === 0 || isLast) {
                            const dateLabel = pt.date.length >= 10 ? `${pt.date.slice(8, 10)}/${pt.date.slice(5, 7)}` : pt.date;
                            return (
                                <text
                                    key={`label-${pt.idx}`}
                                    x={pt.x}
                                    y={pT + plotH + 11}
                                    fontSize="6"
                                    textAnchor="middle"
                                    fill={hoveredIdx === pt.idx ? '#18221d' : '#64748b'}
                                    fontWeight={hoveredIdx === pt.idx ? 'bold' : 'normal'}
                                >
                                    {dateLabel}
                                </text>
                            );
                        }
                        return null;
                    })}

                    {/* Interactive hover trigger rects */}
                    {pointsData.map((pt) => {
                        const sliceW = plotW / n;
                        return (
                            <rect
                                key={`hover-${pt.idx}`}
                                x={pt.x - sliceW / 2}
                                y={pT}
                                width={sliceW}
                                height={plotH + pB}
                                fill="transparent"
                                className="cursor-pointer"
                                onMouseEnter={() => setHoveredIdx(pt.idx)}
                                onMouseLeave={() => setHoveredIdx(null)}
                            />
                        );
                    })}
                </svg>

                {/* Tooltip Overlay */}
                {activeItem && (
                    <div
                        className="absolute bottom-2 right-2 bg-white/95 backdrop-blur-xs border border-[#ccd1ca] shadow-md rounded px-2.5 py-1.5 text-[10px] text-[#18221d] flex items-center gap-3 pointer-events-none z-10"
                    >
                        <span className="font-bold border-r border-[#ccd1ca] pr-2 text-[#334139]">
                            {activeItem.date}
                        </span>
                        <span>
                            Vol: <strong>{activeItem.volume}</strong>
                        </span>
                        <span className="text-[#166534]">
                            NPS: <strong>{activeItem.nps !== null ? (activeItem.nps > 0 ? `+${activeItem.nps.toFixed(2)}` : activeItem.nps.toFixed(2)) : 'N/D'}</strong>
                        </span>
                        <span className="text-[#0284c7]">
                            CSAT: <strong>{activeItem.csat !== null ? `${(activeItem.csat * 100).toFixed(1)}%` : 'N/D'}</strong>
                        </span>
                    </div>
                )}
            </div>
        </div>
    );
}
