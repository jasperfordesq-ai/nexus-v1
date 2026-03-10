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
}

interface DirectoryResponse {
  communities: Community[];
  regions: string[];
  categories: string[];
}

const partnershipStatusConfig: Record<string, { color: 'success' | 'warning' | 'danger' | 'default'; icon: typeof CheckCircle; label: string }> = {
  active: { color: 'success', icon: CheckCircle, label: 'Partner' },
  pending: { color: 'warning', icon: Clock, label: 'Pending' },
  rejected: { color: 'danger', icon: XCircle, label: 'Rejected' },
  terminated: { color: 'default', icon: XCircle, label: 'Terminated' },
  suspended: { color: 'danger', icon: XCircle, label: 'Suspended' },
};

export function PartnerDirectory() {
  usePageTitle('Admin - Partner Directory');
  const toast = useToast();

  const [communities, setCommunities] = useState<Community[]>([]);
  const [regions, setRegions] = useState<string[]>([]);
  const [categories, setCategories] = useState<string[]>([]);
  const [loading, setLoading] = useState(true);

  // Filters
  const [search, setSearch] = useState('');
  const [regionFilter, setRegionFilter] = useState('');
  const [categoryFilter, setCategoryFilter] = useState('');
  const [hidePartnered, setHidePartnered] = useState(false);

  // Partnership request modal
  const [requestTarget, setRequestTarget] = useState<Community | null>(null);
  const [requestNotes, setRequestNotes] = useState('');
  const [requestLoading, setRequestLoading] = useState(false);

  const loadData = useCallback(async (params?: { search?: string; region?: string; category?: string; exclude_partnered?: boolean }) => {
    setLoading(true);
    try {
      const res = await adminFederation.getDirectory(params);
      if (res.success && res.data) {
        const payload = res.data as unknown as DirectoryResponse;
        if (payload && typeof payload === 'object' && 'communities' in payload) {
          setCommunities(payload.communities || []);
          setRegions(payload.regions || []);
          setCategories(payload.categories || []);
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

  useEffect(() => { loadData(); }, [loadData]);

  // Debounced search
  useEffect(() => {
    const timer = setTimeout(() => {
      loadData({
        search: search || undefined,
        region: regionFilter || undefined,
        category: categoryFilter || undefined,
        exclude_partnered: hidePartnered || undefined,
      });
    }, 300);
    return () => clearTimeout(timer);
  }, [search, regionFilter, categoryFilter, hidePartnered, loadData]);

  const handleRequestPartnership = async () => {
    if (!requestTarget) return;
    setRequestLoading(true);
    try {
      const res = await adminFederation.requestPartnership(requestTarget.id, requestNotes || undefined);
      if (res.success) {
        toast.success(`Partnership request sent to "${requestTarget.name}"`);
        setRequestTarget(null);
        setRequestNotes('');
        loadData({ search, region: regionFilter, category: categoryFilter, exclude_partnered: hidePartnered });
      } else {
        const errorData = res.data as { error?: string } | undefined;
        toast.error(errorData?.error || 'Failed to send partnership request');
      }
    } catch {
      toast.error('Failed to send partnership request');
    } finally {
      setRequestLoading(false);
    }
  };

  const parseCategories = (cats?: string): string[] => {
    if (!cats) return [];
    if (cats.startsWith('[')) {
      try { return JSON.parse(cats); } catch { return []; }
    }
    return cats.split(',').map(c => c.trim()).filter(Boolean);
  };

  const enabledFeatures = (community: Community): string[] => {
    const features: string[] = [];
    if (community.profiles_enabled) features.push('Profiles');
    if (community.listings_enabled) features.push('Listings');
    if (community.messaging_enabled) features.push('Messages');
    if (community.transactions_enabled) features.push('Transactions');
    if (community.events_enabled) features.push('Events');
    if (community.groups_enabled) features.push('Groups');
    return features;
  };

  const activeFilters = useMemo(() => {
    let count = 0;
    if (search) count++;
    if (regionFilter) count++;
    if (categoryFilter) count++;
    if (hidePartnered) count++;
    return count;
  }, [search, regionFilter, categoryFilter, hidePartnered]);

  const clearFilters = () => {
    setSearch('');
    setRegionFilter('');
    setCategoryFilter('');
    setHidePartnered(false);
  };

  return (
    <div>
      <PageHeader
        title="Partner Directory"
        description="Discover and connect with communities in the federation network"
        actions={
          <Button
            variant="flat"
            startContent={<RefreshCw size={16} />}
            onPress={() => loadData({ search, region: regionFilter, category: categoryFilter, exclude_partnered: hidePartnered })}
            isLoading={loading}
          >
            Refresh
          </Button>
        }
      />

      {/* Filters */}
      <Card className="mb-6">
        <CardBody className="gap-4">
          <div className="flex flex-col md:flex-row gap-3">
            <Input
              className="flex-1"
              placeholder="Search communities by name, description, or region..."
              aria-label="Search partner communities"
              startContent={<Search size={16} className="text-default-400" />}
              value={search}
              onValueChange={setSearch}
              variant="bordered"
              size="sm"
            />
            {regions.length > 0 && (
              <Select
                className="w-full md:w-48"
                placeholder="All Regions"
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
                placeholder="All Categories"
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
                Hide partnered
              </Button>
              {activeFilters > 0 && (
                <Button variant="light" size="sm" onPress={clearFilters}>
                  Clear filters ({activeFilters})
                </Button>
              )}
            </div>
            <span className="text-sm text-default-400">
              {loading ? 'Loading...' : `${communities.length} communit${communities.length === 1 ? 'y' : 'ies'} found`}
            </span>
          </div>
        </CardBody>
      </Card>

      {/* Loading */}
      {loading && communities.length === 0 && (
        <div className="flex justify-center py-12">
          <Spinner size="lg" label="Loading directory..." />
        </div>
      )}

      {/* Empty state */}
      {!loading && communities.length === 0 && (
        <EmptyState
          icon={Globe}
          title="No Communities Found"
          description={activeFilters > 0
            ? 'No communities match your current filters. Try adjusting your search criteria.'
            : 'The federation directory is empty. Ensure federation is enabled and communities are discoverable.'
          }
        />
      )}

      {/* Community cards grid */}
      {communities.length > 0 && (
        <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
          {communities.map((community) => {
            const cats = parseCategories(community.categories);
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
                      {statusConf.label}
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
                        <Users size={12} /> {Number(community.member_count).toLocaleString()} members
                      </span>
                    )}
                    {community.region && (
                      <span className="flex items-center gap-1">
                        <MapPin size={12} /> {community.region}
                      </span>
                    )}
                    {community.contact_email && (
                      <span className="flex items-center gap-1">
                        <Mail size={12} /> {community.contact_name || 'Contact'}
                      </span>
                    )}
                  </div>

                  {cats.length > 0 && (
                    <div className="flex flex-wrap gap-1">
                      {cats.slice(0, 4).map((cat) => (
                        <Chip key={cat} size="sm" variant="flat" startContent={<Tag size={10} />}>
                          {cat}
                        </Chip>
                      ))}
                      {cats.length > 4 && (
                        <Chip size="sm" variant="flat" color="default">
                          +{cats.length - 4} more
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
                      Visit
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
                      Request Partnership
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
                      Active Partner
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
                      Request Pending
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
            Request Partnership
          </ModalHeader>
          <ModalBody>
            {requestTarget && (
              <div className="space-y-4">
                <div className="flex items-center gap-3 p-3 rounded-lg bg-default-100">
                  {requestTarget.logo_url ? (
                    <img src={requestTarget.logo_url} alt="" className="w-10 h-10 rounded-lg object-cover" loading="lazy" />
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
                  Send a partnership request to this community. Their admin will review and approve or decline.
                </p>
                <Textarea
                  label="Message (optional)"
                  placeholder="Introduce your community and explain why you'd like to partner..."
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
              Cancel
            </Button>
            <Button
              color="primary"
              startContent={<Send size={14} />}
              onPress={handleRequestPartnership}
              isLoading={requestLoading}
            >
              Send Request
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}

export default PartnerDirectory;
