// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Create Volunteer Opportunity Page
 *
 * Allows organisation owners/admins to post new volunteer opportunities.
 * Requires the user to own at least one approved organisation.
 */

import { useState, useEffect } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { motion } from 'framer-motion';
import { Button, Input, Textarea, Select, SelectItem, DatePicker } from '@heroui/react';
import type { DateInputValue } from '@heroui/react';
import { today, getLocalTimeZone } from '@internationalized/date';
import { Save, Heart, Building2, Briefcase, Wrench, AlertTriangle } from 'lucide-react';
import { PlaceAutocompleteInput } from '@/components/location';
import { GlassCard } from '@/components/ui';
import { Breadcrumbs } from '@/components/navigation';
import { LoadingScreen } from '@/components/feedback';
import { useToast, useTenant } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import { usePageTitle } from '@/hooks';

interface MyOrganisation {
  id: number;
  name: string;
  status: string;
  member_role: string;
}

interface FormData {
  organization_id: string;
  title: string;
  description: string;
  location: string;
  skills_needed: string;
  start_date: DateInputValue | null;
  end_date: DateInputValue | null;
}

const initialFormData: FormData = {
  organization_id: '',
  title: '',
  description: '',
  location: '',
  skills_needed: '',
  start_date: null,
  end_date: null,
};

