// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useRef } from 'react';
import type React from 'react';
import {
  Button,
  Input,
  Textarea,
  Avatar,
  Select,
  SelectItem,
} from '@heroui/react';
import {
  Save,
  Camera,
  Phone,
  Building2,
  Monitor,
} from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { PlaceAutocompleteInput } from '@/components/location';
import { resolveAvatarUrl } from '@/lib/helpers';
import { useTranslation } from 'react-i18next';
import { LanguageSwitcher } from '@/components/LanguageSwitcher';
import { useTheme } from '@/contexts';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

export interface ProfileFormData {
  first_name: string;
  last_name: string;
  name: string;
  phone: string;
  tagline: string;
  bio: string;
  location: string;
  latitude?: number;
  longitude?: number;
  avatar: string | null;
  profile_type: 'individual' | 'organisation';
  organization_name: string;
}

interface ProfileTabProps {
  profileData: ProfileFormData;
  isSaving: boolean;
  isUploading: boolean;
  onProfileDataChange: (updater: (prev: ProfileFormData) => ProfileFormData) => void;
  onSave: () => void;
  onAvatarUpload: (event: React.ChangeEvent<HTMLInputElement>) => void;
}

// ─────────────────────────────────────────────────────────────────────────────
// Common style classNames
// ─────────────────────────────────────────────────────────────────────────────

const inputClassNames = {
  input: 'bg-transparent text-theme-primary',
  inputWrapper: 'bg-theme-elevated border-theme-default',
  label: 'text-theme-muted',
};

