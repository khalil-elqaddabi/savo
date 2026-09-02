import ReactMarkdown, { type Components } from 'react-markdown';
import remarkGfm from 'remark-gfm';

/* ------------------------------------------------------------------ *
 * Premium fintech markdown rendering.
 * Isolated in its own module so react-markdown + remark-gfm are
 * code-split away from the Assistant shell and only load on demand.
 * ------------------------------------------------------------------ */

const md: Components = {
    p: ({ children }) => (
        <p className="text-ink mb-4 text-[15px] leading-[1.7] last:mb-0 dark:text-[#cfd5dc]">
            {children}
        </p>
    ),
    h1: ({ children }) => (
        <h1 className="text-ink mt-6 mb-2.5 text-[19px] font-semibold tracking-[-0.01em] first:mt-0 dark:text-white">
            {children}
        </h1>
    ),
    h2: ({ children }) => (
        <h2 className="text-ink mt-6 mb-2.5 text-[17px] font-semibold tracking-[-0.01em] first:mt-0 dark:text-white">
            {children}
        </h2>
    ),
    h3: ({ children }) => (
        <h3 className="text-ink mt-5 mb-1.5 text-[15px] font-semibold first:mt-0 dark:text-white">
            {children}
        </h3>
    ),
    h4: ({ children }) => (
        <h4 className="text-ink mt-4 mb-1.5 text-[14px] font-semibold first:mt-0 dark:text-white">
            {children}
        </h4>
    ),
    strong: ({ children }) => (
        <strong className="text-ink font-semibold dark:text-white">
            {children}
        </strong>
    ),
    em: ({ children }) => <em className="italic">{children}</em>,
    ul: ({ children }) => (
        <ul className="marker:text-ink-faint mb-4 list-disc space-y-1.5 ps-[1.35rem] last:mb-0">
            {children}
        </ul>
    ),
    ol: ({ children }) => (
        <ol className="marker:text-ink-faint mb-4 list-decimal space-y-1.5 ps-[1.5rem] last:mb-0">
            {children}
        </ol>
    ),
    li: ({ children }) => (
        <li className="text-ink leading-[1.7] dark:text-[#cfd5dc] [&>p]:mb-0 [&>p]:leading-[1.7]">
            {children}
        </li>
    ),
    a: ({ href, children }) => (
        <a
            href={href}
            target="_blank"
            rel="noopener noreferrer"
            className="text-accent decoration-accent/40 hover:decoration-accent font-medium underline underline-offset-2"
        >
            {children}
        </a>
    ),
    blockquote: ({ children }) => (
        <blockquote className="border-line-strong dark:border-line-strong mb-4 border-s-2 ps-4 last:mb-0 [&>p]:mb-0">
            {children}
        </blockquote>
    ),
    hr: () => <hr className="border-line dark:border-line my-5" />,
    pre: ({ children }) => (
        <pre className="chat-code-surface border-line/60 text-ink shadow-card overflow-x-auto rounded-xl border text-[13px] leading-[1.7] last:mb-0">
            {children}
        </pre>
    ),
    code: ({ className, children }) => {
        const hasLanguage = /language-/.test(className ?? '');
        const text = Array.isArray(children)
            ? children.join('')
            : String(children ?? '');
        const isBlock = hasLanguage || text.includes('\n');
        if (!isBlock) {
            return (
                <code className="bg-surface-strong text-ink rounded-md px-1.5 py-0.5 font-mono text-[0.85em] dark:bg-[#ffffff14] dark:text-[#d7dde4]">
                    {children}
                </code>
            );
        }
        return <code className="block p-4 font-mono">{children}</code>;
    },
    table: ({ children }) => (
        <div className="border-line/80 dark:border-line mb-4 overflow-x-auto rounded-lg border last:mb-0">
            <table className="w-full border-separate border-spacing-0 text-[13px]">
                {children}
            </table>
        </div>
    ),
    thead: ({ children }) => (
        <thead className="bg-surface-soft text-ink-soft dark:bg-white/[0.04] dark:text-[#9aa1ab]">
            {children}
        </thead>
    ),
    th: ({ children }) => (
        <th className="border-line dark:border-line px-3.5 py-2 text-start font-medium [&:not(:last-child)]:border-e">
            {children}
        </th>
    ),
    td: ({ children }) => (
        <td className="border-line dark:border-line text-ink px-3.5 py-2.5 text-start align-top dark:text-[#cfd5dc] [&:not(:last-child)]:border-e">
            {children}
        </td>
    ),
    tbody: ({ children }) => (
        <tbody className="[&>tr:not(:last-child)>td]:border-line dark:[&>tr:not(:last-child)>td]:border-line [&>tr:not(:last-child)>td]:border-b">
            {children}
        </tbody>
    ),
};

export function Markdown({ content }: { content: string }) {
    return (
        <ReactMarkdown remarkPlugins={[remarkGfm]} components={md}>
            {content}
        </ReactMarkdown>
    );
}
