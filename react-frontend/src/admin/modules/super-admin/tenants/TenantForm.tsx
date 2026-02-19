// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Super Admin - Tenant Form
 * Create/Edit tenant with full configuration options
 */

import { useState, useEffect, useMemo } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import {
  Input,
  Textarea,
  Select,
  SelectItem,
  Switch,
  Button,
  Card,
  CardBody,
  CardHeader,
  Divider,
} from '@heroui/react';
import { Save, X, AlertTriangle, Building2, Globe, Settings, FileText, MapPin } from 'lucide-react';
import { usePageTitle } from '@/hooks/usePageTitle';
import { useApi } from '@/hooks/useApi';
import { useToast } from '@/contexts/ToastContext';
import { adminSuper } from '@/admin/api/adminApi';
import { PageHeader } from '@/admin/components/PageHeader';
import { ConfirmModal } from '@/admin/components/ConfirmModal';
import type { CreateTenantPayload, UpdateTenantPayload } from '@/admin/api/types';

export function TenantForm() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const toast = useToast();
  const isEdit = !!id;

  usePageTitle(isEdit ? 'Edit Tenant - Super Admin' : 'Create Tenant - Super Admin');

  // State
  const [formData, setFormData] = useState({
    parent_id: 1,
    name: '',
    slug: '',
    domain: '',
    tagline: '',
    description: '',
    allows_subtenants: false,
    max_depth: 3,
    is_active: true,
    // SEO fields
    meta_title: '',
    meta_description: '',
    h1_headline: '',
    hero_intro: '',
    og_image_url: '',
    robots_directive: 'index, follow',
    // Contact fields
    contact_email: '',
    contact_phone: '',
    address: '',
    // Location fields
    location_name: '',
    country_code: '',
    service_area: '',
    latitude: '',
    longitude: '',
    // Social fields
    social_facebook: '',
    social_twitter: '',
    social_instagram: '',
    social_linkedin: '',
    social_youtube: '',
  });

  const [slugAutoGen, setSlugAutoGen] = useState(!isEdit);
  const [deleteModalOpen, setDeleteModalOpen] = useState(false);
  const [moveModalOpen, setMoveModalOpen] = useState(false);
  const [newParentId, setNewParentId] = useState<number>(1);
  const [submitting, setSubmitting] = useState(false);

  // Load tenant for edit
  const { data: tenant, isLoading: tenantLoading } = useApi<any>(
    id ? `/v2/admin/super/tenants/${id}` : '',
    { immediate: !!id, deps: [id] }
  );

  // Load all tenants for parent selector and move
  const { data: allTenants } = useApi<any[]>(
    '/v2/admin/super/tenants',
    { immediate: true, deps: [] }
  );

  // Auto-populate form in edit mode
  useEffect(() => {
    if (tenant && isEdit) {
      setFormData({
        parent_id: tenant.parent_id || 1,
        name: tenant.name,
        slug: tenant.slug,
        domain: tenant.domain || '',
        tagline: tenant.tagline || '',
        description: tenant.description || '',
        allows_subtenants: tenant.allows_subtenants,
        max_depth: tenant.max_depth,
        is_active: tenant.is_active,
        meta_title: tenant.meta_title || '',
        meta_description: tenant.meta_description || '',
        h1_headline: tenant.h1_headline || '',
        hero_intro: tenant.hero_intro || '',
        og_image_url: tenant.og_image_url || '',
        robots_directive: tenant.robots_directive || 'index, follow',
        contact_email: tenant.contact_email || '',
        contact_phone: tenant.contact_phone || '',
        address: tenant.address || '',
        location_name: tenant.location_name || '',
        country_code: tenant.country_code || '',
        service_area: tenant.service_area || '',
        latitude: tenant.latitude || '',
        longitude: tenant.longitude || '',
        social_facebook: tenant.social_facebook || '',
        social_twitter: tenant.social_twitter || '',
        social_instagram: tenant.social_instagram || '',
        social_linkedin: tenant.social_linkedin || '',
        social_youtube: tenant.social_youtube || '',
      });
      setSlugAutoGen(false);
    }
  }, [tenant, isEdit]);

  // Auto-generate slug from name
  useEffect(() => {
    if (slugAutoGen && formData.name) {
      const generated = formData.name
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-|-$/g, '');
      setFormData((prev) => ({ ...prev, slug: generated }));
    }
  }, [formData.name, slugAutoGen]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setSubmitting(true);

    try {
      let response;
      if (isEdit) {
        const payload: UpdateTenantPayload = { ...formData };
        response = await adminSuper.updateTenant(Number(id), payload);
      } else {
        const payload: CreateTenantPayload = {
          parent_id: formData.parent_id,
          name: formData.name,
          slug: formData.slug,
          domain: formData.domain || undefined,
          tagline: formData.tagline || undefined,
          description: formData.description || undefined,
          allows_subtenants: formData.allows_subtenants,
          max_depth: formData.max_depth,
          is_active: formData.is_active,
        };
        response = await adminSuper.createTenant(payload);
      }

      if (response.success) {
        toast.success(isEdit ? 'Tenant updated successfully' : 'Tenant created successfully');
        navigate('/admin/super/tenants');
      } else {
        toast.error(response.error || 'Failed to save tenant');
      }
    } catch (error) {
      toast.error('An error occurred');
      console.error('Save error:', error);
    } finally {
      setSubmitting(false);
    }
  };

  const handleDelete = async () => {
    setSubmitting(true);
    try {
      const response = await adminSuper.deleteTenant(Number(id));
      if (response.success) {
        toast.success('Tenant deleted successfully');
        navigate('/admin/super/tenants');
      } else {
        toast.error(response.error || 'Failed to delete tenant');
      }
    } catch (error) {
      toast.error('An error occurred');
      console.error('Delete error:', error);
    } finally {
      setSubmitting(false);
      setDeleteModalOpen(false);
    }
  };

  const handleMove = async () => {
    setSubmitting(true);
    try {
      const response = await adminSuper.moveTenant(Number(id), newParentId);
      if (response.success) {
        toast.success('Tenant moved successfully');
        setMoveModalOpen(false);
        window.location.reload(); // Reload to reflect hierarchy changes
      } else {
        toast.error(response.error || 'Failed to move tenant');
      }
    } catch (error) {
      toast.error('An error occurred');
      console.error('Move error:', error);
    } finally {
      setSubmitting(false);
    }
  };

  // Filter parent options (exclude self and descendants in edit mode)
  const parentOptions = useMemo(() => {
    if (!allTenants) return [];
    if (!isEdit) return allTenants.filter((t) => t.allows_subtenants);
    return allTenants.filter((t) => t.allows_subtenants && t.id !== Number(id));
  }, [allTenants, isEdit, id]);

  if (isEdit && tenantLoading) {
    return (
      <div className="p-6 flex items-center justify-center h-64">
        <div className="text-center">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-primary mx-auto mb-4" />
          <p className="text-default-500">Loading tenant...</p>
        </div>
      </div>
    );
  }

  return (
    <div className="p-6">
      <PageHeader
        title={isEdit ? `Edit ${tenant?.name || 'Tenant'}` : 'Create Tenant'}
        description={isEdit ? 'Update tenant configuration and settings' : 'Create a new tenant in the platform hierarchy'}
      />

      <form onSubmit={handleSubmit}>
        {/* CREATE MODE: Single column with basic fields */}
        {!isEdit && (
          <Card className="mb-6">
            <CardHeader>
              <div className="flex items-center gap-2">
                <Building2 size={18} />
                <span className="font-semibold">Basic Information</span>
              </div>
            </CardHeader>
            <Divider />
            <CardBody className="gap-4">
              <Select
                label="Parent Tenant"
                placeholder="Select parent tenant"
                isRequired
                selectedKeys={[String(formData.parent_id)]}
                onSelectionChange={(keys) => setFormData({ ...formData, parent_id: Number(Array.from(keys)[0]) })}
              >
                {parentOptions.map((t) => (
                  <SelectItem key={String(t.id)}>
                    {t.name}
                  </SelectItem>
                ))}
              </Select>

              <Input
                label="Tenant Name"
                placeholder="e.g. West Cork Timebank"
                value={formData.name}
                onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                isRequired
              />

              <div className="space-y-2">
                <Input
                  label="Slug"
                  placeholder="e.g. west-cork-timebank"
                  value={formData.slug}
                  onChange={(e) => {
                    setSlugAutoGen(false);
                    setFormData({ ...formData, slug: e.target.value });
                  }}
                  description={slugAutoGen ? 'Auto-generated from name. Edit to customize.' : 'URL-friendly identifier.'}
                  isRequired
                />
              </div>

              <Input
                label="Domain (optional)"
                placeholder="e.g. west-cork-timebank.ie"
                value={formData.domain}
                onChange={(e) => setFormData({ ...formData, domain: e.target.value })}
              />

              <Input
                label="Tagline (optional)"
                placeholder="A short description"
                value={formData.tagline}
                onChange={(e) => setFormData({ ...formData, tagline: e.target.value })}
              />

              <Textarea
                label="Description (optional)"
                placeholder="Detailed description of this tenant"
                value={formData.description}
                onChange={(e) => setFormData({ ...formData, description: e.target.value })}
                rows={3}
              />

              <div className="flex items-center gap-6">
                <Switch
                  isSelected={formData.allows_subtenants}
                  onValueChange={(val) => setFormData({ ...formData, allows_subtenants: val })}
                >
                  Allow sub-tenants (Hub)
                </Switch>

                <Switch
                  isSelected={formData.is_active}
                  onValueChange={(val) => setFormData({ ...formData, is_active: val })}
                >
                  Active
                </Switch>
              </div>

              <div className="flex items-center gap-4">
                <Button type="submit" color="primary" isLoading={submitting}>
                  <Save size={16} />
                  Create Tenant
                </Button>
                <Button type="button" variant="light" onPress={() => navigate('/admin/super/tenants')}>
                  <X size={16} />
                  Cancel
                </Button>
              </div>
            </CardBody>
          </Card>
        )}

        {/* EDIT MODE: Two-column layout with full fields */}
        {isEdit && (
          <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
            {/* Main Column */}
            <div className="lg:col-span-2 space-y-6">
              {/* Basic Details */}
              <Card>
                <CardHeader>
                  <div className="flex items-center gap-2">
                    <Building2 size={18} />
                    <span className="font-semibold">Basic Details</span>
                  </div>
                </CardHeader>
                <Divider />
                <CardBody className="gap-4">
                  <Input
                    label="Tenant Name"
                    value={formData.name}
                    onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                    isRequired
                  />
                  <Input
                    label="Slug"
                    value={formData.slug}
                    onChange={(e) => setFormData({ ...formData, slug: e.target.value })}
                    isRequired
                  />
                  <Input
                    label="Domain"
                    value={formData.domain}
                    onChange={(e) => setFormData({ ...formData, domain: e.target.value })}
                  />
                  <Input
                    label="Tagline"
                    value={formData.tagline}
                    onChange={(e) => setFormData({ ...formData, tagline: e.target.value })}
                  />
                  <Textarea
                    label="Description"
                    value={formData.description}
                    onChange={(e) => setFormData({ ...formData, description: e.target.value })}
                    rows={4}
                  />
                </CardBody>
              </Card>

              {/* SEO Settings */}
              <Card>
                <CardHeader>
                  <div className="flex items-center gap-2">
                    <FileText size={18} />
                    <span className="font-semibold">SEO Settings</span>
                  </div>
                </CardHeader>
                <Divider />
                <CardBody className="gap-4">
                  <Input
                    label="Meta Title"
                    value={formData.meta_title}
                    onChange={(e) => setFormData({ ...formData, meta_title: e.target.value })}
                  />
                  <Textarea
                    label="Meta Description"
                    value={formData.meta_description}
                    onChange={(e) => setFormData({ ...formData, meta_description: e.target.value })}
                    rows={2}
                  />
                  <Input
                    label="H1 Headline"
                    value={formData.h1_headline}
                    onChange={(e) => setFormData({ ...formData, h1_headline: e.target.value })}
                  />
                  <Textarea
                    label="Hero Intro"
                    value={formData.hero_intro}
                    onChange={(e) => setFormData({ ...formData, hero_intro: e.target.value })}
                    rows={2}
                  />
                  <Input
                    label="OG Image URL"
                    value={formData.og_image_url}
                    onChange={(e) => setFormData({ ...formData, og_image_url: e.target.value })}
                  />
                  <Input
                    label="Robots Directive"
                    value={formData.robots_directive}
                    onChange={(e) => setFormData({ ...formData, robots_directive: e.target.value })}
                  />
                </CardBody>
              </Card>

              {/* Contact Info */}
              <Card>
                <CardHeader>
                  <div className="flex items-center gap-2">
                    <Globe size={18} />
                    <span className="font-semibold">Contact Information</span>
                  </div>
                </CardHeader>
                <Divider />
                <CardBody className="gap-4">
                  <Input
                    label="Contact Email"
                    type="email"
                    value={formData.contact_email}
                    onChange={(e) => setFormData({ ...formData, contact_email: e.target.value })}
                  />
                  <Input
                    label="Contact Phone"
                    type="tel"
                    value={formData.contact_phone}
                    onChange={(e) => setFormData({ ...formData, contact_phone: e.target.value })}
                  />
                  <Textarea
                    label="Address"
                    value={formData.address}
                    onChange={(e) => setFormData({ ...formData, address: e.target.value })}
                    rows={2}
                  />
                </CardBody>
              </Card>

              {/* Location & Geography */}
              <Card>
                <CardHeader>
                  <div className="flex items-center gap-2">
                    <MapPin size={18} />
                    <span className="font-semibold">Location & Geography</span>
                  </div>
                </CardHeader>
                <Divider />
                <CardBody className="gap-4">
                  <Input
                    label="Location Name"
                    value={formData.location_name}
                    onChange={(e) => setFormData({ ...formData, location_name: e.target.value })}
                  />
                  <Input
                    label="Country Code"
                    value={formData.country_code}
                    onChange={(e) => setFormData({ ...formData, country_code: e.target.value })}
                  />
                  <Input
                    label="Service Area"
                    value={formData.service_area}
                    onChange={(e) => setFormData({ ...formData, service_area: e.target.value })}
                  />
                  <div className="grid grid-cols-2 gap-4">
                    <Input
                      label="Latitude"
                      value={formData.latitude}
                      onChange={(e) => setFormData({ ...formData, latitude: e.target.value })}
                    />
                    <Input
                      label="Longitude"
                      value={formData.longitude}
                      onChange={(e) => setFormData({ ...formData, longitude: e.target.value })}
                    />
                  </div>
                </CardBody>
              </Card>

              {/* Social Media */}
              <Card>
                <CardHeader>
                  <div className="flex items-center gap-2">
                    <Globe size={18} />
                    <span className="font-semibold">Social Media</span>
                  </div>
                </CardHeader>
                <Divider />
                <CardBody className="gap-4">
                  <Input
                    label="Facebook URL"
                    value={formData.social_facebook}
                    onChange={(e) => setFormData({ ...formData, social_facebook: e.target.value })}
                  />
                  <Input
                    label="Twitter URL"
                    value={formData.social_twitter}
                    onChange={(e) => setFormData({ ...formData, social_twitter: e.target.value })}
                  />
                  <Input
                    label="Instagram URL"
                    value={formData.social_instagram}
                    onChange={(e) => setFormData({ ...formData, social_instagram: e.target.value })}
                  />
                  <Input
                    label="LinkedIn URL"
                    value={formData.social_linkedin}
                    onChange={(e) => setFormData({ ...formData, social_linkedin: e.target.value })}
                  />
                  <Input
                    label="YouTube URL"
                    value={formData.social_youtube}
                    onChange={(e) => setFormData({ ...formData, social_youtube: e.target.value })}
                  />
                </CardBody>
              </Card>
            </div>

            {/* Sidebar */}
            <div className="space-y-6">
              {/* Actions */}
              <Card>
                <CardHeader>
                  <span className="font-semibold">Actions</span>
                </CardHeader>
                <Divider />
                <CardBody className="gap-3">
                  <Button type="submit" color="primary" isLoading={submitting} className="w-full">
                    <Save size={16} />
                    Save Changes
                  </Button>
                  <Button
                    type="button"
                    variant="light"
                    onPress={() => navigate('/admin/super/tenants')}
                    className="w-full"
                  >
                    <X size={16} />
                    Cancel
                  </Button>
                </CardBody>
              </Card>

              {/* Hierarchy & Hub Settings */}
              <Card>
                <CardHeader>
                  <div className="flex items-center gap-2">
                    <Settings size={18} />
                    <span className="font-semibold">Hierarchy & Hub</span>
                  </div>
                </CardHeader>
                <Divider />
                <CardBody className="gap-4">
                  <Switch
                    isSelected={formData.allows_subtenants}
                    onValueChange={(val) => setFormData({ ...formData, allows_subtenants: val })}
                  >
                    Allow sub-tenants (Hub)
                  </Switch>

                  <Input
                    type="number"
                    label="Max Depth"
                    value={String(formData.max_depth)}
                    onChange={(e) => setFormData({ ...formData, max_depth: Number(e.target.value) })}
                    min={0}
                    max={10}
                  />

                  <Divider />

                  <Button
                    variant="flat"
                    color="warning"
                    onPress={() => setMoveModalOpen(true)}
                    className="w-full"
                  >
                    Move to Different Parent
                  </Button>
                </CardBody>
              </Card>

              {/* Status */}
              <Card>
                <CardHeader>
                  <span className="font-semibold">Status</span>
                </CardHeader>
                <Divider />
                <CardBody>
                  <Switch
                    isSelected={formData.is_active}
                    onValueChange={(val) => setFormData({ ...formData, is_active: val })}
                  >
                    Active
                  </Switch>
                </CardBody>
              </Card>

              {/* Danger Zone */}
              <Card className="border-danger-200 dark:border-danger-800">
                <CardHeader>
                  <div className="flex items-center gap-2 text-danger">
                    <AlertTriangle size={18} />
                    <span className="font-semibold">Danger Zone</span>
                  </div>
                </CardHeader>
                <Divider />
                <CardBody>
                  <Button
                    color="danger"
                    variant="flat"
                    onPress={() => setDeleteModalOpen(true)}
                    className="w-full"
                  >
                    Delete Tenant
                  </Button>
                </CardBody>
              </Card>
            </div>
          </div>
        )}
      </form>

      {/* Delete Confirmation */}
      <ConfirmModal
        isOpen={deleteModalOpen}
        onClose={() => setDeleteModalOpen(false)}
        onConfirm={handleDelete}
        title="Delete Tenant"
        message={`Are you sure you want to delete "${tenant?.name}"? This action cannot be undone.`}
        confirmLabel="Delete"
        confirmColor="danger"
        isLoading={submitting}
      />

      {/* Move Confirmation */}
      <ConfirmModal
        isOpen={moveModalOpen}
        onClose={() => setMoveModalOpen(false)}
        onConfirm={handleMove}
        title="Move Tenant"
        message="Select the new parent tenant:"
        confirmLabel="Move"
        confirmColor="warning"
        isLoading={submitting}
      >
        <Select
          label="New Parent"
          selectedKeys={[String(newParentId)]}
          onSelectionChange={(keys) => setNewParentId(Number(Array.from(keys)[0]))}
        >
          {parentOptions.map((t) => (
            <SelectItem key={String(t.id)}>
              {t.name}
            </SelectItem>
          ))}
        </Select>
      </ConfirmModal>
    </div>
  );
}

export default TenantForm;