const selectClassNames = {
  trigger: 'bg-theme-elevated border-theme-default',
  value: 'text-theme-primary',
  label: 'text-theme-muted',
};

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export function ProfileTab({
  profileData,
  isSaving,
  isUploading,
  onProfileDataChange,
  onSave,
  onAvatarUpload,
}: ProfileTabProps) {
  const { t } = useTranslation('settings');
  const { theme, setTheme } = useTheme();
  const fileInputRef = useRef<HTMLInputElement>(null);

  return (
    <div className="space-y-6">
      <GlassCard className="p-6">
        <h2 className="text-lg font-semibold text-theme-primary mb-6">{t('profile.section_title')}</h2>

        {/* Avatar */}
        <div className="flex flex-col sm:flex-row items-center sm:items-start gap-4 sm:gap-6 mb-8">
          <div className="relative">
            <Avatar
              src={resolveAvatarUrl(profileData.avatar)}
              name={profileData.first_name || profileData.name}
              className="w-20 h-20 ring-4 ring-theme-default"
            />
            <input
              ref={fileInputRef}
              type="file"
              accept="image/*"
              onChange={onAvatarUpload}
              className="hidden"
              aria-label="Upload profile photo"
            />
            <Button
              isIconOnly
              size="sm"
              className="absolute bottom-0 right-0 rounded-full bg-indigo-500 text-white hover:bg-indigo-600 min-w-0 w-8 h-8"
              onPress={() => fileInputRef.current?.click()}
              isDisabled={isUploading}
              isLoading={isUploading}
              aria-label="Change profile photo"
            >
              <Camera className="w-4 h-4" aria-hidden="true" />
            </Button>
          </div>
          <div>
            <p className="text-theme-primary font-medium">{t('profile.photo_label')}</p>
            <p className="text-theme-subtle text-sm">{t('profile.photo_hint')}</p>
          </div>
        </div>

        {/* Form */}
        <div className="space-y-6">
          {/* Name fields */}
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <Input
              label={t('profile.first_name')}
              placeholder={t('profile.first_name_placeholder')}
              value={profileData.first_name}
              onChange={(e) => onProfileDataChange((prev) => ({ ...prev, first_name: e.target.value }))}
              classNames={inputClassNames}
            />
            <Input
              label={t('profile.last_name')}
              placeholder={t('profile.last_name_placeholder')}
              value={profileData.last_name}
              onChange={(e) => onProfileDataChange((prev) => ({ ...prev, last_name: e.target.value }))}
              classNames={inputClassNames}
            />
          </div>

          {/* Phone */}
          <Input
            type="tel"
            label={t('profile.phone')}
            placeholder={t('profile.phone_placeholder')}
            value={profileData.phone}
            onChange={(e) => onProfileDataChange((prev) => ({ ...prev, phone: e.target.value }))}
            startContent={<Phone className="w-4 h-4 text-theme-subtle" aria-hidden="true" />}
            classNames={inputClassNames}
          />

          {/* Profile Type */}
          <Select
            label={t('profile.profile_type')}
            selectedKeys={[profileData.profile_type]}
            onSelectionChange={(keys) => {
              const value = Array.from(keys)[0] as string;
              if (value) {
                onProfileDataChange((prev) => ({
                  ...prev,
                  profile_type: value as 'individual' | 'organisation',
                }));
              }
            }}
            classNames={selectClassNames}
          >
            <SelectItem key="individual">{t('profile.type_individual')}</SelectItem>
            <SelectItem key="organisation">{t('profile.type_organisation')}</SelectItem>
          </Select>

          {/* Organisation Name (conditional) */}
          {profileData.profile_type === 'organisation' && (
            <Input
              label={t('profile.org_name')}
              placeholder={t('profile.org_name_placeholder')}
              value={profileData.organization_name}
              onChange={(e) => onProfileDataChange((prev) => ({ ...prev, organization_name: e.target.value }))}
              startContent={<Building2 className="w-4 h-4 text-theme-subtle" aria-hidden="true" />}
              classNames={inputClassNames}
            />
          )}

          <Input
            label={t('profile.tagline')}
            placeholder={t('profile.tagline_placeholder')}
            value={profileData.tagline}
            onChange={(e) => onProfileDataChange((prev) => ({ ...prev, tagline: e.target.value }))}
            classNames={inputClassNames}
          />

          <Textarea
            label={t('profile.bio')}
            placeholder={t('profile.bio_placeholder')}
            value={profileData.bio}
            onChange={(e) => onProfileDataChange((prev) => ({ ...prev, bio: e.target.value }))}
            minRows={4}
            classNames={{
              input: 'bg-transparent text-theme-primary',
              inputWrapper: 'bg-theme-elevated border-theme-default',
              label: 'text-theme-muted',
            }}
          />

          <PlaceAutocompleteInput
            label={t('profile.location')}
            placeholder={t('profile.location_placeholder')}
            value={profileData.location}
            onChange={(val) => onProfileDataChange((prev) => ({ ...prev, location: val }))}
            onPlaceSelect={(place) => {
              onProfileDataChange((prev) => ({
                ...prev,
                location: place.formattedAddress,
                latitude: place.lat,
                longitude: place.lng,
              }));
            }}
            onClear={() => {
              onProfileDataChange((prev) => ({
                ...prev,
                location: '',
                latitude: undefined,
                longitude: undefined,
              }));
            }}
            classNames={inputClassNames}
          />

          <Button
            onPress={onSave}
            className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
            startContent={<Save className="w-4 h-4" aria-hidden="true" />}
            isLoading={isSaving}
          >
            Save Changes
          </Button>
        </div>
      </GlassCard>

      {/* Language & Appearance */}
      <GlassCard className="p-6">
        <h2 className="text-lg font-semibold text-theme-primary mb-6 flex items-center gap-2">
          <Monitor className="w-5 h-5 text-indigo-500" aria-hidden="true" />
          {t('language')} &amp; {t('appearance')}
        </h2>

        <div className="space-y-6">
          {/* Language preference */}
          <div>
            <p className="text-sm font-medium text-theme-primary mb-1">{t('language_preference')}</p>
            <p className="text-xs text-theme-muted mb-3">{t('select_language')}</p>
            <LanguageSwitcher compact={false} />
          </div>

          {/* Theme preference */}
          <div className="pt-4 border-t border-theme-default">
            <p className="text-sm font-medium text-theme-primary mb-3">{t('theme.title')}</p>
            <div className="flex gap-2 flex-wrap">
              {(['light', 'dark', 'system'] as const).map((mode) => (
                <Button
                  key={mode}
                  size="sm"
                  variant={theme === mode ? 'solid' : 'flat'}
                  className={theme === mode ? 'bg-indigo-500 text-white' : 'text-theme-secondary'}
                  onPress={() => setTheme(mode)}
                >
                  {t(`theme.${mode}`)}
                </Button>
              ))}
            </div>
          </div>
        </div>
      </GlassCard>
    </div>
  );
}
