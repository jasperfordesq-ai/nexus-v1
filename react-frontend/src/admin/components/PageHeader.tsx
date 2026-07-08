// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Admin Page Header
 * Consistent page title, description, and action buttons.
 * Automatically shows a contextual help button (?) when the current route
 * has an entry in the HELP_CONTENT registry.
 */

import { lazy, Suspense, useEffect, useState } from 'react';
import type { ReactNode } from 'react';
import { useLocation } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import HelpCircle from 'lucide-react/icons/help-circle';
import type { HelpArticle } from '../data/helpContent';
import { Button } from '@/components/ui/Button';

const AdminHelpDrawer = lazy(() =>
  import('./AdminHelpDrawer').then((module) => ({ default: module.AdminHelpDrawer }))
);

interface PageHeaderProps {
  title: string;
  description?: ReactNode;
  subtitle?: ReactNode;
  icon?: ReactNode;
  actions?: ReactNode;
}

export function PageHeader({ title, description, subtitle, icon, actions }: PageHeaderProps) {
  const { t } = useTranslation('admin_nav');
  const body = description ?? subtitle;
  const location = useLocation();
  const [article, setArticle] = useState<HelpArticle | null>(null);
  const [helpOpen, setHelpOpen] = useState(false);

  useEffect(() => {
    let cancelled = false;
    // Strip the tenant-slug prefix so /slug/caring/foo -> /caring/foo
    // and /slug/super-admin/national/kiss -> /super-admin/national/kiss.
    const normalizedPath = location.pathname.replace(/^\/[^/]+(?=\/(?:admin|caring|super-admin))/, '');
    const loadHelpArticle = () => {
      void import('../data/helpContent').then(({ HELP_CONTENT }) => {
        if (!cancelled) {
          setArticle(HELP_CONTENT[normalizedPath] ?? HELP_CONTENT[location.pathname] ?? null);
        }
      });
    };

    setArticle(null);
    setHelpOpen(false);

    if ('requestIdleCallback' in window) {
      const idleId = window.requestIdleCallback(loadHelpArticle, { timeout: 1500 });
      return () => {
        cancelled = true;
        window.cancelIdleCallback(idleId);
      };
    }

    const timeoutId = globalThis.setTimeout(loadHelpArticle, 0);
    return () => {
      cancelled = true;
      globalThis.clearTimeout(timeoutId);
    };
  }, [location.pathname]);

  return (
    <>
      <div className="mb-6 rounded-2xl border border-divider/70 bg-surface p-4 shadow-sm shadow-black/[0.03] sm:p-5">
        <div className="flex flex-col items-stretch gap-4 sm:flex-row sm:items-start sm:justify-between">
          <div className="min-w-0 flex-1">
            <div className="flex min-w-0 items-center gap-3">
              {icon && (
                <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-accent/10 text-accent">
                  {icon}
                </span>
              )}
              <h1 className="min-w-0 break-words text-2xl font-semibold text-foreground [overflow-wrap:anywhere] sm:text-3xl">{title}</h1>
              {article && (
                <Button
                  isIconOnly
                  size="sm"
                  variant="tertiary"
                  onClick={() => setHelpOpen(true)}
                  className="ml-1 shrink-0 text-muted hover:text-accent"
                  aria-label={t('shared.open_page_help')}
                  title={t('shared.help')}
                >
                  <HelpCircle size={16} />
                </Button>
              )}
            </div>
            {body && (
              <p className="mt-2 max-w-3xl break-words text-sm leading-6 text-muted [overflow-wrap:anywhere]">{body}</p>
            )}
          </div>
          {actions && <div className="flex flex-wrap items-center gap-2 sm:justify-end">{actions}</div>}
        </div>
      </div>
      {article && helpOpen && (
        <Suspense fallback={null}>
          <AdminHelpDrawer
            article={article}
            isOpen={helpOpen}
            onClose={() => setHelpOpen(false)}
          />
        </Suspense>
      )}
    </>
  );
}

export default PageHeader;
