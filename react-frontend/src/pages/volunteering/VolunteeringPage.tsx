// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Volunteering Page - Browse opportunities, track hours, manage applications
 */

import { useState, useEffect, useCallback } from 'react';
import { Link } from 'react-router-dom';
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
} from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { EmptyState } from '@/components/feedback';
import { useAuth, useTenant } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';

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
  created_at: string;
}

interface HoursSummary {
  total_verified: number;
  total_pending: number;
  total_declined: number;
  by_organization: { name: string; total: number }[];
  by_month: { month: string; total: number }[];
}

type VolunteerTab = 'opportunities' | 'applications' | 'hours';

/* ───────────────────────── Main Component ───────────────────────── */

export function VolunteeringPage() {
  usePageTitle('Volunteering');
  const { isAuthenticated } = useAuth();
  const { tenantPath } = useTenant();
  const [tab, setTab] = useState<VolunteerTab>('opportunities');

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 className="text-2xl font-bold text-theme-primary flex items-center gap-3">
            <Heart className="w-7 h-7 text-rose-400" aria-hidden="true" />
            Volunteering
          </h1>
          <p className="text-theme-muted mt-1">Find opportunities and track your impact</p>
        </div>
        <Link to={tenantPath("/organisations")}>
          <Button
            variant="flat"
            className="bg-theme-elevated text-theme-muted"
            startContent={<Building2 className="w-4 h-4" aria-hidden="true" />}
          >
            Browse Organisations
          </Button>
        </Link>
      </div>

      {/* Tabs */}
      <div className="flex gap-2 flex-wrap">
        <Button
          variant={tab === 'opportunities' ? 'solid' : 'flat'}
          className={tab === 'opportunities' ? 'bg-gradient-to-r from-rose-500 to-pink-600 text-white' : 'bg-theme-elevated text-theme-muted'}
          onPress={() => setTab('opportunities')}
          startContent={<Briefcase className="w-4 h-4" aria-hidden="true" />}
        >
          Opportunities
        </Button>
        {isAuthenticated && (
          <>
            <Button
              variant={tab === 'applications' ? 'solid' : 'flat'}
              className={tab === 'applications' ? 'bg-gradient-to-r from-rose-500 to-pink-600 text-white' : 'bg-theme-elevated text-theme-muted'}
              onPress={() => setTab('applications')}
              startContent={<Send className="w-4 h-4" aria-hidden="true" />}
            >
              My Applications
            </Button>
            <Button
              variant={tab === 'hours' ? 'solid' : 'flat'}
              className={tab === 'hours' ? 'bg-gradient-to-r from-rose-500 to-pink-600 text-white' : 'bg-theme-elevated text-theme-muted'}
              onPress={() => setTab('hours')}
              startContent={<Timer className="w-4 h-4" aria-hidden="true" />}
            >
              My Hours
            </Button>
          </>
        )}
      </div>

      {/* Tab Content */}
      {tab === 'opportunities' && <OpportunitiesTab />}
      {tab === 'applications' && <ApplicationsTab />}
      {tab === 'hours' && <HoursTab />}
    </div>
  );
}

/* ───────────────────────── Opportunities Tab ───────────────────────── */

