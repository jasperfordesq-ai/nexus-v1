// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Volunteering Page - Browse opportunities, track hours, manage applications
 */

import React, { useState, useEffect, useCallback, useRef, Suspense } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { motion } from 'framer-motion';
import {
  Button,
  Input,
  Chip,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Textarea,
  useDisclosure,
  Avatar,
  Progress,
  Select,
  SelectItem,
  Spinner,
} from '@heroui/react';
import {
  Heart,
  Plus,
  RefreshCw,
  AlertTriangle,
  MapPin,
  Calendar,
  Clock,
  Building2,
  Search,
  ChevronRight,
  Send,
  CheckCircle,
  XCircle,
  Hourglass,
  TrendingUp,
  Globe,
  Briefcase,
  Timer,
  Sparkles,
  Award,
  Siren,
  Smile,
  ShieldCheck,
  ArrowLeftRight,
  Users,
  MessageSquare,
  ClipboardCheck,
  Receipt,
  Shield,
  Lightbulb,
  HandHeart,
  Accessibility,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { EmptyState } from '@/components/feedback';
import { useAuth, useTenant, useToast } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { RecommendedShiftsTab } from './RecommendedShiftsTab';
import { EmergencyAlertsTab } from './EmergencyAlertsTab';
import { CertificatesTab } from './CertificatesTab';
import { WellbeingTab } from './WellbeingTab';
import { CredentialVerificationTab } from './CredentialVerificationTab';
import { WaitlistTab } from './WaitlistTab';
import { ShiftSwapsTab } from './ShiftSwapsTab';
import { GroupSignUpTab } from './GroupSignUpTab';
import { HoursReviewTab } from './HoursReviewTab';
const ExpensesTab = React.lazy(() => import('./ExpensesTab'));
const SafeguardingTab = React.lazy(() => import('./SafeguardingTab'));
const CommunityProjectsTab = React.lazy(() => import('./CommunityProjectsTab'));
const DonationsTab = React.lazy(() => import('./DonationsTab'));
const AccessibilityTab = React.lazy(() => import('./AccessibilityTab'));

/* ───────────────────────── Types ───────────────────────── */

interface Organization {
  id: number;
  name: string;
  logo_url: string | null;
}

interface Opportunity {
  id: number;
  title: string;
  description: string;
  location: string;
  skills_needed: string;
  start_date: string | null;
  end_date: string | null;
  is_active: boolean;
  is_remote: boolean;
  category: string | null;
  organization: Organization;
  created_at: string;
  has_applied?: boolean;
}

interface Application {
  id: number;
  status: 'pending' | 'approved' | 'declined';
  message: string;
  opportunity: {
    id: number;
    title: string;
    location: string;
  };
  organization: {
    id: number;
    name: string;
    logo_url: string | null;
  };
  shift?: {
    id: number;
    start_time: string;
    end_time: string;
  } | null;
  org_note?: string | null;
  created_at: string;
}

interface HoursSummary {
  total_verified: number;
  total_pending: number;
  total_declined: number;
  by_organization: { name: string; total: number }[];
  by_month: { month: string; total: number }[];
}

type VolunteerTab = 'opportunities' | 'applications' | 'hours' | 'recommended' | 'certificates' | 'alerts' | 'wellbeing' | 'credentials' | 'waitlist' | 'swaps' | 'group-signups' | 'hours-review' | 'expenses' | 'safeguarding' | 'community-projects' | 'donations' | 'accessibility';

/* ───────────────────────── Main Component ───────────────────────── */

export function VolunteeringPage() {
  const { t } = useTranslation('community');
  usePageTitle(t('volunteering.page_title'));
  const { isAuthenticated } = useAuth();
  const { tenantPath, hasFeature } = useTenant();
  const [searchParams] = useSearchParams();
  const initialTab = (searchParams.get('tab') as VolunteerTab) ?? 'opportunities';
  const [tab, setTab] = useState<VolunteerTab>(initialTab);
  const [hasApprovedOrg, setHasApprovedOrg] = useState(false);

  useEffect(() => {
    let cancelled = false;
    if (isAuthenticated) {
      api.get<Array<{ status: string; member_role: string }>>('/v2/volunteering/my-organisations')
        .then((res) => {
          if (!cancelled && res.success && Array.isArray(res.data)) {
            setHasApprovedOrg(
              res.data.some((org) => org.status === 'approved' && ['owner', 'admin'].includes(org.member_role)),
            );
          }
        })
        .catch(() => { /* silent — button just won't show */ });
    }
    return () => { cancelled = true; };
  }, [isAuthenticated]);

  // Feature gate
  if (!hasFeature('volunteering')) {
    return (
      <div className="flex flex-col items-center justify-center min-h-[60vh] px-6 py-16 text-center">
        <div className="w-16 h-16 rounded-2xl bg-gradient-to-br from-rose-100 to-orange-100 dark:from-rose-900/30 dark:to-orange-900/30 flex items-center justify-center mb-4">
          <Heart className="w-8 h-8 text-rose-500" aria-hidden="true" />
        </div>
        <h2 className="text-xl font-semibold text-[var(--color-text)] mb-2">{t('volunteering.feature_not_available', 'Volunteering Not Available')}</h2>
        <p className="text-[var(--color-text-muted)] max-w-sm">
          {t('volunteering.feature_not_available_desc', 'The volunteering feature is not enabled for this community. Contact your timebank administrator to learn more.')}
        </p>
      </div>
    );
  }


  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-theme-primary flex items-center gap-3">
            <Heart className="w-7 h-7 text-rose-400" aria-hidden="true" />
            {t('volunteering.heading')}
          </h1>
          <p className="text-theme-muted mt-1">{t('volunteering.subtitle')}</p>
        </div>
        <div className="flex gap-2 flex-wrap">
          {hasApprovedOrg && (
            <Link to={tenantPath('/volunteering/create')}>
              <Button
                className="bg-gradient-to-r from-rose-500 to-pink-600 text-white"
                startContent={<Plus className="w-4 h-4" aria-hidden="true" />}
              >
                {t('volunteering.post_opportunity')}
              </Button>
            </Link>
          )}
          <Link to={tenantPath('/organisations')}>
            <Button
              className="bg-gradient-to-r from-rose-500 to-pink-600 text-white"
              startContent={<Building2 className="w-4 h-4" aria-hidden="true" />}
            >
              {t('volunteering.browse_organisations')}
            </Button>
          </Link>
        </div>
      </div>

      {/* Tabs */}
      <div className="flex gap-2 flex-wrap">
        <Button
          variant={tab === 'opportunities' ? 'solid' : 'flat'}
          className={tab === 'opportunities' ? 'bg-gradient-to-r from-rose-500 to-pink-600 text-white' : 'bg-theme-elevated text-theme-muted'}
          onPress={() => setTab('opportunities')}
          startContent={<Briefcase className="w-4 h-4" aria-hidden="true" />}
        >
          {t('volunteering.tab_opportunities')}
        </Button>
        {isAuthenticated && (
          <>
            <Button
              variant={tab === 'applications' ? 'solid' : 'flat'}
              className={tab === 'applications' ? 'bg-gradient-to-r from-rose-500 to-pink-600 text-white' : 'bg-theme-elevated text-theme-muted'}
              onPress={() => setTab('applications')}
              startContent={<Send className="w-4 h-4" aria-hidden="true" />}
            >
              {t('volunteering.tab_applications')}
            </Button>
            <Button
              variant={tab === 'hours' ? 'solid' : 'flat'}
              className={tab === 'hours' ? 'bg-gradient-to-r from-rose-500 to-pink-600 text-white' : 'bg-theme-elevated text-theme-muted'}
              onPress={() => setTab('hours')}
              startContent={<Timer className="w-4 h-4" aria-hidden="true" />}
            >
              {t('volunteering.tab_hours')}
            </Button>
            <Button
              variant={tab === 'recommended' ? 'solid' : 'flat'}
              className={tab === 'recommended' ? 'bg-gradient-to-r from-rose-500 to-pink-600 text-white' : 'bg-theme-elevated text-theme-muted'}
              onPress={() => setTab('recommended')}
              startContent={<Sparkles className="w-4 h-4" aria-hidden="true" />}
            >
              {t('volunteering.tab_for_you', 'For You')}
            </Button>
            <Button
              variant={tab === 'certificates' ? 'solid' : 'flat'}
              className={tab === 'certificates' ? 'bg-gradient-to-r from-rose-500 to-pink-600 text-white' : 'bg-theme-elevated text-theme-muted'}
              onPress={() => setTab('certificates')}
              startContent={<Award className="w-4 h-4" aria-hidden="true" />}
            >
              {t('volunteering.tab_certificates', 'Certificates')}
            </Button>
            <Button
              variant={tab === 'alerts' ? 'solid' : 'flat'}
              className={tab === 'alerts' ? 'bg-gradient-to-r from-rose-500 to-pink-600 text-white' : 'bg-theme-elevated text-theme-muted'}
              onPress={() => setTab('alerts')}
              startContent={<Siren className="w-4 h-4" aria-hidden="true" />}
            >
              {t('volunteering.tab_alerts', 'Alerts')}
            </Button>
            <Button
              variant={tab === 'wellbeing' ? 'solid' : 'flat'}
              className={tab === 'wellbeing' ? 'bg-gradient-to-r from-rose-500 to-pink-600 text-white' : 'bg-theme-elevated text-theme-muted'}
              onPress={() => setTab('wellbeing')}
              startContent={<Smile className="w-4 h-4" aria-hidden="true" />}
            >
              {t('volunteering.tab_wellbeing', 'Wellbeing')}
            </Button>
            <Button
              variant={tab === 'credentials' ? 'solid' : 'flat'}
              className={tab === 'credentials' ? 'bg-gradient-to-r from-rose-500 to-pink-600 text-white' : 'bg-theme-elevated text-theme-muted'}
              onPress={() => setTab('credentials')}
              startContent={<ShieldCheck className="w-4 h-4" aria-hidden="true" />}
            >
              {t('volunteering.tab_credentials', 'Credentials')}
            </Button>
            <Button
              variant={tab === 'waitlist' ? 'solid' : 'flat'}
              className={tab === 'waitlist' ? 'bg-gradient-to-r from-rose-500 to-pink-600 text-white' : 'bg-theme-elevated text-theme-muted'}
              onPress={() => setTab('waitlist')}
              startContent={<Clock className="w-4 h-4" aria-hidden="true" />}
            >
              {t('volunteering.tab_waitlist', 'Waitlist')}
            </Button>
            <Button
              variant={tab === 'swaps' ? 'solid' : 'flat'}
              className={tab === 'swaps' ? 'bg-gradient-to-r from-rose-500 to-pink-600 text-white' : 'bg-theme-elevated text-theme-muted'}
              onPress={() => setTab('swaps')}
              startContent={<ArrowLeftRight className="w-4 h-4" aria-hidden="true" />}
            >
              {t('volunteering.tab_swap_requests', 'Swap Requests')}
            </Button>
            <Button
              variant={tab === 'group-signups' ? 'solid' : 'flat'}
              className={tab === 'group-signups' ? 'bg-gradient-to-r from-rose-500 to-pink-600 text-white' : 'bg-theme-elevated text-theme-muted'}
              onPress={() => setTab('group-signups')}
              startContent={<Users className="w-4 h-4" aria-hidden="true" />}
            >
              {t('volunteering.tab_group_signups', 'Group Sign-ups')}
            </Button>
            <Button
              variant={tab === 'hours-review' ? 'solid' : 'flat'}
              className={tab === 'hours-review' ? 'bg-gradient-to-r from-rose-500 to-pink-600 text-white' : 'bg-theme-elevated text-theme-muted'}
              onPress={() => setTab('hours-review')}
              startContent={<ClipboardCheck className="w-4 h-4" aria-hidden="true" />}
            >
              {t('volunteering.tab_hours_review', 'Hours Review')}
            </Button>
            <Button
              variant={tab === 'expenses' ? 'solid' : 'flat'}
              className={tab === 'expenses' ? 'bg-gradient-to-r from-rose-500 to-pink-600 text-white' : 'bg-theme-elevated text-theme-muted'}
              onPress={() => setTab('expenses')}
              startContent={<Receipt className="w-4 h-4" aria-hidden="true" />}
            >
              {t('volunteering.tab_expenses', 'Expenses')}
            </Button>
            <Button
              variant={tab === 'safeguarding' ? 'solid' : 'flat'}
              className={tab === 'safeguarding' ? 'bg-gradient-to-r from-rose-500 to-pink-600 text-white' : 'bg-theme-elevated text-theme-muted'}
              onPress={() => setTab('safeguarding')}
              startContent={<Shield className="w-4 h-4" aria-hidden="true" />}
            >
              {t('volunteering.tab_safeguarding', 'Safeguarding')}
            </Button>
            <Button
              variant={tab === 'community-projects' ? 'solid' : 'flat'}
              className={tab === 'community-projects' ? 'bg-gradient-to-r from-rose-500 to-pink-600 text-white' : 'bg-theme-elevated text-theme-muted'}
              onPress={() => setTab('community-projects')}
              startContent={<Lightbulb className="w-4 h-4" aria-hidden="true" />}
            >
              {t('volunteering.tab_community_projects', 'Projects')}
            </Button>
            <Button
              variant={tab === 'donations' ? 'solid' : 'flat'}
              className={tab === 'donations' ? 'bg-gradient-to-r from-rose-500 to-pink-600 text-white' : 'bg-theme-elevated text-theme-muted'}
              onPress={() => setTab('donations')}
              startContent={<HandHeart className="w-4 h-4" aria-hidden="true" />}
            >
              {t('volunteering.tab_donations', 'Donations')}
            </Button>
            <Button
              variant={tab === 'accessibility' ? 'solid' : 'flat'}
              className={tab === 'accessibility' ? 'bg-gradient-to-r from-rose-500 to-pink-600 text-white' : 'bg-theme-elevated text-theme-muted'}
              onPress={() => setTab('accessibility')}
              startContent={<Accessibility className="w-4 h-4" aria-hidden="true" />}
            >
              {t('volunteering.tab_accessibility', 'Accessibility')}
            </Button>
          </>
        )}
      </div>

      {/* Tab Content */}
      {tab === 'opportunities' && <OpportunitiesTab />}
      {tab === 'applications' && <ApplicationsTab />}
      {tab === 'hours' && <HoursTab />}
      {tab === 'recommended' && <RecommendedShiftsTab />}
      {tab === 'certificates' && <CertificatesTab />}
      {tab === 'alerts' && <EmergencyAlertsTab />}
      {tab === 'wellbeing' && <WellbeingTab />}
      {tab === 'credentials' && <CredentialVerificationTab />}
      {tab === 'waitlist' && <WaitlistTab />}
      {tab === 'swaps' && <ShiftSwapsTab />}
      {tab === 'group-signups' && <GroupSignUpTab />}
      {tab === 'hours-review' && <HoursReviewTab />}
      <Suspense fallback={<div className="flex justify-center py-12"><Spinner size="lg" /></div>}>
        {tab === 'expenses' && <ExpensesTab />}
        {tab === 'safeguarding' && <SafeguardingTab />}
        {tab === 'community-projects' && <CommunityProjectsTab />}
        {tab === 'donations' && <DonationsTab />}
        {tab === 'accessibility' && <AccessibilityTab />}
      </Suspense>
    </div>
  );
}

