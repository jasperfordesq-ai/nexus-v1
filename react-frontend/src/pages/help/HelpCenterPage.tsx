// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Help Center Page - FAQ and support resources
 *
 * Displays common questions, guides, and links to contact support.
 * FAQs are loaded dynamically from /api/v2/help/faqs (tenant-specific
 * with fallback to global defaults). Uses HeroUI Accordion component
 * for expand/collapse.
 */

import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import { Accordion, AccordionItem, Button, Input, Spinner } from '@heroui/react';
import { SafeHtml } from '@/components/ui/SafeHtml';
import HelpCircle from 'lucide-react/icons/circle-help';
import Search from 'lucide-react/icons/search';
import MessageSquare from 'lucide-react/icons/message-square';
import BookOpen from 'lucide-react/icons/book-open';
import Wallet from 'lucide-react/icons/wallet';
import Calendar from 'lucide-react/icons/calendar';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { PublicPageHero } from '@/components/public/PublicPageHero';
import { PageMeta } from '@/components/seo/PageMeta';
import { useTenant, useFeature, useModule } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { api } from '@/lib/api';

/* ───────────────────────── Types ───────────────────────── */

interface Faq {
  id: number;
  question: string;
  answer: string;
}

interface FaqGroup {
  category: string;
  faqs: Faq[];
}

/* ───────────────────────── Main Component ───────────────────────── */

