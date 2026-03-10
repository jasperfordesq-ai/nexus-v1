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
import {
  Save,
  Eye,
  Search,
  MessageSquare,
  FileText,
  Download,
  RefreshCw,
  Trash2,
  PenLine,
  Ban,
  Scale,
  Info,
  FileCheck,
  Upload,
  Globe,
  ChevronRight,
} from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { useNavigate } from 'react-router-dom';
import { useTenant } from '@/contexts';

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
    <div className="flex items-center justify-between p-4 rounded-lg bg-theme-elevated">
      <div>
        <p className="font-medium text-theme-primary">{label}</p>
        <p className="text-sm text-theme-subtle">{description}</p>
      </div>
      <Switch
        aria-label={label}
        isSelected={checked}
        onValueChange={onChange}
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
  const navigate = useNavigate();
  const { tenantPath } = useTenant();

  const selectClassNames = {
    trigger: 'bg-theme-elevated border-theme-default',
    value: 'text-theme-primary',
    label: 'text-theme-muted',
  };

  return (
    <div className="space-y-6">
      <GlassCard className="p-6">
        <h2 className="text-lg font-semibold text-theme-primary mb-6">Privacy Settings</h2>

        <div className="space-y-6">
          {/* Profile Visibility */}
          <div className="space-y-4">
            <h3 className="text-sm font-medium text-theme-muted flex items-center gap-2">
              <Eye className="w-4 h-4" aria-hidden="true" />
              Profile Visibility
            </h3>

            <Select
              label="Who can see your profile"
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
              <SelectItem key="public">Public - Anyone can view</SelectItem>
              <SelectItem key="members">Members Only - Community members</SelectItem>
              <SelectItem key="connections">Connections Only - Your connections</SelectItem>
            </Select>
          </div>

          {/* Search & Discovery */}
          <div className="pt-4 border-t border-theme-default space-y-4">
            <h3 className="text-sm font-medium text-theme-muted flex items-center gap-2">
              <Search className="w-4 h-4" aria-hidden="true" />
              Search &amp; Discovery
            </h3>

            <SettingToggle
              label="Search Engine Indexing"
              description="Allow search engines to index your profile"
              checked={privacy.search_indexing}
              onChange={(checked) => onPrivacyChange((prev) => ({ ...prev, search_indexing: checked }))}
            />
          </div>

          {/* Contact Preferences */}
          <div className="pt-4 border-t border-theme-default space-y-4">
            <h3 className="text-sm font-medium text-theme-muted flex items-center gap-2">
              <MessageSquare className="w-4 h-4" aria-hidden="true" />
              Contact Preferences
            </h3>

            <SettingToggle
              label="Allow Contact"
              description="Allow other members to contact me"
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
            Save Privacy Settings
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
                  <p className="font-medium">Federation Settings</p>
                  <p className="text-sm text-theme-subtle font-normal">Manage your visibility and preferences across partner communities</p>
                </div>
              </div>
            }
            endContent={<ChevronRight className="w-4 h-4 text-theme-muted" aria-hidden="true" />}
            onPress={() => navigate(tenantPath('/federation/settings'))}
          />
        </GlassCard>
      )}

      {/* GDPR Section */}
      <GlassCard className="p-6">
        <h2 className="text-lg font-semibold text-theme-primary mb-2 flex items-center gap-2">
          <FileText className="w-5 h-5 text-indigo-600 dark:text-indigo-400" aria-hidden="true" />
          Data &amp; Privacy Rights
        </h2>
        <p className="text-theme-subtle text-sm mb-6">
          Under the General Data Protection Regulation (GDPR), you have the right to access, export, and request
          deletion of your personal data. These requests are processed within 30 days.
        </p>

        <div className="space-y-3">
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
              <p className="font-medium">Download My Data</p>
              <p className="text-sm text-theme-subtle font-normal">Get a copy of all your personal data</p>
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
              <p className="font-medium">Data Portability Request</p>
              <p className="text-sm text-theme-subtle font-normal">Export data in a machine-readable format</p>
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
              <p className="font-medium text-red-600 dark:text-red-400">Request Data Deletion</p>
              <p className="text-sm text-theme-subtle font-normal">Request permanent deletion of your data</p>
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
              <p className="font-medium">Data Rectification</p>
              <p className="text-sm text-theme-subtle font-normal">Request correction of inaccurate personal data</p>
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
              <p className="font-medium">Restriction of Processing</p>
              <p className="text-sm text-theme-subtle font-normal">Request restriction of your data processing</p>
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
              <p className="font-medium">Right to Object</p>
              <p className="text-sm text-theme-subtle font-normal">Object to processing of your personal data</p>
            </div>
          </Button>
        </div>

        <div className="mt-4 p-3 rounded-lg bg-blue-500/10 border border-blue-500/20">
          <p className="text-sm text-theme-muted flex items-start gap-2">
            <Info className="w-4 h-4 text-blue-500 flex-shrink-0 mt-0.5" aria-hidden="true" />
            All six GDPR data subject rights are available above. Contact our Data Protection Officer for any additional concerns.
          </p>
        </div>
      </GlassCard>

      {/* Insurance Certificates — gated behind compliance flag */}
      {insuranceEnabled && (
        <GlassCard className="p-6">
          <h2 className="text-lg font-semibold text-theme-primary mb-2 flex items-center gap-2">
            <FileCheck className="w-5 h-5 text-emerald-600 dark:text-emerald-400" aria-hidden="true" />
            Insurance Certificates
          </h2>
          <p className="text-theme-subtle text-sm mb-4">
            Upload your insurance certificates for verification. Accepted formats: PDF, JPG, PNG (max 10MB).
          </p>

          {insuranceLoading ? (
            <div className="flex items-center gap-2 text-sm text-theme-muted">
              <RefreshCw className="w-4 h-4 animate-spin" />
              Loading certificates...
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
                          {cert.insurance_type.replace(/_/g, ' ').replace(/\b\w/g, (c: string) => c.toUpperCase())}
                        </p>
                        <p className="text-xs text-theme-muted">
                          {cert.provider_name || 'Unknown provider'}
                          {cert.expiry_date ? ` — Expires ${new Date(cert.expiry_date).toLocaleDateString()}` : ''}
                        </p>
                      </div>
                      <span className={`text-xs px-2 py-1 rounded-full font-medium capitalize ${
                        cert.status === 'verified' ? 'bg-emerald-500/20 text-emerald-600 dark:text-emerald-400'
                          : cert.status === 'pending' || cert.status === 'submitted' ? 'bg-amber-500/20 text-amber-600 dark:text-amber-400'
                          : cert.status === 'rejected' ? 'bg-red-500/20 text-red-600 dark:text-red-400'
                          : 'bg-default-200 text-default-600'
                      }`}>
                        {cert.status}
                      </span>
                    </div>
                  ))}
                </div>
              )}

              <div className="flex flex-col sm:flex-row items-start sm:items-end gap-3">
                <Select
                  label="Insurance Type"
                  selectedKeys={[insuranceType]}
                  onSelectionChange={(keys) => {
                    const val = Array.from(keys)[0] as string;
                    if (val) onInsuranceTypeChange(val);
                  }}
                  variant="bordered"
                  size="sm"
                  className="max-w-xs"
                >
                  <SelectItem key="public_liability">Public Liability</SelectItem>
                  <SelectItem key="professional_indemnity">Professional Indemnity</SelectItem>
                  <SelectItem key="employers_liability">{"Employer's Liability"}</SelectItem>
                  <SelectItem key="product_liability">Product Liability</SelectItem>
                  <SelectItem key="personal_accident">Personal Accident</SelectItem>
                  <SelectItem key="other">Other</SelectItem>
                </Select>
                <label className="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-theme-elevated hover:bg-theme-hover cursor-pointer transition-colors border border-default-200">
                  <Upload className="w-4 h-4 text-theme-primary" />
                  <span className="text-sm font-medium text-theme-primary">
                    {insuranceUploading ? 'Uploading...' : 'Upload Certificate'}
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
