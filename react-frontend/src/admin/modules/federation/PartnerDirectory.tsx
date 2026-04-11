// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Partner Directory
 * Browse discoverable communities in the federation network.
 * Supports search, region/category filters, partnership requests.
 */

import { useState, useCallback, useEffect, useMemo } from 'react';
import {
  Button,
  Card,
  CardBody,
  CardFooter,
  Chip,
  Input,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Select,
  SelectItem,
  Textarea,
  Spinner,
} from '@heroui/react';
import {
  Globe,
  RefreshCw,
  Search,
  Users,
  Handshake,
  MapPin,
  Tag,
  Send,
  CheckCircle,
  Clock,
  XCircle,
  Filter,
  Mail,
  ExternalLink,
} from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { adminFederation } from '../../api/adminApi';
import { PageHeader, EmptyState } from '../../components';

import { useTranslation } from 'react-i18next';
interface CommunityTopic {
  name: string;
  slug: string;
  icon: string;
  category: string;
  is_primary: boolean;
}

interface Community {
  id: number;
  name: string;
  slug: string;
  domain?: string;
  logo_url?: string;
  description?: string;
  categories?: string;
  region?: string;
  contact_email?: string;
  contact_name?: string;
  member_count?: number | null;
  federation_member_count_public?: number;
  profiles_enabled?: number;
  listings_enabled?: number;
  messaging_enabled?: number;
  transactions_enabled?: number;
  events_enabled?: number;
  groups_enabled?: number;
  partnership_status?: string | null;
  partnership_id?: number | null;
  topics?: CommunityTopic[];
}

interface TopicFilter {
  id: number;
  name: string;
  slug: string;
  icon: string;
  category: string;
  tenant_count: number;
}

interface DirectoryResponse {
  communities: Community[];
  regions: string[];
  categories: string[];
  topics: TopicFilter[];
}

const partnershipStatusConfig: Record<string, { color: 'success' | 'warning' | 'danger' | 'default'; icon: typeof CheckCircle; label: string }> = {
  active: { color: 'success', icon: CheckCircle, label: 'federation.status_partner' },
  pending: { color: 'warning', icon: Clock, label: 'federation.status_pending' },
  rejected: { color: 'danger', icon: XCircle, label: 'federation.status_rejected' },
  terminated: { color: 'default', icon: XCircle, label: 'federation.status_terminated' },
  suspended: { color: 'danger', icon: XCircle, label: 'federation.status_suspended' },
};