export function HelpCenterPage() {
  const { t } = useTranslation('utility');
  const { branding, tenantPath } = useTenant();
  usePageTitle(t('help.page_title'));

  // QuickLinks must respect this tenant's enabled modules/features — otherwise
  // members hit dead links to features their tenant has turned off.
  const hasListings = useModule('listings');
  const hasWallet = useModule('wallet');
  const hasEvents = useFeature('events');

  const [searchQuery, setSearchQuery] = useState('');
  const [faqGroups, setFaqGroups] = useState<FaqGroup[]>([]);
  const [loading, setLoading] = useState(true);
  const [loadError, setLoadError] = useState(false);

  // Fetch FAQ groups from API on mount
  useEffect(() => {
    let cancelled = false;

    async function loadFaqs() {
      setLoading(true);
      setLoadError(false);

      const result = await api.get<FaqGroup[]>('/v2/help/faqs');

      if (cancelled) return;

      if (result.success && Array.isArray(result.data)) {
        setFaqGroups(result.data);
      } else {
        setLoadError(true);
      }

      setLoading(false);
    }

    void loadFaqs();

    return () => {
      cancelled = true;
    };
  }, []);

  // Filter FAQ groups by search query (client-side after load)
  const filteredGroups = searchQuery.trim()
    ? faqGroups
        .map((group) => ({
          ...group,
          faqs: group.faqs.filter(
            (faq) =>
              faq.question.toLowerCase().includes(searchQuery.toLowerCase()) ||
              faq.answer.toLowerCase().includes(searchQuery.toLowerCase())
          ),
        }))
        .filter((group) => group.faqs.length > 0)
    : faqGroups;

  return (
    <div className="mx-auto max-w-5xl space-y-6 px-1 sm:px-0">
      <PageMeta title={t('help.page_title')} description={t('help.meta_description')} />
      <PublicPageHero
        eyebrow={t('help.hero_eyebrow')}
        title={t('help.heading')}
        description={t('help.subtitle', { name: branding.name })}
        icon={<HelpCircle className="h-6 w-6" aria-hidden="true" />}
        accent="blue"
        action={
          <Button
            as={Link}
            to={tenantPath('/contact')}
            color="primary"
            startContent={<MessageSquare className="h-4 w-4" aria-hidden="true" />}
          >
            {t('help.contact_support')}
          </Button>
        }
      />

      <motion.div initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }}>
        {/* Search */}
        <div className="max-w-md mx-auto mb-8">
          <Input
            placeholder={t('help.search_placeholder')}
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            startContent={<Search className="w-4 h-4 text-theme-muted" aria-hidden="true" />}
            aria-label={t('help.search_placeholder')}
            size="lg"
            classNames={{
              input: 'bg-transparent text-theme-primary',
              inputWrapper: 'bg-theme-elevated border-theme-default',
            }}
          />
        </div>
      </motion.div>

      {/* Quick Links */}
      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ delay: 0.1 }}
        className="grid grid-cols-2 sm:grid-cols-4 gap-3"
      >
        {hasListings && <QuickLink to={tenantPath('/listings')} icon={<BookOpen aria-hidden="true" />} label={t('help.quick_browse_listings')} />}
        {hasWallet && <QuickLink to={tenantPath('/wallet')} icon={<Wallet aria-hidden="true" />} label={t('help.quick_my_wallet')} />}
        {hasEvents && <QuickLink to={tenantPath('/events')} icon={<Calendar aria-hidden="true" />} label={t('help.quick_events')} />}
        <QuickLink to={tenantPath('/contact')} icon={<MessageSquare aria-hidden="true" />} label={t('help.quick_contact_us')} />
      </motion.div>

      {/* FAQ Categories */}
      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ delay: 0.2 }}
        className="space-y-4"
      >
        {/* Loading state */}
        {loading && (
          <GlassCard className="p-12 text-center">
            <Spinner size="lg" className="mx-auto" />
            <p className="text-theme-muted mt-4 text-sm">{t('help.loading')}</p>
          </GlassCard>
        )}

        {/* Error state */}
        {!loading && loadError && (
          <GlassCard className="p-8 text-center">
            <HelpCircle className="w-12 h-12 text-theme-subtle mx-auto mb-4 opacity-50" aria-hidden="true" />
            <h2 className="text-lg font-semibold text-theme-primary mb-2">
              {t('help.load_error_title')}
            </h2>
            <p className="text-theme-muted">
              {t('help.load_error_description')}
            </p>
          </GlassCard>
        )}

        {/* Empty state (loaded, no results) */}
        {!loading && !loadError && filteredGroups.length === 0 && (
          <GlassCard className="p-8 text-center">
            <Search className="w-12 h-12 text-theme-subtle mx-auto mb-4 opacity-50" aria-hidden="true" />
            <h2 className="text-lg font-semibold text-theme-primary mb-2">{t('help.no_results_found')}</h2>
            <p className="text-theme-muted mb-4">
              {t('help.no_results_description_before')}{' '}
              <Link to={tenantPath('/contact')} className="text-indigo-500 hover:underline">
                {t('help.no_results_contact_link')}
              </Link>{' '}
              {t('help.no_results_description_after')}
            </p>
          </GlassCard>
        )}

        {/* FAQ Accordion */}
        {!loading && !loadError && filteredGroups.length > 0 && (
          <Accordion
            selectionMode="multiple"
            variant="splitted"
            defaultExpandedKeys={['0']}
            itemClasses={{
              base: 'bg-theme-elevated/50 backdrop-blur-md border border-theme-default/30 shadow-sm',
              title: 'font-semibold text-theme-primary',
              subtitle: 'text-xs text-theme-subtle',
              trigger: 'p-5 hover:bg-theme-hover/30 data-[hover=true]:bg-theme-hover/30',
              indicator: 'text-theme-muted',
              content: 'px-5 pb-2',
            }}
          >
            {filteredGroups.map((group, catIdx) => (
              <AccordionItem
                key={String(catIdx)}
                aria-label={group.category}
                title={group.category}
                subtitle={t('help.articles_count', { count: group.faqs.length })}
                startContent={
                  <div className="p-2 rounded-lg bg-gradient-to-br from-indigo-500/20 to-purple-500/20 text-indigo-600 dark:text-indigo-400">
                    <HelpCircle className="w-5 h-5" aria-hidden="true" />
                  </div>
                }
              >
                <Accordion
                  selectionMode="multiple"
                  variant="light"
                  itemClasses={{
                    base: 'border-b border-theme-default/50 last:border-b-0',
                    title: 'text-sm font-medium text-theme-primary',
                    trigger: 'px-2 py-3 hover:bg-theme-hover/20 data-[hover=true]:bg-theme-hover/20',
                    content: 'px-2 pb-3 text-sm text-theme-muted leading-relaxed',
                    indicator: 'text-theme-muted',
                  }}
                >
                  {group.faqs.map((faq) => (
                    <AccordionItem
                      key={String(faq.id)}
                      aria-label={faq.question}
                      title={faq.question}
                    >
                      <SafeHtml content={faq.answer} className="text-sm text-theme-muted leading-relaxed" />
                    </AccordionItem>
                  ))}
                </Accordion>
              </AccordionItem>
            ))}
          </Accordion>
        )}
      </motion.div>

      {/* Still Need Help */}
      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ delay: 0.3 }}
      >
        <GlassCard className="p-6 text-center">
          <h2 className="text-lg font-semibold text-theme-primary mb-2">{t('help.still_need_help')}</h2>
          <p className="text-sm text-theme-muted mb-4">
            {t('help.still_need_help_description')}
          </p>
          <Link to={tenantPath('/contact')}>
            <Button
              className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
              startContent={<MessageSquare className="w-4 h-4" aria-hidden="true" />}
            >
              {t('help.contact_support')}
            </Button>
          </Link>
        </GlassCard>
      </motion.div>
    </div>
  );
}

/* ───────────────────────── Quick Link ───────────────────────── */

interface QuickLinkProps {
  to: string;
  icon: React.ReactNode;
  label: string;
}

function QuickLink({ to, icon, label }: QuickLinkProps) {
  return (
    <Link to={to}>
      <GlassCard className="p-4 text-center hover:scale-[1.02] transition-transform">
        <div className="inline-flex p-2 rounded-lg bg-gradient-to-br from-indigo-500/20 to-purple-500/20 text-indigo-600 dark:text-indigo-400 mb-2">
          {icon}
        </div>
        <p className="text-sm font-medium text-theme-primary">{label}</p>
      </GlassCard>
    </Link>
  );
}

export default HelpCenterPage;
