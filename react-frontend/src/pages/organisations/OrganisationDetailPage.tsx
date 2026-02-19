// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Organisation Detail Page - View organisation profile, opportunities, and stats
 *
 * Uses V2 API:
 * - GET /api/v2/volunteering/organisations/{id}
 * - GET /api/v2/volunteering/opportunities?organization_id={id}
 * - GET /api/v2/volunteering/reviews/organization/{id}
 */

import { useState, useEffect, useCallback } from 'react';
import { useParams, Link } from 'react-router-dom';
import { motion } from 'framer-motion';
import {
  Button,
  Avatar,
  Chip,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Textarea,
  useDisclosure,
} from '@heroui/react';
import {
  MapPin,
  Globe,
  Mail,
  Clock,
  Star,
  Users,
  Heart,
  Calendar,
  Briefcase,
  ChevronRight,
  AlertTriangle,
  RefreshCw,
  ExternalLink,
  Send,
} from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { EmptyState, LoadingScreen } from '@/components/feedback';
import { Breadcrumbs } from '@/components/navigation';
import { useAuth, useTenant } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';

/* ───────────────────────── Types ───────────────────────── */

interface OrganisationDetail {
  id: number;
  name: string;
  description: string | null;
  logo_url: string | null;
  website: string | null;
  contact_email: string | null;
  location: string | null;
  opportunity_count: number;
  total_hours: number;
  volunteer_count: number;
  average_rating: number | null;
  review_count: number;
  created_at: string;
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
  organization: { id: number; name: string; logo_url: string | null };
  created_at: string;
  has_applied?: boolean;
}

interface Review {
  id: number;
  rating: number;
  comment: string;
  author: { id: number; name: string; avatar: string | null };
  created_at: string;
}

/* ───────────────────────── Main Component ───────────────────────── */

