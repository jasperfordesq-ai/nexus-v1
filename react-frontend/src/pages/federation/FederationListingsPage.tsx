// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { Autocomplete, AutocompleteItem, GlassCard, Button, ToggleButton, ToggleButtonGroup, Chip, SearchField, Modal, ModalContent, ModalHeader, ModalBody, ModalFooter, Avatar, CardRowsSkeleton } from '@/components/ui';

/**
 * Federation Listings Page - Browse listings from partner communities
 *
 * Features:
 * - Search input with debounce
 * - Type filter (All / Offers / Requests) as Chip group
 * - Partner community Select dropdown
 * - Responsive grid (1/2/3 cols)
 * - Cursor-based pagination with Load More
 * - Loading skeletons and error states
 */

import { useState, useEffect, useCallback, useRef } from 'react';
import { useSearchParams, useNavigate } from 'react-router-dom';
import { motion } from '@/lib/motion';

import Search from 'lucide-react/icons/search';
import Globe from 'lucide-react/icons/globe';
import Hand from 'lucide-react/icons/hand';
import Clock from 'lucide-react/icons/clock';
import MapPin from 'lucide-react/icons/map-pin';
import AlertTriangle from 'lucide-react/icons/triangle-alert';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import ListTodo from 'lucide-react/icons/list-todo';
import MessageSquare from 'lucide-react/icons/message-square';
import User from 'lucide-react/icons/user';
import { useTranslation } from 'react-i18next';
import { Breadcrumbs } from '@/components/navigation';
import { EmptyState } from '@/components/feedback';
import { PageMeta } from '@/components/seo';
import { useAuth, useTenant, useToast } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { resolveAvatarUrl, resolveThumbnailUrl } from '@/lib/helpers';
import type { FederatedListing, FederationPartner } from '@/types/api';

type ListingTypeFilter = 'all' | 'offer' | 'request';

const SEARCH_DEBOUNCE_MS = 300;
const PER_PAGE = 20;

