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

import { useState } from 'react';
import type { ReactNode } from 'react';
import { useLocation } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { Button } from '@heroui/react';
import HelpCircle from 'lucide-react/icons/help-circle';
import { HELP_CONTENT } from '../data/helpContent';
import { AdminHelpDrawer } from './AdminHelpDrawer';

interface PageHeaderProps {
  title: string;
  description?: ReactNode;
  subtitle?: ReactNode;
  icon?: ReactNode;
  actions?: ReactNode;
}

export function PageHeader({ title, description, subtitle, icon, actions }: PageHeaderProps) {
  const { t } = useTranslation('admin');
  const body = description ?? subtitle;
  const location = useLocation();

  // Strip the tenant-slug prefix so /slug/caring/foo → /caring/foo
  // and /slug/admin/national/kiss → /admin/national/kiss
  const normalizedPath = location.pathname.replace(/^\/[^/]+(?=\/(?:admin|caring))/, '');
  const article = HELP_CONTENT[normalizedPath] ?? HELP_CONTENT[location.pathname] ?? null;

  const [helpOpen, setHelpOpen] = useState(false);

  return (
    <>
      <div className="mb-6 rounded-2xl border border-divider/70 bg-content1 p-4 shadow-sm shadow-black/[0.03] sm:p-5">
        <div className="flex flex-col items-stretch gap-4 sm:flex-row sm:items-start sm:justify-between">
          <div className="min-w-0 flex-1">
            <div className="flex min-w-0 items-center gap-3">
              {icon && (
                <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary">
                  {icon}
                </span>
              )}
              <h1 className="min-w-0 break-words text-2xl font-semibold text-foreground [overflow-wrap:anywhere] sm:text-3xl">{title}</h1>
              {article && (
                <Button
                  isIconOnly
                  size="sm"
                  variant="light"
                  onClick={() => setHelpOpen(true)}
                  className="ml-1 shrink-0 text-default-400 hover:text-primary"
                  aria-label={t('shared.open_page_help')}
                  title={t('shared.help')}
                >
                  <HelpCircle size={16} />
                </Button>
              )}
            </div>
            {body && (
              <p className="mt-2 max-w-3xl break-words text-sm leading-6 text-default-500 [overflow-wrap:anywhere]">{body}</p>
            )}
          </div>
          {actions && <div className="flex flex-wrap items-center gap-2 sm:justify-end">{actions}</div>}
        </div>
      </div>
      {article && (
        <AdminHelpDrawer
          article={article}
          isOpen={helpOpen}
          onClose={() => setHelpOpen(false)}
        />
      )}
    </>
  );
}

export default PageHeader;
