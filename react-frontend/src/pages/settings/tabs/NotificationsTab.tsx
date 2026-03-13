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
import { useTranslation } from 'react-i18next';
import {
  Save,
  Mail,
  Smartphone,
  AlertTriangle,
  CreditCard,
  Trophy,
  Building2,
  Search,
} from 'lucide-react';
import { GlassCard } from '@/components/ui';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

export interface NotificationSettings {
  email_messages: boolean;
  email_listings: boolean;
  email_digest: boolean;
  email_connections: boolean;
  email_transactions: boolean;
  email_reviews: boolean;
  email_gamification_digest: boolean;
  email_gamification_milestones: boolean;
  email_org_payments: boolean;
  email_org_transfers: boolean;
  email_org_membership: boolean;
  email_org_admin: boolean;
  push_enabled: boolean;
}

interface NotificationsTabProps {
  notifications: NotificationSettings;
  notificationError: string | null;
  isSaving: boolean;
  matchDigestFrequency: string;
  notifyHotMatches: boolean;
  notifyMutualMatches: boolean;
  marketingConsent: boolean;
  marketingConsentLoading: boolean;
  isOrganisation: boolean;
  onNotificationsChange: (updater: (prev: NotificationSettings) => NotificationSettings) => void;
  onMatchDigestFrequencyChange: (value: string) => void;
  onNotifyHotMatchesChange: (value: boolean) => void;
  onNotifyMutualMatchesChange: (value: boolean) => void;
  onMarketingConsentToggle: (checked: boolean) => void;
  onSave: () => void;
  onRetry: () => void;
}

// ─────────────────────────────────────────────────────────────────────────────
// SettingToggle helper
// ─────────────────────────────────────────────────────────────────────────────

interface SettingToggleProps {
  label: string;
  description: string;
  checked: boolean;
  onChange: (checked: boolean) => void;
  disabled?: boolean;
}

