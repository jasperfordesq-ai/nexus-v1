// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Tenant Detail (Read-Only) Page
 * Displays full tenant information matching the PHP tenants/show.php view.
 */

import { useEffect, useState, useCallback, useRef } from 'react';
import { useParams, Link, useNavigate } from 'react-router-dom';
import {
  Card,
  CardBody,
  CardHeader,
  Button,
  Chip,
  Divider,
  Avatar,
  Spinner,
  Input,
  Select,
  SelectItem,
  Switch,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  useDisclosure,
} from '@heroui/react';
import {
  Building2,
  ArrowLeft,
  Edit,
  Globe,
  Languages,
  MapPin,
  Search,
  Users,
  ExternalLink,
  Network,
  CheckCircle2,
  XCircle,
  Facebook,
  Twitter,
  Instagram,
  Linkedin,
  Youtube,
  UserPlus,
  Plus,
  MoveRight,
  Power,
  AlertTriangle,
} from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { adminSuper } from '../../api/adminApi';
import { PageHeader } from '../../components';
import type { SuperAdminTenantDetail } from '../../api/types';

const FEATURE_OPTIONS = [
  'events', 'groups', 'gamification', 'goals', 'blog', 'resources',
  'volunteering', 'exchange_workflow', 'federation', 'organisations',
  'listings', 'wallet', 'messages', 'dashboard', 'feed',
];

const LANGUAGE_LABELS: Record<string, string> = {
  en: 'English',
  ga: 'Gaeilge',
  de: 'Deutsch',
  fr: 'Français',
  it: 'Italiano',
  pt: 'Português',
  es: 'Español',
};

