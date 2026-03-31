// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import ReactMarkdown from "react-markdown";
import remarkGfm from "remark-gfm";

interface MarkdownRendererProps {
  content: string;
  className?: string;
}

const proseClasses = [
  "prose prose-sm dark:prose-invert max-w-none text-theme-primary",
  "prose-headings:text-theme-primary prose-p:text-theme-secondary",
  "prose-a:text-blue-400 prose-a:no-underline hover:prose-a:underline",
  "prose-strong:text-theme-primary prose-code:text-blue-400",
  "prose-pre:bg-theme-elevated prose-pre:border prose-pre:border-theme-default",
  "prose-img:rounded-lg prose-blockquote:border-blue-400",
  "prose-li:text-theme-secondary",
].join(" ");

export function MarkdownRenderer({
  content,
  className,
}: MarkdownRendererProps) {
  return (
    <div className={`${proseClasses}${className ? ` ${className}` : ""}`}>
      <ReactMarkdown
        remarkPlugins={[remarkGfm]}
        components={{
          a: ({ href, children, ...props }) => {
            const isExternal =
              href && (href.startsWith("http://") || href.startsWith("https://"));
            return (
              <a
                href={href}
                {...(isExternal
                  ? { target: "_blank", rel: "noopener noreferrer" }
                  : {})}
                {...props}
              >
                {children}
              </a>
            );
          },
        }}
      >
        {content}
      </ReactMarkdown>
    </div>
  );
}

export default MarkdownRenderer;