export function FederationListingsPage() {
  const { t } = useTranslation('federation');
  usePageTitle(t('listings.page_title'));
  const toast = useToast();
  const navigate = useNavigate();
  const { isAuthenticated } = useAuth();
  const { tenantPath } = useTenant();
  const [searchParams, setSearchParams] = useSearchParams();

  // Data
  const [listings, setListings] = useState<FederatedListing[]>([]);
  const [partners, setPartners] = useState<FederationPartner[]>([]);

  // State
  const [isLoading, setIsLoading] = useState(true);
  const [isLoadingMore, setIsLoadingMore] = useState(false);
  const [loadError, setLoadError] = useState<string | null>(null);
  const [cursor, setCursor] = useState<string | null>(null);
  const [hasMore, setHasMore] = useState(false);

  // Detail modal
  const [selectedListing, setSelectedListing] = useState<FederatedListing | null>(null);
  const [isDetailOpen, setIsDetailOpen] = useState(false);

  // Filters
  const [searchQuery, setSearchQuery] = useState(searchParams.get('q') || '');
  const [debouncedQuery, setDebouncedQuery] = useState(searchQuery);
  const [selectedType, setSelectedType] = useState<ListingTypeFilter>(
    (searchParams.get('type') as ListingTypeFilter) || 'all'
  );
  const [selectedPartner, setSelectedPartner] = useState(
    searchParams.get('partner_id') || ''
  );

  const searchTimeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const abortRef = useRef<AbortController | null>(null);
  const tRef = useRef(t);
  tRef.current = t;
  const toastRef = useRef(toast);
  toastRef.current = toast;
  const loadListingsRef = useRef<(append?: boolean) => Promise<void>>(null!);

  const memberProfilePath = useCallback((listing: FederatedListing) => {
    if (!listing.author?.id) return '/federation/members';
    const tenantId = listing.timebank?.id;
    const tenantParam = tenantId ? `?tenant_id=${encodeURIComponent(String(tenantId))}` : '';
    return `/federation/members/${listing.author.id}${tenantParam}`;
  }, []);

  // â”€â”€ Debounce search â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
  useEffect(() => {
    if (searchTimeoutRef.current) clearTimeout(searchTimeoutRef.current);
    searchTimeoutRef.current = setTimeout(() => {
      setDebouncedQuery(searchQuery);
    }, SEARCH_DEBOUNCE_MS);

    return () => {
      if (searchTimeoutRef.current) clearTimeout(searchTimeoutRef.current);
    };
  }, [searchQuery]);

  // â”€â”€ Load partners for dropdown â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
  const loadPartners = useCallback(async () => {
    try {
      const response = await api.get<FederationPartner[]>('/v2/federation/partners');
      if (response.success && response.data) {
        setPartners(response.data);
      }
    } catch (error) {
      logError('Failed to load federation partners for filter', error);
    }
  }, []);

  useEffect(() => {
    loadPartners();
  }, [loadPartners]);

  // â”€â”€ Load listings â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
  const loadListings = useCallback(
    async (append = false) => {
      abortRef.current?.abort();
      const controller = new AbortController();
      abortRef.current = controller;

      try {
        if (!append) {
          setIsLoading(true);
          setLoadError(null);
        } else {
          setIsLoadingMore(true);
        }

        const params = new URLSearchParams();
        if (debouncedQuery) params.set('q', debouncedQuery);
        if (selectedType !== 'all') params.set('type', selectedType);
        if (selectedPartner) params.set('partner_id', selectedPartner);
        if (append && cursor) params.set('cursor', cursor);
        params.set('per_page', String(PER_PAGE));

        const response = await api.get<FederatedListing[]>(
          `/v2/federation/listings?${params}`,
          { signal: controller.signal }
        );

        if (controller.signal.aborted) return;

        if (response.success && response.data) {
          if (append) {
            setListings((prev) => [...prev, ...response.data!]);
          } else {
            setListings(response.data);
          }
          const nextCursor = response.meta?.cursor ?? response.meta?.next_cursor ?? null;
          setCursor(nextCursor);
          setHasMore(response.meta?.has_more ?? response.data.length >= PER_PAGE);
        } else {
          if (!append) setListings([]);
          setHasMore(false);
        }
      } catch (error) {
        if (controller.signal.aborted) return;
        logError('Failed to load federated listings', error);
        if (!append) {
          setLoadError(tRef.current('listings.load_error'));
        } else {
          toastRef.current.error(tRef.current('listings.load_more_error'));
        }
      } finally {
        if (!controller.signal.aborted) {
          setIsLoading(false);
          setIsLoadingMore(false);
        }
      }
    },
    [debouncedQuery, selectedType, selectedPartner, cursor]
  );
  loadListingsRef.current = loadListings;

  // Reload on filter change
  useEffect(() => {
    setCursor(null);
    setHasMore(false);
    loadListingsRef.current(false);

    return () => {
      abortRef.current?.abort();
    };
  }, [debouncedQuery, selectedType, selectedPartner]);

  // Sync URL params
  useEffect(() => {
    const params = new URLSearchParams();
    if (searchQuery) params.set('q', searchQuery);
    if (selectedType !== 'all') params.set('type', selectedType);
    if (selectedPartner) params.set('partner_id', selectedPartner);
    setSearchParams(params, { replace: true });
  }, [searchQuery, selectedType, selectedPartner, setSearchParams]);

  function handleLoadMore() {
    if (!isLoadingMore && hasMore) {
      loadListings(true);
    }
  }

  return (
    <div className="space-y-6">
      <PageMeta title={t('listings.page_title')} noIndex />
      {/* Breadcrumbs */}
      <Breadcrumbs
        items={[
          { label: t('listings.breadcrumb_federation'), href: tenantPath('/federation') },
          { label: t('listings.breadcrumb_listings') },
        ]}
      />

      {/* Header */}
      <div>
        <h1 className="text-2xl font-bold text-theme-primary flex items-center gap-3">
          <ListTodo className="w-7 h-7 text-emerald-600 dark:text-emerald-400" aria-hidden="true" />
          {t('listings.title')}
        </h1>
        <p className="text-theme-muted mt-1">
          {t('listings.subtitle')}
        </p>
      </div>

      {/* Filter Bar */}
      <GlassCard className="p-4">
        <div className="flex flex-col lg:flex-row gap-4">
          {/* Search */}
          <div className="flex-1">
            <SearchField
              placeholder={t('listings.search_placeholder')}
              aria-label={t('listings.search_placeholder')}
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              classNames={{
                input: 'bg-transparent text-theme-primary placeholder:text-theme-subtle',
                inputWrapper: 'bg-theme-elevated border-theme-default hover:bg-theme-hover',
              }}
            />
          </div>

          {/* Partner filter */}
          <Autocomplete
            placeholder={t('listings.all_communities')}
            searchPlaceholder={t('listings.search_communities')}
            aria-label={t('listings.filter_by_community')}
            value={selectedPartner || null}
            onChange={(key) => setSelectedPartner(key && !Array.isArray(key) ? String(key) : '')}
            className="w-full lg:w-52"
            classNames={{
              trigger: 'bg-theme-elevated border-theme-default hover:bg-theme-hover',
              value: 'text-theme-primary',
            }}
            startContent={<Globe className="w-4 h-4 text-theme-subtle" aria-hidden="true" />}
          >
            {partners.map((partner) => {
              const label = partner.is_external ? `${partner.name} (${t('external')})` : partner.name;
              return (
                <AutocompleteItem key={String(partner.id)} id={String(partner.id)} textValue={label}>
                  {label}
                </AutocompleteItem>
              );
            })}
          </Autocomplete>
        </div>

        {/* Type filter â€” single-select ToggleButtonGroup */}
        <ToggleButtonGroup
          aria-label={t('listings.filter_by_type')}
          selectionMode="single"
          disallowEmptySelection
          isDetached
          size="sm"
          selectedKeys={new Set([selectedType])}
          onSelectionChange={(keys) => { const [k] = Array.from(keys); if (k) setSelectedType(k as typeof selectedType); }}
          className="flex flex-wrap gap-2 mt-3"
        >
          {[
            { key: 'all' as const, label: t('listings.type_all') },
            { key: 'offer' as const, label: t('listings.type_offers') },
            { key: 'request' as const, label: t('listings.type_requests') },
          ].map((item) => (
            <ToggleButton
              key={item.key}
              id={item.key}
              variant="ghost"
              className="bg-theme-elevated text-theme-muted hover:bg-theme-hover data-[selected=true]:bg-gradient-to-r data-[selected=true]:from-indigo-500 data-[selected=true]:to-purple-600 data-[selected=true]:text-white"
            >
              {item.label}
            </ToggleButton>
          ))}
        </ToggleButtonGroup>
      </GlassCard>

      {/* Loading State */}
      {isLoading && listings.length === 0 && (
        <div role="status" aria-busy="true" aria-label={t('loading', { ns: 'common' })} className="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
          {[1, 2, 3, 4, 5, 6].map((i) => (
            <CardRowsSkeleton key={i} />
          ))}
        </div>
      )}

      {/* Error State */}
      {!isLoading && loadError && (
        <GlassCard role="alert" className="p-8 text-center">
          <AlertTriangle className="w-12 h-12 text-[var(--color-warning)] mx-auto mb-4" aria-hidden="true" />
          <h2 className="text-lg font-semibold text-theme-primary mb-2">
            {t('listings.unable_to_load')}
          </h2>
          <p className="text-theme-muted mb-4">{loadError}</p>
          <Button
            className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
            startContent={<RefreshCw className="w-4 h-4" aria-hidden="true" />}
            onPress={() => { setCursor(null); loadListings(false); }}
          >
            {t('listings.try_again')}
          </Button>
        </GlassCard>
      )}

      {/* Empty State */}
      {!isLoading && !loadError && listings.length === 0 && (
        <EmptyState
          icon={<Search className="w-12 h-12" />}
          title={t('listings.no_listings_found')}
          description={t('listings.no_listings_description')}
        />
      )}

      {/* Listings Grid */}
      {!isLoading && !loadError && listings.length > 0 && (
        <>
          <div className="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
            {listings.map((listing) => (
              <motion.div
                key={`${listing.timebank.id}-${listing.id}`}
                initial={{ opacity: 0, y: 20 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.3 }}
              >
                <FederatedListingCard
                  listing={listing}
                  onViewDetails={() => {
                    setSelectedListing(listing);
                    setIsDetailOpen(true);
                  }}
                />
              </motion.div>
            ))}
          </div>

          {/* Load More */}
          {hasMore && (
            <div className="text-center pt-4">
              <Button
                variant="flat"
                className="bg-theme-elevated text-theme-primary"
                onPress={handleLoadMore}
                isLoading={isLoadingMore}
              >
                {t('listings.load_more')}
              </Button>
            </div>
          )}
        </>
      )}
      {/* Listing Detail Modal */}
      <Modal
        isOpen={isDetailOpen}
        onOpenChange={(open) => {
          if (!open) {
            setIsDetailOpen(false);
            setSelectedListing(null);
          }
        }}
        size="2xl"
        backdrop="blur"
        classNames={{
          base: 'bg-overlay border border-theme-default',
          header: 'border-b border-theme-default',
          body: 'py-4',
          footer: 'border-t border-theme-default',
        }}
      >
        <ModalContent>
          {selectedListing && (() => {
            const isOffer = selectedListing.type === 'offer';
            const isExternal = !!selectedListing.is_external;
            const authorAvatar = resolveAvatarUrl(selectedListing.author?.avatar);
            const listingImage = selectedListing.image_url
              ? resolveThumbnailUrl(selectedListing.image_url, { width: 960, height: 540 })
              : null;
            const authorName = selectedListing.author?.name || t('listings.anonymous_user');
            const canNavigateToProfile = !isExternal && selectedListing.author?.id;

            return (
              <>
                <ModalHeader className="flex items-center gap-3">
                  <div
                    className={`w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0 ${
                      isOffer ? 'bg-emerald-500/20' : 'bg-amber-500/20'
                    }`}
                  >
                    <Hand
                      className={`w-5 h-5 ${
                        isOffer
                          ? 'text-emerald-600 dark:text-emerald-400'
                          : 'text-amber-600 dark:text-amber-400'
                      }`}
                      aria-hidden="true"
                    />
                  </div>
                  <div className="min-w-0">
                    <h2 className="text-lg font-bold text-theme-primary line-clamp-2">
                      {selectedListing.title}
                    </h2>
                    <div className="flex items-center gap-2 mt-0.5 flex-wrap">
                      <Chip
                        size="sm"
                        variant="flat"
                        className={
                          isOffer
                            ? 'bg-emerald-500/20 text-emerald-600 dark:text-emerald-400'
                            : 'bg-amber-500/20 text-amber-600 dark:text-amber-400'
                        }
                      >
                        {isOffer ? t('listings.offer') : t('listings.request')}
                      </Chip>
                      {selectedListing.category_name && (
                        <Chip size="sm" variant="flat" className="bg-theme-hover text-theme-muted">
                          {selectedListing.category_name}
                        </Chip>
                      )}
                      {isExternal && (
                        <Chip size="sm" variant="flat" className="bg-indigo-500/10 text-indigo-600 dark:text-indigo-400"
                          startContent={<Globe className="w-3 h-3" aria-hidden="true" />}
                        >
                          {t('listings.external_partner')}
                        </Chip>
                      )}
                    </div>
                  </div>
                </ModalHeader>
                <ModalBody>
                  <div className="space-y-4">
                    {/* Listing image */}
                    {listingImage && (
                      <div className="w-full h-48 rounded-lg overflow-hidden bg-theme-hover">
                        <img
                          src={listingImage}
                          alt={selectedListing.title}
                          className="w-full h-full object-cover"
                          loading="lazy"
                          decoding="async"
                        />
                      </div>
                    )}

                    {/* Description */}
                    {selectedListing.description && (
                      <p className="text-theme-muted whitespace-pre-line">
                        {selectedListing.description}
                      </p>
                    )}

                    {/* Meta info */}
                    <div className="flex flex-wrap gap-4 text-sm">
                      {selectedListing.estimated_hours && (
                        <span className="flex items-center gap-1.5 text-theme-muted">
                          <Clock className="w-4 h-4" aria-hidden="true" />
                          {t('listings.hours_estimated', { hours: selectedListing.estimated_hours })}
                        </span>
                      )}
                      {selectedListing.location && (
                        <span className="flex items-center gap-1.5 text-theme-muted">
                          <MapPin className="w-4 h-4" aria-hidden="true" />
                          {selectedListing.location}
                        </span>
                      )}
                      {selectedListing.created_at && (
                        <span className="flex items-center gap-1.5 text-theme-muted">
                          <Clock className="w-4 h-4" aria-hidden="true" />
                          {new Date(selectedListing.created_at).toLocaleDateString()}
                        </span>
                      )}
                    </div>

                    {/* Author & Community */}
                    <div className="flex items-center justify-between pt-3 border-t border-theme-default">
                      {canNavigateToProfile ? (
                        <Button
                          variant="light"
                          className="flex items-center gap-3 hover:opacity-80 transition-opacity min-h-9 min-w-0 p-0 justify-start"
                          onPress={() => {
                            setIsDetailOpen(false);
                            setSelectedListing(null);
                            navigate(tenantPath(memberProfilePath(selectedListing)));
                          }}
                        >
                          <Avatar src={authorAvatar} name={authorName} size="sm" />
                          <div className="text-left">
                            <p className="text-sm font-medium text-theme-primary">{authorName}</p>
                            <p className="text-xs text-theme-subtle">{t('listings.view_profile')}</p>
                          </div>
                        </Button>
                      ) : (
                        <div className="flex items-center gap-3">
                          <Avatar src={authorAvatar} name={authorName} size="sm" />
                          <div className="text-left">
                            <p className="text-sm font-medium text-theme-primary">{authorName}</p>
                            <p className="text-xs text-theme-subtle">{selectedListing.timebank.name}</p>
                          </div>
                        </div>
                      )}
                      <Chip
                        size="sm"
                        variant="flat"
                        className="bg-indigo-500/10 text-indigo-600 dark:text-indigo-400"
                        startContent={<Globe className="w-3 h-3" aria-hidden="true" />}
                      >
                        {selectedListing.timebank.name}
                      </Chip>
                    </div>
                  </div>
                </ModalBody>
                <ModalFooter className="flex gap-2">
                  {isAuthenticated && selectedListing.author?.id && (
                    <>
                      {!isExternal && (
                        <Button
                          variant="flat"
                          className="bg-theme-elevated text-theme-primary"
                          startContent={<User className="w-4 h-4" aria-hidden="true" />}
                          onPress={() => {
                            setIsDetailOpen(false);
                            setSelectedListing(null);
                            navigate(tenantPath(memberProfilePath(selectedListing)));
                          }}
                        >
                          {t('listings.view_profile')}
                        </Button>
                      )}
                      <Button
                        className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                        startContent={<MessageSquare className="w-4 h-4" aria-hidden="true" />}
                        onPress={() => {
                          setIsDetailOpen(false);
                          setSelectedListing(null);
                          const nameParam = authorName ? `&name=${encodeURIComponent(authorName)}` : '';
                          navigate(
                            tenantPath(`/federation/messages?compose=true&to_user=${selectedListing.author!.id}&to_tenant=${selectedListing.timebank.id}${nameParam}`)
                          );
                        }}
                      >
                        {t('listings.contact_author')}
                      </Button>
                    </>
                  )}
                  <Button
                    variant="flat"
                    className="bg-theme-elevated text-theme-muted"
                    onPress={() => {
                      setIsDetailOpen(false);
                      setSelectedListing(null);
                    }}
                  >
                    {t('listings.close')}
                  </Button>
                </ModalFooter>
              </>
            );
          })()}
        </ModalContent>
      </Modal>
    </div>
  );
}

// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€
// Federated Listing Card
// â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€â”€

interface FederatedListingCardProps {
  listing: FederatedListing;
  onViewDetails: () => void;
}

function FederatedListingCard({ listing, onViewDetails }: FederatedListingCardProps) {
  const { t } = useTranslation('federation');
  const isOffer = listing.type === 'offer';
  const avatarSrc = resolveAvatarUrl(listing.author?.avatar);
  const imageSrc = listing.image_url
    ? resolveThumbnailUrl(listing.image_url, { width: 640, height: 360 })
    : null;

  return (
    <GlassCard
      className="p-5 md:hover:scale-[1.02] transition-transform h-full flex flex-col cursor-pointer"
      onClick={onViewDetails}
    >
      {/* Image or Type Icon */}
      {imageSrc ? (
        <div className="w-full h-36 rounded-lg overflow-hidden mb-3 bg-theme-hover">
          <img
            src={imageSrc}
            alt={listing.title}
            className="w-full h-full object-cover"
            loading="lazy"
            decoding="async"
          />
        </div>
      ) : (
        <div
          className={`w-full h-20 rounded-lg mb-3 flex items-center justify-center ${
            isOffer
              ? 'bg-emerald-500/10'
              : 'bg-amber-500/10'
          }`}
        >
          <Hand
            className={`w-8 h-8 ${
              isOffer
                ? 'text-emerald-600 dark:text-emerald-400'
                : 'text-amber-600 dark:text-amber-400'
            }`}
            aria-hidden="true"
          />
        </div>
      )}

      {/* Badges */}
      <div className="flex items-center gap-2 mb-2 flex-wrap">
        <Chip
          size="sm"
          variant="flat"
          className={
            isOffer
              ? 'bg-emerald-500/20 text-emerald-600 dark:text-emerald-400'
              : 'bg-amber-500/20 text-amber-600 dark:text-amber-400'
          }
        >
          {isOffer ? t('listings.offer') : t('listings.request')}
        </Chip>
        {listing.category_name && (
          <Chip size="sm" variant="flat" className="bg-theme-hover text-theme-muted">
            {listing.category_name}
          </Chip>
        )}
        {listing.is_external && (
          <Chip
            size="sm"
            variant="flat"
            className="bg-indigo-500/10 text-indigo-600 dark:text-indigo-400"
            startContent={<Globe className="w-3 h-3" />}
          >
            {t('external')}
          </Chip>
        )}
      </div>

      {/* Title & Description */}
      <h3 className="font-semibold text-theme-primary text-lg mb-1 line-clamp-2">
        {listing.title}
      </h3>
      <p className="text-theme-muted text-sm line-clamp-2 mb-3 flex-1">
        {listing.description}
      </p>

      {/* Meta */}
      <div className="flex flex-wrap items-center gap-3 text-xs text-theme-subtle mb-3">
        {listing.estimated_hours && (
          <span className="flex items-center gap-1">
            <Clock className="w-3 h-3" aria-hidden="true" />
            {t('listings.hours_estimated', { hours: listing.estimated_hours })}
          </span>
        )}
        {listing.location && (
          <span className="flex items-center gap-1">
            <MapPin className="w-3 h-3" aria-hidden="true" />
            <span className="truncate max-w-[100px]">{listing.location}</span>
          </span>
        )}
      </div>

      {/* Footer: Author + Community */}
      <div className="flex items-center justify-between pt-3 border-t border-theme-default">
        <div className="flex items-center gap-2 min-w-0">
          <Avatar
            src={avatarSrc}
            name={listing.author?.name || t('listings.anonymous_user')}
            size="sm"
            className="flex-shrink-0 w-6 h-6"
          />
          <span className="text-sm text-theme-subtle truncate">
            {listing.author?.name || t('listings.anonymous_user')}
          </span>
        </div>
        <Chip
          size="sm"
          variant="flat"
          className="bg-indigo-500/10 text-indigo-600 dark:text-indigo-400"
          startContent={<Globe className="w-3 h-3" aria-hidden="true" />}
        >
          {listing.timebank.name}
        </Chip>
      </div>
    </GlassCard>
  );
}

export default FederationListingsPage;
