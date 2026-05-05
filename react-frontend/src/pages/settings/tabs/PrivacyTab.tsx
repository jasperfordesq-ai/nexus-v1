// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import {
  Button,
  Switch,
  Select,
  SelectItem,
} from '@heroui/react';
import Save from 'lucide-react/icons/save';
import Eye from 'lucide-react/icons/eye';
import Search from 'lucide-react/icons/search';
import MessageSquare from 'lucide-react/icons/message-square';
import FileText from 'lucide-react/icons/file-text';
import Download from 'lucide-react/icons/download';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import Trash2 from 'lucide-react/icons/trash-2';
import PenLine from 'lucide-react/icons/pen-line';
import Ban from 'lucide-react/icons/ban';
import Scale from 'lucide-react/icons/scale';
import Info from 'lucide-react/icons/info';
import FileCheck from 'lucide-react/icons/file-check';
import Upload from 'lucide-react/icons/upload';
import Globe from 'lucide-react/icons/globe';
import ChevronRight from 'lucide-react/icons/chevron-right';
import { GlassCard } from '@/components/ui';
import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { useTenant } from '@/contexts';

const INSURANCE_TYPE_KEYS = [
  'public_liability',
  'professional_indemnity',
  'employers_liability',
  'product_liability',
  'personal_accident',
  'other',
] as const;

const INSURANCE_STATUS_KEYS = ['verified', 'pending', 'submitted', 'rejected'] as const;

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

export interface PrivacySettings {
  profile_visibility: 'public' | 'members' | 'connections';
  search_indexing: boolean;
  contact_permission: boolean;
}

export interface UserInsuranceCert {
  id: number;
  insurance_type: string;
  provider_name: string | null;
  status: string;
  expiry_date: string | null;
  created_at: string;
}

interface PrivacyTabProps {
  privacy: PrivacySettings;
  isSavingPrivacy: boolean;
  insuranceCerts: UserInsuranceCert[];
  insuranceLoading: boolean;
  insuranceUploading: boolean;
  insuranceType: string;
  insuranceEnabled: boolean;
  federationEnabled: boolean;
  onPrivacyChange: (updater: (prev: PrivacySettings) => PrivacySettings) => void;
  onSavePrivacy: () => void;
  onInsuranceUpload: (event: React.ChangeEvent<HTMLInputElement>) => void;
  onInsuranceTypeChange: (value: string) => void;
  onOpenGdprModal: (type: string) => void;
}

// ─────────────────────────────────────────────────────────────────────────────
// SettingToggle helper
// ─────────────────────────────────────────────────────────────────────────────

interface SettingToggleProps {
  label: string;
  description: string;
  checked: boolean;
  onChange: (checked: boolean) => void;
}