/* ───────────────────────── Opportunities Tab ───────────────────────── */

function OpportunitiesTab() {
  const { t } = useTranslation('community');
  const { isAuthenticated } = useAuth();
  const toast = useToast();
  const [opportunities, setOpportunities] = useState<Opportunity[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [searchQuery, setSearchQuery] = useState('');
  const [hasMore, setHasMore] = useState(false);
  const [, setCursor] = useState<string | undefined>();
  const cursorRef = useRef<string | undefined>(undefined);
  const tRef = useRef(t);
  tRef.current = t;
  const abortOpportunitiesRef = useRef<AbortController | null>(null);

  // Apply modal
  const { isOpen, onOpen, onClose } = useDisclosure();
  const [selectedOpportunity, setSelectedOpportunity] = useState<Opportunity | null>(null);
  const [applyMessage, setApplyMessage] = useState('');
  const [isApplying, setIsApplying] = useState(false);

  const loadOpportunities = useCallback(async (append = false) => {
    abortOpportunitiesRef.current?.abort();
    const controller = new AbortController();
    abortOpportunitiesRef.current = controller;

    try {
      if (!append) {
        setIsLoading(true);
        setError(null);
      }

      const params = new URLSearchParams();
      params.set('per_page', '20');
      if (append && cursorRef.current) params.set('cursor', cursorRef.current);
      if (searchQuery.trim()) params.set('search', searchQuery.trim());

      const response = await api.get<Opportunity[]>(
        `/v2/volunteering/opportunities?${params}`
      );

      if (controller.signal.aborted) return;

      if (response.success && response.data) {
        const items = Array.isArray(response.data) ? response.data : [];

        if (append) {
          setOpportunities((prev) => [...prev, ...items]);
        } else {
          setOpportunities(items);
        }
        setHasMore(response.meta?.has_more ?? false);
        const newCursor = response.meta?.cursor ?? undefined;
        cursorRef.current = newCursor;
        setCursor(newCursor);
      } else {
        if (!append) setError(tRef.current('volunteering.error_load_opportunities'));
      }
    } catch (err) {
      if (controller.signal.aborted) return;
      logError('Failed to load opportunities', err);
      if (!append) setError(tRef.current('volunteering.error_load_opportunities_retry'));
    } finally {
      if (!controller.signal.aborted) {
        setIsLoading(false);
      }
    }
  }, [searchQuery]);

  const loadOpportunitiesRef = useRef(loadOpportunities);
  loadOpportunitiesRef.current = loadOpportunities;

  useEffect(() => {
    cursorRef.current = undefined;
    setCursor(undefined);
    loadOpportunitiesRef.current();
    return () => { abortOpportunitiesRef.current?.abort(); };
  }, [searchQuery]);

  const handleApply = async () => {
    if (!selectedOpportunity) return;

    try {
      setIsApplying(true);
      const response = await api.post(`/v2/volunteering/opportunities/${selectedOpportunity.id}/apply`, {
        message: applyMessage || undefined,
      });

      if (response.success) {
        toast.success(t('volunteering.applied_success', 'Successfully applied!'));
        onClose();
        setApplyMessage('');
        setSelectedOpportunity(null);
        loadOpportunities();
      } else {
        toast.error(response.error || t('volunteering.apply_error', 'Failed to apply'));
      }
    } catch (err) {
      logError('Failed to apply', err);
      toast.error(t('volunteering.apply_error', 'Something went wrong. Please try again.'));
    } finally {
      setIsApplying(false);
    }
  };

  const openApplyModal = (opp: Opportunity) => {
    setSelectedOpportunity(opp);
    setApplyMessage('');
    onOpen();
  };

  return (
    <>
      {/* Search */}
      <div className="w-full sm:max-w-md">
        <Input
          placeholder={t('volunteering.search_placeholder')}
          value={searchQuery}
          onChange={(e) => setSearchQuery(e.target.value)}
          startContent={<Search className="w-4 h-4 text-theme-muted" aria-hidden="true" />}
          aria-label={t('volunteering.search_placeholder')}
          classNames={{
            input: 'bg-transparent text-theme-primary',
            inputWrapper: 'bg-theme-elevated border-theme-default',
          }}
        />
      </div>

      {/* Error */}
      {error && !isLoading && (
        <GlassCard className="p-8 text-center">
          <AlertTriangle className="w-12 h-12 text-amber-500 mx-auto mb-4" aria-hidden="true" />
          <h2 className="text-lg font-semibold text-theme-primary mb-2">{t('volunteering.unable_to_load_opportunities')}</h2>
          <p className="text-theme-muted mb-4">{error}</p>
          <Button
            className="bg-gradient-to-r from-rose-500 to-pink-600 text-white"
            startContent={<RefreshCw className="w-4 h-4" aria-hidden="true" />}
            onPress={() => loadOpportunities()}
          >
            {t('volunteering.try_again')}
          </Button>
        </GlassCard>
      )}

      {/* Opportunities List */}
      {!error && (
        <>
          {isLoading ? (
            <div className="space-y-4">
              {[1, 2, 3].map((i) => (
                <GlassCard key={i} className="p-5 animate-pulse">
                  <div className="h-5 bg-theme-hover rounded w-1/3 mb-3" />
                  <div className="h-3 bg-theme-hover rounded w-full mb-3" />
                  <div className="h-3 bg-theme-hover rounded w-2/3 mb-3" />
                  <div className="h-3 bg-theme-hover rounded w-1/4" />
                </GlassCard>
              ))}
            </div>
          ) : opportunities.length === 0 ? (
            <EmptyState
              icon={<Briefcase className="w-12 h-12" aria-hidden="true" />}
              title={t('volunteering.no_opportunities_found')}
              description={searchQuery ? t('volunteering.try_different_search') : t('volunteering.no_opportunities_available')}
            />
          ) : (
            <div className="space-y-4">
              {opportunities.map((opp) => (
                <motion.div key={opp.id} initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} transition={{ duration: 0.3 }}>
                  <OpportunityCard
                    opportunity={opp}
                    onApply={isAuthenticated && !opp.has_applied ? () => openApplyModal(opp) : undefined}
                  />
                </motion.div>
              ))}

              {hasMore && (
                <div className="pt-4 text-center">
                  <Button
                    variant="flat"
                    className="bg-theme-elevated text-theme-muted"
                    onPress={() => loadOpportunities(true)}
                  >
                    {t('volunteering.load_more')}
                  </Button>
                </div>
              )}
            </div>
          )}
        </>
      )}

      {/* Apply Modal */}
      <Modal isOpen={isOpen} onClose={onClose} size="lg" classNames={{
        base: 'bg-content1 border border-theme-default',
      }}>
        <ModalContent>
          <ModalHeader className="text-theme-primary">
            {t('volunteering.apply_to_volunteer')}
          </ModalHeader>
          <ModalBody className="space-y-4">
            {selectedOpportunity && (
              <div>
                <h3 className="font-semibold text-theme-primary">{selectedOpportunity.title}</h3>
                <p className="text-sm text-theme-muted">{selectedOpportunity.organization.name}</p>
              </div>
            )}
            <Textarea
              label={t('volunteering.cover_message_label')}
              placeholder={t('volunteering.cover_message_placeholder')}
              value={applyMessage}
              onChange={(e) => setApplyMessage(e.target.value)}
              classNames={{
                input: 'bg-transparent text-theme-primary',
                inputWrapper: 'bg-theme-elevated border-theme-default',
              }}
            />
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={onClose} className="text-theme-muted">{t('volunteering.cancel')}</Button>
            <Button
              className="bg-gradient-to-r from-rose-500 to-pink-600 text-white"
              onPress={handleApply}
              isLoading={isApplying}
            >
              {t('volunteering.submit_application')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </>
  );
}

/* ───────────────────────── Opportunity Card ───────────────────────── */

interface OpportunityCardProps {
  opportunity: Opportunity;
  onApply?: () => void;
}

function OpportunityCard({ opportunity, onApply }: OpportunityCardProps) {
  const { t } = useTranslation('community');
  const { tenantPath } = useTenant();
  const navigate = useNavigate();
  const startDate = opportunity.start_date ? new Date(opportunity.start_date) : null;
  const endDate = opportunity.end_date ? new Date(opportunity.end_date) : null;

  return (
    <GlassCard className="p-5">
      <div className="flex items-start justify-between gap-4">
        <div className="flex-1 min-w-0">
          <div className="flex items-center gap-3 mb-2">
            <Link to={tenantPath(`/organisations/${opportunity.organization.id}`)}>
              <Avatar
                name={opportunity.organization.name}
                src={opportunity.organization.logo_url ?? undefined}
                size="sm"
                className="flex-shrink-0"
              />
            </Link>
            <div className="min-w-0">
              <Link
                to={tenantPath(`/volunteering/opportunities/${opportunity.id}`)}
                className="font-semibold text-theme-primary text-lg truncate block hover:text-indigo-500 transition-colors"
              >
                {opportunity.title}
              </Link>
              <Link
                to={tenantPath(`/organisations/${opportunity.organization.id}`)}
                className="text-sm text-theme-muted hover:text-indigo-500 hover:underline transition-colors"
              >
                {opportunity.organization.name}
              </Link>
            </div>
          </div>

          {opportunity.description && (
            <p className="text-sm text-theme-muted mb-3 line-clamp-2">{opportunity.description}</p>
          )}

          <div className="flex flex-wrap items-center gap-3 text-xs text-theme-subtle">
            {opportunity.location && (
              <span className="flex items-center gap-1">
                <MapPin className="w-3 h-3" aria-hidden="true" />
                {opportunity.location}
              </span>
            )}
            {opportunity.is_remote && (
              <Chip size="sm" variant="flat" color="primary" startContent={<Globe className="w-3 h-3" />}>
                {t('volunteering.remote')}
              </Chip>
            )}
            {startDate && (
              <span className="flex items-center gap-1">
                <Calendar className="w-3 h-3" aria-hidden="true" />
                {startDate.toLocaleDateString()}
                {endDate ? ` - ${endDate.toLocaleDateString()}` : ''}
              </span>
            )}
            {opportunity.category && (
              <Chip size="sm" variant="flat" className="text-theme-subtle">
                {typeof opportunity.category === 'object' ? (opportunity.category as { name?: string }).name : opportunity.category}
              </Chip>
            )}
            {opportunity.skills_needed && (
              <span className="flex items-center gap-1">
                <Briefcase className="w-3 h-3" aria-hidden="true" />
                {opportunity.skills_needed}
              </span>
            )}
          </div>

          {opportunity.has_applied && (
            <Chip size="sm" color="success" variant="flat" className="mt-2" startContent={<CheckCircle className="w-3 h-3" />}>
              {t('volunteering.applied')}
            </Chip>
          )}
        </div>

        {/* Actions */}
        <div className="flex flex-col gap-2 flex-shrink-0">
          <Button
            size="sm"
            variant="light"
            className="text-theme-muted"
            onPress={() => navigate(tenantPath(`/volunteering/opportunities/${opportunity.id}`))}
            endContent={<ChevronRight className="w-4 h-4" aria-hidden="true" />}
          >
            {t('volunteering.view_details', 'View Details')}
          </Button>
          {onApply && (
            <Button
              size="sm"
              className="bg-gradient-to-r from-rose-500 to-pink-600 text-white"
              onPress={onApply}
              endContent={<Send className="w-4 h-4" aria-hidden="true" />}
            >
              {t('volunteering.apply')}
            </Button>
          )}
        </div>
      </div>
    </GlassCard>
  );
}

/* ───────────────────────── Applications Tab ───────────────────────── */

function ApplicationsTab() {
  const { t } = useTranslation('community');
  const toast = useToast();
  const { tenantPath } = useTenant();
  const [applications, setApplications] = useState<Application[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [statusFilter, setStatusFilter] = useState<string>('');
  const [hasMore, setHasMore] = useState(false);
  const [, setCursor] = useState<string | undefined>();
  const cursorRef = useRef<string | undefined>(undefined);
  const tRef = useRef(t);
  tRef.current = t;
  const abortApplicationsRef = useRef<AbortController | null>(null);

  const loadApplications = useCallback(async (append = false) => {
    abortApplicationsRef.current?.abort();
    const controller = new AbortController();
    abortApplicationsRef.current = controller;

    try {
      if (!append) {
        setIsLoading(true);
        setError(null);
      }

      const params = new URLSearchParams();
      params.set('per_page', '20');
      if (append && cursorRef.current) params.set('cursor', cursorRef.current);
      if (statusFilter) params.set('status', statusFilter);

      const response = await api.get<Application[]>(
        `/v2/volunteering/applications?${params}`
      );

      if (controller.signal.aborted) return;

      if (response.success && response.data) {
        const items = Array.isArray(response.data) ? response.data : [];

        if (append) {
          setApplications((prev) => [...prev, ...items]);
        } else {
          setApplications(items);
        }
        setHasMore(response.meta?.has_more ?? false);
        const newCursor = response.meta?.cursor ?? undefined;
        cursorRef.current = newCursor;
        setCursor(newCursor);
      } else {
        if (!append) setError(tRef.current('volunteering.error_load_applications'));
      }
    } catch (err) {
      if (controller.signal.aborted) return;
      logError('Failed to load applications', err);
      if (!append) setError(tRef.current('volunteering.error_load_applications_retry'));
    } finally {
      if (!controller.signal.aborted) {
        setIsLoading(false);
      }
    }
  }, [statusFilter]);

  const loadApplicationsRef = useRef(loadApplications);
  loadApplicationsRef.current = loadApplications;

  useEffect(() => {
    cursorRef.current = undefined;
    setCursor(undefined);
    loadApplicationsRef.current();
    return () => { abortApplicationsRef.current?.abort(); };
  }, [statusFilter]);

  const handleWithdraw = async (applicationId: number) => {
    try {
      const response = await api.delete(`/v2/volunteering/applications/${applicationId}`);
      if (response.success) {
        toast.success(t('volunteering.withdraw_success', 'Application withdrawn.'));
        loadApplications();
      } else {
        toast.error(response.error || t('volunteering.withdraw_failed', 'Failed to withdraw application.'));
      }
    } catch (err) {
      logError('Failed to withdraw application', err);
      toast.error(t('volunteering.withdraw_failed', 'Failed to withdraw application.'));
    }
  };

  const statusColor = (status: string) => {
    switch (status) {
      case 'approved': return 'success';
      case 'declined': return 'danger';
      default: return 'warning';
    }
  };

  const statusIcon = (status: string) => {
    switch (status) {
      case 'approved': return <CheckCircle className="w-3 h-3" />;
      case 'declined': return <XCircle className="w-3 h-3" />;
      default: return <Hourglass className="w-3 h-3" />;
    }
  };

  return (
    <>
      {/* Status Filter */}
      <div className="flex gap-2 flex-wrap">
        {['', 'pending', 'approved', 'declined'].map((s) => (
          <Button
            key={s}
            size="sm"
            variant={statusFilter === s ? 'solid' : 'flat'}
            className={statusFilter === s ? 'bg-gradient-to-r from-rose-500 to-pink-600 text-white' : 'bg-theme-elevated text-theme-muted'}
            onPress={() => setStatusFilter(s)}
          >
            {s ? t('volunteering.status_' + s) : t('volunteering.filter_all')}
          </Button>
        ))}
      </div>

      {/* Error */}
      {error && !isLoading && (
        <GlassCard className="p-8 text-center">
          <AlertTriangle className="w-12 h-12 text-amber-500 mx-auto mb-4" aria-hidden="true" />
          <p className="text-theme-muted mb-4">{error}</p>
          <Button
            className="bg-gradient-to-r from-rose-500 to-pink-600 text-white"
            startContent={<RefreshCw className="w-4 h-4" aria-hidden="true" />}
            onPress={() => loadApplications()}
          >
            {t('volunteering.try_again')}
          </Button>
        </GlassCard>
      )}

      {/* Applications List */}
      {!error && (
        <>
          {isLoading ? (
            <div className="space-y-4">
              {[1, 2, 3].map((i) => (
                <GlassCard key={i} className="p-5 animate-pulse">
                  <div className="h-5 bg-theme-hover rounded w-1/3 mb-3" />
                  <div className="h-3 bg-theme-hover rounded w-2/3 mb-3" />
                  <div className="h-3 bg-theme-hover rounded w-1/4" />
                </GlassCard>
              ))}
            </div>
          ) : applications.length === 0 ? (
            <EmptyState
              icon={<Send className="w-12 h-12" aria-hidden="true" />}
              title={t('volunteering.no_applications')}
              description={statusFilter ? t('volunteering.no_status_applications', { status: statusFilter }) : t('volunteering.no_applications_yet')}
            />
          ) : (
            <div className="space-y-4">
              {applications.map((app) => (
                <motion.div key={app.id} initial={{ opacity: 0, y: 20 }} animate={{ opacity: 1, y: 0 }} transition={{ duration: 0.3 }}>
                  <GlassCard className="p-5">
                    <div className="flex items-start justify-between gap-4">
                      <div className="flex-1 min-w-0">
                        <div className="flex items-center gap-2 mb-1 flex-wrap">
                          <Link to={tenantPath(`/volunteering/opportunities/${app.opportunity.id}`)} className="font-semibold text-theme-primary hover:text-indigo-400 transition-colors">{app.opportunity.title}</Link>
                          <Chip
                            size="sm"
                            color={statusColor(app.status)}
                            variant="flat"
                            startContent={statusIcon(app.status)}
                          >
                            {t('volunteering.status_' + app.status)}
                          </Chip>
                        </div>

                        <Link
                          to={tenantPath(`/organisations/${app.organization.id}`)}
                          className="text-sm text-theme-muted hover:text-indigo-500 hover:underline transition-colors mb-2 inline-flex items-center gap-1"
                        >
                          <Building2 className="w-3 h-3" aria-hidden="true" />
                          {app.organization.name}
                        </Link>

                        {app.opportunity.location && (
                          <p className="text-xs text-theme-subtle flex items-center gap-1 mb-1">
                            <MapPin className="w-3 h-3" aria-hidden="true" />
                            {app.opportunity.location}
                          </p>
                        )}

                        {app.shift && (
                          <p className="text-xs text-theme-subtle flex items-center gap-1 mb-1">
                            <Clock className="w-3 h-3" aria-hidden="true" />
                            {new Date(app.shift.start_time).toLocaleString()} - {new Date(app.shift.end_time).toLocaleTimeString()}
                          </p>
                        )}

                        {(app.status === 'approved' || app.status === 'declined') && app.org_note && (
                          <p className="text-xs text-theme-muted flex items-start gap-1 mt-1">
                            <MessageSquare className="w-3.5 h-3.5 flex-shrink-0 mt-0.5 text-theme-subtle" aria-hidden="true" />
                            <span><span className="text-theme-subtle">Organiser's note: </span>{app.org_note}</span>
                          </p>
                        )}

                        <p className="text-xs text-theme-subtle mt-2">
                          {t('volunteering.applied_on', { date: new Date(app.created_at).toLocaleDateString() })}
                        </p>
                      </div>

                      {app.status === 'pending' && (
                        <Button
                          size="sm"
                          variant="flat"
                          color="danger"
                          onPress={() => handleWithdraw(app.id)}
                        >
                          {t('volunteering.withdraw')}
                        </Button>
                      )}
                    </div>
                  </GlassCard>
                </motion.div>
              ))}

              {hasMore && (
                <div className="pt-4 text-center">
                  <Button
                    variant="flat"
                    className="bg-theme-elevated text-theme-muted"
                    onPress={() => loadApplications(true)}
                  >
                    {t('volunteering.load_more')}
                  </Button>
                </div>
              )}
            </div>
          )}
        </>
      )}
    </>
  );
}

/* ───────────────────────── Hours Tab ───────────────────────── */

function HoursTab() {
  const { t } = useTranslation('community');
  const toast = useToast();
  const [summary, setSummary] = useState<HoursSummary | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [organisations, setOrganisations] = useState<Organization[]>([]);

  // Log hours modal
  const { isOpen, onOpen, onClose } = useDisclosure();
  const [logForm, setLogForm] = useState({
    organization_id: '',
    date: new Date().toISOString().split('T')[0],
    hours: '',
    description: '',
  });
  const [isLogging, setIsLogging] = useState(false);

  const loadSummary = useCallback(async () => {
    try {
      setIsLoading(true);
      setError(null);

      const [summaryRes, orgsRes] = await Promise.all([
        api.get<HoursSummary>('/v2/volunteering/hours/summary'),
        api.get<Organization[]>('/v2/volunteering/organisations?per_page=50'),
      ]);

      if (summaryRes.success && summaryRes.data) {
        setSummary(summaryRes.data);
      } else {
        setError(t('volunteering.error_load_hours'));
      }

      if (orgsRes.success && orgsRes.data) {
        setOrganisations(Array.isArray(orgsRes.data) ? orgsRes.data : []);
      }
    } catch (err) {
      logError('Failed to load hours summary', err);
      setError(t('volunteering.error_load_hours_retry'));
    } finally {
      setIsLoading(false);
    }
  }, [t]);

  useEffect(() => {
    loadSummary();
  }, [loadSummary]);

  const handleLogHours = async () => {
    if (!logForm.hours || !logForm.date || !logForm.organization_id) return;

    try {
      setIsLogging(true);
      const response = await api.post('/v2/volunteering/hours', {
        organization_id: parseInt(logForm.organization_id, 10),
        date: logForm.date,
        hours: parseFloat(logForm.hours),
        description: logForm.description || undefined,
      });

      if (response.success) {
        toast.success(t('volunteering.hours_logged_success', 'Hours logged successfully!'));
        onClose();
        setLogForm({ organization_id: '', date: new Date().toISOString().split('T')[0], hours: '', description: '' });
        loadSummary();
      } else {
        toast.error(response.error || t('volunteering.hours_log_failed', 'Failed to log hours. Please try again.'));
      }
    } catch (err) {
      logError('Failed to log hours', err);
      toast.error(t('volunteering.hours_log_failed', 'Failed to log hours. Please try again.'));
    } finally {
      setIsLogging(false);
    }
  };

  const totalHours = (summary?.total_verified ?? 0) + (summary?.total_pending ?? 0);

  return (
    <>
      {/* Log Hours Button */}
      <div className="flex justify-end">
        <Button
          className="bg-gradient-to-r from-rose-500 to-pink-600 text-white"
          startContent={<Plus className="w-4 h-4" aria-hidden="true" />}
          onPress={onOpen}
        >
          {t('volunteering.log_hours')}
        </Button>
      </div>

      {/* Error */}
      {error && !isLoading && (
        <GlassCard className="p-8 text-center">
          <AlertTriangle className="w-12 h-12 text-amber-500 mx-auto mb-4" aria-hidden="true" />
          <p className="text-theme-muted mb-4">{error}</p>
          <Button
            className="bg-gradient-to-r from-rose-500 to-pink-600 text-white"
            startContent={<RefreshCw className="w-4 h-4" aria-hidden="true" />}
            onPress={() => loadSummary()}
          >
            {t('volunteering.try_again')}
          </Button>
        </GlassCard>
      )}

      {/* Summary */}
      {!error && (
        <>
          {isLoading ? (
            <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
              {[1, 2, 3].map((i) => (
                <GlassCard key={i} className="p-5 animate-pulse">
                  <div className="h-8 bg-theme-hover rounded w-1/2 mb-2" />
                  <div className="h-3 bg-theme-hover rounded w-3/4" />
                </GlassCard>
              ))}
            </div>
          ) : summary ? (
            <div className="space-y-6">
              {/* Stats Cards */}
              <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                <GlassCard className="p-5">
                  <div className="flex items-center gap-3">
                    <div className="w-10 h-10 rounded-xl bg-emerald-500/10 flex items-center justify-center">
                      <CheckCircle className="w-5 h-5 text-emerald-400" aria-hidden="true" />
                    </div>
                    <div>
                      <p className="text-2xl font-bold text-theme-primary">{summary.total_verified}</p>
                      <p className="text-xs text-theme-muted">{t('volunteering.verified_hours')}</p>
                    </div>
                  </div>
                </GlassCard>

                <GlassCard className="p-5">
                  <div className="flex items-center gap-3">
                    <div className="w-10 h-10 rounded-xl bg-amber-500/10 flex items-center justify-center">
                      <Hourglass className="w-5 h-5 text-amber-400" aria-hidden="true" />
                    </div>
                    <div>
                      <p className="text-2xl font-bold text-theme-primary">{summary.total_pending}</p>
                      <p className="text-xs text-theme-muted">{t('volunteering.pending_hours')}</p>
                    </div>
                  </div>
                </GlassCard>

                <GlassCard className="p-5">
                  <div className="flex items-center gap-3">
                    <div className="w-10 h-10 rounded-xl bg-rose-500/10 flex items-center justify-center">
                      <TrendingUp className="w-5 h-5 text-rose-400" aria-hidden="true" />
                    </div>
                    <div>
                      <p className="text-2xl font-bold text-theme-primary">{totalHours}</p>
                      <p className="text-xs text-theme-muted">{t('volunteering.total_hours')}</p>
                    </div>
                  </div>
                </GlassCard>
              </div>

              {/* Progress toward a round number goal */}
              {totalHours > 0 && (
                <GlassCard className="p-5">
                  <div className="flex justify-between text-sm text-theme-muted mb-2">
                    <span>{t('volunteering.progress')}</span>
                    <span>{t('volunteering.hours_of_goal', { current: totalHours, goal: Math.ceil(totalHours / 50) * 50 })}</span>
                  </div>
                  <Progress
                    value={(totalHours / (Math.ceil(totalHours / 50) * 50)) * 100}
                    classNames={{
                      indicator: 'bg-gradient-to-r from-rose-500 to-pink-600',
                      track: 'bg-theme-hover',
                    }}
                    size="md"
                    aria-label={t('volunteering.hours_progress_aria', { hours: totalHours })}
                  />
                </GlassCard>
              )}

              {/* By Organization */}
              {(summary.by_organization ?? []).length > 0 && (
                <GlassCard className="p-5">
                  <h3 className="font-semibold text-theme-primary mb-4 flex items-center gap-2">
                    <Building2 className="w-4 h-4 text-rose-400" aria-hidden="true" />
                    {t('volunteering.hours_by_organization')}
                  </h3>
                  <div className="space-y-3">
                    {(summary.by_organization ?? []).map((org, i) => (
                      <div key={i} className="flex items-center justify-between">
                        <span className="text-sm text-theme-muted">{org.name}</span>
                        <span className="text-sm font-medium text-theme-primary">{org.total}h</span>
                      </div>
                    ))}
                  </div>
                </GlassCard>
              )}

              {/* By Month */}
              {(summary.by_month ?? []).length > 0 && (
                <GlassCard className="p-5">
                  <h3 className="font-semibold text-theme-primary mb-4 flex items-center gap-2">
                    <Calendar className="w-4 h-4 text-rose-400" aria-hidden="true" />
                    {t('volunteering.hours_by_month')}
                  </h3>
                  <div className="space-y-3">
                    {(summary.by_month ?? []).map((month, i) => (
                      <div key={i} className="flex items-center justify-between">
                        <span className="text-sm text-theme-muted">
                          {new Date(month.month + '-01').toLocaleDateString(undefined, { year: 'numeric', month: 'long' })}
                        </span>
                        <span className="text-sm font-medium text-theme-primary">{month.total}h</span>
                      </div>
                    ))}
                  </div>
                </GlassCard>
              )}

              {totalHours === 0 && (
                <EmptyState
                  icon={<Timer className="w-12 h-12" aria-hidden="true" />}
                  title={t('volunteering.no_hours_logged')}
                  description={t('volunteering.no_hours_description')}
                  action={
                    <Button
                      className="bg-gradient-to-r from-rose-500 to-pink-600 text-white"
                      onPress={onOpen}
                    >
                      {t('volunteering.log_hours')}
                    </Button>
                  }
                />
              )}
            </div>
          ) : null}
        </>
      )}

      {/* Log Hours Modal */}
      <Modal isOpen={isOpen} onClose={onClose} size="lg" classNames={{
        base: 'bg-content1 border border-theme-default',
      }}>
        <ModalContent>
          <ModalHeader className="text-theme-primary">{t('volunteering.log_volunteering_hours')}</ModalHeader>
          <ModalBody className="space-y-4">
            <Select
              label={t('volunteering.organisation_label')}
              placeholder={t('volunteering.select_organisation')}
              selectedKeys={logForm.organization_id ? new Set([logForm.organization_id]) : new Set()}
              onSelectionChange={(keys) => { const val = Array.from(keys)[0] as string; if (val) setLogForm((prev) => ({ ...prev, organization_id: val })); }}
              isRequired
              classNames={{
                trigger: 'bg-theme-elevated border-theme-default',
                value: 'text-theme-primary',
              }}
              startContent={<Building2 className="w-4 h-4 text-theme-subtle" aria-hidden="true" />}
            >
              {organisations.map((org) => (
                <SelectItem key={String(org.id)}>{org.name}</SelectItem>
              ))}
            </Select>
            <Input
              type="date"
              label={t('volunteering.date_label')}
              value={logForm.date}
              onChange={(e) => setLogForm((prev) => ({ ...prev, date: e.target.value }))}
              isRequired
              classNames={{
                input: 'bg-transparent text-theme-primary',
                inputWrapper: 'bg-theme-elevated border-theme-default',
              }}
            />
            <Input
              type="number"
              label={t('volunteering.hours_label')}
              placeholder={t('volunteering.hours_placeholder')}
              value={logForm.hours}
              onChange={(e) => setLogForm((prev) => ({ ...prev, hours: e.target.value }))}
              isRequired
              classNames={{
                input: 'bg-transparent text-theme-primary',
                inputWrapper: 'bg-theme-elevated border-theme-default',
              }}
            />
            <Textarea
              label={t('volunteering.description_label')}
              placeholder={t('volunteering.description_placeholder')}
              value={logForm.description}
              onChange={(e) => setLogForm((prev) => ({ ...prev, description: e.target.value }))}
              classNames={{
                input: 'bg-transparent text-theme-primary',
                inputWrapper: 'bg-theme-elevated border-theme-default',
              }}
            />
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={onClose} className="text-theme-muted">{t('volunteering.cancel')}</Button>
            <Button
              className="bg-gradient-to-r from-rose-500 to-pink-600 text-white"
              onPress={handleLogHours}
              isLoading={isLogging}
              isDisabled={!logForm.hours || !logForm.date || !logForm.organization_id}
            >
              {t('volunteering.log_hours')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </>
  );
}

export default VolunteeringPage;
