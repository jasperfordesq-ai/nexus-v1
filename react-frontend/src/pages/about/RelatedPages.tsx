/**
 * Related Pages - Cross-navigation strip for About sub-pages
 *
 * Displays links to sibling pages within the About section,
 * excluding the current page.
 */

import { Link } from 'react-router-dom';
import {
  BookOpen,
  Handshake,
  Stethoscope,
  TrendingUp,
  BarChart3,
  Compass,
  ArrowRight,
} from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { useTenant } from '@/contexts';

interface AboutLink {
  label: string;
  href: string;
  icon: React.ComponentType<{ className?: string }>;
  description: string;
}

const aboutPages: AboutLink[] = [
  { label: 'Timebanking Guide', href: '/timebanking-guide', icon: BookOpen, description: 'How timebanking works' },
  { label: 'Partner With Us', href: '/partner', icon: Handshake, description: 'Partnership opportunities' },
  { label: 'Social Prescribing', href: '/social-prescribing', icon: Stethoscope, description: 'Evidence-based referral pathway' },
  { label: 'Our Impact', href: '/impact-summary', icon: TrendingUp, description: 'Social return on investment' },
  { label: 'Impact Report', href: '/impact-report', icon: BarChart3, description: 'Full 2023 SROI study' },
  { label: 'Strategic Plan', href: '/strategic-plan', icon: Compass, description: '2026–2030 roadmap' },
];

interface RelatedPagesProps {
  /** The href of the current page (e.g. '/timebanking-guide') — will be excluded from the list */
  current: string;
}

export function RelatedPages({ current }: RelatedPagesProps) {
  const { tenantPath } = useTenant();
  const links = aboutPages.filter((p) => p.href !== current);

  return (
    <section className="py-12 sm:py-16 px-4 sm:px-6 lg:px-8">
      <div className="max-w-5xl mx-auto">
        <h2 className="text-sm font-semibold text-theme-subtle uppercase tracking-wider mb-4 px-1">
          Related Pages
        </h2>
        <div className="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
          {links.map((link) => (
            <Link key={link.href} to={tenantPath(link.href)}>
              <GlassCard className="p-4 flex items-center gap-3 group hover:scale-[1.01] transition-transform h-full">
                <div className="flex-shrink-0 p-2 rounded-lg bg-indigo-500/10">
                  <link.icon className="w-4 h-4 text-indigo-500 dark:text-indigo-400" aria-hidden="true" />
                </div>
                <div className="flex-1 min-w-0">
                  <p className="text-sm font-medium text-theme-primary truncate">{link.label}</p>
                  <p className="text-xs text-theme-subtle truncate">{link.description}</p>
                </div>
                <ArrowRight className="w-4 h-4 text-theme-subtle group-hover:text-indigo-500 transition-colors flex-shrink-0" aria-hidden="true" />
              </GlassCard>
            </Link>
          ))}
        </div>
      </div>
    </section>
  );
}
