// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState, useEffect, useCallback, useRef } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import {
  Button,
  Card,
  CardBody,
  CardHeader,
  Input,
  Select,
  SelectItem,
  Spinner,
  Switch,
  Textarea,
  Avatar,
  Chip,
} from '@heroui/react';
import {
  ArrowLeft, Save, Users, AlertTriangle, Settings,
  MapPin, Globe, Star, Palette, Image, Shield,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { adminGroups } from '../../api/adminApi';
import { api } from '@/lib/api';
import { resolveAssetUrl } from '@/lib/helpers';
import { PageHeader } from '../../components';
import type { AdminGroup, GroupType } from '../../api/types';

export function GroupEdit() {
  const { t } = useTranslation('admin');
  const { id } = useParams<{ id: string }>();
  const { tenantPath, hasFeature } = useTenant();
  const toast = useToast();
  const navigate = useNavigate();
  const avatarInputRef = useRef<HTMLInputElement>(null);
  const coverInputRef = useRef<HTMLInputElement>(null);

  const [loading, setLoading] = useState(true);
  const [loadError, setLoadError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);
  const [uploadingAvatar, setUploadingAvatar] = useState(false);
  const [uploadingCover, setUploadingCover] = useState(false);
  const [group, setGroup] = useState<AdminGroup | null>(null);
  const [groupTypes, setGroupTypes] = useState<GroupType[]>([]);

  // Form fields
  const [name, setName] = useState('');
  const [description, setDescription] = useState('');
  const [visibility, setVisibility] = useState('public');
  const [location, setLocation] = useState('');
  const [status, setStatus] = useState('active');
  const [typeId, setTypeId] = useState<string>('');
  const [isFeatured, setIsFeatured] = useState(false);
  const [federatedVisibility, setFederatedVisibility] = useState<'none' | 'listed' | 'joinable'>('none');
  const [primaryColor, setPrimaryColor] = useState('');
  const [accentColor, setAccentColor] = useState('');
  const [imageUrl, setImageUrl] = useState<string | null>(null);
  const [coverImageUrl, setCoverImageUrl] = useState<string | null>(null);

  usePageTitle(group ? t('groups.edit_page_title', { name: group.name }) : t('groups.edit_page_title_loading'));

  const loadGroup = useCallback(async () => {
    if (!id) return;
    setLoading(true);
    const [groupRes, typesRes] = await Promise.all([
      adminGroups.getGroup(Number(id)),
      adminGroups.getGroupTypes(),
    ]);

    if (groupRes.success && groupRes.data) {
      const g = groupRes.data;
      setGroup(g);
      setName(g.name ?? '');
      setDescription(g.description ?? '');
      setVisibility(g.visibility ?? 'public');
      setLocation(g.location ?? '');
      setStatus(g.status === 'active' ? 'active' : g.status === 'inactive' ? 'inactive' : g.status === 'archived' ? 'archived' : 'inactive');
      setTypeId(g.type_id != null ? String(g.type_id) : '');
      setIsFeatured(g.is_featured ?? false);
      setFederatedVisibility(g.federated_visibility ?? 'none');
      setPrimaryColor(g.primary_color ?? '');
      setAccentColor(g.accent_color ?? '');
      setImageUrl(g.image_url ?? null);
      setCoverImageUrl(g.cover_image_url ?? null);
    } else {
      setLoadError(groupRes.error ?? t('groups.failed_to_load_group'));
    }

    if (typesRes.success && Array.isArray(typesRes.data)) {
      setGroupTypes(typesRes.data);
    }

    setLoading(false);
  }, [id, t]);

  useEffect(() => { loadGroup(); }, [loadGroup]);

  const handleImageUpload = async (e: React.ChangeEvent<HTMLInputElement>, type: 'avatar' | 'cover') => {
    const file = e.target.files?.[0];
    if (!file || !id) return;
    const setter = type === 'avatar' ? setUploadingAvatar : setUploadingCover;
    setter(true);
    try {
      const formData = new FormData();
      formData.append('image', file);
      formData.append('type', type);
      const res = await api.upload(`/v2/groups/${id}/image`, formData);
      if (res.success && res.data) {
        const url = (res.data as Record<string, unknown>).url as string
          || (res.data as Record<string, unknown>).image_url as string
          || '';
        if (url) {
          if (type === 'avatar') setImageUrl(url);
          else setCoverImageUrl(url);
          toast.success(t('groups.edit_image_uploaded'));
        }
      } else {
        toast.error(res.error ?? t('groups.edit_image_upload_failed'));
      }
    } catch {
      toast.error(t('groups.edit_image_upload_failed'));
    } finally {
      setter(false);
      e.target.value = '';
    }
  };

  const handleSave = async () => {
    if (!id || !name.trim()) {
      toast.error(t('groups.edit_name_required'));
      return;
    }
    setSubmitting(true);

    const updatePayload = {
      name: name.trim(),
      description: description || undefined,
      visibility,
      location: location || undefined,
      type_id: typeId ? Number(typeId) : null,
      is_featured: isFeatured,
      federated_visibility: federatedVisibility,
      primary_color: primaryColor || null,
      accent_color: accentColor || null,
    };

    const [updateRes, statusRes] = await Promise.all([
      adminGroups.updateGroup(Number(id), updatePayload),
      group?.status !== status
        ? adminGroups.updateStatus(Number(id), status as 'active' | 'inactive')
        : Promise.resolve({ success: true }),
    ]);

    if (updateRes.success && statusRes.success) {
      toast.success(t('groups.edit_saved', { name: name.trim() }));
      navigate(tenantPath('/admin/groups'));
    } else {
      toast.error((updateRes as { success: boolean; error?: string }).error ?? t('groups.edit_save_failed'));
    }
    setSubmitting(false);
  };

  if (loading) {
    return (
      <div className="flex justify-center items-center min-h-[400px]">
        <Spinner size="lg" />
      </div>
    );
  }

  if (loadError) {
    return (
      <div className="max-w-3xl mx-auto px-4 pb-8">
        <div className="flex flex-col items-center justify-center py-16 text-center gap-4 text-default-500">
          <AlertTriangle size={40} className="text-warning" />
          <p className="text-lg font-medium">{loadError}</p>
          <Button
            variant="flat"
            startContent={<ArrowLeft size={16} />}
            onPress={() => navigate(tenantPath('/admin/groups'))}
          >
            {t('groups.back_to_groups')}
          </Button>
        </div>
      </div>
    );
  }

  return (
    <div className="max-w-3xl mx-auto px-4 pb-8">
      <PageHeader
        title={t('groups.edit_page_title', { name: group?.name ?? '' })}
        description={t('groups.edit_page_desc')}
        actions={
          <Button
            variant="flat"
            startContent={<ArrowLeft size={16} />}
            onPress={() => navigate(tenantPath('/admin/groups'))}
          >
            {t('groups.back_to_groups')}
          </Button>
        }
      />

      <div className="flex flex-col gap-4">

        {/* Images */}
        <Card>
          <CardHeader className="flex items-center gap-2 pb-0">
            <Image size={18} className="text-default-500" />
            <h3 className="font-semibold">{t('groups.edit_section_images')}</h3>
          </CardHeader>
          <CardBody className="flex flex-col gap-4">
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              {/* Avatar */}
              <div className="flex flex-col gap-2">
                <p className="text-sm font-medium text-default-600">{t('groups.edit_label_avatar')}</p>
                <div className="flex items-center gap-3">
                  <Avatar
                    src={imageUrl ? resolveAssetUrl(imageUrl) : undefined}
                    name={name}
                    size="lg"
                    className="shrink-0"
                  />
                  <Button
                    size="sm"
                    variant="flat"
                    isLoading={uploadingAvatar}
                    onPress={() => avatarInputRef.current?.click()}
                  >
                    {t('groups.edit_upload_avatar')}
                  </Button>
                </div>
                <input
                  ref={avatarInputRef}
                  type="file"
                  accept="image/*"
                  className="hidden"
                  onChange={(e) => handleImageUpload(e, 'avatar')}
                />
              </div>

              {/* Cover */}
              <div className="flex flex-col gap-2">
                <p className="text-sm font-medium text-default-600">{t('groups.edit_label_cover')}</p>
                <div className="flex flex-col gap-2">
                  {coverImageUrl && (
                    <img
                      src={resolveAssetUrl(coverImageUrl)}
                      alt=""
                      className="w-full h-20 object-cover rounded-lg"
                    />
                  )}
                  <Button
                    size="sm"
                    variant="flat"
                    isLoading={uploadingCover}
                    onPress={() => coverInputRef.current?.click()}
                  >
                    {t('groups.edit_upload_cover')}
                  </Button>
                </div>
                <input
                  ref={coverInputRef}
                  type="file"
                  accept="image/*"
                  className="hidden"
                  onChange={(e) => handleImageUpload(e, 'cover')}
                />
              </div>
            </div>
          </CardBody>
        </Card>

        {/* Basic info */}
        <Card>
          <CardHeader className="flex items-center gap-2 pb-0">
            <Users size={18} className="text-default-500" />
            <h3 className="font-semibold">{t('groups.edit_section_basic')}</h3>
          </CardHeader>
          <CardBody className="flex flex-col gap-4">
            <Input
              label={t('groups.edit_label_name')}
              value={name}
              onValueChange={setName}
              variant="bordered"
              isRequired
              maxLength={255}
              description={name.length > 200 ? `${name.length}/255` : undefined}
            />
            <Textarea
              label={t('groups.edit_label_description')}
              value={description}
              onValueChange={setDescription}
              variant="bordered"
              minRows={3}
              maxRows={8}
            />
            <Input
              label={t('groups.edit_label_location')}
              value={location}
              onValueChange={setLocation}
              variant="bordered"
              placeholder={t('groups.edit_placeholder_location')}
              startContent={<MapPin size={14} className="text-default-400" />}
            />
          </CardBody>
        </Card>

        {/* Group Type */}
        <Card>
          <CardHeader className="flex items-center gap-2 pb-0">
            <Settings size={18} className="text-default-500" />
            <h3 className="font-semibold">{t('groups.edit_section_type')}</h3>
          </CardHeader>
          <CardBody>
            <Select
              label={t('groups.edit_label_type')}
              selectedKeys={typeId ? [typeId] : []}
              onSelectionChange={(keys) => {
                const val = Array.from(keys)[0] as string | undefined;
                setTypeId(val ?? '');
              }}
              variant="bordered"
              placeholder={t('groups.edit_placeholder_type')}
            >
              {groupTypes.map((gt) => (
                <SelectItem key={String(gt.id)} textValue={gt.name}>
                  <div className="flex items-center gap-2">
                    {gt.color && (
                      <span
                        className="w-2.5 h-2.5 rounded-full shrink-0"
                        style={{ backgroundColor: gt.color }}
                      />
                    )}
                    <span>{gt.name}</span>
                    <Chip size="sm" variant="flat" className="ml-auto text-xs">
                      {gt.member_count}
                    </Chip>
                  </div>
                </SelectItem>
              ))}
            </Select>
            {groupTypes.length === 0 && (
              <p className="text-xs text-default-400 mt-1">{t('groups.edit_no_types')}</p>
            )}
          </CardBody>
        </Card>

        {/* Visibility & Status */}
        <Card>
          <CardHeader className="flex items-center gap-2 pb-0">
            <Shield size={18} className="text-default-500" />
            <h3 className="font-semibold">{t('groups.edit_section_visibility')}</h3>
          </CardHeader>
          <CardBody className="flex flex-col gap-4">
            <Select
              label={t('groups.edit_label_visibility')}
              selectedKeys={[visibility]}
              onSelectionChange={(keys) => setVisibility(Array.from(keys)[0] as string)}
              variant="bordered"
            >
              <SelectItem key="public">{t('groups.visibility_public')}</SelectItem>
              <SelectItem key="private">{t('groups.visibility_private')}</SelectItem>
            </Select>
            <Select
              label={t('groups.edit_label_status')}
              selectedKeys={[status]}
              onSelectionChange={(keys) => setStatus(Array.from(keys)[0] as string)}
              variant="bordered"
            >
              <SelectItem key="active">{t('groups.status_active')}</SelectItem>
              <SelectItem key="inactive">{t('groups.status_inactive')}</SelectItem>
              <SelectItem key="archived">{t('groups.status_archived')}</SelectItem>
            </Select>
          </CardBody>
        </Card>

        {/* Federation — only shown when the tenant has federation enabled */}
        {hasFeature('federation') && (
          <Card>
            <CardHeader className="flex items-center gap-2 pb-0">
              <Globe size={18} className="text-default-500" />
              <h3 className="font-semibold">{t('groups.edit_section_federation')}</h3>
            </CardHeader>
            <CardBody>
              <Select
                label={t('groups.edit_label_federated_visibility')}
                selectedKeys={[federatedVisibility]}
                onSelectionChange={(keys) => setFederatedVisibility(Array.from(keys)[0] as 'none' | 'listed' | 'joinable')}
                variant="bordered"
                description={t('groups.edit_federated_desc')}
              >
                <SelectItem key="none">{t('groups.federated_none')}</SelectItem>
                <SelectItem key="listed">{t('groups.federated_listed')}</SelectItem>
                <SelectItem key="joinable">{t('groups.federated_joinable')}</SelectItem>
              </Select>
            </CardBody>
          </Card>
        )}

        {/* Admin Controls */}
        <Card>
          <CardHeader className="flex items-center gap-2 pb-0">
            <Star size={18} className="text-default-500" />
            <h3 className="font-semibold">{t('groups.edit_section_admin')}</h3>
          </CardHeader>
          <CardBody>
            <div className="flex items-center justify-between py-1">
              <div>
                <p className="text-sm font-medium">{t('groups.edit_label_featured')}</p>
                <p className="text-xs text-default-400">{t('groups.edit_featured_desc')}</p>
              </div>
              <Switch
                isSelected={isFeatured}
                onValueChange={setIsFeatured}
                aria-label={t('groups.edit_label_featured')}
              />
            </div>
          </CardBody>
        </Card>

        {/* Branding */}
        <Card>
          <CardHeader className="flex items-center gap-2 pb-0">
            <Palette size={18} className="text-default-500" />
            <h3 className="font-semibold">{t('groups.edit_section_branding')}</h3>
          </CardHeader>
          <CardBody className="flex flex-col gap-4">
            <div className="grid grid-cols-2 gap-4">
              <div className="flex flex-col gap-1.5">
                <label className="text-sm font-medium text-default-600">{t('groups.edit_label_primary_color')}</label>
                <div className="flex items-center gap-2">
                  <input
                    type="color"
                    value={primaryColor || '#6366f1'}
                    onChange={(e) => setPrimaryColor(e.target.value)}
                    className="w-10 h-10 rounded cursor-pointer border border-default-200"
                    aria-label={t('groups.edit_label_primary_color')}
                  />
                  <Input
                    value={primaryColor}
                    onValueChange={(v) => setPrimaryColor(v)}
                    variant="bordered"
                    size="sm"
                    placeholder="#6366f1"
                    maxLength={7}
                    className="flex-1"
                  />
                  {primaryColor && (
                    <Button size="sm" variant="light" isIconOnly onPress={() => setPrimaryColor('')}>×</Button>
                  )}
                </div>
              </div>
              <div className="flex flex-col gap-1.5">
                <label className="text-sm font-medium text-default-600">{t('groups.edit_label_accent_color')}</label>
                <div className="flex items-center gap-2">
                  <input
                    type="color"
                    value={accentColor || '#8b5cf6'}
                    onChange={(e) => setAccentColor(e.target.value)}
                    className="w-10 h-10 rounded cursor-pointer border border-default-200"
                    aria-label={t('groups.edit_label_accent_color')}
                  />
                  <Input
                    value={accentColor}
                    onValueChange={(v) => setAccentColor(v)}
                    variant="bordered"
                    size="sm"
                    placeholder="#8b5cf6"
                    maxLength={7}
                    className="flex-1"
                  />
                  {accentColor && (
                    <Button size="sm" variant="light" isIconOnly onPress={() => setAccentColor('')}>×</Button>
                  )}
                </div>
              </div>
            </div>
          </CardBody>
        </Card>

        {/* Stats (read-only) */}
        {group?.stats && (
          <Card>
            <CardBody>
              <div className="grid grid-cols-3 gap-4 text-center">
                <div>
                  <p className="text-2xl font-bold">{group.member_count}</p>
                  <p className="text-xs text-default-500">{t('groups.col_members')}</p>
                </div>
                <div>
                  <p className="text-2xl font-bold">{group.stats.posts_count}</p>
                  <p className="text-xs text-default-500">{t('groups.edit_stat_posts')}</p>
                </div>
                <div>
                  <p className="text-2xl font-bold">{group.stats.events_count}</p>
                  <p className="text-xs text-default-500">{t('groups.edit_stat_events')}</p>
                </div>
              </div>
            </CardBody>
          </Card>
        )}

        {/* Actions */}
        <div className="flex justify-end gap-3">
          <Button
            variant="flat"
            onPress={() => navigate(tenantPath('/admin/groups'))}
          >
            {t('groups.edit_cancel')}
          </Button>
          <Button
            color="primary"
            startContent={<Save size={16} />}
            onPress={handleSave}
            isLoading={submitting}
            isDisabled={submitting || !name.trim()}
          >
            {t('groups.edit_save')}
          </Button>
        </div>
      </div>
    </div>
  );
}

export default GroupEdit;