export default function CreateOpportunityPage() {
  const { t } = useTranslation('community');
  usePageTitle(t('volunteering.create_opportunity_title'));
  const navigate = useNavigate();
  const { tenantPath } = useTenant();
  const toast = useToast();

  const [formData, setFormData] = useState<FormData>(initialFormData);
  const [approvedOrgs, setApprovedOrgs] = useState<MyOrganisation[]>([]);
  const [isLoadingOrgs, setIsLoadingOrgs] = useState(true);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [errors, setErrors] = useState<Partial<Record<keyof FormData, string>>>({});

  useEffect(() => {
    loadMyOrganisations();
  }, []);

  async function loadMyOrganisations() {
    try {
      setIsLoadingOrgs(true);
      const response = await api.get<MyOrganisation[]>('/v2/volunteering/my-organisations');
      if (response.success && response.data) {
        const orgs = (Array.isArray(response.data) ? response.data : []);
        const approved = orgs.filter(
          (org) => org.status === 'approved' && ['owner', 'admin'].includes(org.member_role),
        );
        setApprovedOrgs(approved);

        // Auto-select if only one approved org
        if (approved.length === 1) {
          setFormData((prev) => ({ ...prev, organization_id: approved[0].id.toString() }));
        }
      }
    } catch (error) {
      logError('Failed to load organisations', error);
    } finally {
      setIsLoadingOrgs(false);
    }
  }

  function updateField<K extends keyof FormData>(field: K, value: FormData[K]) {
    setFormData((prev) => ({ ...prev, [field]: value }));
    if (errors[field]) {
      setErrors((prev) => ({ ...prev, [field]: undefined }));
    }
  }

  function validateForm(): boolean {
    const newErrors: Partial<Record<keyof FormData, string>> = {};

    if (!formData.organization_id) {
      newErrors.organization_id = t('volunteering.form_org_required');
    }

    if (!formData.title.trim()) {
      newErrors.title = t('volunteering.form_title_required');
    } else if (formData.title.trim().length < 5) {
      newErrors.title = t('volunteering.form_title_min_length');
    }

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();

    if (!validateForm()) return;

    try {
      setIsSubmitting(true);

      const payload: Record<string, unknown> = {
        organization_id: parseInt(formData.organization_id),
        title: formData.title.trim(),
        description: formData.description.trim(),
        location: formData.location.trim(),
        skills_needed: formData.skills_needed.trim(),
      };

      if (formData.start_date) {
        payload.start_date = formData.start_date.toString();
      }
      if (formData.end_date) {
        payload.end_date = formData.end_date.toString();
      }

      const response = await api.post('/v2/volunteering/opportunities', payload);

      if (response.success) {
        toast.success(t('volunteering.form_success'));
        navigate(tenantPath('/volunteering'));
      } else {
        toast.error(t('volunteering.form_save_error'));
      }
    } catch (error) {
      logError('Failed to create opportunity', error);
      toast.error(t('volunteering.form_save_error'));
    } finally {
      setIsSubmitting(false);
    }
  }

  if (isLoadingOrgs) {
    return <LoadingScreen message="Loading..." />;
  }

  // No approved organisations — show message
  if (approvedOrgs.length === 0) {
    return (
      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        className="max-w-2xl mx-auto space-y-6"
      >
        <Breadcrumbs items={[
          { label: t('volunteering.heading'), href: '/volunteering' },
          { label: t('volunteering.create_opportunity_title') },
        ]} />

        <GlassCard className="p-8 text-center">
          <AlertTriangle className="w-12 h-12 text-amber-500 mx-auto mb-4" aria-hidden="true" />
          <h2 className="text-lg font-semibold text-theme-primary mb-2">
            {t('volunteering.no_approved_orgs_title')}
          </h2>
          <p className="text-theme-muted mb-6">
            {t('volunteering.no_approved_orgs_description')}
          </p>
          <div className="flex justify-center gap-3">
            <Link to={tenantPath('/organisations/register')}>
              <Button className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white">
                {t('volunteering.register_org_link')}
              </Button>
            </Link>
            <Link to={tenantPath('/volunteering')}>
              <Button variant="flat" className="bg-theme-elevated text-theme-primary">
                {t('volunteering.form_cancel')}
              </Button>
            </Link>
          </div>
        </GlassCard>
      </motion.div>
    );
  }

  return (
    <motion.div
      initial={{ opacity: 0, y: 20 }}
      animate={{ opacity: 1, y: 0 }}
      className="max-w-2xl mx-auto space-y-6"
    >
      {/* Breadcrumbs */}
      <Breadcrumbs items={[
        { label: t('volunteering.heading'), href: '/volunteering' },
        { label: t('volunteering.create_opportunity_title') },
      ]} />

      {/* Form */}
      <GlassCard className="p-6 sm:p-8">
        <div className="mb-6">
          <h1 className="text-2xl font-bold text-theme-primary flex items-center gap-3">
            <Heart className="w-7 h-7 text-rose-500" aria-hidden="true" />
            {t('volunteering.create_opportunity_title')}
          </h1>
          <p className="text-theme-muted mt-1">
            {t('volunteering.create_opportunity_subtitle')}
          </p>
        </div>

        <form onSubmit={handleSubmit} className="space-y-6">
          {/* Organisation Selector */}
          {approvedOrgs.length > 1 ? (
            <Select
              label={t('volunteering.form_org_label')}
              placeholder={t('volunteering.form_org_placeholder')}
              selectedKeys={formData.organization_id ? [formData.organization_id] : []}
              onChange={(e) => updateField('organization_id', e.target.value)}
              isInvalid={!!errors.organization_id}
              errorMessage={errors.organization_id}
              startContent={<Building2 className="w-4 h-4 text-theme-subtle" />}
              classNames={{
                trigger: 'bg-theme-elevated border-theme-default',
                label: 'text-theme-muted',
                value: 'text-theme-primary',
              }}
            >
              {approvedOrgs.map((org) => (
                <SelectItem key={org.id.toString()}>
                  {org.name}
                </SelectItem>
              ))}
            </Select>
          ) : (
            <Input
              label={t('volunteering.form_org_label')}
              value={approvedOrgs[0]?.name ?? ''}
              isReadOnly
              startContent={<Building2 className="w-4 h-4 text-theme-subtle" />}
              classNames={{
                input: 'bg-transparent text-theme-primary',
                inputWrapper: 'bg-theme-elevated border-theme-default',
              }}
            />
          )}

          {/* Title */}
          <Input
            label={t('volunteering.form_title_label')}
            placeholder={t('volunteering.form_title_placeholder')}
            value={formData.title}
            onChange={(e) => updateField('title', e.target.value)}
            isInvalid={!!errors.title}
            errorMessage={errors.title}
            startContent={<Briefcase className="w-4 h-4 text-theme-subtle" />}
            classNames={{
              input: 'bg-transparent text-theme-primary',
              inputWrapper: 'bg-theme-elevated border-theme-default',
            }}
          />

          {/* Description */}
          <Textarea
            label={t('volunteering.form_desc_label')}
            placeholder={t('volunteering.form_desc_placeholder')}
            value={formData.description}
            onChange={(e) => updateField('description', e.target.value)}
            minRows={4}
            classNames={{
              input: 'bg-transparent text-theme-primary',
              inputWrapper: 'bg-theme-elevated border-theme-default',
            }}
          />

          {/* Location */}
          <PlaceAutocompleteInput
            label={t('volunteering.form_location_label')}
            placeholder={t('volunteering.form_location_placeholder')}
            value={formData.location}
            onChange={(val) => updateField('location', val)}
            onPlaceSelect={(place) => {
              setFormData((prev) => ({
                ...prev,
                location: place.formattedAddress,
              }));
            }}
            onClear={() => {
              setFormData((prev) => ({
                ...prev,
                location: '',
              }));
            }}
            classNames={{
              inputWrapper: 'bg-theme-elevated border-theme-default',
              label: 'text-theme-muted',
              input: 'text-theme-primary placeholder:text-theme-subtle',
            }}
          />

          {/* Skills Needed */}
          <Input
            label={t('volunteering.form_skills_label')}
            placeholder={t('volunteering.form_skills_placeholder')}
            value={formData.skills_needed}
            onChange={(e) => updateField('skills_needed', e.target.value)}
            startContent={<Wrench className="w-4 h-4 text-theme-subtle" />}
            classNames={{
              input: 'bg-transparent text-theme-primary',
              inputWrapper: 'bg-theme-elevated border-theme-default',
            }}
          />

          {/* Date Range */}
          <fieldset className="grid sm:grid-cols-2 gap-4">
            <legend className="sr-only">Opportunity date range</legend>
            <div>
              <DatePicker
                label={t('volunteering.form_start_date_label')}
                value={formData.start_date}
                onChange={(val) => updateField('start_date', val)}
                minValue={today(getLocalTimeZone())}
                classNames={{
                  inputWrapper: 'bg-theme-elevated border-theme-default',
                  label: 'text-theme-muted',
                }}
              />
            </div>
            <div>
              <DatePicker
                label={t('volunteering.form_end_date_label')}
                value={formData.end_date}
                onChange={(val) => updateField('end_date', val)}
                minValue={formData.start_date || today(getLocalTimeZone())}
                classNames={{
                  inputWrapper: 'bg-theme-elevated border-theme-default',
                  label: 'text-theme-muted',
                }}
              />
            </div>
          </fieldset>

          {/* Submit buttons */}
          <div className="flex gap-3 pt-4">
            <Button
              type="submit"
              className="flex-1 bg-gradient-to-r from-rose-500 to-pink-600 text-white"
              startContent={<Save className="w-4 h-4" />}
              isLoading={isSubmitting}
            >
              {t('volunteering.form_submit_opportunity')}
            </Button>
            <Link to={tenantPath('/volunteering')}>
              <Button
                type="button"
                variant="flat"
                className="bg-theme-elevated text-theme-primary"
              >
                {t('volunteering.form_cancel')}
              </Button>
            </Link>
          </div>
        </form>
      </GlassCard>
    </motion.div>
  );
}

export { CreateOpportunityPage };
