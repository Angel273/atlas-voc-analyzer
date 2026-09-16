import React, { useMemo, useState, useEffect, useRef } from 'react';
import ReactMarkdown from 'react-markdown';
import remarkGfm from 'remark-gfm';
import remarkMath from 'remark-math';
import rehypeKatex from 'rehype-katex';
import katex from 'katex';
import mermaid from 'mermaid';
import EChartComponent from '@/Components/EChartComponent';
import { Copy, Check, BarChart3, GitBranch } from 'lucide-react';

interface Props {
    content: string;
}

function sanitizeMarkdownMath(raw: string): string {
    if (!raw) return '';
    // Strip accidental code backticks around LaTeX math expressions, e.g. `$N = 4393$` -> $N = 4393$
    return raw.replace(/`(\${1,2}[^`\n]+?\${1,2})`/g, '$1');
}

function MermaidBlock({ chart }: { chart: string }) {
    const [svg, setSvg] = useState<string>('');
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        let isMounted = true;
        const id = `mermaid_${Math.random().toString(36).substring(2, 9)}`;

        async function renderChart() {
            try {
                const isDark = typeof document !== 'undefined' && document.documentElement.classList.contains('dark');
                mermaid.initialize({
                    startOnLoad: false,
                    theme: isDark ? 'dark' : 'neutral',
                    securityLevel: 'loose',
                    fontFamily: 'DM Sans, sans-serif',
                });
                const { svg: renderedSvg } = await mermaid.render(id, chart.trim());
                if (isMounted) {
                    setSvg(renderedSvg);
                    setError(null);
                }
            } catch (err: any) {
                if (isMounted) {
                    setError(err?.message || 'Error al renderizar diagrama');
                }
            }
        }

        renderChart();
        return () => {
            isMounted = false;
        };
    }, [chart]);

    if (error) {
        return (
            <div className="my-3 border border-[#ccd1ca] bg-[#f7f6f1]">
                <div className="px-3 py-1.5 border-b border-[#ccd1ca] bg-white text-[10px] font-mono text-[#687169] flex items-center gap-1.5">
                    <GitBranch className="w-3 h-3 text-red-600" />
                    <span>Diagrama Mermaid (Texto)</span>
                </div>
                <pre className="p-3 text-[11px] font-mono text-[#18221d] overflow-x-auto">
                    <code>{chart}</code>
                </pre>
            </div>
        );
    }

    if (!svg) {
        return (
            <div className="my-3 p-5 border border-[#ccd1ca] bg-[#f7f6f1] text-xs text-[#687169] animate-pulse flex items-center justify-center gap-2">
                <GitBranch className="w-3.5 h-3.5 animate-spin text-[#18221d]" />
                <span>Generando diagrama vectorial...</span>
            </div>
        );
    }

    return (
        <div className="my-3 border border-[#ccd1ca] bg-white overflow-hidden shadow-xs">
            <div className="px-3 py-1.5 border-b border-[#ccd1ca] bg-[#f7f6f1] text-[10px] font-mono uppercase tracking-wider text-[#687169] flex items-center justify-between">
                <div className="flex items-center gap-1.5">
                    <GitBranch className="w-3 h-3 text-[#18221d]" />
                    <span className="font-semibold text-[#18221d]">Diagrama de Flujo / Taxonomía</span>
                </div>
                <span>Vector SVG</span>
            </div>
            <div
                className="p-4 overflow-x-auto flex justify-center max-w-full"
                dangerouslySetInnerHTML={{ __html: svg }}
            />
        </div>
    );
}

function EChartBlock({ configStr }: { configStr: string }) {
    const parsedOptions = useMemo(() => {
        try {
            return JSON.parse(configStr.trim());
        } catch {
            return null;
        }
    }, [configStr]);

    if (!parsedOptions) {
        return (
            <div className="my-3 border border-[#ccd1ca] bg-[#f7f6f1]">
                <div className="px-3 py-1.5 border-b border-[#ccd1ca] bg-white text-[10px] font-mono text-[#687169] flex items-center gap-1.5">
                    <BarChart3 className="w-3 h-3 text-amber-600" />
                    <span>Configuración de Gráfico (JSON)</span>
                </div>
                <pre className="bg-[#18221d] text-white p-3 text-[11px] font-mono overflow-x-auto">
                    <code>{configStr}</code>
                </pre>
            </div>
        );
    }

    return (
        <div className="my-4 border border-[#ccd1ca] bg-white overflow-hidden shadow-xs">
            <div className="px-3 py-1.5 border-b border-[#ccd1ca] bg-[#f7f6f1] text-[10px] font-mono uppercase tracking-wider text-[#687169] flex items-center justify-between">
                <div className="flex items-center gap-1.5">
                    <BarChart3 className="w-3 h-3 text-[#18221d]" />
                    <span className="font-semibold text-[#18221d]">Gráfico Interactivo</span>
                </div>
                <span>ECharts</span>
            </div>
            <div className="p-3">
                <EChartComponent options={parsedOptions} height={320} />
            </div>
        </div>
    );
}

function CodeBlockWithCopy({ children, language }: { children: string; language?: string }) {
    const [copied, setCopied] = useState(false);

    const handleCopy = () => {
        navigator.clipboard.writeText(children);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    return (
        <div className="my-3 border border-[#ccd1ca] bg-[#18221d] text-white overflow-hidden shadow-xs">
            <div className="px-3 py-1.5 bg-[#121915] border-b border-[#29382f] flex items-center justify-between text-[10px] font-mono text-[#8e9e94]">
                <span>{language || 'code'}</span>
                <button
                    type="button"
                    onClick={handleCopy}
                    className="hover:text-white transition-colors flex items-center gap-1 cursor-pointer"
                    title="Copiar código"
                >
                    {copied ? <Check className="w-3 h-3 text-[#d7f45b]" /> : <Copy className="w-3 h-3" />}
                    <span>{copied ? 'Copiado' : 'Copiar'}</span>
                </button>
            </div>
            <pre className="p-3 text-[11px] font-mono overflow-x-auto">
                <code>{children}</code>
            </pre>
        </div>
    );
}

export default function MarkdownRenderer({ content }: Props) {
    const sanitizedContent = useMemo(() => sanitizeMarkdownMath(content), [content]);

    return (
        <ReactMarkdown
            remarkPlugins={[remarkGfm, remarkMath]}
            rehypePlugins={[[rehypeKatex, { throwOnError: false, strict: false }]]}
            components={{
                table: ({ children }) => (
                    <div className="overflow-x-auto my-3.5 border border-[#ccd1ca] bg-white shadow-xs">
                        <table className="w-full text-sm text-left border-collapse min-w-[500px]">{children}</table>
                    </div>
                ),
                thead: ({ children }) => (
                    <thead className="bg-[#f7f6f1] border-b border-[#ccd1ca] text-xs font-mono uppercase tracking-wider text-[#18221d]">
                        {children}
                    </thead>
                ),
                tbody: ({ children }) => (
                    <tbody className="divide-y divide-[#ccd1ca]/60">{children}</tbody>
                ),
                th: ({ children }) => (
                    <th className="px-4 py-3 font-bold text-[#18221d] border-r border-[#ccd1ca] last:border-r-0 whitespace-nowrap bg-[#f7f6f1]">
                        {children}
                    </th>
                ),
                td: ({ children }) => (
                    <td className="px-4 py-2.5 text-[13.5px] leading-relaxed text-[#18221d] border-r border-[#ccd1ca]/50 last:border-r-0 whitespace-nowrap">
                        {children}
                    </td>
                ),
                tr: ({ children }) => (
                    <tr className="hover:bg-[#f7f6f1]/60 transition-colors">{children}</tr>
                ),
                h1: ({ children }) => (
                    <h2 className="font-serif text-2xl sm:text-3xl font-bold text-[#18221d] mt-7 mb-3.5 first:mt-0 border-b border-[#ccd1ca] pb-2 tracking-tight">
                        {children}
                    </h2>
                ),
                h2: ({ children }) => (
                    <h3 className="font-serif text-xl sm:text-2xl font-bold text-[#18221d] mt-6 mb-3 first:mt-0 border-b border-[#ccd1ca]/60 pb-1.5 tracking-tight">
                        {children}
                    </h3>
                ),
                h3: ({ children }) => (
                    <h4 className="font-serif text-lg sm:text-xl font-bold text-[#18221d] mt-5 mb-2.5 first:mt-0 flex items-center gap-2">
                        <span className="w-1.5 h-1.5 rounded-full bg-[#18221d] inline-block flex-shrink-0" />
                        <span>{children}</span>
                    </h4>
                ),
                h4: ({ children }) => (
                    <h5 className="font-sans text-xs sm:text-[13px] font-bold uppercase tracking-wider text-[#687169] mt-4 mb-2">
                        {children}
                    </h5>
                ),
                p: ({ children }) => (
                    <p className="my-3 text-[15px] sm:text-base leading-[1.75] text-[#18221d] first:mt-0 last:mb-0">{children}</p>
                ),
                ul: ({ children }) => (
                    <ul className="list-disc pl-6 my-3.5 space-y-2 text-[15px] sm:text-base leading-[1.75] text-[#18221d]">{children}</ul>
                ),
                ol: ({ children }) => (
                    <ol className="list-decimal pl-6 my-3.5 space-y-2 text-[15px] sm:text-base leading-[1.75] text-[#18221d]">{children}</ol>
                ),
                li: ({ children }) => (
                    <li className="leading-[1.75] pl-0.5">{children}</li>
                ),
                blockquote: ({ children }) => (
                    <blockquote className="border-l-3 border-[#18221d] pl-4 py-3 my-4 text-[14px] sm:text-[15px] bg-[#f7f6f1] text-[#18221d]/90 italic leading-[1.75]">
                        {children}
                    </blockquote>
                ),
                code: ({ className, children }: any) => {
                    const text = typeof children === 'string' ? children : String(children || '');
                    const trimmed = text.trim();

                    // Fallback: If an inline code span still contains $...$ or $$...$$, render it via KaTeX
                    const mathMatch = /^\${1,2}(.+)\${1,2}$/.exec(trimmed);
                    if (mathMatch && mathMatch[1]) {
                        try {
                            const html = katex.renderToString(mathMatch[1].trim(), {
                                throwOnError: false,
                                displayMode: trimmed.startsWith('$$'),
                            });
                            return <span className="inline-block mx-0.5" dangerouslySetInnerHTML={{ __html: html }} />;
                        } catch {
                            // fallback to standard inline code
                        }
                    }

                    const match = /language-(\w+)/.exec(className || '');
                    const lang = match ? match[1].toLowerCase() : '';

                    // Interactive ECharts Block
                    if (lang === 'echart' || lang === 'echarts' || lang === 'chart') {
                        return <EChartBlock configStr={text} />;
                    }

                    // Vector Mermaid Diagram Block
                    if (lang === 'mermaid') {
                        return <MermaidBlock chart={text} />;
                    }

                    const isInline = !match && !text.includes('\n');
                    return isInline ? (
                        <code className="bg-[#dce4d8]/60 px-1.5 py-0.5 rounded text-xs sm:text-[13px] font-mono text-[#18221d]">
                            {children}
                        </code>
                    ) : (
                        <CodeBlockWithCopy language={lang}>
                            {text}
                        </CodeBlockWithCopy>
                    );
                },
                hr: () => <hr className="my-5 border-[#ccd1ca]" />,
                strong: ({ children }) => <strong className="font-bold text-[#18221d]">{children}</strong>,
            }}
        >
            {sanitizedContent}
        </ReactMarkdown>
    );
}
