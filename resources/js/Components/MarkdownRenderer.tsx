import React from 'react';
import ReactMarkdown from 'react-markdown';
import remarkGfm from 'remark-gfm';

interface Props {
    content: string;
}

export default function MarkdownRenderer({ content }: Props) {
    return (
        <ReactMarkdown
            remarkPlugins={[remarkGfm]}
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
                    <h3 className="font-serif text-lg font-bold text-[#18221d] mt-4 mb-2 first:mt-0">{children}</h3>
                ),
                h2: ({ children }) => (
                    <h4 className="font-serif text-base font-bold text-[#18221d] mt-3.5 mb-2 first:mt-0">{children}</h4>
                ),
                h3: ({ children }) => (
                    <h5 className="font-serif text-sm font-bold text-[#18221d] mt-3 mb-1.5 first:mt-0">{children}</h5>
                ),
                p: ({ children }) => (
                    <p className="my-2 leading-relaxed first:mt-0 last:mb-0">{children}</p>
                ),
                ul: ({ children }) => (
                    <ul className="list-disc pl-5 my-2 space-y-1">{children}</ul>
                ),
                ol: ({ children }) => (
                    <ol className="list-decimal pl-5 my-2 space-y-1">{children}</ol>
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
            {content}
        </ReactMarkdown>
    );
}