function SettingToggle({ label, description, checked, onChange }: SettingToggleProps) {
  return (
    <div className="flex items-center justify-between gap-4 p-4 rounded-lg bg-theme-elevated">
      <div className="min-w-0">
        <p className="font-medium text-theme-primary">{label}</p>
        <p className="text-sm text-theme-subtle">{description}</p>
      </div>
      <Switch
        aria-label={label}
        isSelected={checked}
        onValueChange={onChange}
        className="shrink-0"
        classNames={{
          wrapper: 'group-data-[selected=true]:bg-indigo-500',
        }}
      />
    </div>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export function PrivacyTab({
  privacy,
  isSavingPrivacy,
  insuranceCerts,
  insuranceLoading,
  insuranceUploading,
  insuranceType,
  insuranceEnabled,
  federationEnabled,
  onPrivacyChange,
  onSavePrivacy,
  onInsuranceUpload,
  onInsuranceTypeChange,
  onOpenGdprModal,
}: PrivacyTabProps) {
  const { t } = useTranslation('settings');
  const navigate = useNavigate();
  const { tenantPath } = useTenant();

  const getInsuranceTypeLabel = (type: string) => (
    INSURANCE_TYPE_KEYS.includes(type as (typeof INSURANCE_TYPE_KEYS)[number])
      ? t(`insurance.${type}`)
      : t('insurance.other')
  );

  const getInsuranceStatusLabel = (status: string) => (
    INSURANCE_STATUS_KEYS.includes(status as (typeof INSURANCE_STATUS_KEYS)[number])
      ? t(`insurance.status_${status}`)
      : t('insurance.status_unknown')
  );

  const selectClassNames = {
    trigger: 'bg-theme-elevated border-theme-default',
    value: 'text-theme-primary',
    label: 'text-theme-muted',
  };

  return (
    <div className="space-y-6">
      <GlassCard className="p-6">
        <h2 className="text-lg font-semibold text-theme-primary mb-6">{t('privacy_sections.title')}</h2>

        <div className="space-y-6">
          {/* Profile Visibility */}
          <div className="space-y-4">
            <h3 className="text-sm font-medium text-theme-muted flex items-center gap-2">
              <Eye className="w-4 h-4" aria-hidden="true" />
              {t('privacy_sections.profile_visibility')}
            </h3>

            <Select
              label={t('privacy_prefs.profile_visibility')}
              selectedKeys={[privacy.profile_visibility]}
              onSelectionChange={(keys) => {
                const value = Array.from(keys)[0] as string;
                if (value) {
                  onPrivacyChange((prev) => ({
                    ...prev,
                    profile_visibility: value as 'public' | 'members' | 'connections',
                  }));
                }
              }}
              classNames={selectClassNames}
            >
              <SelectItem key="public">{t('visibility_options.public')}</SelectItem>
              <SelectItem key="members">{t('visibility_options.members')}</SelectItem>
              <SelectItem key="connections">{t('visibility_options.connections')}</SelectItem>
            </Select>
          </div>

          {/* Search & Discovery */}
          <div className="pt-4 border-t border-theme-default space-y-4">
            <h3 className="text-sm font-medium text-theme-muted flex items-center gap-2">
              <Search className="w-4 h-4" aria-hidden="true" />
              {t('privacy_sections.search_discovery')}
            </h3>

            <SettingToggle
              label={t('privacy_prefs.search_indexing')}
              description={t('privacy_descriptions.search_indexing')}
              checked={privacy.search_indexing}
              onChange={(checked) => onPrivacyChange((prev) => ({ ...prev, search_indexing: checked }))}
            />
          </div>

          {/* Contact Preferences */}
          <div className="pt-4 border-t border-theme-default space-y-4">
            <h3 className="text-sm font-medium text-theme-muted flex items-center gap-2">
              <MessageSquare className="w-4 h-4" aria-hidden="true" />
              {t('privacy_sections.contact_preferences')}
            </h3>

            <SettingToggle
              label={t('privacy_prefs.allow_contact')}
              description={t('privacy_descriptions.allow_contact')}
              checked={privacy.contact_permission}
              onChange={(checked) => onPrivacyChange((prev) => ({ ...prev, contact_permission: checked }))}
            />
          </div>

          <Button
            onPress={onSavePrivacy}
            className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
            startContent={<Save className="w-4 h-4" aria-hidden="true" />}
            isLoading={isSavingPrivacy}
          >
            {t('save_privacy')}
          </Button>
        </div>
      </GlassCard>

      {/* Federation Settings Link */}
      {federationEnabled && (
        <GlassCard className="p-6">
          <Button
            variant="flat"
            className="w-full justify-between bg-theme-elevated text-theme-primary h-auto py-4 px-4"
            startContent={
              <div className="flex items-center gap-3">
                <div className="p-2 rounded-lg bg-indigo-500/20">
                  <Globe className="w-4 h-4 text-indigo-600 dark:text-indigo-400" aria-hidden="true" />
                </div>
                <div className="text-left">
                  <p className="font-medium">{t('federation.title')}</p>
                  <p className="text-sm text-theme-subtle font-normal">{t('federation.description')}</p>
                </div>
              </div>
            }
            endContent={<ChevronRight className="w-4 h-4 text-theme-muted" aria-hidden="true" />}
            onPress={() => navigate(tenantPath('/federation/settings'))}
          />
        </GlassCard>
      )}

      {/* Blocked Users */}
      <GlassCard className="p-6">
        <Button
          variant="flat"
          className="w-full justify-between bg-theme-elevated text-theme-primary h-auto py-3 px-4"
          startContent={
            <div className="flex items-center gap-3">
              <div className="p-2 rounded-lg bg-red-500/20">
                <Ban className="w-4 h-4 text-red-600 dark:text-red-400" aria-hidden="true" />
              </div>
              <div className="text-left">
                <p className="font-medium">{t('blocked_users.title')}</p>
                <p className="text-sm text-theme-subtle font-normal">{t('blocked_users.description')}</p>
              </div>
            </div>
          }
          endContent={<ChevronRight className="w-4 h-4 text-theme-muted" aria-hidden="true" />}
          onPress={() => navigate(tenantPath('/settings/blocked'))}
        />
      </GlassCard>

      {/* GDPR Section */}
      <GlassCard className="p-6">
        <h2 className="text-lg font-semibold text-theme-primary mb-2 flex items-center gap-2">
          <FileText className="w-5 h-5 text-indigo-600 dark:text-indigo-400" aria-hidden="true" />
          {t('gdpr.title')}
        </h2>
        <p className="text-theme-subtle text-sm mb-6">
          {t('gdpr.description')}
        </p>

        <div className="space-y-3">
          <Button
            variant="flat"
            className="w-full justify-start bg-theme-elevated text-theme-primary h-auto py-3 px-4"
            startContent={
              <div className="p-2 rounded-lg bg-indigo-500/20">
                <Download className="w-4 h-4 text-indigo-600 dark:text-indigo-400" aria-hidden="true" />
              </div>
            }
            endContent={<ChevronRight className="w-4 h-4 text-theme-muted" aria-hidden="true" />}
            onPress={() => navigate(tenantPath('/settings/data-export'))}
          >
            <div className="text-left">
              <p className="font-medium">{t('data_export.title', { ns: 'common' })}</p>
              <p className="text-sm text-theme-subtle font-normal">{t('data_export.subtitle', { ns: 'common' })}</p>
            </div>
          </Button>

          <Button
            variant="flat"
            className="w-full justify-start bg-theme-elevated text-theme-primary h-auto py-3 px-4"
            startContent={
              <div className="p-2 rounded-lg bg-blue-500/20">
                <Download className="w-4 h-4 text-blue-600 dark:text-blue-400" aria-hidden="true" />
              </div>
            }
            onPress={() => onOpenGdprModal('download')}
          >
            <div className="text-left">
              <p className="font-medium">{t('gdpr.download_title')}</p>
              <p className="text-sm text-theme-subtle font-normal">{t('gdpr.download_desc')}</p>
            </div>
          </Button>

          <Button
            variant="flat"
            className="w-full justify-start bg-theme-elevated text-theme-primary h-auto py-3 px-4"
            startContent={
              <div className="p-2 rounded-lg bg-emerald-500/20">
                <RefreshCw className="w-4 h-4 text-emerald-600 dark:text-emerald-400" aria-hidden="true" />
              </div>
            }
            onPress={() => onOpenGdprModal('portability')}
          >
            <div className="text-left">
              <p className="font-medium">{t('gdpr.portability_title')}</p>
              <p className="text-sm text-theme-subtle font-normal">{t('gdpr.portability_desc')}</p>
            </div>
          </Button>

          <Button
            variant="flat"
            className="w-full justify-start bg-red-500/10 text-theme-primary h-auto py-3 px-4"
            startContent={
              <div className="p-2 rounded-lg bg-red-500/20">
                <Trash2 className="w-4 h-4 text-red-600 dark:text-red-400" aria-hidden="true" />
              </div>
            }
            onPress={() => onOpenGdprModal('deletion')}
          >
            <div className="text-left">
              <p className="font-medium text-red-600 dark:text-red-400">{t('gdpr.deletion_title')}</p>
              <p className="text-sm text-theme-subtle font-normal">{t('gdpr.deletion_desc')}</p>
            </div>
          </Button>

          <Button
            variant="flat"
            className="w-full justify-start bg-theme-elevated text-theme-primary h-auto py-3 px-4"
            startContent={
              <div className="p-2 rounded-lg bg-amber-500/20">
                <PenLine className="w-4 h-4 text-amber-600 dark:text-amber-400" aria-hidden="true" />
              </div>
            }
            onPress={() => onOpenGdprModal('rectification')}
          >
            <div className="text-left">
              <p className="font-medium">{t('gdpr.rectification_title')}</p>
              <p className="text-sm text-theme-subtle font-normal">{t('gdpr.rectification_desc')}</p>
            </div>
          </Button>

          <Button
            variant="flat"
            className="w-full justify-start bg-theme-elevated text-theme-primary h-auto py-3 px-4"
            startContent={
              <div className="p-2 rounded-lg bg-orange-500/20">
                <Ban className="w-4 h-4 text-orange-600 dark:text-orange-400" aria-hidden="true" />
              </div>
            }
            onPress={() => onOpenGdprModal('restriction')}
          >
            <div className="text-left">
              <p className="font-medium">{t('gdpr.restriction_title')}</p>
              <p className="text-sm text-theme-subtle font-normal">{t('gdpr.restriction_desc')}</p>
            </div>
          </Button>

          <Button
            variant="flat"
            className="w-full justify-start bg-theme-elevated text-theme-primary h-auto py-3 px-4"
            startContent={
              <div className="p-2 rounded-lg bg-violet-500/20">
                <Scale className="w-4 h-4 text-violet-600 dark:text-violet-400" aria-hidden="true" />
              </div>
            }
            onPress={() => onOpenGdprModal('objection')}
          >
            <div className="text-left">
              <p className="font-medium">{t('gdpr.objection_title')}</p>
              <p className="text-sm text-theme-subtle font-normal">{t('gdpr.objection_desc')}</p>
            </div>
          </Button>
        </div>

        <div className="mt-4 p-3 rounded-lg bg-blue-500/10 border border-blue-500/20">
          <p className="text-sm text-theme-muted flex items-start gap-2">
            <Info className="w-4 h-4 text-[var(--color-info)] flex-shrink-0 mt-0.5" aria-hidden="true" />
            {t('gdpr.info')}
          </p>
        </div>
      </GlassCard>

      {/* Insurance Certificates — gated behind compliance flag */}
      {insuranceEnabled && (
        <GlassCard className="p-6">
          <h2 className="text-lg font-semibold text-theme-primary mb-2 flex items-center gap-2">
            <FileCheck className="w-5 h-5 text-emerald-600 dark:text-emerald-400" aria-hidden="true" />
            {t('insurance.title')}
          </h2>
          <p className="text-theme-subtle text-sm mb-4">
            {t('insurance.description')}
          </p>

          {insuranceLoading ? (
            <div className="flex items-center gap-2 text-sm text-theme-muted">
              <RefreshCw className="w-4 h-4 animate-spin" />
              {t('insurance.loading')}
            </div>
          ) : (
            <>
              {insuranceCerts.length > 0 && (
                <div className="space-y-2 mb-4">
                  {insuranceCerts.map((cert) => (
                    <div
                      key={cert.id}
                      className="flex items-center justify-between rounded-lg border border-default-200 bg-theme-elevated p-3"
                    >
                      <div>
                        <p className="text-sm font-medium text-theme-primary">
                          {getInsuranceTypeLabel(cert.insurance_type)}
                        </p>
                        <p className="text-xs text-theme-muted">
                          {cert.provider_name || t('insurance.unknown_provider')}
                          {cert.expiry_date ? ` ${t('insurance.expires', { date: new Date(cert.expiry_date).toLocaleDateString() })}` : ''}
                        </p>
                      </div>
                      <span className={`text-xs px-2 py-1 rounded-full font-medium ${
                        cert.status === 'verified' ? 'bg-emerald-500/20 text-emerald-600 dark:text-emerald-400'
                          : cert.status === 'pending' || cert.status === 'submitted' ? 'bg-amber-500/20 text-amber-600 dark:text-amber-400'
                          : cert.status === 'rejected' ? 'bg-red-500/20 text-red-600 dark:text-red-400'
                          : 'bg-default-200 text-default-600'
                      }`}>
                        {getInsuranceStatusLabel(cert.status)}
                      </span>
                    </div>
                  ))}
                </div>
              )}

              <div className="flex flex-col sm:flex-row items-start sm:items-end gap-3">
                <Select
                  label={t('insurance.type_label')}
                  selectedKeys={[insuranceType]}
                  onSelectionChange={(keys) => {
                    const val = Array.from(keys)[0] as string;
                    if (val) onInsuranceTypeChange(val);
                  }}
                  variant="bordered"
                  size="sm"
                  className="max-w-xs"
                >
                  <SelectItem key="public_liability">{t('insurance.public_liability')}</SelectItem>
                  <SelectItem key="professional_indemnity">{t('insurance.professional_indemnity')}</SelectItem>
                  <SelectItem key="employers_liability">{t('insurance.employers_liability')}</SelectItem>
                  <SelectItem key="product_liability">{t('insurance.product_liability')}</SelectItem>
                  <SelectItem key="personal_accident">{t('insurance.personal_accident')}</SelectItem>
                  <SelectItem key="other">{t('insurance.other')}</SelectItem>
                </Select>
                <label className="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-theme-elevated hover:bg-theme-hover cursor-pointer transition-colors border border-default-200">
                  <Upload className="w-4 h-4 text-theme-primary" />
                  <span className="text-sm font-medium text-theme-primary">
                    {insuranceUploading ? t('insurance.uploading') : t('insurance.upload_certificate')}
                  </span>
                  <input
                    type="file"
                    accept=".pdf,.jpg,.jpeg,.png"
                    className="hidden"
                    onChange={onInsuranceUpload}
                    disabled={insuranceUploading}
                  />
                </label>
              </div>
            </>
          )}
        </GlassCard>
      )}
    </div>
  );
}
