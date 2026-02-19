// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Breadcrumbs - Structured navigation for detail pages
 *
 * Renders a chain of links: Home > Section > Current Page
 * Supports aria breadcrumb navigation for screen readers.
 */

import { Link } from 'react-router-dom';
import { ChevronRight, Home } from 'lucide-react';
import { useTenant } from '@/contexts';

export interface BreadcrumbItem {
  label: string;
  href?: string;
}

interface BreadcrumbsProps {
  items: BreadcrumbItem[];
  /** Whether to show a home icon at the start (default: true) */
  showHome?: boolean;
}

export function Breadcrumbs({ items, showHome = true }: BreadcrumbsProps) {
  const { tenantPath } = useTenant();
  if (items.length === 0) return null;

  return (
    <nav aria-label="Breadcrumb" className="mb-4">
      <ol className="flex flex-wrap items-center gap-1 text-sm">
        {showHome && (
          <li className="flex items-center">
            <Link
              to={tenantPath('/')}
              className="text-theme-subtle hover:text-theme-primary transition-colors"
              aria-label="Home"
            >
              <Home className="w-3.5 h-3.5" aria-hidden="true" />
            </Link>
            <ChevronRight className="w-3 h-3 text-theme-subtle/50 mx-1" aria-hidden="true" />
          </li>
        )}

        {items.map((item, index) => {
          const isLast = index === items.length - 1;

          return (
            <li key={`${item.label}-${index}`} className="flex items-center">
              {isLast || !item.href ? (
                <span
                  className="text-theme-primary font-medium truncate max-w-[200px]"
                  aria-current="page"
                  title={item.label}
                >
                  {item.label}
                </span>
              ) : (
                <>
                  <Link
                    to={tenantPath(item.href)}
                    className="text-theme-subtle hover:text-theme-primary transition-colors"
                  >
                    {item.label}
                  </Link>
                  <ChevronRight className="w-3 h-3 text-theme-subtle/50 mx-1" aria-hidden="true" />
                </>
              )}
            </li>
          );
        })}
      </ol>
    </nav>
  );
}
