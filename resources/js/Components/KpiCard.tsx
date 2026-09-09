import React from 'react';

interface Props {
    title: string;
    value: string | number;
    unit?: string;
    sampleSize?: number;
    target?: string | number;
    statusText?: string;
    statusType?: 'positive' | 'warning' | 'negative' | 'neutral';
    subtext?: string;
}

export default function KpiCard({
    title,
    value,
    unit = '',
    sampleSize,
    target,
    statusText,
    statusType = 'neutral',
    subtext,
}: Props) {
    const statusStyles = {
        positive: 'text-[#2e5e33] bg-[#eef7e8] border-[#aac69c]',
        warning: 'text-[#7a6418] bg-[#fff8dc] border-[#d7bf70]',
        negative: 'text-[#943126] bg-[#fff0ed] border-[#e1a89e]',
        neutral: 'text-[#687169] bg-[#f7f6f1] border-[#ccd1ca]',
    };

    return (
        <div className="border border-[#ccd1ca] bg-white p-6 flex flex-col justify-between h-full">
            <div>
                <div className="flex items-center justify-between gap-2 mb-2">
                    <span className="text-[12px] font-bold uppercase tracking-[0.14em] text-[#687169]">
                        {title}
                    </span>
                    {statusText && (
                        <span className={`text-[11px] font-medium px-2 py-0.5 border rounded-none ${statusStyles[statusType]}`}>
                            {statusText}
                        </span>
                    )}
                </div>

                <div className="flex items-baseline space-x-1 mt-1">
                    <span className="font-serif text-4xl sm:text-5xl text-[#18221d] tracking-tight">
                        {value}
                    </span>
                    {unit && (
                        <span className="text-base font-normal text-[#687169] font-sans">
                            {unit}
                        </span>
                    )}
                </div>
            </div>

            <div className="mt-4 pt-3 border-t border-[#ccd1ca]/60 flex items-center justify-between text-[12px] text-[#687169]">
                <span>
                    {target ? `Meta: ${target}` : subtext || '—'}
                </span>
                {sampleSize !== undefined && (
                    <span className="font-mono text-[11px]">
                        n = {sampleSize.toLocaleString()}
                    </span>
                )}
            </div>
        </div>
    );
}