export function PartnerDirectory() {
  const { t } = useTranslation('admin');
  usePageTitle(t('federation.page_title'));
  const toast = useToast();

  const [communities, setCommunities] = useState<Community[]>([]);
  const [regions, setRegions] = useState<string[]>([]);
  const [categories, setCategories] = useState<string[]>([]);
  const [topics, setTopics] = useState<TopicFilter[]>([]);
  const [loading, setLoading] = useState(true);

  // Filters
  const [search, setSearch] = useState('');
  const [regionFilter, setRegionFilter] = useState('');
  const [categoryFilter, setCategoryFilter] = useState('');
  const [topicFilter, setTopicFilter] = useState('');
  const [hidePartnered, setHidePartnered] = useState(false);

  // Partnership request modal
  const [requestTarget, setRequestTarget] = useState<Community | null>(null);
  const [requestNotes, setRequestNotes] = useState('');
  const [requestLoading, setRequestLoading] = useState(false);

  const loadData = useCallback(async (params?: { search?: string; region?: string; category?: string; topic?: string; exclude_partnered?: boolean }) => {
    setLoading(true);
    try {
      const res = await adminFederation.getDirectory(params);
      if (res.success && res.data) {
        const payload = res.data as unknown as DirectoryResponse;
        if (payload && typeof payload === 'object' && 'communities' in payload) {
          setCommunities(payload.communities || []);
          setRegions(payload.regions || []);
          setCategories(payload.categories || []);
          setTopics(payload.topics || []);
        } else if (Array.isArray(payload)) {
          // Fallback for old response format
          setCommunities(payload as unknown as Community[]);
        }
      }
    } catch {
      setCommunities([]);
    }
    setLoading(false);
  }, []);

  // Debounced search — also handles initial load on mount (300ms delay)
  useEffect(() => {
    const timer = setTimeout(() => {
      loadData({
        search: search || undefined,
        region: regionFilter || undefined,
        category: categoryFilter || undefined,
        topic: topicFilter || undefined,
        exclude_partnered: hidePartnered || undefined,
      });
    }, 300);
    return () => clearTimeout(timer);
  }, [search, regionFilter, categoryFilter, topicFilter, hidePartnered, loadData]);

  const handleRequestPartnership = async () => {
    if (!requestTarget) return;
    setRequestLoading(true);
    try {
      const res = await adminFederation.requestPartnership(requestTarget.id, requestNotes || undefined);
      if (res.success) {
        toast.success(t('federation.partnership_request_sent', { name: requestTarget.name }));
        setRequestTarget(null);
        setRequestNotes('');
        loadData({ search, region: regionFilter, category: categoryFilter, topic: topicFilter, exclude_partnered: hidePartnered });
      } else {
        const errorData = res.data as { error?: string } | undefined;
        toast.error(errorData?.error || t('federation.failed_to_send_partnership_request'));
      }
    } catch {
      toast.error(t('federation.failed_to_send_partnership_request'));
    } finally {
      setRequestLoading(false);
    }
  };

  const enabledFeatures = (community: Community): string[] => {
    const features: string[] = [];
    if (community.profiles_enabled) features.push(t('federation.feature_profiles'));
    if (community.listings_enabled) features.push(t('federation.feature_listings'));
    if (community.messaging_enabled) features.push(t('federation.feature_messages'));
    if (community.transactions_enabled) features.push(t('federation.feature_transactions'));
    if (community.events_enabled) features.push(t('federation.feature_events'));
    if (community.groups_enabled) features.push(t('federation.feature_groups'));
    return features;
  };

  const activeFilters = useMemo(() => {
    let count = 0;
    if (search) count++;
    if (regionFilter) count++;
    if (categoryFilter) count++;
    if (topicFilter) count++;
    if (hidePartnered) count++;
    return count;
  }, [search, regionFilter, categoryFilter, topicFilter, hidePartnered]);

  const clearFilters = () => {
    setSearch('');
    setRegionFilter('');
    setCategoryFilter('');
    setTopicFilter('');
    setHidePartnered(false);
  };

  return (
    <div>
      <PageHeader
        title={t('federation.partner_directory_title')}
        description={t('federation.partner_directory_desc')}
        actions={
          <Button
            variant="flat"
            startContent={<RefreshCw size={16} />}
            onPress={() => loadData({ search, region: regionFilter, category: categoryFilter, topic: topicFilter, exclude_partnered: hidePartnered })}
            isLoading={loading}
          >
            {t('federation.refresh')}
          </Button>
        }
      />

      {/* Filters */}
      <Card className="mb-6">
        <CardBody className="gap-4">
          <div className="flex flex-col md:flex-row gap-3">
            <Input
              className="flex-1"
              placeholder={t('federation.search_communities_placeholder')}
              aria-label={t('federation.label_search_partner_communities')}
              startContent={<Search size={16} className="text-default-400" />}
              value={search}
              onValueChange={setSearch}
              variant="bordered"
              size="sm"
            />
            {regions.length > 0 && (
              <Select
                className="w-full md:w-48"
                placeholder={t('federation.placeholder_all_regions')}
                size="sm"
                variant="bordered"
                selectedKeys={regionFilter ? [regionFilter] : []}
                onSelectionChange={(keys) => {
                  const arr = Array.from(keys);
                  setRegionFilter(arr.length > 0 ? String(arr[0]) : '');
                }}
              >
                {regions.map((r) => (
                  <SelectItem key={r}>{r}</SelectItem>
                ))}
              </Select>
            )}
            {categories.length > 0 && (
              <Select
                className="w-full md:w-48"
                placeholder={t('federation.placeholder_all_categories')}
                size="sm"
                variant="bordered"
                selectedKeys={categoryFilter ? [categoryFilter] : []}
                onSelectionChange={(keys) => {
                  const arr = Array.from(keys);
                  setCategoryFilter(arr.length > 0 ? String(arr[0]) : '');
                }}
              >
                {categories.map((c) => (
                  <SelectItem key={c}>{c}</SelectItem>
                ))}
              </Select>
            )}
            {topics.length > 0 && (
              <Select
                className="w-full md:w-48"
                placeholder={t('federation.placeholder_all_topics', 'All Topics')}
                size="sm"
                variant="bordered"
                selectedKeys={topicFilter ? [topicFilter] : []}
                onSelectionChange={(keys) => {
                  const arr = Array.from(keys);
                  setTopicFilter(arr.length > 0 ? String(arr[0]) : '');
                }}
                startContent={<Tag size={14} className="text-default-400" />}
              >
                {topics.map((tp) => (
                  <SelectItem key={tp.slug} textValue={tp.name}>
                    {tp.name} ({tp.tenant_count})
                  </SelectItem>
                ))}
              </Select>
            )}
          </div>
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-3">
              <Button
                variant={hidePartnered ? 'solid' : 'flat'}
                color={hidePartnered ? 'primary' : 'default'}
                size="sm"
                startContent={<Filter size={14} />}
                onPress={() => setHidePartnered(!hidePartnered)}
              >
                {t('federation.hide_partnered')}
              </Button>
              {activeFilters > 0 && (
                <Button variant="light" size="sm" onPress={clearFilters}>
                  {t('federation.clear_filters', { count: activeFilters })}
                </Button>
              )}
            </div>
            <span className="text-sm text-default-400">
              {loading ? t('federation.loading') : t('federation.communities_found', { count: communities.length })}
            </span>
          </div>
        </CardBody>
      </Card>

      {/* Loading */}
      {loading && communities.length === 0 && (
        <div className="flex justify-center py-12">
          <Spinner size="lg" label={t('federation.loading_directory')} />
        </div>
      )}

      {/* Empty state */}
      {!loading && communities.length === 0 && (
        <EmptyState
          icon={Globe}
          title={t('federation.no_communities_found')}
          description={activeFilters > 0
            ? t('federation.no_communities_match_filters')
            : t('federation.directory_empty')
          }
        />
      )}

      {/* Community cards grid */}
      {communities.length > 0 && (
        <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
          {communities.map((community) => {
            const communityTopics = community.topics || [];
            const features = enabledFeatures(community);
            const status = community.partnership_status;
            const statusConf = status ? partnershipStatusConfig[status] : null;

            return (
              <Card key={community.id} className="relative">
                {statusConf && (
                  <div className="absolute top-3 right-3 z-10">
                    <Chip
                      size="sm"
                      variant="flat"
                      color={statusConf.color}
                      startContent={<statusConf.icon size={12} />}
                    >
                      {t(statusConf.label)}
                    </Chip>
                  </div>
                )}

                <CardBody className="gap-3 pb-2">
                  <div className="flex items-start gap-3">
                    {community.logo_url ? (
                      <img
                        src={community.logo_url}
                        alt={community.name}
                        className="w-12 h-12 rounded-lg object-cover shrink-0"
                        loading="lazy"
                      />
                    ) : (
                      <div className="w-12 h-12 rounded-lg bg-primary/10 flex items-center justify-center shrink-0">
                        <Globe size={20} className="text-primary" />
                      </div>
                    )}
                    <div className="min-w-0 flex-1">
                      <h3 className="text-base font-semibold text-foreground truncate pr-16">
                        {community.name}
                      </h3>
                      <p className="text-xs text-default-400">/{community.slug}</p>
                    </div>
                  </div>

                  {community.description && (
                    <p className="text-sm text-default-500 line-clamp-2">
                      {community.description}
                    </p>
                  )}

                  <div className="flex flex-wrap gap-x-4 gap-y-1 text-xs text-default-400">
                    {community.member_count != null && (
                      <span className="flex items-center gap-1">
                        <Users size={12} /> {t('federation.members_count', { count: Number(community.member_count) })}
                      </span>
                    )}
                    {community.region && (
                      <span className="flex items-center gap-1">
                        <MapPin size={12} /> {community.region}
                      </span>
                    )}
                    {community.contact_email && (
                      <span className="flex items-center gap-1">
                        <Mail size={12} /> {community.contact_name || t('federation.contact')}
                      </span>
                    )}
                  </div>

                  {communityTopics.length > 0 && (
                    <div className="flex flex-wrap gap-1">
                      {communityTopics.slice(0, 5).map((topic) => (
                        <Chip
                          key={topic.slug}
                          size="sm"
                          variant={topic.is_primary ? 'solid' : 'flat'}
                          color={topic.is_primary ? 'warning' : 'default'}
                          startContent={<Tag size={10} />}
                        >
                          {topic.name}
                        </Chip>
                      ))}
                      {communityTopics.length > 5 && (
                        <Chip size="sm" variant="flat" color="default">
                          {t('federation.more_count', { count: communityTopics.length - 5 })}
                        </Chip>
                      )}
                    </div>
                  )}

                  {features.length > 0 && (
                    <div className="flex flex-wrap gap-1">
                      {features.map((f) => (
                        <Chip key={f} size="sm" variant="dot" color="success" className="text-xs">
                          {f}
                        </Chip>
                      ))}
                    </div>
                  )}
                </CardBody>

                <CardFooter className="pt-0 gap-2">
                  {community.domain && (
                    <Button
                      as="a"
                      href={`https://${community.domain}`}
                      target="_blank"
                      rel="noopener noreferrer"
                      variant="flat"
                      size="sm"
                      startContent={<ExternalLink size={14} />}
                    >
                      {t('federation.visit')}
                    </Button>
                  )}

                  {!status && (
                    <Button
                      color="primary"
                      variant="flat"
                      size="sm"
                      startContent={<Handshake size={14} />}
                      onPress={() => setRequestTarget(community)}
                    >
                      {t('federation.request_partnership')}
                    </Button>
                  )}
                  {status === 'active' && (
                    <Button
                      color="success"
                      variant="flat"
                      size="sm"
                      isDisabled
                      startContent={<CheckCircle size={14} />}
                    >
                      {t('federation.active_partner')}
                    </Button>
                  )}
                  {status === 'pending' && (
                    <Button
                      color="warning"
                      variant="flat"
                      size="sm"
                      isDisabled
                      startContent={<Clock size={14} />}
                    >
                      {t('federation.request_pending')}
                    </Button>
                  )}
                </CardFooter>
              </Card>
            );
          })}
        </div>
      )}

      {/* Partnership request modal */}
      <Modal isOpen={!!requestTarget} onClose={() => { setRequestTarget(null); setRequestNotes(''); }}>
        <ModalContent>
          <ModalHeader className="flex items-center gap-2">
            <Handshake size={20} />
            {t('federation.request_partnership')}
          </ModalHeader>
          <ModalBody>
            {requestTarget && (
              <div className="space-y-4">
                <div className="flex items-center gap-3 p-3 rounded-lg bg-default-100">
                  {requestTarget.logo_url ? (
                    <img src={requestTarget.logo_url} alt={`${requestTarget.name || 'Partner'} logo`} className="w-10 h-10 rounded-lg object-cover" loading="lazy" />
                  ) : (
                    <div className="w-10 h-10 rounded-lg bg-primary/10 flex items-center justify-center">
                      <Globe size={18} className="text-primary" />
                    </div>
                  )}
                  <div>
                    <p className="font-medium">{requestTarget.name}</p>
                    {requestTarget.region && (
                      <p className="text-xs text-default-400">{requestTarget.region}</p>
                    )}
                  </div>
                </div>
                <p className="text-sm text-default-500">
                  {t('federation.partnership_request_description')}
                </p>
                <Textarea
                  label={t('federation.message_optional')}
                  placeholder={t('federation.partnership_message_placeholder')}
                  value={requestNotes}
                  onValueChange={setRequestNotes}
                  variant="bordered"
                  maxLength={1000}
                  minRows={3}
                />
              </div>
            )}
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={() => { setRequestTarget(null); setRequestNotes(''); }}>
              {t('federation.cancel')}
            </Button>
            <Button
              color="primary"
              startContent={<Send size={14} />}
              onPress={handleRequestPartnership}
              isLoading={requestLoading}
            >
              {t('federation.send_request')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}

export default PartnerDirectory;