function OpportunitiesTab() {
  const { isAuthenticated } = useAuth();
  const [opportunities, setOpportunities] = useState<Opportunity[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [searchQuery, setSearchQuery] = useState('');
  const [hasMore, setHasMore] = useState(false);
  const [cursor, setCursor] = useState<string | undefined>();

  // Apply modal
  const { isOpen, onOpen, onClose } = useDisclosure();
  const [selectedOpportunity, setSelectedOpportunity] = useState<Opportunity | null>(null);
  const [applyMessage, setApplyMessage] = useState('');
  const [isApplying, setIsApplying] = useState(false);

  const loadOpportunities = useCallback(async (append = false) => {
    try {
      if (!append) {
        setIsLoading(true);
        setError(null);
      }

      const params = new URLSearchParams();
      params.set('per_page', '20');
      if (append && cursor) params.set('cursor', cursor);
      if (searchQuery.trim()) params.set('search', searchQuery.trim());

      const response = await api.get<{ data: Opportunity[]; meta: { cursor: string | null; has_more: boolean } }>(
        `/v2/volunteering/opportunities?${params}`
      );

      if (response.success && response.data) {
        const items = Array.isArray(response.data) ? response.data : [];

        if (append) {
          setOpportunities((prev) => [...prev, ...items]);
        } else {
          setOpportunities(items);
        }
        setHasMore(response.meta?.has_more ?? false);
        setCursor(response.meta?.cursor ?? undefined);
      } else {
        if (!append) setError('Failed to load opportunities.');
      }
    } catch (err) {
      logError('Failed to load opportunities', err);
      if (!append) setError('Failed to load opportunities. Please try again.');
    } finally {
      setIsLoading(false);
    }
  }, [cursor, searchQuery]);

  useEffect(() => {
    setCursor(undefined);
    loadOpportunities();
  }, [searchQuery, loadOpportunities]);

  const handleApply = async () => {
    if (!selectedOpportunity) return;

    try {
      setIsApplying(true);
      const response = await api.post(`/v2/volunteering/opportunities/${selectedOpportunity.id}/apply`, {
        message: applyMessage || undefined,
      });

      if (response.success) {
        onClose();
        setApplyMessage('');
        setSelectedOpportunity(null);
        loadOpportunities();
      }
    } catch (err) {
      logError('Failed to apply', err);
    } finally {
      setIsApplying(false);
    }
  };

  const openApplyModal = (opp: Opportunity) => {
    setSelectedOpportunity(opp);
    setApplyMessage('');
    onOpen();
  };

  const containerVariants = {
    hidden: { opacity: 0 },
    visible: { opacity: 1, transition: { staggerChildren: 0.05 } },
  };

  const itemVariants = {
    hidden: { opacity: 0, y: 20 },
    visible: { opacity: 1, y: 0 },
  };

  return (
    <>
      {/* Search */}
      <div className="max-w-md">
        <Input
          placeholder="Search opportunities..."
          value={searchQuery}
          onChange={(e) => setSearchQuery(e.target.value)}
          startContent={<Search className="w-4 h-4 text-theme-muted" aria-hidden="true" />}
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
          <h2 className="text-lg font-semibold text-theme-primary mb-2">Unable to Load Opportunities</h2>
          <p className="text-theme-muted mb-4">{error}</p>
          <Button
            className="bg-gradient-to-r from-rose-500 to-pink-600 text-white"
            startContent={<RefreshCw className="w-4 h-4" aria-hidden="true" />}
            onPress={() => loadOpportunities()}
          >
            Try Again
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
              title="No opportunities found"
              description={searchQuery ? 'Try a different search term' : 'No volunteering opportunities available right now'}
            />
          ) : (
            <motion.div
              variants={containerVariants}
              initial="hidden"
              animate="visible"
              className="space-y-4"
            >
              {opportunities.map((opp) => (
                <motion.div key={opp.id} variants={itemVariants}>
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
                    Load More
                  </Button>
                </div>
              )}
            </motion.div>
          )}
        </>
      )}

      {/* Apply Modal */}
      <Modal isOpen={isOpen} onClose={onClose} size="lg" classNames={{
        base: 'bg-content1 border border-theme-default',
      }}>
        <ModalContent>
          <ModalHeader className="text-theme-primary">
            Apply to Volunteer
          </ModalHeader>
          <ModalBody className="space-y-4">
            {selectedOpportunity && (
              <div>
                <h3 className="font-semibold text-theme-primary">{selectedOpportunity.title}</h3>
                <p className="text-sm text-theme-muted">{selectedOpportunity.organization.name}</p>
              </div>
            )}
            <Textarea
              label="Cover Message (optional)"
              placeholder="Tell the organization why you'd like to volunteer..."
              value={applyMessage}
              onChange={(e) => setApplyMessage(e.target.value)}
              classNames={{
                input: 'bg-transparent text-theme-primary',
                inputWrapper: 'bg-theme-elevated border-theme-default',
              }}
            />
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={onClose} className="text-theme-muted">Cancel</Button>
            <Button
              className="bg-gradient-to-r from-rose-500 to-pink-600 text-white"
              onPress={handleApply}
              isLoading={isApplying}
            >
              Submit Application
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
  const { tenantPath } = useTenant();
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
              <h3 className="font-semibold text-theme-primary text-lg truncate">{opportunity.title}</h3>
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
                Remote
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
                {opportunity.category}
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
              Applied
            </Chip>
          )}
        </div>

        {/* Apply Button */}
        {onApply && (
          <div className="flex-shrink-0">
            <Button
              size="sm"
              className="bg-gradient-to-r from-rose-500 to-pink-600 text-white"
              onPress={onApply}
              endContent={<ChevronRight className="w-4 h-4" aria-hidden="true" />}
            >
              Apply
            </Button>
          </div>
        )}
      </div>
    </GlassCard>
  );
}

