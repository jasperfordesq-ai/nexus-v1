// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState, useEffect, useCallback } from 'react';
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
  Textarea,
} from '@heroui/react';
import { ArrowLeft, Save, Users } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { adminGroups } from '../../api/adminApi';
import { PageHeader } from '../../components';
import type { AdminGroup } from '../../api/types';

export function GroupEdit() {
  const { t } = useTranslation('admin');
  const { id } = useParams<{ id: string }>();
  const { tenantPath } = useTenant();
  const toast = useToast();
  const navigate = useNavigate();

  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [group, setGroup] = useState<AdminGroup | null>(null);

  // Form fields
  const [name, setName] = useState('');
  const [description, setDescription] = useState('');
  const [visibility, setVisibility] = useState('public');
  const [location, setLocation] = useState('');
  const [status, setStatus] = useState('active');

  usePageTitle(group ? t('groups.edit_page_title', { name: group.name }) : t('groups.edit_page_title_loading'));

  const loadGroup = useCallback(async () => {
    if (!id) return;
    setLoading(true);
    const res = await adminGroups.getGroup(Number(id));
    if (res.success && res.data) {
      const g = res.data;
      setGroup(g);
      setName(g.name ?? '');
      setDescription(g.description ?? '');
      setVisibility(g.visibility ?? 'public');
      setLocation(g.location ?? '');
      setStatus(g.status === 'active' ? 'active' : 'inactive');
    } else {
      toast.error(t('groups.failed_to_load_group'));
      navigate(tenantPath('/admin/groups'));
    }
    setLoading(false);
  }, [id, navigate, tenantPath, toast, t]);

  useEffect(() => { loadGroup(); }, [loadGroup]);

  const handleSave = async () => {
    if (!id || !name.trim()) {
      toast.error(t('groups.edit_name_required'));
      return;
    }
    setSubmitting(true);
    const [updateRes, statusRes] = await Promise.all([
      adminGroups.updateGroup(Number(id), {
        name: name.trim(),
        description: description || undefined,
        visibility,
        location: location || undefined,
      }),
      group?.status !== status
        ? adminGroups.updateStatus(Number(id), status)
        : Promise.resolve({ success: true }),
    ]);

    if (updateRes.success && statusRes.success) {
      toast.success(t('groups.edit_saved', { name: name.trim() }));
      navigate(tenantPath('/admin/groups'));
    } else {
      toast.error(updateRes.error ?? t('groups.edit_save_failed'));
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

  return (
    <div className="max-w-2xl mx-auto px-4 pb-8">
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
              maxLength={120}
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
            />
          </CardBody>
        </Card>

        {/* Visibility & status */}
        <Card>
          <CardBody className="flex flex-col gap-4">
            <Select
              label={t('groups.edit_label_visibility')}
              selectedKeys={[visibility]}
              onSelectionChange={(keys) => setVisibility(Array.from(keys)[0] as string)}
              variant="bordered"
            >
              <SelectItem key="public">{t('groups.visibility_public')}</SelectItem>
              <SelectItem key="private">{t('groups.visibility_private')}</SelectItem>
              <SelectItem key="hidden">{t('groups.visibility_hidden')}</SelectItem>
            </Select>
            <Select
              label={t('groups.edit_label_status')}
              selectedKeys={[status]}
              onSelectionChange={(keys) => setStatus(Array.from(keys)[0] as string)}
              variant="bordered"
            >
              <SelectItem key="active">{t('groups.status_active')}</SelectItem>
              <SelectItem key="inactive">{t('groups.status_inactive')}</SelectItem>
            </Select>
          </CardBody>
        </Card>

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