export function OrganisationDetailPage() {
  const { id } = useParams<{ id: string }>();
  const { isAuthenticated } = useAuth();
  const { tenantPath } = useTenant();

  const [organisation, setOrganisation] = useState<OrganisationDetail | null>(null);
  const [opportunities, setOpportunities] = useState<Opportunity[]>([]);
  const [reviews, setReviews] = useState<Review[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // Apply modal
  const applyModal = useDisclosure();
  const [selectedOpp, setSelectedOpp] = useState<Opportunity | null>(null);
  const [applyMessage, setApplyMessage] = useState('');
  const [isApplying, setIsApplying] = useState(false);

  usePageTitle(organisation?.name ?? 'Organisation');

  const loadData = useCallback(async () => {
    if (!id) return;
    try {
      setIsLoading(true);
      setError(null);

      // Load org details, opportunities, and reviews in parallel
      const [orgRes, oppsRes, reviewsRes] = await Promise.all([
        api.get<OrganisationDetail>(`/v2/volunteering/organisations/${id}`),
        api.get<{ data: Opportunity[] }>(`/v2/volunteering/opportunities?organization_id=${id}&per_page=50`),
        api.get<{ reviews: Review[] }>(`/v2/volunteering/reviews/organization/${id}`),
      ]);

      if (orgRes.success && orgRes.data) {
        setOrganisation(orgRes.data as OrganisationDetail);
      } else {
        setError('Organisation not found.');
        return;
      }

      if (oppsRes.success && oppsRes.data) {
        setOpportunities(Array.isArray(oppsRes.data) ? oppsRes.data as Opportunity[] : []);
      }

      if (reviewsRes.success && reviewsRes.data) {
        const reviewsData = reviewsRes.data as { reviews?: Review[] };
        setReviews(reviewsData.reviews ?? []);
      }
    } catch (err) {
      logError('Failed to load organisation', err);
      setError('Failed to load organisation details. Please try again.');
    } finally {
      setIsLoading(false);
    }
  }, [id]);

  useEffect(() => {
    loadData();
  }, [loadData]);

  const handleApply = async () => {
    if (!selectedOpp) return;
    try {
      setIsApplying(true);
      const response = await api.post(`/v2/volunteering/opportunities/${selectedOpp.id}/apply`, {
        message: applyMessage || undefined,
      });
      if (response.success) {
        applyModal.onClose();
        setApplyMessage('');
        setSelectedOpp(null);
        loadData();
      }
    } catch (err) {
      logError('Failed to apply', err);
    } finally {
      setIsApplying(false);
    }
  };

  if (isLoading) return <LoadingScreen message="Loading organisation..." />;

  if (error) {
    return (
      <div className="space-y-6">
        <Breadcrumbs items={[
          { label: 'Volunteering', href: tenantPath('/volunteering') },
          { label: 'Organisations', href: tenantPath('/organisations') },
          { label: 'Error' },
        ]} />
        <GlassCard className="p-8 text-center">
          <AlertTriangle className="w-12 h-12 text-amber-500 mx-auto mb-4" aria-hidden="true" />
          <h2 className="text-lg font-semibold text-theme-primary mb-2">Unable to Load Organisation</h2>
          <p className="text-theme-muted mb-4">{error}</p>
          <div className="flex gap-3 justify-center">
            <Button
              className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
              startContent={<RefreshCw className="w-4 h-4" aria-hidden="true" />}
              onPress={() => loadData()}
            >
              Try Again
            </Button>
            <Link to={tenantPath("/organisations")}>
              <Button variant="flat" className="bg-theme-elevated text-theme-muted">
                Browse Organisations
              </Button>
            </Link>
          </div>
        </GlassCard>
      </div>
    );
  }

  if (!organisation) return null;

  const activeOpps = opportunities.filter((o) => o.is_active);

  return (
    <div className="space-y-6">
      {/* Breadcrumbs */}
      <Breadcrumbs items={[
        { label: 'Volunteering', href: tenantPath('/volunteering') },
        { label: 'Organisations', href: tenantPath('/organisations') },
        { label: organisation.name },
      ]} />

      {/* Hero Section */}
      <GlassCard className="p-6 md:p-8">
        <div className="flex flex-col sm:flex-row items-start gap-6">
          <Avatar
            name={organisation.name}
            src={organisation.logo_url ?? undefined}
            className="w-20 h-20 flex-shrink-0"
          />
          <div className="flex-1 min-w-0">
            <h1 className="text-2xl font-bold text-theme-primary">{organisation.name}</h1>

            {organisation.location && (
              <p className="text-theme-muted flex items-center gap-1 mt-1">
                <MapPin className="w-4 h-4" aria-hidden="true" />
                {organisation.location}
              </p>
            )}

            {organisation.description && (
              <p className="text-theme-muted mt-3">{organisation.description}</p>
            )}

            {/* Action Buttons */}
            <div className="flex flex-wrap gap-3 mt-4">
              {organisation.website && (
                <a href={organisation.website} target="_blank" rel="noopener noreferrer">
                  <Button
                    variant="flat"
                    className="bg-theme-elevated text-theme-muted"
                    startContent={<Globe className="w-4 h-4" aria-hidden="true" />}
                    endContent={<ExternalLink className="w-3 h-3" aria-hidden="true" />}
                  >
                    Website
                  </Button>
                </a>
              )}
              {organisation.contact_email && (
                <a href={`mailto:${organisation.contact_email}`}>
                  <Button
                    variant="flat"
                    className="bg-theme-elevated text-theme-muted"
                    startContent={<Mail className="w-4 h-4" aria-hidden="true" />}
                  >
                    Contact
                  </Button>
                </a>
              )}
            </div>
          </div>
        </div>
      </GlassCard>

      {/* Stats Cards */}
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        <GlassCard className="p-4 text-center">
          <div className="flex items-center justify-center w-10 h-10 rounded-xl bg-rose-500/10 mx-auto mb-2">
            <Heart className="w-5 h-5 text-rose-400" aria-hidden="true" />
          </div>
          <p className="text-xl font-bold text-theme-primary">{organisation.opportunity_count}</p>
          <p className="text-xs text-theme-muted">Opportunities</p>
        </GlassCard>

        <GlassCard className="p-4 text-center">
          <div className="flex items-center justify-center w-10 h-10 rounded-xl bg-indigo-500/10 mx-auto mb-2">
            <Users className="w-5 h-5 text-indigo-400" aria-hidden="true" />
          </div>
          <p className="text-xl font-bold text-theme-primary">{organisation.volunteer_count}</p>
          <p className="text-xs text-theme-muted">Volunteers</p>
        </GlassCard>

        <GlassCard className="p-4 text-center">
          <div className="flex items-center justify-center w-10 h-10 rounded-xl bg-emerald-500/10 mx-auto mb-2">
            <Clock className="w-5 h-5 text-emerald-400" aria-hidden="true" />
          </div>
          <p className="text-xl font-bold text-theme-primary">{organisation.total_hours}</p>
          <p className="text-xs text-theme-muted">Hours Logged</p>
        </GlassCard>

        <GlassCard className="p-4 text-center">
          <div className="flex items-center justify-center w-10 h-10 rounded-xl bg-amber-500/10 mx-auto mb-2">
            <Star className="w-5 h-5 text-amber-400" aria-hidden="true" />
          </div>
          <p className="text-xl font-bold text-theme-primary">
            {organisation.average_rating ? organisation.average_rating.toFixed(1) : '—'}
          </p>
          <p className="text-xs text-theme-muted">
            {organisation.review_count > 0 ? `${organisation.review_count} reviews` : 'No reviews'}
          </p>
        </GlassCard>
      </div>

      {/* Active Opportunities */}
      <div>
        <h2 className="text-lg font-semibold text-theme-primary mb-4 flex items-center gap-2">
          <Briefcase className="w-5 h-5 text-rose-400" aria-hidden="true" />
          Active Opportunities
          {activeOpps.length > 0 && (
            <Chip size="sm" variant="flat" className="text-theme-subtle">{activeOpps.length}</Chip>
          )}
        </h2>

        {activeOpps.length === 0 ? (
          <EmptyState
            icon={<Briefcase className="w-12 h-12" aria-hidden="true" />}
            title="No active opportunities"
            description="This organisation has no open volunteer opportunities right now"
          />
        ) : (
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            className="space-y-3"
          >
            {activeOpps.map((opp) => (
              <GlassCard key={opp.id} className="p-4">
                <div className="flex items-start justify-between gap-4">
                  <div className="flex-1 min-w-0">
                    <h3 className="font-semibold text-theme-primary">{opp.title}</h3>
                    {opp.description && (
                      <p className="text-sm text-theme-muted mt-1 line-clamp-2">{opp.description}</p>
                    )}

                    <div className="flex flex-wrap items-center gap-3 mt-2 text-xs text-theme-subtle">
                      {opp.location && (
                        <span className="flex items-center gap-1">
                          <MapPin className="w-3 h-3" aria-hidden="true" />
                          {opp.location}
                        </span>
                      )}
                      {opp.is_remote && (
                        <Chip size="sm" variant="flat" color="primary" startContent={<Globe className="w-3 h-3" />}>
                          Remote
                        </Chip>
                      )}
                      {opp.start_date && (
                        <span className="flex items-center gap-1">
                          <Calendar className="w-3 h-3" aria-hidden="true" />
                          {new Date(opp.start_date).toLocaleDateString()}
                          {opp.end_date ? ` – ${new Date(opp.end_date).toLocaleDateString()}` : ''}
                        </span>
                      )}
                      {opp.category && (
                        <Chip size="sm" variant="flat" className="text-theme-subtle">{opp.category}</Chip>
                      )}
                    </div>

                    {opp.has_applied && (
                      <Chip size="sm" color="success" variant="flat" className="mt-2">
                        Applied
                      </Chip>
                    )}
                  </div>

                  {isAuthenticated && !opp.has_applied && (
                    <Button
                      size="sm"
                      className="bg-gradient-to-r from-rose-500 to-pink-600 text-white flex-shrink-0"
                      endContent={<ChevronRight className="w-4 h-4" aria-hidden="true" />}
                      onPress={() => {
                        setSelectedOpp(opp);
                        setApplyMessage('');
                        applyModal.onOpen();
                      }}
                    >
                      Apply
                    </Button>
                  )}
                </div>
              </GlassCard>
            ))}
          </motion.div>
        )}
      </div>

      {/* Reviews */}
      {reviews.length > 0 && (
        <div>
          <h2 className="text-lg font-semibold text-theme-primary mb-4 flex items-center gap-2">
            <Star className="w-5 h-5 text-amber-400" aria-hidden="true" />
            Reviews
            <Chip size="sm" variant="flat" className="text-theme-subtle">{reviews.length}</Chip>
          </h2>

          <div className="space-y-3">
            {reviews.map((review) => (
              <GlassCard key={review.id} className="p-4">
                <div className="flex items-start gap-3">
                  <Link to={tenantPath(`/profile/${review.author.id}`)}>
                    <Avatar
                      name={review.author.name}
                      src={review.author.avatar ?? undefined}
                      size="sm"
                    />
                  </Link>
                  <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2 mb-1">
                      <Link
                        to={tenantPath(`/profile/${review.author.id}`)}
                        className="font-semibold text-sm text-theme-primary hover:underline"
                      >
                        {review.author.name}
                      </Link>
                      <div className="flex items-center gap-0.5">
                        {Array.from({ length: 5 }, (_, i) => (
                          <Star
                            key={i}
                            className={`w-3 h-3 ${i < review.rating ? 'text-amber-400 fill-amber-400' : 'text-theme-subtle'}`}
                            aria-hidden="true"
                          />
                        ))}
                      </div>
                      <span className="text-xs text-theme-subtle">
                        {new Date(review.created_at).toLocaleDateString()}
                      </span>
                    </div>
                    {review.comment && (
                      <p className="text-sm text-theme-muted">{review.comment}</p>
                    )}
                  </div>
                </div>
              </GlassCard>
            ))}
          </div>
        </div>
      )}

      {/* Apply Modal */}
      <Modal isOpen={applyModal.isOpen} onClose={applyModal.onClose} size="lg" classNames={{
        base: 'bg-content1 border border-theme-default',
      }}>
        <ModalContent>
          <ModalHeader className="text-theme-primary">
            Apply to Volunteer
          </ModalHeader>
          <ModalBody className="space-y-4">
            {selectedOpp && (
              <div>
                <h3 className="font-semibold text-theme-primary">{selectedOpp.title}</h3>
                <p className="text-sm text-theme-muted">{organisation.name}</p>
              </div>
            )}
            <Textarea
              label="Cover Message (optional)"
              placeholder="Tell the organisation why you'd like to volunteer..."
              value={applyMessage}
              onChange={(e) => setApplyMessage(e.target.value)}
              classNames={{
                input: 'bg-transparent text-theme-primary',
                inputWrapper: 'bg-theme-elevated border-theme-default',
              }}
            />
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={applyModal.onClose} className="text-theme-muted">Cancel</Button>
            <Button
              className="bg-gradient-to-r from-rose-500 to-pink-600 text-white"
              onPress={handleApply}
              isLoading={isApplying}
              startContent={<Send className="w-4 h-4" aria-hidden="true" />}
            >
              Submit Application
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}

export default OrganisationDetailPage;