/* ───────────────────────── Applications Tab ───────────────────────── */

function ApplicationsTab() {
  const { tenantPath } = useTenant();
  const [applications, setApplications] = useState<Application[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [statusFilter, setStatusFilter] = useState<string>('');
  const [hasMore, setHasMore] = useState(false);
  const [cursor, setCursor] = useState<string | undefined>();

  const loadApplications = useCallback(async (append = false) => {
    try {
      if (!append) {
        setIsLoading(true);
        setError(null);
      }

      const params = new URLSearchParams();
      params.set('per_page', '20');
      if (append && cursor) params.set('cursor', cursor);
      if (statusFilter) params.set('status', statusFilter);

      const response = await api.get<{ data: Application[]; meta: { cursor: string | null; has_more: boolean } }>(
        `/v2/volunteering/applications?${params}`
      );

      if (response.success && response.data) {
        const items = Array.isArray(response.data) ? response.data : [];

        if (append) {
          setApplications((prev) => [...prev, ...items]);
        } else {
          setApplications(items);
        }
        setHasMore(response.meta?.has_more ?? false);
        setCursor(response.meta?.cursor ?? undefined);
      } else {
        if (!append) setError('Failed to load applications.');
      }
    } catch (err) {
      logError('Failed to load applications', err);
      if (!append) setError('Failed to load applications. Please try again.');
    } finally {
      setIsLoading(false);
    }
  }, [cursor, statusFilter]);

  useEffect(() => {
    setCursor(undefined);
    loadApplications();
  }, [statusFilter, loadApplications]);

  const handleWithdraw = async (applicationId: number) => {
    try {
      const response = await api.delete(`/v2/volunteering/applications/${applicationId}`);
      if (response.success) {
        loadApplications();
      }
    } catch (err) {
      logError('Failed to withdraw application', err);
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

  const containerVariants = {
    hidden: { opacity: 0 },
    visible: { opacity: 1, transition: { staggerChildren: 0.05 } },
  };

  const itemVariants = {
    hidden: { opacity: 0, y: 20 },
    visible: { opacity: 1, y: 0 },
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
            {s ? s.charAt(0).toUpperCase() + s.slice(1) : 'All'}
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
            Try Again
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
              title="No applications"
              description={statusFilter ? `No ${statusFilter} applications` : "You haven't applied to any opportunities yet"}
            />
          ) : (
            <motion.div
              variants={containerVariants}
              initial="hidden"
              animate="visible"
              className="space-y-4"
            >
              {applications.map((app) => (
                <motion.div key={app.id} variants={itemVariants}>
                  <GlassCard className="p-5">
                    <div className="flex items-start justify-between gap-4">
                      <div className="flex-1 min-w-0">
                        <div className="flex items-center gap-2 mb-1 flex-wrap">
                          <h3 className="font-semibold text-theme-primary">{app.opportunity.title}</h3>
                          <Chip
                            size="sm"
                            color={statusColor(app.status)}
                            variant="flat"
                            startContent={statusIcon(app.status)}
                          >
                            {app.status.charAt(0).toUpperCase() + app.status.slice(1)}
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

                        <p className="text-xs text-theme-subtle mt-2">
                          Applied {new Date(app.created_at).toLocaleDateString()}
                        </p>
                      </div>

                      {app.status === 'pending' && (
                        <Button
                          size="sm"
                          variant="flat"
                          color="danger"
                          onPress={() => handleWithdraw(app.id)}
                        >
                          Withdraw
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
                    Load More
                  </Button>
                </div>
              )}
            </motion.div>
          )}
        </>
      )}
    </>
  );
}

/* ───────────────────────── Hours Tab ───────────────────────── */

function HoursTab() {
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
        api.get<{ data: Organization[] }>('/v2/volunteering/organisations?per_page=50'),
      ]);

      if (summaryRes.success && summaryRes.data) {
        setSummary(summaryRes.data as HoursSummary);
      } else {
        setError('Failed to load hours summary.');
      }

      if (orgsRes.success && orgsRes.data) {
        setOrganisations(Array.isArray(orgsRes.data) ? orgsRes.data as Organization[] : []);
      }
    } catch (err) {
      logError('Failed to load hours summary', err);
      setError('Failed to load hours summary. Please try again.');
    } finally {
      setIsLoading(false);
    }
  }, []);

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
        onClose();
        setLogForm({ organization_id: '', date: new Date().toISOString().split('T')[0], hours: '', description: '' });
        loadSummary();
      }
    } catch (err) {
      logError('Failed to log hours', err);
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
          Log Hours
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
            Try Again
          </Button>
        </GlassCard>
      )}

      {/* Summary */}
      {!error && (
        <>
          {isLoading ? (
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
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
              <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                <GlassCard className="p-5">
                  <div className="flex items-center gap-3">
                    <div className="w-10 h-10 rounded-xl bg-emerald-500/10 flex items-center justify-center">
                      <CheckCircle className="w-5 h-5 text-emerald-400" aria-hidden="true" />
                    </div>
                    <div>
                      <p className="text-2xl font-bold text-theme-primary">{summary.total_verified}</p>
                      <p className="text-xs text-theme-muted">Verified Hours</p>
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
                      <p className="text-xs text-theme-muted">Pending Hours</p>
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
                      <p className="text-xs text-theme-muted">Total Hours</p>
                    </div>
                  </div>
                </GlassCard>
              </div>

              {/* Progress toward a round number goal */}
              {totalHours > 0 && (
                <GlassCard className="p-5">
                  <div className="flex justify-between text-sm text-theme-muted mb-2">
                    <span>Progress</span>
                    <span>{totalHours} / {Math.ceil(totalHours / 50) * 50} hours</span>
                  </div>
                  <Progress
                    value={(totalHours / (Math.ceil(totalHours / 50) * 50)) * 100}
                    classNames={{
                      indicator: 'bg-gradient-to-r from-rose-500 to-pink-600',
                      track: 'bg-theme-hover',
                    }}
                    size="md"
                    aria-label={`Hours progress: ${totalHours} hours`}
                  />
                </GlassCard>
              )}

              {/* By Organization */}
              {summary.by_organization.length > 0 && (
                <GlassCard className="p-5">
                  <h3 className="font-semibold text-theme-primary mb-4 flex items-center gap-2">
                    <Building2 className="w-4 h-4 text-rose-400" aria-hidden="true" />
                    Hours by Organization
                  </h3>
                  <div className="space-y-3">
                    {summary.by_organization.map((org, i) => (
                      <div key={i} className="flex items-center justify-between">
                        <span className="text-sm text-theme-muted">{org.name}</span>
                        <span className="text-sm font-medium text-theme-primary">{org.total}h</span>
                      </div>
                    ))}
                  </div>
                </GlassCard>
              )}

              {/* By Month */}
              {summary.by_month.length > 0 && (
                <GlassCard className="p-5">
                  <h3 className="font-semibold text-theme-primary mb-4 flex items-center gap-2">
                    <Calendar className="w-4 h-4 text-rose-400" aria-hidden="true" />
                    Hours by Month
                  </h3>
                  <div className="space-y-3">
                    {summary.by_month.map((month, i) => (
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
                  title="No hours logged yet"
                  description="Start logging your volunteering hours to track your impact"
                  action={
                    <Button
                      className="bg-gradient-to-r from-rose-500 to-pink-600 text-white"
                      onPress={onOpen}
                    >
                      Log Hours
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
          <ModalHeader className="text-theme-primary">Log Volunteering Hours</ModalHeader>
          <ModalBody className="space-y-4">
            <Select
              label="Organisation"
              placeholder="Select organisation"
              selectedKeys={logForm.organization_id ? [logForm.organization_id] : []}
              onChange={(e) => setLogForm((prev) => ({ ...prev, organization_id: e.target.value }))}
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
              label="Date"
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
              label="Hours"
              placeholder="e.g., 3.5"
              value={logForm.hours}
              onChange={(e) => setLogForm((prev) => ({ ...prev, hours: e.target.value }))}
              isRequired
              classNames={{
                input: 'bg-transparent text-theme-primary',
                inputWrapper: 'bg-theme-elevated border-theme-default',
              }}
            />
            <Textarea
              label="Description (optional)"
              placeholder="What did you do?"
              value={logForm.description}
              onChange={(e) => setLogForm((prev) => ({ ...prev, description: e.target.value }))}
              classNames={{
                input: 'bg-transparent text-theme-primary',
                inputWrapper: 'bg-theme-elevated border-theme-default',
              }}
            />
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={onClose} className="text-theme-muted">Cancel</Button>
            <Button
              className="bg-gradient-to-r from-rose-500 to-pink-600 text-white"
              onPress={handleLogHours}
              isLoading={isLogging}
              isDisabled={!logForm.hours || !logForm.date || !logForm.organization_id}
            >
              Log Hours
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </>
  );
}

export default VolunteeringPage;
