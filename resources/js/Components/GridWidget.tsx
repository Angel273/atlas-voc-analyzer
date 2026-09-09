import React, { ReactNode, useState } from 'react';
import { X, Settings2, GripVertical, Table as TableIcon, BarChart3 } from 'lucide-react';

interface Props {
    title: string;
    subtitle?: string;
    colSpan?: number; // 1 to 12
    rowSpan?: number; // relative height units
    children: ReactNode;
    accessibleTable?: ReactNode; // WCAG 2.2 AA accessible raw data table alternative
    isEditing?: boolean;
    onDelete?: () => void;
    onConfigure?: () => void;
    onResize?: (w: number, h: number) => void;
    draggable?: boolean;
    onDragStart?: (e: React.DragEvent) => void;
    onDragOver?: (e: React.DragEvent) => void;
    onDrop?: (e: React.DragEvent) => void;
    className?: string;
    style?: React.CSSProperties;
}

export default function GridWidget({
    title,
    subtitle,
    colSpan = 6,
    rowSpan = 4,
    children,
    accessibleTable,
    isEditing = false,
    onDelete,
    onConfigure,
    onResize,
    draggable = false,
    onDragStart,
    onDragOver,
    onDrop,
    className = '',
    style = {},
}: Props) {
    const [showRawTable, setShowRawTable] = useState(false);

    // 1. Lateral Width Handle: Horizontal drag calculates columns; single-click cycles [3, 4, 6, 8, 12]
    const handleStartResizeWidth = (e: React.MouseEvent) => {
        e.preventDefault();
        e.stopPropagation();

        const startX = e.clientX;
        const gridEl = (e.currentTarget.closest('[data-grid-container="true"]') as HTMLElement) || document.body;
        const gridWidth = gridEl.getBoundingClientRect().width || 1200;
        const colWidth = gridWidth / 12;
        let didMove = false;
        let latestSpan = colSpan;

        document.body.style.cursor = 'col-resize';
        document.body.style.userSelect = 'none';

        const onMouseMove = (ev: MouseEvent) => {
            ev.preventDefault();
            const deltaX = ev.clientX - startX;
            if (Math.abs(deltaX) > 4) didMove = true;
            const deltaCols = Math.round(deltaX / colWidth);
            const targetSpan = Math.max(2, Math.min(12, colSpan + deltaCols));
            if (targetSpan !== latestSpan) {
                latestSpan = targetSpan;
                onResize?.(targetSpan, rowSpan);
            }
        };

        const onMouseUp = () => {
            document.body.style.cursor = '';
            document.body.style.userSelect = '';
            window.removeEventListener('mousemove', onMouseMove);
            window.removeEventListener('mouseup', onMouseUp);
            if (!didMove) {
                const cycle = [3, 4, 6, 8, 12];
                const currIdx = cycle.indexOf(colSpan);
                const nextSpan = currIdx === -1 || currIdx === cycle.length - 1 ? cycle[0] : cycle[currIdx + 1];
                onResize?.(nextSpan, rowSpan);
            }
        };

        window.addEventListener('mousemove', onMouseMove);
        window.addEventListener('mouseup', onMouseUp);
    };

    // 2. Bottom Height Handle: Vertical drag calculates rows; single-click cycles [2, 3, 4, 5, 6]
    const handleStartResizeHeight = (e: React.MouseEvent) => {
        e.preventDefault();
        e.stopPropagation();

        const startY = e.clientY;
        const rowHeight = 70;
        let didMove = false;
        let latestSpan = rowSpan;

        document.body.style.cursor = 'row-resize';
        document.body.style.userSelect = 'none';

        const onMouseMove = (ev: MouseEvent) => {
            ev.preventDefault();
            const deltaY = ev.clientY - startY;
            if (Math.abs(deltaY) > 4) didMove = true;
            const deltaRows = Math.round(deltaY / rowHeight);
            const targetH = Math.max(2, Math.min(18, rowSpan + deltaRows));
            if (targetH !== latestSpan) {
                latestSpan = targetH;
                onResize?.(colSpan, targetH);
            }
        };

        const onMouseUp = () => {
            document.body.style.cursor = '';
            document.body.style.userSelect = '';
            window.removeEventListener('mousemove', onMouseMove);
            window.removeEventListener('mouseup', onMouseUp);
            if (!didMove) {
                const cycle = [2, 3, 4, 5, 6];
                const currIdx = cycle.indexOf(rowSpan);
                const nextH = currIdx === -1 || currIdx === cycle.length - 1 ? cycle[0] : cycle[currIdx + 1];
                onResize?.(colSpan, nextH);
            }
        };

        window.addEventListener('mousemove', onMouseMove);
        window.addEventListener('mouseup', onMouseUp);
    };

    // 3. Corner Handle: 2D simultaneous resize
    const handleStartResizeCorner = (e: React.MouseEvent) => {
        e.preventDefault();
        e.stopPropagation();

        const startX = e.clientX;
        const startY = e.clientY;
        const gridEl = (e.currentTarget.closest('[data-grid-container="true"]') as HTMLElement) || document.body;
        const colWidth = (gridEl.getBoundingClientRect().width || 1200) / 12;
        const rowHeight = 70;
        let latestW = colSpan;
        let latestH = rowSpan;

        document.body.style.cursor = 'nwse-resize';
        document.body.style.userSelect = 'none';

        const onMouseMove = (ev: MouseEvent) => {
            ev.preventDefault();
            const deltaX = ev.clientX - startX;
            const deltaY = ev.clientY - startY;
            const deltaCols = Math.round(deltaX / colWidth);
            const deltaRows = Math.round(deltaY / rowHeight);
            const targetW = Math.max(2, Math.min(12, colSpan + deltaCols));
            const targetH = Math.max(2, Math.min(18, rowSpan + deltaRows));
            if (targetW !== latestW || targetH !== latestH) {
                latestW = targetW;
                latestH = targetH;
                onResize?.(targetW, targetH);
            }
        };

        const onMouseUp = () => {
            document.body.style.cursor = '';
            document.body.style.userSelect = '';
            window.removeEventListener('mousemove', onMouseMove);
            window.removeEventListener('mouseup', onMouseUp);
        };

        window.addEventListener('mousemove', onMouseMove);
        window.addEventListener('mouseup', onMouseUp);
    };

    return (
        <article
            style={{
                gridColumn: `span ${colSpan}`,
                minHeight: `${rowSpan * 70}px`,
                position: 'relative',
                display: 'flex',
                flexDirection: 'column',
                ...style,
            }}
            onDragOver={onDragOver}
            onDrop={onDrop}
            className={`group border border-[#ccd1ca] bg-white select-none ${
                isEditing ? 'hover:border-[#18221d] hover:shadow-md' : ''
            } ${className}`}
        >
            {/* Widget Header */}
            <div className="px-5 py-3.5 border-b border-[#ccd1ca] flex items-center justify-between bg-white select-none">
                <div className="flex items-center gap-2 overflow-hidden">
                    {isEditing && (
                        <span
                            draggable={draggable && isEditing}
                            onDragStart={onDragStart}
                            className="cursor-grab active:cursor-grabbing text-[#687169] hover:text-[#18221d] p-1 border border-transparent hover:border-[#ccd1ca] hover:bg-[#f7f6f1] transition-colors"
                            title="Arrastrar para reordenar"
                        >
                            <GripVertical className="w-4 h-4" />
                        </span>
                    )}
                    <div
                        className={isEditing ? 'cursor-pointer hover:opacity-80 transition-opacity' : ''}
                        onClick={isEditing ? onConfigure : undefined}
                        title={isEditing ? 'Click para configurar este widget' : undefined}
                    >
                        <h3 className="text-[13px] font-bold tracking-tight text-[#18221d] truncate">
                            {title}
                        </h3>
                        {subtitle && (
                            <p className="text-[11px] text-[#687169] mt-0.5 truncate">
                                {subtitle}
                            </p>
                        )}
                    </div>
                </div>

                <div className="flex items-center space-x-1.5 shrink-0">
                    {/* Accessible Table Alternative Toggle (WCAG 2.2 AA) */}
                    {accessibleTable && (
                        <button
                            type="button"
                            onClick={() => setShowRawTable(!showRawTable)}
                            title={showRawTable ? 'Ver gráfico' : 'Ver tabla de datos accesibles'}
                            className={`p-1 text-xs border transition-colors ${
                                showRawTable
                                    ? 'bg-[#18221d] text-white border-[#18221d]'
                                    : 'border-[#ccd1ca] bg-white hover:bg-[#f7f6f1] text-[#687169] hover:text-[#18221d]'
                            }`}
                        >
                            {showRawTable ? (
                                <BarChart3 className="w-3.5 h-3.5" />
                            ) : (
                                <TableIcon className="w-3.5 h-3.5" />
                            )}
                        </button>
                    )}

                    {isEditing && (
                        <>
                            {onConfigure && (
                                <button
                                    type="button"
                                    onClick={onConfigure}
                                    title="Configuración de widget"
                                    className="p-1 border border-[#ccd1ca] hover:bg-[#f7f6f1] text-[#687169] hover:text-[#18221d] transition-colors"
                                >
                                    <Settings2 className="w-3.5 h-3.5" />
                                </button>
                            )}
                            {onDelete && (
                                <button
                                    type="button"
                                    onClick={onDelete}
                                    title="Eliminar widget"
                                    className="p-1 border border-[#ccd1ca] hover:bg-[#fff0ed] text-[#687169] hover:text-[#943126] transition-colors"
                                >
                                    <X className="w-3.5 h-3.5" />
                                </button>
                            )}
                        </>
                    )}
                </div>
            </div>

            {/* Widget Body */}
            <div className="p-5 flex-1 flex flex-col justify-center overflow-auto">
                {showRawTable && accessibleTable ? accessibleTable : children}
            </div>

            {/* Resize Handles (Active during edit mode) */}
            {isEditing && (
                <>
                    {/* Right Handle */}
                    <div
                        draggable={false}
                        onDragStart={(e) => { e.preventDefault(); e.stopPropagation(); }}
                        onMouseDown={handleStartResizeWidth}
                        title="Arrastrar para redimensionar ancho (o clic para ciclar)"
                        className="absolute top-0 right-0 w-3 h-full cursor-col-resize hover:bg-[#d7f45b]/80 transition-colors z-20 flex items-center justify-center group-hover:bg-[#ccd1ca]/30"
                    >
                        <div className="w-0.5 h-8 bg-[#687169] rounded-full" />
                    </div>

                    {/* Bottom Handle */}
                    <div
                        draggable={false}
                        onDragStart={(e) => { e.preventDefault(); e.stopPropagation(); }}
                        onMouseDown={handleStartResizeHeight}
                        title="Arrastrar para redimensionar alto (o clic para ciclar)"
                        className="absolute bottom-0 left-0 w-full h-3 cursor-row-resize hover:bg-[#d7f45b]/80 transition-colors z-20 flex items-center justify-center group-hover:bg-[#ccd1ca]/30"
                    >
                        <div className="h-0.5 w-8 bg-[#687169] rounded-full" />
                    </div>

                    {/* Corner Handle */}
                    <div
                        draggable={false}
                        onDragStart={(e) => { e.preventDefault(); e.stopPropagation(); }}
                        onMouseDown={handleStartResizeCorner}
                        title="Arrastrar esquina para redimensionar libremente"
                        className="absolute bottom-0 right-0 w-5 h-5 cursor-nwse-resize bg-white hover:bg-[#d7f45b] border-r-3 border-b-3 border-[#18221d] z-30 transition-colors"
                    />
                </>
            )}
        </article>
    );
}
