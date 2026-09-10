import React, { useMemo } from 'react';
import ReactMarkdown from 'react-markdown';
import remarkGfm from 'remark-gfm';
import remarkMath from 'remark-math';
import rehypeKatex from 'rehype-katex';
import katex from 'katex';

interface Props {
    content: string;
}

function sanitizeMarkdownMath(raw: string): string {
    if (!raw) return '';
    // Strip accidental code backticks around LaTeX math expressions, e.g. `$N = 4393$` -> $N = 4393$
    return raw.replace(/`(\${1,2}[^`\n]+?\${1,2})`/g, '$1');
}

export default function MarkdownRenderer({ content }: Props) {
    const sanitizedContent = useMemo(() => sanitizeMarkdownMath(content), [content]);

    return (
        <ReactMarkdown
            remarkPlugins={[remarkGfm, remarkMath]}
            rehypePlugins={[[rehypeKatex, { throwOnError: false, strict: false }]]}
            components={{
                table: ({ children }) => (
                    <div className="overflow-x-auto my-3 border border-[#ccd1ca] bg-white">
                        <table className="w-full text-xs text-left border-collapse">{children}</table>
                    </div>
                ),
                thead: ({ children }) => (
                    <thead className="bg-[#f7f6f1] border-b border-[#ccd1ca] text-[11px] font-mono uppercase tracking-wider text-[#18221d]">
                        {children}
                    </thead>
                ),
                tbody: ({ children }) => (
                    <tbody className="divide-y divide-[#ccd1ca]/60">{children}</tbody>
                ),
                th: ({ children }) => (
                    <th className="px-3 py-2 font-bold text-[#18221d] border-r border-[#ccd1ca] last:border-r-0 whitespace-nowrap">
                        {children}
                    </th>
                ),
                td: ({ children }) => (
                    <td className="px-3 py-2 text-xs text-[#18221d] border-r border-[#ccd1ca]/60 last:border-r-0 whitespace-nowrap">
                        {children}
                    </td>
                ),
                tr: ({ children }) => (
                    <tr className="hover:bg-[#f7f6f1]/60 transition-colors">{children}</tr>
                ),
                h1: ({ children }) => (
                    <h3 className="font-serif text-lg font-bold text-[#18221d] mt-4 mb-2 first:mt-0 border-b border-[#ccd1ca]/40 pb-1">{children}</h3>
                ),
                h2: ({ children }) => (
                    <h4 className="font-serif text-base font-bold text-[#18221d] mt-3.5 mb-2 first:mt-0">{children}</h4>
                ),
                h3: ({ children }) => {
                    const text = String(children);
                    const isHechos = text.includes('Hechos');
                    const isCalculos = text.includes('Cálculos');
                    const isInterp = text.includes('Interpretación');

                    return (
                        <div className={`mt-4 mb-2 pb-1 border-b flex items-center gap-1.5 ${
                            isHechos
                                ? 'border-blue-200 text-blue-900'
                                : isCalculos
                                ? 'border-emerald-200 text-emerald-900'
                                : isInterp
                                ? 'border-purple-200 text-purple-900'
                                : 'border-[#ccd1ca] text-[#18221d]'
                        }`}>
                            <span className="font-serif text-sm font-bold tracking-tight">
                                {children}
                            </span>
                        </div>
                    );
                },
                p: ({ children }) => (
                    <p className="my-2 leading-relaxed first:mt-0 last:mb-0 text-[#18221d]">{children}</p>
                ),
                ul: ({ children }) => (
                    <ul className="list-disc pl-5 my-2 space-y-1 text-[#18221d]">{children}</ul>
                ),
                ol: ({ children }) => (
                    <ol className="list-decimal pl-5 my-2 space-y-1 text-[#18221d]">{children}</ol>
                ),
                li: ({ children }) => (
                    <li className="leading-relaxed">{children}</li>
                ),
                blockquote: ({ children }) => (
                    <blockquote className="border-l-2 border-[#18221d] pl-3 py-1.5 my-2.5 text-xs bg-[#f7f6f1] text-[#18221d]/85 italic">
                        {children}
                    </blockquote>
                ),
                code: ({ className, children }: any) => {
                    const text = typeof children === 'string' ? children.trim() : '';
                    // Fallback: If somehow an inline code span still contains $...$ or $$...$$, render it via KaTeX
                    const mathMatch = /^\${1,2}(.+)\${1,2}$/.exec(text);
                    if (mathMatch && mathMatch[1]) {
                        try {
                            const html = katex.renderToString(mathMatch[1].trim(), {
                                throwOnError: false,
                                displayMode: text.startsWith('$$'),
                            });
                            return <span className="inline-block mx-0.5" dangerouslySetInnerHTML={{ __html: html }} />;
                        } catch {
                            // fallback to standard inline code
                        }
                    }

                    const match = /language-(\w+)/.exec(className || '');
                    const isInline = !match && !String(children).includes('\n');
                    return isInline ? (
                        <code className="bg-[#dce4d8]/50 px-1.5 py-0.5 rounded text-[11px] font-mono text-[#18221d]">
                            {children}
                        </code>
                    ) : (
                        <pre className="bg-[#18221d] text-white p-3 my-2.5 overflow-x-auto text-[11px] font-mono">
                            <code>{children}</code>
                        </pre>
                    );
                },
                hr: () => <hr className="my-3.5 border-[#ccd1ca]" />,
                strong: ({ children }) => <strong className="font-bold text-[#18221d]">{children}</strong>,
            }}
        >
            {sanitizedContent}
        </ReactMarkdown>
    );
}