export function TenantShow() {
  const { id } = useParams<{ id: string }>();
  usePageTitle('Super Admin - Tenant Details');
  const { tenantPath } = useTenant();
  const toast = useToast();
  const navigate = useNavigate();

  const [loading, setLoading] = useState(true);
  const [tenant, setTenant] = useState<SuperAdminTenantDetail | null>(null);

  // Add Administrator form
  const [showAddAdmin, setShowAddAdmin] = useState(false);
  const [adminForm, setAdminForm] = useState({
    first_name: '',
    last_name: '',
    email: '',
    password: '',
    role: 'admin',
  });
  const [addingAdmin, setAddingAdmin] = useState(false);
  const [actionLoading, setActionLoading] = useState(false);

  // Guard ref to prevent Switch onValueChange re-entry (HeroUI fires on programmatic isSelected changes)
  const togglingHub = useRef(false);

  // Move Tenant modal
  const moveModal = useDisclosure();
  const [moveParentId, setMoveParentId] = useState('');
  const [hubTenants, setHubTenants] = useState<{ id: number; name: string }[]>([]);

  // Load hub tenants for move dropdown
  const loadHubTenants = useCallback(async () => {
    try {
      const res = await adminSuper.listTenants({ hub: true });
      if (res.success && res.data) {
        setHubTenants(Array.isArray(res.data) ? res.data : []);
      }
    } catch { /* non-critical */ }
  }, []);

  const handleMove = async () => {
    if (!tenant || !moveParentId) return;
    setActionLoading(true);
    try {
      const res = await adminSuper.moveTenant(tenant.id, Number(moveParentId));
      if (res.success) {
        toast.success('Tenant moved successfully');
        moveModal.onClose();
        loadTenant();
      } else {
        toast.error(res.error || 'Failed to move tenant');
      }
    } catch { toast.error('An error occurred'); }
    setActionLoading(false);
  };

  const handleToggleActive = async () => {
    if (!tenant) return;
    setActionLoading(true);
    try {
      let res;
      if (tenant.is_active) {
        res = await adminSuper.deleteTenant(tenant.id);
      } else {
        res = await adminSuper.reactivateTenant(tenant.id);
      }
      if (res.success) {
        const newActive = !tenant.is_active;
        toast.success(newActive ? 'Tenant reactivated' : 'Tenant deactivated');
        setTenant((prev) => prev ? { ...prev, is_active: newActive } : prev);
      } else {
        toast.error(res.error || 'Operation failed');
      }
    } catch { toast.error('An error occurred'); }
    setActionLoading(false);
  };

  const handleToggleHub = async () => {
    if (!tenant || togglingHub.current) return;
    togglingHub.current = true;
    setActionLoading(true);
    const newValue = !tenant.allows_subtenants;
    try {
      const res = await adminSuper.toggleHub(tenant.id, newValue);
      if (res.success) {
        toast.success(newValue ? 'Hub enabled' : 'Hub disabled');
        // Optimistic update — avoids refetch which re-triggers Switch onValueChange
        setTenant((prev) => prev ? { ...prev, allows_subtenants: newValue } : prev);
      } else {
        toast.error(res.error || 'Failed to toggle hub');
      }
    } catch { toast.error('An error occurred'); }
    setActionLoading(false);
    // Release guard after a tick so the Switch settles
    requestAnimationFrame(() => { togglingHub.current = false; });
  };

  const loadTenant = useCallback(async () => {
    if (!id) return;
    setLoading(true);
    try {
      const res = await adminSuper.getTenant(Number(id));
      if (res.success && res.data) {
        setTenant(res.data as SuperAdminTenantDetail);
      } else {
        toast.error(`Tenant: ${res.error || 'Failed to load tenant details'}`);
      }
    } catch (err) {
      toast.error(`Tenant error: ${err instanceof Error ? err.message : 'Unknown error'}`);
    }
    setLoading(false);
  }, [id, toast]);

  const handleAddAdmin = async () => {
    if (!tenant) return;
    if (!adminForm.first_name.trim() || !adminForm.email.trim() || !adminForm.password.trim()) {
      toast.error('First name, email, and password are required');
      return;
    }
    setAddingAdmin(true);
    try {
      const res = await adminSuper.createUser({
        tenant_id: tenant.id,
        first_name: adminForm.first_name.trim(),
        last_name: adminForm.last_name.trim(),
        email: adminForm.email.trim(),
        password: adminForm.password,
        role: adminForm.role,
      });
      if (res.success) {
        toast.success('Administrator added successfully');
        setShowAddAdmin(false);
        setAdminForm({ first_name: '', last_name: '', email: '', password: '', role: 'admin' });
        loadTenant(); // Refresh to show new admin
      } else {
        toast.error(res.error || 'Failed to add administrator');
      }
    } catch (err: unknown) {
      const message = err instanceof Error ? err.message : 'Failed to add administrator';
      toast.error(message);
    }
    setAddingAdmin(false);
  };

  useEffect(() => {
    loadTenant();
  }, [loadTenant]);

  if (loading) {
    return (
      <div className="flex items-center justify-center py-20">
        <Spinner size="lg" />
      </div>
    );
  }

  if (!tenant) {
    return (
      <div className="py-20 text-center">
        <p className="text-default-500">Tenant not found.</p>
        <Button
          variant="flat"
          className="mt-4"
          startContent={<ArrowLeft size={16} />}
          onPress={() => navigate(tenantPath('/admin/super/tenants'))}
        >
          Back to Tenants
        </Button>
      </div>
    );
  }

  const socialLinks = [
    { key: 'facebook', label: 'Facebook', url: tenant.social_facebook, icon: Facebook },
    { key: 'twitter', label: 'Twitter / X', url: tenant.social_twitter, icon: Twitter },
    { key: 'instagram', label: 'Instagram', url: tenant.social_instagram, icon: Instagram },
    { key: 'linkedin', label: 'LinkedIn', url: tenant.social_linkedin, icon: Linkedin },
    { key: 'youtube', label: 'YouTube', url: tenant.social_youtube, icon: Youtube },
  ];

  const hasSocialLinks = socialLinks.some((s) => s.url);

  return (
    <div>
      <PageHeader
        title={tenant.name}
        description={tenant.tagline || `Tenant ID: ${tenant.id}`}
        actions={
          <div className="flex items-center gap-2">
            <Button
              variant="flat"
              startContent={<ArrowLeft size={16} />}
              onPress={() => navigate(tenantPath('/admin/super/tenants'))}
            >
              Back
            </Button>
            <Button
              color="primary"
              startContent={<Edit size={16} />}
              onPress={() => navigate(tenantPath(`/admin/super/tenants/${tenant.id}/edit`))}
            >
              Edit
            </Button>
          </div>
        }
      />

      {/* Breadcrumb trail */}
      {tenant.breadcrumb && tenant.breadcrumb.length > 0 && (
        <div className="mb-4 flex items-center gap-1 text-sm text-default-500">
          {tenant.breadcrumb.map((crumb, i) => (
            <span key={crumb.id} className="flex items-center gap-1">
              {i > 0 && <span className="mx-1">/</span>}
              {crumb.id === tenant.id ? (
                <span className="font-medium text-foreground">{crumb.name}</span>
              ) : (
                <Link
                  to={tenantPath(`/admin/super/tenants/${crumb.id}`)}
                  className="text-primary hover:underline"
                >
                  {crumb.name}
                </Link>
              )}
            </span>
          ))}
        </div>
      )}

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
        {/* ── Left Column (2/3) ── */}
        <div className="space-y-6 lg:col-span-2">
          {/* Tenant Information */}
          <Card shadow="sm">
            <CardHeader className="pb-0">
              <div className="flex items-center gap-2">
                <Building2 size={18} className="text-primary" />
                <h3 className="text-lg font-semibold">Tenant Information</h3>
              </div>
            </CardHeader>
            <CardBody className="pt-3">
              <dl className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <DetailField label="Name" value={tenant.name} />
                <DetailField label="Slug" value={tenant.slug} />
                <DetailField label="Domain" value={tenant.domain} />
                <DetailField
                  label="Parent Tenant"
                  value={
                    tenant.parent_id ? (
                      <Link
                        to={tenantPath(`/admin/super/tenants/${tenant.parent_id}`)}
                        className="text-primary hover:underline"
                      >
                        {tenant.parent_name || `Tenant #${tenant.parent_id}`}
                      </Link>
                    ) : (
                      <span className="text-default-400">None (top-level)</span>
                    )
                  }
                />
                <DetailField label="Depth in Hierarchy" value={String(tenant.depth ?? 0)} />
                <DetailField label="Max Sub-tenant Depth" value={String(tenant.max_depth)} />
                <DetailField
                  label="Allows Sub-tenants"
                  value={
                    tenant.allows_subtenants ? (
                      <Chip color="success" variant="flat" size="sm">Yes (Hub)</Chip>
                    ) : (
                      <Chip color="default" variant="flat" size="sm">No</Chip>
                    )
                  }
                />
              </dl>
              {tenant.description && (
                <>
                  <Divider className="my-4" />
                  <div>
                    <p className="text-xs font-medium uppercase text-default-400 mb-1">Description</p>
                    <p className="text-sm text-default-700 whitespace-pre-line">{tenant.description}</p>
                  </div>
                </>
              )}
              {tenant.tagline && (
                <div className="mt-3">
                  <p className="text-xs font-medium uppercase text-default-400 mb-1">Tagline</p>
                  <p className="text-sm text-default-700">{tenant.tagline}</p>
                </div>
              )}
            </CardBody>
          </Card>

          {/* Contact Information */}
          {(tenant.contact_email || tenant.contact_phone || tenant.address) && (
            <Card shadow="sm">
              <CardHeader className="pb-0">
                <div className="flex items-center gap-2">
                  <Users size={18} className="text-primary" />
                  <h3 className="text-lg font-semibold">Contact Information</h3>
                </div>
              </CardHeader>
              <CardBody className="pt-3">
                <dl className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                  <DetailField label="Email" value={tenant.contact_email} />
                  <DetailField label="Phone" value={tenant.contact_phone} />
                  <DetailField label="Address" value={tenant.address} />
                </dl>
              </CardBody>
            </Card>
          )}

          {/* SEO Settings */}
          <Card shadow="sm">
            <CardHeader className="pb-0">
              <div className="flex items-center gap-2">
                <Search size={18} className="text-primary" />
                <h3 className="text-lg font-semibold">SEO Settings</h3>
              </div>
            </CardHeader>
            <CardBody className="pt-3">
              <dl className="grid grid-cols-1 gap-4">
                <DetailField label="Meta Title" value={tenant.meta_title} />
                <DetailField label="Meta Description" value={tenant.meta_description} />
                <DetailField label="H1 Headline" value={tenant.h1_headline} />
                <DetailField label="Hero Introduction" value={tenant.hero_intro} />
                <DetailField label="OG Image URL" value={tenant.og_image_url} />
                <DetailField label="Robots Directive" value={tenant.robots_directive} />
              </dl>
            </CardBody>
          </Card>

          {/* Location Info */}
          <Card shadow="sm">
            <CardHeader className="pb-0">
              <div className="flex items-center gap-2">
                <MapPin size={18} className="text-primary" />
                <h3 className="text-lg font-semibold">Location</h3>
              </div>
            </CardHeader>
            <CardBody className="pt-3">
              <dl className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <DetailField label="Location Name" value={tenant.location_name} />
                <DetailField label="Country Code" value={tenant.country_code} />
                <DetailField label="Service Area" value={tenant.service_area} />
                <DetailField label="Latitude" value={tenant.latitude} />
                <DetailField label="Longitude" value={tenant.longitude} />
              </dl>
            </CardBody>
          </Card>

          {/* Social Media Links */}
          <Card shadow="sm">
            <CardHeader className="pb-0">
              <div className="flex items-center gap-2">
                <Globe size={18} className="text-primary" />
                <h3 className="text-lg font-semibold">Social Media</h3>
              </div>
            </CardHeader>
            <CardBody className="pt-3">
              {hasSocialLinks ? (
                <div className="space-y-3">
                  {socialLinks.map((social) => {
                    if (!social.url) return null;
                    const SocialIcon = social.icon;
                    return (
                      <div key={social.key} className="flex items-center gap-3">
                        <SocialIcon size={18} className="text-default-500 shrink-0" />
                        <span className="text-sm font-medium text-default-600 w-24 shrink-0">
                          {social.label}
                        </span>
                        <a
                          href={social.url}
                          target="_blank"
                          rel="noopener noreferrer"
                          className="flex items-center gap-1 text-sm text-primary hover:underline truncate"
                        >
                          {social.url}
                          <ExternalLink size={12} className="shrink-0" />
                        </a>
                      </div>
                    );
                  })}
                </div>
              ) : (
                <p className="text-sm text-default-400">No social media links configured.</p>
              )}
            </CardBody>
          </Card>

          {/* Languages */}
          <Card shadow="sm">
            <CardHeader className="pb-0">
              <div className="flex items-center gap-2">
                <Languages size={18} className="text-primary" />
                <h3 className="text-lg font-semibold">Languages</h3>
              </div>
            </CardHeader>
            <CardBody className="pt-3">
              <dl className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <DetailField
                  label="Default Language"
                  value={
                    (() => {
                      const code = (tenant.configuration as Record<string, unknown>)?.default_language as string | undefined;
                      return code ? `${LANGUAGE_LABELS[code] || code} (${code.toUpperCase()})` : 'English (EN)';
                    })()
                  }
                />
                <div>
                  <dt className="text-xs font-medium uppercase text-default-400">Supported Languages</dt>
                  <dd className="mt-1 flex flex-wrap gap-1.5">
                    {(() => {
                      const langs = (tenant.configuration as Record<string, unknown>)?.supported_languages as string[] | undefined;
                      const codes = langs ?? ['en'];
                      return codes.map((code) => (
                        <Chip key={code} size="sm" variant="flat" color="primary">
                          {LANGUAGE_LABELS[code] || code}
                        </Chip>
                      ));
                    })()}
                  </dd>
                </div>
              </dl>
            </CardBody>
          </Card>

          {/* Module Features */}
          <Card shadow="sm">
            <CardHeader className="pb-0">
              <div className="flex items-center gap-2">
                <Network size={18} className="text-primary" />
                <h3 className="text-lg font-semibold">Features &amp; Modules</h3>
              </div>
            </CardHeader>
            <CardBody className="pt-3">
              <div className="flex flex-wrap gap-2">
                {FEATURE_OPTIONS.map((feature) => {
                  const enabled = tenant.features?.[feature] === true;
                  return (
                    <Chip
                      key={feature}
                      color={enabled ? 'success' : 'default'}
                      variant={enabled ? 'flat' : 'bordered'}
                      size="sm"
                      startContent={
                        enabled
                          ? <CheckCircle2 size={12} />
                          : <XCircle size={12} />
                      }
                    >
                      {feature.replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())}
                    </Chip>
                  );
                })}
              </div>
            </CardBody>
          </Card>
        </div>

        {/* ── Right Column (1/3) ── */}
        <div className="space-y-6">
          {/* Status */}
          <Card shadow="sm">
            <CardHeader className="pb-0">
              <h3 className="text-lg font-semibold">Status</h3>
            </CardHeader>
            <CardBody className="pt-3 space-y-3">
              <div className="flex items-center justify-between">
                <span className="text-sm text-default-500">Active</span>
                <Chip
                  color={tenant.is_active ? 'success' : 'danger'}
                  variant="flat"
                  size="sm"
                >
                  {tenant.is_active ? 'Active' : 'Inactive'}
                </Chip>
              </div>
              {tenant.allows_subtenants && (
                <div className="flex items-center justify-between">
                  <span className="text-sm text-default-500">Type</span>
                  <Chip color="secondary" variant="flat" size="sm">Hub</Chip>
                </div>
              )}
              <div className="flex items-center justify-between">
                <span className="text-sm text-default-500">Max Depth</span>
                <span className="text-sm font-medium">{tenant.max_depth}</span>
              </div>
              {tenant.user_count !== undefined && (
                <div className="flex items-center justify-between">
                  <span className="text-sm text-default-500">Users</span>
                  <span className="text-sm font-medium">{tenant.user_count}</span>
                </div>
              )}
              {tenant.listing_count !== undefined && (
                <div className="flex items-center justify-between">
                  <span className="text-sm text-default-500">Listings</span>
                  <span className="text-sm font-medium">{tenant.listing_count}</span>
                </div>
              )}
              <Divider />
              <div className="text-xs text-default-400">
                <p>Created: {new Date(tenant.created_at).toLocaleDateString()}</p>
                {tenant.updated_at && (
                  <p>Updated: {new Date(tenant.updated_at).toLocaleDateString()}</p>
                )}
              </div>
            </CardBody>
          </Card>

          {/* Direct Children */}
          <Card shadow="sm">
            <CardHeader className="pb-0">
              <div className="flex items-center gap-2">
                <Building2 size={18} className="text-primary" />
                <h3 className="text-lg font-semibold">
                  Children
                  {tenant.children.length > 0 && (
                    <span className="ml-1 text-sm font-normal text-default-400">
                      ({tenant.children.length})
                    </span>
                  )}
                </h3>
              </div>
            </CardHeader>
            <CardBody className="pt-3">
              {tenant.children.length === 0 ? (
                <p className="text-sm text-default-400">No child tenants.</p>
              ) : (
                <ul className="space-y-2">
                  {tenant.children.map((child) => (
                    <li key={child.id}>
                      <Link
                        to={tenantPath(`/admin/super/tenants/${child.id}`)}
                        className="flex items-center gap-3 rounded-lg p-2 hover:bg-default-100 transition-colors"
                      >
                        <Avatar
                          name={child.name}
                          size="sm"
                          className="shrink-0"
                        />
                        <div className="min-w-0 flex-1">
                          <p className="text-sm font-medium text-foreground truncate">{child.name}</p>
                          <p className="text-xs text-default-400 truncate">{child.slug}</p>
                        </div>
                        <Chip
                          color={child.is_active ? 'success' : 'danger'}
                          variant="dot"
                          size="sm"
                        >
                          {child.is_active ? 'Active' : 'Inactive'}
                        </Chip>
                      </Link>
                    </li>
                  ))}
                </ul>
              )}
            </CardBody>
          </Card>

          {/* Tenant Admins */}
          <Card shadow="sm">
            <CardHeader className="pb-0">
              <div className="flex items-center justify-between w-full">
                <div className="flex items-center gap-2">
                  <Users size={18} className="text-primary" />
                  <h3 className="text-lg font-semibold">
                    Admins
                    {tenant.admins.length > 0 && (
                      <span className="ml-1 text-sm font-normal text-default-400">
                        ({tenant.admins.length})
                      </span>
                    )}
                  </h3>
                </div>
                <Button
                  size="sm"
                  variant="flat"
                  color="primary"
                  startContent={<UserPlus size={14} />}
                  onPress={() => setShowAddAdmin(!showAddAdmin)}
                >
                  {showAddAdmin ? 'Cancel' : 'Add'}
                </Button>
              </div>
            </CardHeader>
            <CardBody className="pt-3">
              {/* Add Administrator Form */}
              {showAddAdmin && (
                <div className="mb-4 space-y-3 rounded-lg border border-default-200 p-3">
                  <p className="text-sm font-medium text-foreground">Add Administrator</p>
                  <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <Input
                      size="sm"
                      label="First Name"
                      isRequired
                      value={adminForm.first_name}
                      onValueChange={(v) => setAdminForm({ ...adminForm, first_name: v })}
                    />
                    <Input
                      size="sm"
                      label="Last Name"
                      value={adminForm.last_name}
                      onValueChange={(v) => setAdminForm({ ...adminForm, last_name: v })}
                    />
                  </div>
                  <Input
                    size="sm"
                    label="Email"
                    type="email"
                    isRequired
                    value={adminForm.email}
                    onValueChange={(v) => setAdminForm({ ...adminForm, email: v })}
                  />
                  <Input
                    size="sm"
                    label="Password"
                    type="password"
                    isRequired
                    value={adminForm.password}
                    onValueChange={(v) => setAdminForm({ ...adminForm, password: v })}
                  />
                  <Select
                    size="sm"
                    label="Role"
                    selectedKeys={[adminForm.role]}
                    onSelectionChange={(keys) => {
                      const val = Array.from(keys)[0] as string;
                      if (val) setAdminForm({ ...adminForm, role: val });
                    }}
                  >
                    <SelectItem key="admin">Admin</SelectItem>
                    <SelectItem key="tenant_admin">Tenant Admin</SelectItem>
                    <SelectItem key="member">Member</SelectItem>
                  </Select>
                  <Button
                    size="sm"
                    color="primary"
                    isLoading={addingAdmin}
                    onPress={handleAddAdmin}
                    fullWidth
                  >
                    Create Administrator
                  </Button>
                </div>
              )}

              {tenant.admins.length === 0 && !showAddAdmin ? (
                <p className="text-sm text-default-400">No admins found.</p>
              ) : (
                <ul className="space-y-2">
                  {tenant.admins.map((admin) => (
                    <li key={admin.id}>
                      <Link
                        to={tenantPath(`/admin/super/users/${admin.id}`)}
                        className="flex items-center gap-3 rounded-lg p-2 hover:bg-default-100 transition-colors"
                      >
                        <Avatar
                          name={admin.name}
                          size="sm"
                          className="shrink-0"
                        />
                        <div className="min-w-0 flex-1">
                          <p className="text-sm font-medium text-foreground truncate">{admin.name}</p>
                          <p className="text-xs text-default-400 truncate">{admin.email}</p>
                        </div>
                        <Chip variant="flat" size="sm" className="capitalize">
                          {admin.role}
                        </Chip>
                      </Link>
                    </li>
                  ))}
                </ul>
              )}
            </CardBody>
          </Card>

          {/* Quick Actions */}
          <Card shadow="sm">
            <CardHeader className="pb-0">
              <h3 className="text-lg font-semibold">Actions</h3>
            </CardHeader>
            <CardBody className="pt-3 space-y-2">
              <Button
                color="primary"
                variant="flat"
                fullWidth
                startContent={<Edit size={16} />}
                onPress={() => navigate(tenantPath(`/admin/super/tenants/${tenant.id}/edit`))}
              >
                Edit Tenant
              </Button>
              {tenant.allows_subtenants && (
                <Button
                  color="secondary"
                  variant="flat"
                  fullWidth
                  startContent={<Plus size={16} />}
                  onPress={() => navigate(tenantPath(`/admin/super/tenants/create?parent_id=${tenant.id}`))}
                >
                  Create Sub-Tenant
                </Button>
              )}
              <Button
                variant="flat"
                fullWidth
                startContent={<ArrowLeft size={16} />}
                onPress={() => navigate(tenantPath('/admin/super/tenants'))}
              >
                Back to Tenants
              </Button>
            </CardBody>
          </Card>

          {/* Hub Settings */}
          <Card shadow="sm">
            <CardHeader className="pb-0">
              <div className="flex items-center gap-2">
                <Network size={18} className="text-primary" />
                <h3 className="text-lg font-semibold">Hub Settings</h3>
              </div>
            </CardHeader>
            <CardBody className="pt-3 space-y-3">
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm font-medium">Sub-tenant Capability</p>
                  <p className="text-xs text-default-400">
                    {tenant.allows_subtenants ? 'This tenant can create sub-tenants' : 'Standard tenant'}
                  </p>
                </div>
                <Switch
                  isSelected={tenant.allows_subtenants}
                  isDisabled={actionLoading}
                  onValueChange={handleToggleHub}
                  aria-label="Toggle hub capability"
                />
              </div>
            </CardBody>
          </Card>

          {/* Move Tenant */}
          {tenant.id !== 1 && (
            <Card shadow="sm">
              <CardHeader className="pb-0">
                <div className="flex items-center gap-2">
                  <MoveRight size={18} className="text-warning" />
                  <h3 className="text-lg font-semibold">Move Tenant</h3>
                </div>
              </CardHeader>
              <CardBody className="pt-3">
                <p className="text-xs text-default-400 mb-3">
                  Move this tenant under a different parent in the hierarchy.
                </p>
                <Button
                  color="warning"
                  variant="flat"
                  fullWidth
                  onPress={() => {
                    loadHubTenants();
                    moveModal.onOpen();
                  }}
                >
                  Move to Different Parent
                </Button>
              </CardBody>
            </Card>
          )}

          {/* Danger Zone */}
          {tenant.id !== 1 && (
            <Card shadow="sm" className="border-danger-200 dark:border-danger-800">
              <CardHeader className="pb-0">
                <div className="flex items-center gap-2 text-danger">
                  <AlertTriangle size={18} />
                  <h3 className="text-lg font-semibold">Danger Zone</h3>
                </div>
              </CardHeader>
              <CardBody className="pt-3">
                <Button
                  color={tenant.is_active ? 'danger' : 'success'}
                  variant="flat"
                  fullWidth
                  isDisabled={actionLoading}
                  startContent={<Power size={16} />}
                  onPress={handleToggleActive}
                >
                  {tenant.is_active ? 'Deactivate Tenant' : 'Reactivate Tenant'}
                </Button>
                {tenant.is_active && (
                  <p className="text-xs text-default-400 mt-2">
                    Deactivating will prevent users from accessing this tenant.
                  </p>
                )}
              </CardBody>
            </Card>
          )}
        </div>
      </div>

      {/* Move Tenant Modal */}
      <Modal isOpen={moveModal.isOpen} onClose={moveModal.onClose}>
        <ModalContent>
          <ModalHeader>Move Tenant</ModalHeader>
          <ModalBody>
            <p className="text-sm text-default-500 mb-3">
              Select the new parent tenant for &ldquo;{tenant.name}&rdquo;.
            </p>
            <Select
              label="New Parent Tenant"
              placeholder="Select parent"
              selectedKeys={moveParentId ? [moveParentId] : []}
              onSelectionChange={(keys) => {
                const arr = Array.from(keys);
                setMoveParentId(arr.length > 0 ? String(arr[0]) : '');
              }}
            >
              {hubTenants
                .filter((t) => t.id !== tenant.id && t.id !== tenant.parent_id)
                .map((t) => (
                  <SelectItem key={String(t.id)}>{t.name}</SelectItem>
                ))}
            </Select>
          </ModalBody>
          <ModalFooter>
            <Button variant="light" onPress={moveModal.onClose}>Cancel</Button>
            <Button
              color="warning"
              isDisabled={!moveParentId}
              isLoading={actionLoading}
              onPress={handleMove}
            >
              Move
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}

// Reusable detail field component
function DetailField({ label, value }: { label: string; value?: string | React.ReactNode }) {
  const isEmpty = !value || (typeof value === 'string' && !value.trim());
  return (
    <div>
      <dt className="text-xs font-medium uppercase text-default-400">{label}</dt>
      <dd className="mt-0.5 text-sm text-default-700">
        {isEmpty ? <span className="text-default-300">--</span> : value}
      </dd>
    </div>
  );
}

export default TenantShow;
