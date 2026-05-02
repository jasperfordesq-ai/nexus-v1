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
  const body = description ?? subtitle;
  const location = useLocation();

  // Strip the tenant-slug prefix so /slug/caring/foo → /caring/foo
  // and /slug/admin/national/kiss → /admin/national/kiss
  const normalizedPath = location.pathname.replace(/^\/[^/]+(?=\/(?:admin|caring))/, '');
  const article = HELP_CONTENT[normalizedPath] ?? HELP_CONTENT[location.pathname] ?? null;

  const [helpOpen, setHelpOpen] = useState(false);

  return (
    <>
      <div className="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div className="min-w-0 flex-1">
          <div className="flex min-w-0 items-center gap-3">
            {icon && <span className="shrink-0 text-primary">{icon}</span>}
            <h1 className="text-2xl font-bold text-foreground">{title}</h1>
            {article && (
              <button
                type="button"
                onClick={() => setHelpOpen(true)}
                className="ml-1 shrink-0 rounded-full p-1 text-default-400 hover:bg-default-100 hover:text-primary transition-colors"
                aria-label="Open page help"
                title="Help"
              >
                <HelpCircle size={16} />
              </button>
            )}
          </div>
          {body && (
            <p className="mt-1 text-sm text-default-500">{body}</p>
          )}
        </div>
        {actions && <div className="flex flex-wrap items-center gap-2">{actions}</div>}
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