function SettingToggle({ label, description, checked, onChange, disabled }: SettingToggleProps) {
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
        isDisabled={disabled}
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

export function NotificationsTab({
  notifications,
  notificationError,
  isSaving,
  matchDigestFrequency,
  notifyHotMatches,
  notifyMutualMatches,
  marketingConsent,
  marketingConsentLoading,
  isOrganisation,
  onNotificationsChange,
  onMatchDigestFrequencyChange,
  onNotifyHotMatchesChange,
  onNotifyMutualMatchesChange,
  onMarketingConsentToggle,
  onSave,
  onRetry,
}: NotificationsTabProps) {
  const { t } = useTranslation('settings');

  return (
    <GlassCard className="p-6">
      <h2 className="text-lg font-semibold text-theme-primary mb-6">{t('notifications')}</h2>

      {notificationError ? (
        <div className="text-center py-8">
          <AlertTriangle className="w-12 h-12 text-amber-500 mx-auto mb-4" aria-hidden="true" />
          <p className="text-theme-muted mb-4">{notificationError}</p>
          <Button
            className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
            onPress={onRetry}
          >
            Try Again
          </Button>
        </div>
      ) : (
        <div className="space-y-6">
          {/* Messages & Communication */}
          <div className="space-y-4">
            <h3 className="text-sm font-medium text-theme-muted flex items-center gap-2">
              <Mail className="w-4 h-4" aria-hidden="true" />
              Messages &amp; Communication
            </h3>

            <SettingToggle
              label={t('notification_prefs.new_messages')}
              description="Get notified when you receive a new message"
              checked={notifications.email_messages}
              onChange={(checked) => onNotificationsChange((prev) => ({ ...prev, email_messages: checked }))}
            />

            <SettingToggle
              label={t('notification_prefs.connection_requests')}
              description="Connection requests and updates"
              checked={notifications.email_connections}
              onChange={(checked) => onNotificationsChange((prev) => ({ ...prev, email_connections: checked }))}
            />
          </div>

          {/* Activity & Listings */}
          <div className="pt-4 border-t border-theme-default space-y-4">
            <h3 className="text-sm font-medium text-theme-muted flex items-center gap-2">
              <CreditCard className="w-4 h-4" aria-hidden="true" />
              Activity &amp; Listings
            </h3>

            <SettingToggle
              label={t('notification_prefs.listing_activity')}
              description="Updates about your listings (new responses, etc.)"
              checked={notifications.email_listings}
              onChange={(checked) => onNotificationsChange((prev) => ({ ...prev, email_listings: checked }))}
            />

            <SettingToggle
              label={t('notification_prefs.credit_transactions')}
              description="Notifications for credit transactions"
              checked={notifications.email_transactions}
              onChange={(checked) => onNotificationsChange((prev) => ({ ...prev, email_transactions: checked }))}
            />

            <SettingToggle
              label={t('notification_prefs.new_reviews')}
              description="New reviews received on your profile or listings"
              checked={notifications.email_reviews}
              onChange={(checked) => onNotificationsChange((prev) => ({ ...prev, email_reviews: checked }))}
            />
          </div>

          {/* Community & Achievements */}
          <div className="pt-4 border-t border-theme-default space-y-4">
            <h3 className="text-sm font-medium text-theme-muted flex items-center gap-2">
              <Trophy className="w-4 h-4" aria-hidden="true" />
              Community &amp; Achievements
            </h3>

            <SettingToggle
              label={t('notification_prefs.gamification_digest')}
              description="Periodic summary of your gamification activity and progress"
              checked={notifications.email_gamification_digest}
              onChange={(checked) => onNotificationsChange((prev) => ({ ...prev, email_gamification_digest: checked }))}
            />

            <SettingToggle
              label={t('notification_prefs.achievement_milestones')}
              description="Badge unlocks, level ups, and achievement notifications"
              checked={notifications.email_gamification_milestones}
              onChange={(checked) => onNotificationsChange((prev) => ({ ...prev, email_gamification_milestones: checked }))}
            />

            <SettingToggle
              label={t('notification_prefs.weekly_digest')}
              description="A weekly summary of community activity"
              checked={notifications.email_digest}
              onChange={(checked) => onNotificationsChange((prev) => ({ ...prev, email_digest: checked }))}
            />
          </div>

          {/* Organisation Notifications */}
          {isOrganisation && (
            <div className="pt-4 border-t border-theme-default space-y-4">
              <h3 className="text-sm font-medium text-theme-muted flex items-center gap-2">
                <Building2 className="w-4 h-4" aria-hidden="true" />
                Organisation Notifications
              </h3>

              <SettingToggle
                label={t('notification_prefs.payment_notifications')}
                description="Notifications for organisation payment activity"
                checked={notifications.email_org_payments}
                onChange={(checked) => onNotificationsChange((prev) => ({ ...prev, email_org_payments: checked }))}
              />

              <SettingToggle
                label={t('notification_prefs.transfer_notifications')}
                description="Notifications for credit transfers"
                checked={notifications.email_org_transfers}
                onChange={(checked) => onNotificationsChange((prev) => ({ ...prev, email_org_transfers: checked }))}
              />

              <SettingToggle
                label={t('notification_prefs.membership_updates')}
                description="Member joins, leaves, and membership changes"
                checked={notifications.email_org_membership}
                onChange={(checked) => onNotificationsChange((prev) => ({ ...prev, email_org_membership: checked }))}
              />

              <SettingToggle
                label={t('notification_prefs.admin_notifications')}
                description="Administrative alerts and system notifications"
                checked={notifications.email_org_admin}
                onChange={(checked) => onNotificationsChange((prev) => ({ ...prev, email_org_admin: checked }))}
              />
            </div>
          )}

          {/* Match Digest */}
          <div className="pt-4 border-t border-theme-default space-y-4">
            <h3 className="text-sm font-medium text-theme-muted flex items-center gap-2">
              <Search className="w-4 h-4" aria-hidden="true" />
              Match Digest Emails
            </h3>

            <div className="p-4 rounded-lg bg-theme-elevated">
              <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                  <p className="font-medium text-theme-primary">Digest Frequency</p>
                  <p className="text-sm text-theme-subtle">How often you receive match digest emails</p>
                </div>
                <Select
                  aria-label="Match digest frequency"
                  selectedKeys={[matchDigestFrequency]}
                  onSelectionChange={(keys) => {
                    const value = Array.from(keys)[0] as string;
                    if (value) onMatchDigestFrequencyChange(value);
                  }}
                  className="sm:max-w-[180px]"
                  classNames={{
                    trigger: 'bg-theme-elevated border-theme-default',
                    value: 'text-theme-primary',
                  }}
                >
                  <SelectItem key="daily">Daily</SelectItem>
                  <SelectItem key="weekly">Weekly</SelectItem>
                  <SelectItem key="fortnightly">Fortnightly</SelectItem>
                  <SelectItem key="never">Never</SelectItem>
                </Select>
              </div>
            </div>

            <SettingToggle
              label={t('notification_prefs.hot_match_alerts')}
              description="Get notified about high-compatibility matches"
              checked={notifyHotMatches}
              onChange={onNotifyHotMatchesChange}
            />

            <SettingToggle
              label={t('notification_prefs.mutual_match_alerts')}
              description="Get notified when someone you matched with also matches you"
              checked={notifyMutualMatches}
              onChange={onNotifyMutualMatchesChange}
            />
          </div>

          {/* Push Notifications */}
          <div className="pt-4 border-t border-theme-default space-y-4">
            <h3 className="text-sm font-medium text-theme-muted flex items-center gap-2">
              <Smartphone className="w-4 h-4" aria-hidden="true" />
              Push Notifications
            </h3>

            <SettingToggle
              label={t('notification_prefs.enable_push')}
              description="Receive real-time notifications on your device"
              checked={notifications.push_enabled}
              onChange={(checked) => onNotificationsChange((prev) => ({ ...prev, push_enabled: checked }))}
            />
          </div>

          {/* Marketing & Communications */}
          <div className="pt-4 border-t border-theme-default space-y-4">
            <h3 className="text-sm font-medium text-theme-muted flex items-center gap-2">
              <Mail className="w-4 h-4" aria-hidden="true" />
              Marketing &amp; Communications
            </h3>

            <SettingToggle
              label={t('notification_prefs.marketing_emails')}
              description="Receive newsletters, promotions, and community updates"
              checked={marketingConsent}
              onChange={onMarketingConsentToggle}
              disabled={marketingConsentLoading}
            />
          </div>

          <Button
            onPress={onSave}
            className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
            startContent={<Save className="w-4 h-4" aria-hidden="true" />}
            isLoading={isSaving}
          >
            Save Preferences
          </Button>
        </div>
      )}
    </GlassCard>
  );
}
