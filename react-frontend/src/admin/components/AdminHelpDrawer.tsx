// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { Divider, Chip } from '@heroui/react';
import { Link } from 'react-router-dom';
import X from 'lucide-react/icons/x';
import LightbulbIcon from 'lucide-react/icons/lightbulb';
import TriangleAlertIcon from 'lucide-react/icons/triangle-alert';
import type { HelpArticle } from '../data/helpContent';

interface AdminHelpDrawerProps {
  article: HelpArticle;
  isOpen: boolean;
  onClose: () => void;
}

export function AdminHelpDrawer({ article, isOpen, onClose }: AdminHelpDrawerProps) {
  return (
    <>
      {/* Backdrop */}
      <div
        className={`fixed inset-0 z-40 bg-black/40 transition-opacity duration-300 ${
          isOpen ? 'opacity-100 pointer-events-auto' : 'opacity-0 pointer-events-none'
        }`}
        aria-hidden="true"
        onClick={onClose}
      />

      {/* Drawer panel */}
      <div
        role="dialog"
        aria-modal="true"
        aria-label={`Help: ${article.title}`}
        className={`fixed inset-y-0 right-0 z-50 flex w-96 max-w-full flex-col bg-content1 shadow-xl transition-transform duration-300 ${
          isOpen ? 'translate-x-0' : 'translate-x-full'
        }`}
      >
        {/* Header */}
        <div className="flex shrink-0 items-start justify-between gap-3 px-5 py-4 border-b border-divider">
          <div className="min-w-0">
            <p className="text-xs font-semibold uppercase tracking-wider text-default-400 mb-0.5">
              Help
            </p>
            <h2 className="text-base font-bold text-foreground leading-snug">
              {article.title}
            </h2>
          </div>
          <button
            type="button"
            onClick={onClose}
            className="mt-0.5 shrink-0 rounded-full p-1.5 text-default-400 hover:bg-default-100 hover:text-foreground transition-colors"
            aria-label="Close help panel"
          >
            <X size={18} />
          </button>
        </div>

        {/* Scrollable body */}
        <div className="flex-1 overflow-y-auto px-5 py-4 space-y-5">

          {/* Summary */}
          <p className="text-sm text-default-600 leading-relaxed">
            {article.summary}
          </p>

          {/* Steps */}
          {article.steps && article.steps.length > 0 && (
            <>
              <Divider />
              <div>
                <h3 className="text-xs font-semibold uppercase tracking-wider text-default-400 mb-3">
                  How to use this page
                </h3>
                <ol className="space-y-3">
                  {article.steps.map((step, idx) => (
                    <li key={idx} className="flex gap-3">
                      <span className="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-bold text-primary">
                        {idx + 1}
                      </span>
                      <div className="min-w-0">
                        <p className="text-sm font-medium text-foreground leading-snug">
                          {step.label}
                        </p>
                        {step.detail && (
                          <p className="mt-0.5 text-xs text-default-500 leading-relaxed">
                            {step.detail}
                          </p>
                        )}
                      </div>
                    </li>
                  ))}
                </ol>
              </div>
            </>
          )}

          {/* Tips */}
          {article.tips && article.tips.length > 0 && (
            <>
              <Divider />
              <div>
                <h3 className="text-xs font-semibold uppercase tracking-wider text-default-400 mb-3">
                  Tips &amp; gotchas
                </h3>
                <ul className="space-y-2">
                  {article.tips.map((tip, idx) => (
                    <li
                      key={idx}
                      className="flex gap-2.5 rounded-lg bg-default-50 px-3 py-2.5 text-xs text-default-600 leading-relaxed"
                    >
                      <LightbulbIcon
                        size={14}
                        className="mt-0.5 shrink-0 text-warning"
                        aria-hidden="true"
                      />
                      <span>{tip}</span>
                    </li>
                  ))}
                </ul>
              </div>
            </>
          )}

          {/* Caution */}
          {article.caution && (
            <>
              <Divider />
              <div className="flex gap-2.5 rounded-lg border border-danger-200 bg-danger-50 px-3 py-3 text-xs text-danger-700 leading-relaxed">
                <TriangleAlertIcon
                  size={14}
                  className="mt-0.5 shrink-0 text-danger"
                  aria-hidden="true"
                />
                <span>{article.caution}</span>
              </div>
            </>
          )}

          {/* Related pages */}
          {article.relatedPaths && article.relatedPaths.length > 0 && (
            <>
              <Divider />
              <div>
                <h3 className="text-xs font-semibold uppercase tracking-wider text-default-400 mb-3">
                  Related pages
                </h3>
                <div className="flex flex-wrap gap-2">
                  {article.relatedPaths.map((rel) => (
                    <Chip
                      key={rel.path}
                      as={Link}
                      to={rel.path}
                      size="sm"
                      variant="flat"
                      color="primary"
                      className="cursor-pointer"
                      onClick={onClose}
                    >
                      {rel.label}
                    </Chip>
                  ))}
                </div>
              </div>
            </>
          )}

          {/* Bottom padding so content doesn't sit right against the edge */}
          <div className="h-4" />
        </div>
      </div>
    </>
  );
}

export default AdminHelpDrawer;
