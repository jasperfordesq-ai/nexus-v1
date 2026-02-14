/**
 * Admin Breadcrumbs
 * Auto-generates breadcrumbs from the current URL path
 */

import { Link, useLocation } from 'react-router-dom';
import { useTenant } from '@/contexts';
import { ChevronRight, LayoutDashboard } from 'lucide-react';

interface BreadcrumbItem {
  label: string;
  href?: string;
}

interface AdminBreadcrumbsProps {
  items?: BreadcrumbItem[];
}

// Map URL segments to human-readable labels
const SEGMENT_LABELS: Record<string, string> = {
  admin: 'Admin',
  users: 'Users',
  listings: 'Listings',
  blog: 'Blog',
  pages: 'Pages',
  menus: 'Menus',
  categories: 'Categories',
  attributes: 'Attributes',
  gamification: 'Gamification',
  campaigns: 'Campaigns',
  'custom-badges': 'Custom Badges',
  'smart-matching': 'Smart Matching',
  'match-approvals': 'Match Approvals',
  'broker-controls': 'Broker Controls',
  newsletters: 'Newsletters',
  subscribers: 'Subscribers',
  segments: 'Segments',
  templates: 'Templates',
  federation: 'Federation',
  partnerships: 'Partnerships',
  directory: 'Directory',
  'api-keys': 'API Keys',
  enterprise: 'Enterprise',
  roles: 'Roles',
  permissions: 'Permissions',
  gdpr: 'GDPR',
  monitoring: 'Monitoring',
  config: 'Configuration',
  secrets: 'Secrets',
  'legal-documents': 'Legal Documents',
  seo: 'SEO',
  '404-errors': '404 Errors',
  timebanking: 'Timebanking',
  alerts: 'Alerts',
  'org-wallets': 'Org Wallets',
  plans: 'Plans',
  settings: 'Settings',
  'tenant-features': 'Tenant Features',
  'cron-jobs': 'Cron Jobs',
  'activity-log': 'Activity Log',
  analytics: 'Analytics',
  create: 'Create',
  edit: 'Edit',
};

export function AdminBreadcrumbs({ items }: AdminBreadcrumbsProps) {
  const location = useLocation();
  const { tenantSlug } = useTenant();

  // Auto-generate breadcrumbs from URL if not provided
  const breadcrumbs: BreadcrumbItem[] = items || (() => {
    let path = location.pathname;

    // Strip tenant slug prefix if present
    if (tenantSlug) {
      path = path.replace(`/${tenantSlug}`, '');
    }

    const segments = path.split('/').filter(Boolean);
    const crumbs: BreadcrumbItem[] = [];

    let currentPath = tenantSlug ? `/${tenantSlug}` : '';

    for (let i = 0; i < segments.length; i++) {
      const segment = segments[i];
      currentPath += `/${segment}`;

      // Skip numeric IDs
      if (/^\d+$/.test(segment)) continue;

      const label = SEGMENT_LABELS[segment] || segment.charAt(0).toUpperCase() + segment.slice(1).replace(/-/g, ' ');
      const isLast = i === segments.length - 1;

      crumbs.push({
        label,
        href: isLast ? undefined : currentPath,
      });
    }

    return crumbs;
  })();

  if (breadcrumbs.length <= 1) return null;

  return (
    <nav aria-label="Breadcrumbs" className="mb-4">
      <ol className="flex items-center gap-1.5 text-sm text-default-500">
        {breadcrumbs.map((crumb, index) => (
          <li key={index} className="flex items-center gap-1.5">
            {index > 0 && <ChevronRight size={14} className="text-default-300" />}
            {index === 0 && <LayoutDashboard size={14} className="mr-1" />}
            {crumb.href ? (
              <Link
                to={crumb.href}
                className="hover:text-foreground transition-colors"
              >
                {crumb.label}
              </Link>
            ) : (
              <span className="font-medium text-foreground">{crumb.label}</span>
            )}
          </li>
        ))}
      </ol>
    </nav>
  );
}

export default AdminBreadcrumbs;
