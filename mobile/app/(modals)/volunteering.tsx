// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
  Alert,
  FlatList,
  Linking,
  RefreshControl,
  ScrollView,
  Text,
  View,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { router, type Href } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { Button as HeroButton, Card as HeroCard, Chip, Spinner, Surface } from 'heroui-native';
import * as Haptics from '@/lib/haptics';
import { useTranslation } from 'react-i18next';

import {
  cancelShiftSignup,
  expressInterest,
  generateVolunteerCertificate,
  getHoursSummary,
  getMyApplications,
  getMyOrganisations,
  getMyShifts,
  getOpportunities,
  getVolunteerCertificates,
  getVolunteerDonations,
  getVolunteerExpenses,
  getVolunteerGivingDays,
  logVolunteerHours,
  submitVolunteerExpense,
  submitVolunteerDonation,
  withdrawApplication,
  type MyOrganisationsResponse,
  type MyShiftsResponse,
  type VolunteerApplication,
  type VolunteerApplicationsResponse,
  type VolunteerCertificate,
  type VolunteerCertificatesResponse,
  type VolunteerExpense,
  type VolunteerExpensesResponse,
  type VolunteerExpenseType,
  type VolunteerDonation,
  type VolunteerDonationsResponse,
  type VolunteerGivingDay,
  type VolunteerGivingDaysResponse,
  type VolunteerHoursSummary,
  type VolunteerOpportunity,
  type VolunteerShiftRegistration,
  type VolunteeringOrganisation,
  type VolunteeringResponse,
} from '@/lib/api/volunteering';
import { useAuth } from '@/lib/hooks/useAuth';
import { useApi } from '@/lib/hooks/useApi';
import { usePaginatedApi } from '@/lib/hooks/usePaginatedApi';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme } from '@/lib/hooks/useTheme';
import { withAlpha } from '@/lib/utils/color';
import { API_BASE_URL } from '@/lib/constants';
import AppTopBar from '@/components/ui/AppTopBar';
import Avatar from '@/components/ui/Avatar';
import EmptyState from '@/components/ui/EmptyState';
import Input from '@/components/ui/Input';
import LoadingSpinner from '@/components/ui/LoadingSpinner';
import ModalErrorBoundary from '@/components/ModalErrorBoundary';

type TabKey = 'opportunities' | 'applications' | 'shifts' | 'hours' | 'certificates' | 'expenses' | 'donations';
type IoniconName = React.ComponentProps<typeof Ionicons>['name'];
const EXPENSE_TYPES: VolunteerExpenseType[] = ['travel', 'meals', 'supplies', 'equipment', 'parking', 'other'];

function formatDate(value?: string | null) {
  if (!value) return null;
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return null;
  return new Intl.DateTimeFormat(undefined, { day: 'numeric', month: 'short', year: 'numeric' }).format(date);
}

function formatTime(value?: string | null) {
  if (!value) return null;
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return null;
  return new Intl.DateTimeFormat(undefined, { hour: '2-digit', minute: '2-digit' }).format(date);
}

function formatMoney(value: number | string | null | undefined, currency = 'EUR') {
  const amount = Number(value ?? 0);
  try {
    return new Intl.NumberFormat(undefined, {
      style: 'currency',
      currency: currency || 'EUR',
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    }).format(amount);
  } catch {
    return `${currency || 'EUR'} ${amount.toFixed(2)}`;
  }
}

function opportunityOrg(item: VolunteerOpportunity) {
  const mixed = item as VolunteerOpportunity & {
    organization?: VolunteeringOrganisation | null;
  };
  return item.organisation ?? mixed.organization ?? null;
}

function normalizeSkills(skills: unknown): string[] {
  if (Array.isArray(skills)) return skills;
  if (typeof skills === 'string') return skills.split(',').map((skill: string) => skill.trim()).filter(Boolean);
  return [];
}

function getLoggableOrganisations(
  organisations: VolunteeringOrganisation[],
  applications: VolunteerApplication[],
): VolunteeringOrganisation[] {
  const byId = new Map<number, VolunteeringOrganisation>();

  organisations.forEach((organisation) => {
    if (organisation.id) {
      byId.set(organisation.id, organisation);
    }
  });

  applications.forEach((application) => {
    if (application.status !== 'approved' || !application.organization?.id) {
      return;
    }

    byId.set(application.organization.id, {
      id: application.organization.id,
      name: application.organization.name,
      logo_url: application.organization.logo_url ?? null,
      status: 'approved',
      member_role: 'volunteer',
    });
  });

  return Array.from(byId.values());
}

function statusLabelKey(status: VolunteerOpportunity['status'] | string) {
  return ['open', 'closed', 'filled'].includes(status) ? `status.${status}` : 'status.open';
}

function StatusChip({ label, tone, icon }: { label: string; tone: string; icon?: IoniconName }) {
  return (
    <Chip size="sm" variant="secondary" color="default">
      {icon ? <Ionicons name={icon} size={12} color={tone} /> : null}
      <Chip.Label>{label}</Chip.Label>
    </Chip>
  );
}

function StatTile({
  label,
  value,
  tone,
}: {
  label: string;
  value: string;
  tone: string;
}) {
  const theme = useTheme();
  return (
    <Surface variant="secondary" className="min-w-[46%] flex-1 gap-1 rounded-panel-inner p-4">
      <Text className="text-[11px] font-semibold uppercase" style={{ color: theme.textSecondary }} numberOfLines={1}>
        {label}
      </Text>
      <View className="flex-row items-end justify-between gap-2">
        <Text className="text-2xl font-bold" style={{ color: theme.text }} numberOfLines={1}>
          {value}
        </Text>
        <View className="h-1.5 w-10 rounded-full" style={{ backgroundColor: tone }} />
      </View>
    </Surface>
  );
}

function HeroHeader({
  activeCount,
  applicationsCount,
  verifiedHours,
}: {
  activeCount: number;
  applicationsCount: number;
  verifiedHours: number;
}) {
  const { t } = useTranslation('volunteering');
  const primary = usePrimaryColor();
  const theme = useTheme();

  return (
    <HeroCard className="overflow-hidden rounded-panel p-0">
      <View className="h-1.5" style={{ backgroundColor: '#e11d48' }} />
      <HeroCard.Body className="gap-5 p-5">
        <View className="flex-row items-start gap-3">
          <View className="size-12 items-center justify-center rounded-panel-inner" style={{ backgroundColor: withAlpha('#e11d48', 0.14) }}>
            <Ionicons name="heart-outline" size={24} color="#e11d48" />
          </View>
          <View className="min-w-0 flex-1">
            <Text className="text-xs font-semibold uppercase" style={{ color: theme.textSecondary }}>
              {t('heroEyebrow')}
            </Text>
            <Text className="mt-1 text-2xl font-bold" style={{ color: theme.text }} numberOfLines={2}>
              {t('title')}
            </Text>
            <Text className="mt-2 text-sm leading-5" style={{ color: theme.textSecondary }}>
              {t('subtitle')}
            </Text>
          </View>
        </View>

        <View className="flex-row flex-wrap gap-3">
          <StatTile label={t('stats.opportunities')} value={String(activeCount)} tone="#e11d48" />
          <StatTile label={t('stats.applications')} value={String(applicationsCount)} tone={primary} />
          <StatTile label={t('stats.hours')} value={String(verifiedHours)} tone="#22c55e" />
        </View>

        <View className="flex-row gap-2">
          <HeroButton
            className="flex-1"
            variant="primary"
            onPress={() => router.push('/(modals)/new-volunteering' as Href)}
            style={{ backgroundColor: primary }}
          >
            <Ionicons name="add-outline" size={16} color="#fff" />
            <HeroButton.Label>{t('createOpportunity')}</HeroButton.Label>
          </HeroButton>
          <HeroButton
            className="flex-1"
            variant="secondary"
            onPress={() => router.push('/(modals)/organisations')}
          >
            <Ionicons name="business-outline" size={16} color={primary} />
            <HeroButton.Label>{t('browseOrganisations')}</HeroButton.Label>
          </HeroButton>
        </View>
      </HeroCard.Body>
    </HeroCard>
  );
}

function OpportunityCard({
  item,
  onOpen,
  onApply,
  applying,
}: {
  item: VolunteerOpportunity;
  onOpen: () => void;
  onApply: () => void;
  applying: boolean;
}) {
  const { t } = useTranslation('volunteering');
  const primary = usePrimaryColor();
  const theme = useTheme();
  const org = opportunityOrg(item);
  const skills = normalizeSkills(item.skills_needed);
  const statusColor = item.status === 'closed' ? theme.textMuted : item.status === 'filled' ? theme.warning : theme.success;
  const deadline = formatDate(item.deadline);

  return (
    <HeroCard className="mb-3 rounded-panel p-0">
      <HeroCard.Body className="gap-4 p-4" style={{ minHeight: 178 }}>
        <View className="flex-row items-start justify-between gap-3">
          <View className="min-w-0 flex-1">
            <Text className="text-base font-semibold" style={{ color: theme.text }} numberOfLines={2}>
              {item.title}
            </Text>
            {org ? (
              <View className="mt-2 flex-row items-center gap-2">
                <Avatar uri={org.avatar ?? org.logo_url ?? undefined} name={org.name} size={26} />
                <Text className="min-w-0 flex-1 text-sm" style={{ color: theme.textSecondary }} numberOfLines={1}>
                  {org.name}
                </Text>
              </View>
            ) : null}
          </View>
          <StatusChip label={t(statusLabelKey(item.status))} tone={statusColor} icon="radio-button-on-outline" />
        </View>

        <Text className="text-sm leading-5" style={{ color: theme.textSecondary }} numberOfLines={3}>
          {item.description ?? t('noDescription')}
        </Text>

        <View className="flex-row flex-wrap gap-2">
          {item.is_remote ? (
            <StatusChip label={t('remote')} tone={primary} icon="globe-outline" />
          ) : item.location ? (
            <StatusChip label={item.location} tone={theme.textMuted} icon="location-outline" />
          ) : null}
          {typeof item.hours_per_week === 'number' ? (
            <StatusChip label={t('hoursPerWeek', { hours: item.hours_per_week })} tone={theme.textMuted} icon="time-outline" />
          ) : null}
          {deadline ? (
            <StatusChip label={t('deadlineShort', { date: deadline })} tone={theme.textMuted} icon="calendar-outline" />
          ) : null}
        </View>

        {skills.length > 0 ? (
          <View className="flex-row flex-wrap gap-2">
            {skills.slice(0, 3).map((skill) => (
              <Chip key={skill} size="sm" variant="secondary" color="default">
                <Chip.Label>{skill}</Chip.Label>
              </Chip>
            ))}
          </View>
        ) : null}

        <View className="flex-row gap-2">
          <HeroButton className="flex-1" size="sm" variant="secondary" onPress={onOpen} accessibilityLabel={t('openOpportunityLabel', { title: item.title })}>
            <Ionicons name="open-outline" size={16} color={primary} />
            <HeroButton.Label>{t('viewOpportunity')}</HeroButton.Label>
          </HeroButton>
          {item.status !== 'closed' && item.status !== 'filled' && !item.has_applied ? (
            <HeroButton className="flex-1" size="sm" isDisabled={applying} onPress={onApply} accessibilityLabel={t('applyOpportunityLabel', { title: item.title })}>
              {applying ? <Spinner size="sm" /> : <Ionicons name="send-outline" size={16} color="#fff" />}
              <HeroButton.Label>{t('apply')}</HeroButton.Label>
            </HeroButton>
          ) : null}
        </View>
      </HeroCard.Body>
    </HeroCard>
  );
}

function ApplicationsPanel({
  applications,
  isLoading,
  onRefresh,
}: {
  applications: VolunteerApplication[];
  isLoading: boolean;
  onRefresh: () => void;
}) {
  const { t } = useTranslation('volunteering');
  const theme = useTheme();
  const [withdrawingId, setWithdrawingId] = useState<number | null>(null);

  async function handleWithdraw(id: number) {
    setWithdrawingId(id);
    try {
      await withdrawApplication(id);
      onRefresh();
      void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
    } catch {
      void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Error);
      Alert.alert(t('common:errors.alertTitle'), t('withdrawError'));
    } finally {
      setWithdrawingId(null);
    }
  }

  if (isLoading) {
    return <LoadingSpinner />;
  }

  if (applications.length === 0) {
    return <EmptyState icon="send-outline" title={t('noApplications')} />;
  }

  return (
    <View className="gap-3">
      {applications.map((application) => {
        const statusTone = application.status === 'approved' ? theme.success : application.status === 'declined' ? theme.error : theme.warning;
        return (
          <HeroCard key={application.id} className="rounded-panel p-0">
            <HeroCard.Body className="gap-3 p-4" style={{ minHeight: 134 }}>
              <View className="flex-row items-start justify-between gap-3">
                <View className="min-w-0 flex-1">
                  <Text className="text-base font-semibold" style={{ color: theme.text }} numberOfLines={2}>
                    {application.opportunity.title}
                  </Text>
                  <Text className="mt-1 text-sm" style={{ color: theme.textSecondary }} numberOfLines={1}>
                    {application.organization.name}
                  </Text>
                </View>
                <StatusChip label={t(`applicationStatus.${application.status}`)} tone={statusTone} icon="ellipse-outline" />
              </View>
              <Text className="text-xs" style={{ color: theme.textMuted }} numberOfLines={1}>
                {t('appliedOn', { date: formatDate(application.created_at) ?? '' })}
              </Text>
              {application.org_note ? (
                <Text className="text-sm leading-5" style={{ color: theme.textSecondary }} numberOfLines={3}>
                  {application.org_note}
                </Text>
              ) : null}
              {application.status === 'pending' ? (
                <HeroButton
                  size="sm"
                  variant="secondary"
                  isDisabled={withdrawingId === application.id}
                  onPress={() => void handleWithdraw(application.id)}
                >
                  {withdrawingId === application.id ? <Spinner size="sm" /> : <HeroButton.Label>{t('withdraw')}</HeroButton.Label>}
                </HeroButton>
              ) : null}
            </HeroCard.Body>
          </HeroCard>
        );
      })}
    </View>
  );
}

function ShiftsPanel({
  shifts,
  isLoading,
  onRefresh,
}: {
  shifts: VolunteerShiftRegistration[];
  isLoading: boolean;
  onRefresh: () => void;
}) {
  const { t } = useTranslation('volunteering');
  const primary = usePrimaryColor();
  const theme = useTheme();
  const [cancellingId, setCancellingId] = useState<number | null>(null);

  async function handleCancel(id: number) {
    setCancellingId(id);
    try {
      await cancelShiftSignup(id);
      onRefresh();
      void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
    } catch {
      void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Error);
      Alert.alert(t('common:errors.alertTitle'), t('myShifts.cancelError'));
    } finally {
      setCancellingId(null);
    }
  }

  if (isLoading) {
    return <LoadingSpinner />;
  }

  if (shifts.length === 0) {
    return <EmptyState icon="calendar-outline" title={t('myShifts.empty')} />;
  }

  return (
    <View className="gap-3">
      {shifts.map((shift) => {
        const date = formatDate(shift.start_time);
        const start = formatTime(shift.start_time);
        const end = formatTime(shift.end_time);
        return (
          <HeroCard key={shift.id} className="rounded-panel p-0">
            <HeroCard.Body className="gap-3 p-4">
              <View className="flex-row items-start justify-between gap-3">
                <View className="min-w-0 flex-1">
                  <Text className="text-base font-semibold" style={{ color: theme.text }} numberOfLines={2}>
                    {shift.opportunity_title}
                  </Text>
                  <Text className="mt-1 text-sm" style={{ color: theme.textSecondary }} numberOfLines={1}>
                    {date ? t('myShifts.date', { date }) : t('myShifts.dateUnknown')}
                  </Text>
                </View>
                <Chip size="sm" variant="secondary">
                  <Ionicons name="calendar-outline" size={12} color={primary} />
                  <Chip.Label>{t('myShifts.confirmed')}</Chip.Label>
                </Chip>
              </View>

              <View className="flex-row flex-wrap gap-2">
                {start && end ? (
                  <StatusChip label={t('myShifts.timeRange', { start, end })} tone={theme.textMuted} icon="time-outline" />
                ) : null}
                {shift.location ? (
                  <StatusChip label={shift.location} tone={theme.textMuted} icon="location-outline" />
                ) : null}
              </View>

              <View className="flex-row gap-2">
                <HeroButton
                  className="flex-1"
                  size="sm"
                  variant="secondary"
                  onPress={() => router.push({ pathname: '/(modals)/volunteering-detail', params: { id: String(shift.opportunity_id) } })}
                  accessibilityLabel={t('myShifts.openOpportunityLabel', { title: shift.opportunity_title })}
                >
                  <Ionicons name="open-outline" size={16} color={primary} />
                  <HeroButton.Label>{t('viewOpportunity')}</HeroButton.Label>
                </HeroButton>
                <HeroButton
                  className="flex-1"
                  size="sm"
                  variant="danger-soft"
                  isDisabled={cancellingId === shift.id}
                  onPress={() => void handleCancel(shift.id)}
                  accessibilityLabel={t('myShifts.cancelLabel', { title: shift.opportunity_title })}
                >
                  {cancellingId === shift.id ? <Spinner size="sm" /> : <HeroButton.Label>{t('myShifts.cancel')}</HeroButton.Label>}
                </HeroButton>
              </View>
            </HeroCard.Body>
          </HeroCard>
        );
      })}
    </View>
  );
}

function CertificatesPanel({
  certificates,
  isLoading,
  onRefresh,
}: {
  certificates: VolunteerCertificate[];
  isLoading: boolean;
  onRefresh: () => void;
}) {
  const { t } = useTranslation('volunteering');
  const primary = usePrimaryColor();
  const theme = useTheme();
  const [generating, setGenerating] = useState(false);

  async function handleGenerate() {
    setGenerating(true);
    try {
      await generateVolunteerCertificate();
      onRefresh();
      void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
    } catch {
      void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Error);
      Alert.alert(t('common:errors.alertTitle'), t('certificates.generateError'));
    } finally {
      setGenerating(false);
    }
  }

  async function openCertificate(code: string) {
    const url = `${API_BASE_URL}${API_BASE_URL.endsWith('/') ? '' : '/'}api/v2/volunteering/certificates/${encodeURIComponent(code)}/html`;
    await Linking.openURL(url);
  }

  if (isLoading) {
    return <LoadingSpinner />;
  }

  return (
    <View className="gap-3">
      <HeroCard className="rounded-panel p-0">
        <HeroCard.Body className="gap-3 p-4">
          <View className="flex-row items-start justify-between gap-3">
            <View className="min-w-0 flex-1">
              <Text className="text-base font-semibold" style={{ color: theme.text }}>
                {t('certificates.title')}
              </Text>
              <Text className="mt-1 text-sm leading-5" style={{ color: theme.textSecondary }}>
                {t('certificates.description')}
              </Text>
            </View>
            <View className="size-10 items-center justify-center rounded-panel-inner" style={{ backgroundColor: withAlpha(primary, 0.14) }}>
              <Ionicons name="ribbon-outline" size={20} color={primary} />
            </View>
          </View>
          <HeroButton isDisabled={generating} onPress={() => void handleGenerate()}>
            {generating ? <Spinner size="sm" /> : <Ionicons name="add-outline" size={16} color="#fff" />}
            <HeroButton.Label>{t('certificates.generate')}</HeroButton.Label>
          </HeroButton>
        </HeroCard.Body>
      </HeroCard>

      {certificates.length === 0 ? (
        <EmptyState icon="ribbon-outline" title={t('certificates.emptyTitle')} />
      ) : (
        certificates.map((certificate) => {
          const start = formatDate(certificate.date_range?.start);
          const end = formatDate(certificate.date_range?.end);
          return (
            <HeroCard key={certificate.id} className="rounded-panel p-0">
              <HeroCard.Body className="gap-3 p-4">
                <View className="flex-row items-start justify-between gap-3">
                  <View className="min-w-0 flex-1">
                    <Text className="text-base font-semibold" style={{ color: theme.text }}>
                      {t('certificates.verifiedHours', { count: certificate.total_hours })}
                    </Text>
                    <Text className="mt-1 text-sm" style={{ color: theme.textSecondary }}>
                      {start && end ? t('certificates.dateRange', { start, end }) : t('certificates.dateUnknown')}
                    </Text>
                  </View>
                  <Chip size="sm" variant="secondary">
                    <Chip.Label>{certificate.verification_code}</Chip.Label>
                  </Chip>
                </View>

                {certificate.organizations?.length ? (
                  <View className="flex-row flex-wrap gap-2">
                    {certificate.organizations.slice(0, 3).map((organization) => (
                      <Chip key={`${certificate.id}-${organization.name}`} size="sm" variant="secondary">
                        <Chip.Label>{t('certificates.organizationHours', { name: organization.name, hours: organization.hours })}</Chip.Label>
                      </Chip>
                    ))}
                  </View>
                ) : null}

                <HeroButton
                  size="sm"
                  variant="secondary"
                  onPress={() => void openCertificate(certificate.verification_code)}
                  accessibilityLabel={t('certificates.openLabel', { code: certificate.verification_code })}
                >
                  <Ionicons name="open-outline" size={16} color={primary} />
                  <HeroButton.Label>{t('certificates.open')}</HeroButton.Label>
                </HeroButton>
              </HeroCard.Body>
            </HeroCard>
          );
        })
      )}
    </View>
  );
}

function ExpensesPanel({
  expenses,
  organisations,
  isLoading,
  onRefresh,
}: {
  expenses: VolunteerExpense[];
  organisations: VolunteeringOrganisation[];
  isLoading: boolean;
  onRefresh: () => void;
}) {
  const { t } = useTranslation('volunteering');
  const primary = usePrimaryColor();
  const theme = useTheme();
  const [selectedOrgId, setSelectedOrgId] = useState<number | null>(null);
  const [expenseType, setExpenseType] = useState<VolunteerExpenseType>('travel');
  const [amount, setAmount] = useState('');
  const [currency, setCurrency] = useState('EUR');
  const [description, setDescription] = useState('');
  const [submitting, setSubmitting] = useState(false);

  useEffect(() => {
    if (selectedOrgId === null && organisations.length > 0) {
      setSelectedOrgId(organisations[0]?.id ?? null);
    }
  }, [organisations, selectedOrgId]);

  async function handleSubmit() {
    const parsedAmount = Number(amount);
    if (!selectedOrgId || !Number.isFinite(parsedAmount) || parsedAmount <= 0 || description.trim().length === 0) {
      Alert.alert(t('common:errors.alertTitle'), t('expenses.validation'));
      return;
    }

    setSubmitting(true);
    try {
      await submitVolunteerExpense({
        organization_id: selectedOrgId,
        expense_type: expenseType,
        amount: parsedAmount,
        currency: currency.trim() || 'EUR',
        description: description.trim(),
      });
      setAmount('');
      setDescription('');
      onRefresh();
      void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
    } catch {
      void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Error);
      Alert.alert(t('common:errors.alertTitle'), t('expenses.submitError'));
    } finally {
      setSubmitting(false);
    }
  }

  if (isLoading) {
    return <LoadingSpinner />;
  }

  const claimed = expenses.reduce((sum, expense) => sum + Number(expense.amount ?? 0), 0);
  const approved = expenses.reduce((sum, expense) => (
    ['approved', 'paid'].includes(String(expense.status)) ? sum + Number(expense.amount ?? 0) : sum
  ), 0);

  return (
    <View className="gap-4">
      <View className="flex-row flex-wrap gap-3">
        <StatTile label={t('expenses.stats.claimed')} value={formatMoney(claimed, currency)} tone={primary} />
        <StatTile label={t('expenses.stats.approved')} value={formatMoney(approved, currency)} tone="#22c55e" />
      </View>

      <HeroCard className="rounded-panel p-0">
        <HeroCard.Body className="gap-4 p-4">
          <View>
            <Text className="text-base font-semibold" style={{ color: theme.text }}>
              {t('expenses.submit')}
            </Text>
            <Text className="mt-1 text-sm" style={{ color: theme.textSecondary }}>
              {organisations.length > 0 ? t('expenses.submitHint') : t('expenses.noOrganisations')}
            </Text>
          </View>

          {organisations.length > 0 ? (
            <>
              <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerClassName="gap-2">
                {organisations.map((org) => {
                  const selected = selectedOrgId === org.id;
                  return (
                    <HeroButton
                      key={org.id}
                      size="sm"
                      variant={selected ? 'primary' : 'secondary'}
                      onPress={() => setSelectedOrgId(org.id)}
                      style={selected ? { backgroundColor: withAlpha(primary, 0.18) } : undefined}
                    >
                      <HeroButton.Label style={{ color: selected ? primary : theme.textSecondary }}>
                        {org.name}
                      </HeroButton.Label>
                    </HeroButton>
                  );
                })}
              </ScrollView>
              <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerClassName="gap-2">
                {EXPENSE_TYPES.map((type) => {
                  const selected = expenseType === type;
                  return (
                    <HeroButton
                      key={type}
                      size="sm"
                      variant={selected ? 'primary' : 'secondary'}
                      onPress={() => setExpenseType(type)}
                      style={selected ? { backgroundColor: withAlpha(primary, 0.18) } : undefined}
                    >
                      <HeroButton.Label style={{ color: selected ? primary : theme.textSecondary }}>
                        {t(`expenses.types.${type}`)}
                      </HeroButton.Label>
                    </HeroButton>
                  );
                })}
              </ScrollView>
              <View className="flex-row gap-2">
                <Input
                  value={amount}
                  onChangeText={setAmount}
                  placeholder={t('expenses.amountPlaceholder')}
                  placeholderTextColor={theme.textMuted}
                  keyboardType="decimal-pad"
                  className="flex-1 text-base"
                  style={{ color: theme.text }}
                  accessibilityLabel={t('expenses.amountPlaceholder')}
                />
                <Input
                  value={currency}
                  onChangeText={setCurrency}
                  placeholder={t('expenses.currencyPlaceholder')}
                  placeholderTextColor={theme.textMuted}
                  autoCapitalize="characters"
                  className="w-24 text-base"
                  style={{ color: theme.text }}
                  accessibilityLabel={t('expenses.currencyPlaceholder')}
                />
              </View>
              <Input
                value={description}
                onChangeText={setDescription}
                placeholder={t('expenses.descriptionPlaceholder')}
                placeholderTextColor={theme.textMuted}
                multiline
                className="min-h-[92px] text-base"
                style={{ color: theme.text, textAlignVertical: 'top' }}
                accessibilityLabel={t('expenses.descriptionPlaceholder')}
              />
              <HeroButton isDisabled={submitting} onPress={() => void handleSubmit()}>
                {submitting ? <Spinner size="sm" /> : <HeroButton.Label>{t('expenses.submit')}</HeroButton.Label>}
              </HeroButton>
            </>
          ) : null}
        </HeroCard.Body>
      </HeroCard>

      {expenses.length === 0 ? (
        <EmptyState icon="receipt-outline" title={t('expenses.emptyTitle')} />
      ) : (
        expenses.map((expense) => {
          const statusTone = expense.status === 'paid' || expense.status === 'approved'
            ? theme.success
            : expense.status === 'rejected'
              ? theme.error
              : theme.warning;
          return (
            <HeroCard key={expense.id} className="rounded-panel p-0">
              <HeroCard.Body className="gap-2 p-4">
                <View className="flex-row items-start justify-between gap-3">
                  <View className="min-w-0 flex-1">
                    <Text className="text-base font-semibold" style={{ color: theme.text }}>
                      {formatMoney(expense.amount, expense.currency)}
                    </Text>
                    <Text className="mt-1 text-sm" style={{ color: theme.textSecondary }} numberOfLines={2}>
                      {expense.description}
                    </Text>
                  </View>
                  <StatusChip label={t(`expenses.status.${expense.status}`, { defaultValue: String(expense.status) })} tone={statusTone} icon="ellipse-outline" />
                </View>
                <Text className="text-xs" style={{ color: theme.textMuted }}>
                  {t(`expenses.types.${expense.expense_type}`)} - {formatDate(expense.submitted_at) ?? t('expenses.dateUnknown')}
                </Text>
              </HeroCard.Body>
            </HeroCard>
          );
        })
      )}
    </View>
  );
}

function DonationsPanel({
  givingDays,
  donations,
  isLoading,
  onRefresh,
}: {
  givingDays: VolunteerGivingDay[];
  donations: VolunteerDonation[];
  isLoading: boolean;
  onRefresh: () => void;
}) {
  const { t } = useTranslation('volunteering');
  const primary = usePrimaryColor();
  const theme = useTheme();
  const [selectedDayId, setSelectedDayId] = useState<number | null>(null);
  const [amount, setAmount] = useState('');
  const [currency, setCurrency] = useState('EUR');
  const [message, setMessage] = useState('');
  const [anonymous, setAnonymous] = useState(false);
  const [submitting, setSubmitting] = useState(false);

  async function handleSubmit() {
    const parsedAmount = Number(amount);
    if (!Number.isFinite(parsedAmount) || parsedAmount <= 0) {
      Alert.alert(t('common:errors.alertTitle'), t('donations.validation'));
      return;
    }
    setSubmitting(true);
    try {
      await submitVolunteerDonation({
        giving_day_id: selectedDayId,
        amount: parsedAmount,
        currency: currency.trim() || 'EUR',
        payment_method: 'bank_transfer',
        message: message.trim() || null,
        is_anonymous: anonymous,
      });
      setAmount('');
      setMessage('');
      onRefresh();
      void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
    } catch {
      void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Error);
      Alert.alert(t('common:errors.alertTitle'), t('donations.submitError'));
    } finally {
      setSubmitting(false);
    }
  }

  if (isLoading) {
    return <LoadingSpinner />;
  }

  const raised = givingDays.reduce((sum, day) => sum + Number(day.raised_amount ?? 0), 0);
  const donorCount = givingDays.reduce((sum, day) => sum + Number(day.donor_count ?? 0), 0);

  return (
    <View className="gap-4">
      <View className="flex-row flex-wrap gap-3">
        <StatTile label={t('donations.stats.raised')} value={formatMoney(raised, currency)} tone="#e11d48" />
        <StatTile label={t('donations.stats.donors')} value={String(donorCount)} tone={primary} />
      </View>

      {givingDays.length > 0 ? (
        <View className="gap-3">
          <Text className="text-xs font-semibold uppercase" style={{ color: theme.textSecondary }}>
            {t('donations.activeGivingDays')}
          </Text>
          {givingDays.slice(0, 3).map((day) => {
            const goal = Number(day.goal_amount ?? 0);
            const current = Number(day.raised_amount ?? 0);
            const pct = goal > 0 ? Math.min(100, Math.round((current / goal) * 100)) : 0;
            return (
              <HeroCard key={day.id} className="rounded-panel p-0">
                <HeroCard.Body className="gap-3 p-4">
                  <View className="flex-row items-start justify-between gap-3">
                    <View className="min-w-0 flex-1">
                      <Text className="text-base font-semibold" style={{ color: theme.text }} numberOfLines={2}>{day.title}</Text>
                      {day.description ? (
                        <Text className="mt-1 text-sm leading-5" style={{ color: theme.textSecondary }} numberOfLines={2}>{day.description}</Text>
                      ) : null}
                    </View>
                    <Chip size="sm" variant="secondary"><Chip.Label>{t('donations.progress', { percent: pct })}</Chip.Label></Chip>
                  </View>
                  <View className="h-2 overflow-hidden rounded-full" style={{ backgroundColor: withAlpha(primary, 0.12) }}>
                    <View className="h-full rounded-full" style={{ width: `${pct}%`, backgroundColor: primary }} />
                  </View>
                  <Text className="text-xs" style={{ color: theme.textMuted }}>
                    {t('donations.raisedOfGoal', { raised: formatMoney(current, currency), goal: formatMoney(goal, currency) })}
                  </Text>
                  <HeroButton
                    size="sm"
                    variant={selectedDayId === day.id ? 'primary' : 'secondary'}
                    onPress={() => setSelectedDayId(selectedDayId === day.id ? null : day.id)}
                  >
                    <HeroButton.Label>{selectedDayId === day.id ? t('donations.selected') : t('donations.selectCampaign')}</HeroButton.Label>
                  </HeroButton>
                </HeroCard.Body>
              </HeroCard>
            );
          })}
        </View>
      ) : null}

      <HeroCard className="rounded-panel p-0">
        <HeroCard.Body className="gap-4 p-4">
          <View>
            <Text className="text-base font-semibold" style={{ color: theme.text }}>{t('donations.makeDonation')}</Text>
            <Text className="mt-1 text-sm" style={{ color: theme.textSecondary }}>{t('donations.makeDonationHint')}</Text>
          </View>
          <View className="flex-row gap-2">
            <Input
              value={amount}
              onChangeText={setAmount}
              placeholder={t('donations.amountPlaceholder')}
              placeholderTextColor={theme.textMuted}
              keyboardType="decimal-pad"
              className="flex-1 text-base"
              style={{ color: theme.text }}
              accessibilityLabel={t('donations.amountPlaceholder')}
            />
            <Input
              value={currency}
              onChangeText={setCurrency}
              placeholder={t('expenses.currencyPlaceholder')}
              placeholderTextColor={theme.textMuted}
              autoCapitalize="characters"
              className="w-24 text-base"
              style={{ color: theme.text }}
              accessibilityLabel={t('expenses.currencyPlaceholder')}
            />
          </View>
          <Input
            value={message}
            onChangeText={setMessage}
            placeholder={t('donations.messagePlaceholder')}
            placeholderTextColor={theme.textMuted}
            multiline
            className="min-h-[86px] text-base"
            style={{ color: theme.text, textAlignVertical: 'top' }}
            accessibilityLabel={t('donations.messagePlaceholder')}
          />
          <HeroButton size="sm" variant={anonymous ? 'primary' : 'secondary'} onPress={() => setAnonymous((value) => !value)}>
            <Ionicons name={anonymous ? 'eye-off-outline' : 'eye-outline'} size={16} color={anonymous ? '#fff' : primary} />
            <HeroButton.Label>{anonymous ? t('donations.anonymousOn') : t('donations.anonymousOff')}</HeroButton.Label>
          </HeroButton>
          <HeroButton isDisabled={submitting} onPress={() => void handleSubmit()}>
            {submitting ? <Spinner size="sm" /> : <HeroButton.Label>{t('donations.submit')}</HeroButton.Label>}
          </HeroButton>
        </HeroCard.Body>
      </HeroCard>

      {donations.length === 0 ? (
        <EmptyState icon="heart-outline" title={t('donations.emptyTitle')} />
      ) : (
        donations.map((donation) => (
          <HeroCard key={donation.id} className="rounded-panel p-0">
            <HeroCard.Body className="gap-2 p-4">
              <View className="flex-row items-start justify-between gap-3">
                <View className="min-w-0 flex-1">
                  <Text className="text-base font-semibold" style={{ color: theme.text }}>
                    {formatMoney(donation.amount, donation.currency)}
                  </Text>
                  {donation.message ? (
                    <Text className="mt-1 text-sm" style={{ color: theme.textSecondary }} numberOfLines={2}>{donation.message}</Text>
                  ) : null}
                </View>
                <StatusChip label={t(`donations.status.${donation.status}`, { defaultValue: String(donation.status) })} tone={donation.status === 'completed' ? theme.success : theme.warning} icon="ellipse-outline" />
              </View>
              <Text className="text-xs" style={{ color: theme.textMuted }}>
                {formatDate(donation.created_at) ?? t('expenses.dateUnknown')}
              </Text>
            </HeroCard.Body>
          </HeroCard>
        ))
      )}
    </View>
  );
}

function HoursPanel({
  summary,
  organisations,
  isLoading,
  onRefresh,
}: {
  summary: VolunteerHoursSummary | null;
  organisations: VolunteeringOrganisation[];
  isLoading: boolean;
  onRefresh: () => void;
}) {
  const { t } = useTranslation('volunteering');
  const primary = usePrimaryColor();
  const theme = useTheme();
  const [selectedOrgId, setSelectedOrgId] = useState<number | null>(null);
  const [hours, setHours] = useState('');
  const [description, setDescription] = useState('');
  const [logging, setLogging] = useState(false);

  useEffect(() => {
    if (selectedOrgId === null && organisations.length > 0) {
      setSelectedOrgId(organisations[0]?.id ?? null);
    }
  }, [organisations, selectedOrgId]);

  async function handleLogHours() {
    const parsedHours = Number(hours);
    if (!selectedOrgId || !Number.isFinite(parsedHours) || parsedHours <= 0) {
      Alert.alert(t('common:errors.alertTitle'), t('hoursRequired'));
      return;
    }

    setLogging(true);
    try {
      await logVolunteerHours({
        organization_id: selectedOrgId,
        date: new Date().toISOString().split('T')[0],
        hours: parsedHours,
        description: description.trim() || undefined,
      });
      setHours('');
      setDescription('');
      onRefresh();
      void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
    } catch {
      void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Error);
      Alert.alert(t('common:errors.alertTitle'), t('hoursLogError'));
    } finally {
      setLogging(false);
    }
  }

  if (isLoading) {
    return <LoadingSpinner />;
  }

  const verified = summary?.total_verified ?? 0;
  const pending = summary?.total_pending ?? 0;
  const declined = summary?.total_declined ?? 0;

  return (
    <View className="gap-4">
      <View className="flex-row flex-wrap gap-3">
        <StatTile label={t('hoursStats.verified')} value={String(verified)} tone="#22c55e" />
        <StatTile label={t('hoursStats.pending')} value={String(pending)} tone="#f59e0b" />
        <StatTile label={t('hoursStats.declined')} value={String(declined)} tone="#ef4444" />
      </View>

      <HeroCard className="rounded-panel p-0">
        <HeroCard.Body className="gap-4 p-4">
          <View>
            <Text className="text-base font-semibold" style={{ color: theme.text }}>
              {t('logHours')}
            </Text>
            <Text className="mt-1 text-sm" style={{ color: theme.textSecondary }}>
              {organisations.length > 0 ? t('logHoursHint') : t('noLoggableOrganisations')}
            </Text>
          </View>

          {organisations.length > 0 ? (
            <>
              <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerClassName="gap-2">
                {organisations.map((org) => {
                  const selected = selectedOrgId === org.id;
                  return (
                    <HeroButton
                      key={org.id}
                      size="sm"
                      variant={selected ? 'primary' : 'secondary'}
                      onPress={() => setSelectedOrgId(org.id)}
                      style={selected ? { backgroundColor: withAlpha(primary, 0.18) } : undefined}
                    >
                      <HeroButton.Label style={{ color: selected ? primary : theme.textSecondary }}>
                        {org.name}
                      </HeroButton.Label>
                    </HeroButton>
                  );
                })}
              </ScrollView>
              <Input
                value={hours}
                onChangeText={setHours}
                placeholder={t('hoursPlaceholder')}
                placeholderTextColor={theme.textMuted}
                keyboardType="decimal-pad"
                className="text-base"
                style={{ color: theme.text }}
                accessibilityLabel={t('hoursPlaceholder')}
              />
              <Input
                value={description}
                onChangeText={setDescription}
                placeholder={t('hoursDescriptionPlaceholder')}
                placeholderTextColor={theme.textMuted}
                multiline
                className="min-h-[92px] text-base"
                style={{ color: theme.text, textAlignVertical: 'top' }}
                accessibilityLabel={t('hoursDescriptionPlaceholder')}
              />
              <HeroButton isDisabled={logging} onPress={() => void handleLogHours()}>
                {logging ? <Spinner size="sm" /> : <HeroButton.Label>{t('submitHours')}</HeroButton.Label>}
              </HeroButton>
            </>
          ) : null}
        </HeroCard.Body>
      </HeroCard>

      {summary?.by_organization?.length ? (
        <HeroCard className="rounded-panel p-0">
          <HeroCard.Body className="gap-3 p-4">
            <Text className="text-xs font-semibold uppercase" style={{ color: theme.textSecondary }}>
              {t('byOrganisation')}
            </Text>
            {summary.by_organization.slice(0, 5).map((item) => (
              <View key={item.name} className="flex-row items-center justify-between gap-3">
                <Text className="min-w-0 flex-1 text-sm" style={{ color: theme.text }} numberOfLines={1}>
                  {item.name}
                </Text>
                <Text className="text-sm font-semibold" style={{ color: theme.text }}>
                  {t('hoursValue', { count: item.hours })}
                </Text>
              </View>
            ))}
          </HeroCard.Body>
        </HeroCard>
      ) : null}
    </View>
  );
}

export default function VolunteeringScreen() {
  return (
    <ModalErrorBoundary>
      <VolunteeringScreenInner />
    </ModalErrorBoundary>
  );
}

function VolunteeringScreenInner() {
  const { t } = useTranslation(['volunteering', 'common']);
  const { isAuthenticated } = useAuth();
  const primary = usePrimaryColor();
  const theme = useTheme();
  const [activeTab, setActiveTab] = useState<TabKey>('opportunities');
  const [search, setSearch] = useState('');
  const [committedSearch, setCommittedSearch] = useState('');
  const [applyingId, setApplyingId] = useState<number | null>(null);
  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  function handleSearchChange(text: string) {
    setSearch(text);
    if (debounceRef.current) clearTimeout(debounceRef.current);
    debounceRef.current = setTimeout(() => {
      setCommittedSearch(text.trim());
    }, 400);
  }

  function handleClear() {
    if (debounceRef.current) clearTimeout(debounceRef.current);
    setSearch('');
    setCommittedSearch('');
  }

  useEffect(() => {
    return () => {
      if (debounceRef.current) clearTimeout(debounceRef.current);
    };
  }, []);

  const fetchFn = useCallback(
    (cursor: string | null) => getOpportunities(cursor, committedSearch || undefined),
    [committedSearch],
  );

  const extractor = useCallback(
    (response: VolunteeringResponse) => ({
      items: response.data,
      cursor: response.meta.cursor,
      hasMore: response.meta.has_more,
    }),
    [],
  );

  const opportunitiesApi = usePaginatedApi<VolunteerOpportunity, VolunteeringResponse>(fetchFn, extractor, [committedSearch]);
  const applicationsApi = useApi<VolunteerApplicationsResponse>(() => getMyApplications(), [], { enabled: isAuthenticated });
  const shiftsApi = useApi<MyShiftsResponse>(() => getMyShifts(), [], { enabled: isAuthenticated });
  const hoursApi = useApi<{ data: VolunteerHoursSummary }>(() => getHoursSummary(), [], { enabled: isAuthenticated });
  const organisationsApi = useApi<MyOrganisationsResponse>(() => getMyOrganisations(), [], { enabled: isAuthenticated });
  const certificatesApi = useApi<VolunteerCertificatesResponse>(() => getVolunteerCertificates(), [], { enabled: isAuthenticated });
  const expensesApi = useApi<VolunteerExpensesResponse>(() => getVolunteerExpenses(), [], { enabled: isAuthenticated });
  const givingDaysApi = useApi<VolunteerGivingDaysResponse>(() => getVolunteerGivingDays(), [], { enabled: isAuthenticated });
  const donationsApi = useApi<VolunteerDonationsResponse>(() => getVolunteerDonations(), [], { enabled: isAuthenticated });

  const opportunities = opportunitiesApi.items;
  const applications = applicationsApi.data?.data ?? [];
  const shifts = shiftsApi.data?.data.items ?? [];
  const summary = hoursApi.data?.data ?? null;
  const organisations = organisationsApi.data?.data ?? [];
  const certificates = certificatesApi.data?.data.items ?? [];
  const expenses = expensesApi.data?.data.items ?? expensesApi.data?.data.expenses ?? [];
  const givingDays = givingDaysApi.data?.data ?? [];
  const donations = donationsApi.data?.data.items ?? [];
  const loggableOrganisations = useMemo(
    () => getLoggableOrganisations(organisations, applications),
    [applications, organisations],
  );
  const verifiedHours = summary?.total_verified ?? 0;

  async function handleApply(item: VolunteerOpportunity) {
    if (!isAuthenticated) {
      Alert.alert(t('signInRequiredTitle'), t('signInRequiredMessage'));
      return;
    }
    setApplyingId(item.id);
    try {
      await expressInterest(item.id);
      opportunitiesApi.refresh();
      applicationsApi.refresh();
      void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
    } catch {
      void Haptics.notificationAsync(Haptics.NotificationFeedbackType.Error);
      Alert.alert(t('common:errors.alertTitle'), t('applyError'));
    } finally {
      setApplyingId(null);
    }
  }

  const tabs: Array<{ key: TabKey; label: string; icon: IoniconName; requiresAuth?: boolean }> = [
    { key: 'opportunities', label: t('tabs.opportunities'), icon: 'briefcase-outline' },
    { key: 'applications', label: t('tabs.applications'), icon: 'send-outline', requiresAuth: true },
    { key: 'shifts', label: t('tabs.shifts'), icon: 'calendar-outline', requiresAuth: true },
    { key: 'hours', label: t('tabs.hours'), icon: 'time-outline', requiresAuth: true },
    { key: 'certificates', label: t('tabs.certificates'), icon: 'ribbon-outline', requiresAuth: true },
    { key: 'expenses', label: t('tabs.expenses'), icon: 'receipt-outline', requiresAuth: true },
    { key: 'donations', label: t('tabs.donations'), icon: 'heart-outline', requiresAuth: true },
  ];

  const visibleTabs = tabs.filter((tab) => !tab.requiresAuth || isAuthenticated);

  useEffect(() => {
    if (!visibleTabs.some((tab) => tab.key === activeTab)) {
      setActiveTab('opportunities');
    }
  }, [activeTab, visibleTabs]);

  return (
    <SafeAreaView className="flex-1 bg-background">
      <AppTopBar title={t('title')} backLabel={t('common:back')} fallbackHref="/(tabs)/home" />
      <FlatList<VolunteerOpportunity>
        data={activeTab === 'opportunities' ? opportunities : []}
        keyExtractor={(item) => String(item.id)}
        renderItem={({ item }) => (
          <OpportunityCard
            item={item}
            applying={applyingId === item.id}
            onOpen={() => {
              void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
              router.push({ pathname: '/(modals)/volunteering-detail', params: { id: String(item.id) } });
            }}
            onApply={() => void handleApply(item)}
          />
        )}
        onEndReached={activeTab === 'opportunities' && opportunitiesApi.hasMore ? opportunitiesApi.loadMore : undefined}
        onEndReachedThreshold={0.3}
        refreshControl={
          <RefreshControl
            refreshing={activeTab === 'opportunities' && opportunitiesApi.isLoading && opportunities.length > 0}
            onRefresh={() => {
              opportunitiesApi.refresh();
              applicationsApi.refresh();
              shiftsApi.refresh();
              hoursApi.refresh();
              organisationsApi.refresh();
              certificatesApi.refresh();
              expensesApi.refresh();
              givingDaysApi.refresh();
              donationsApi.refresh();
            }}
            tintColor={primary}
            colors={[primary]}
          />
        }
        ListHeaderComponent={
          <View className="gap-4 pt-3">
            <HeroHeader
              activeCount={opportunities.length}
              applicationsCount={applications.length}
              verifiedHours={verifiedHours}
            />

            <Surface variant="secondary" className="rounded-panel p-1">
              <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerClassName="gap-1">
                {visibleTabs.map((tab) => {
                  const selected = activeTab === tab.key;
                  return (
                    <HeroButton
                      key={tab.key}
                      size="sm"
                      variant={selected ? 'primary' : 'ghost'}
                      onPress={() => {
                        void Haptics.impactAsync(Haptics.ImpactFeedbackStyle.Light);
                        setActiveTab(tab.key);
                      }}
                      className="h-11 min-w-[132px] rounded-panel-inner"
                      style={{ backgroundColor: selected ? withAlpha(primary, 0.18) : 'transparent' }}
                    >
                      <Ionicons name={tab.icon} size={16} color={selected ? primary : theme.textSecondary} />
                      <HeroButton.Label style={{ color: selected ? primary : theme.textSecondary }}>
                        {tab.label}
                      </HeroButton.Label>
                    </HeroButton>
                  );
                })}
              </ScrollView>
            </Surface>

            {activeTab === 'opportunities' ? (
              <Input
                className="text-sm"
                style={{ color: theme.text }}
                placeholder={t('searchPlaceholder')}
                placeholderTextColor={theme.textMuted}
                value={search}
                onChangeText={handleSearchChange}
                returnKeyType="search"
                clearButtonMode="never"
                autoCorrect={false}
                autoCapitalize="none"
                accessibilityLabel={t('searchPlaceholder')}
                leftIcon={<Ionicons name="search-outline" size={18} color={theme.textMuted} />}
                rightIcon={search.length > 0 ? (
                  <HeroButton
                    isIconOnly
                    size="sm"
                    variant="ghost"
                    onPress={handleClear}
                    accessibilityLabel={t('clearSearch')}
                  >
                    <Ionicons name="close-circle" size={18} color={theme.textMuted} />
                  </HeroButton>
                ) : null}
              />
            ) : null}

            {activeTab === 'applications' ? (
              <ApplicationsPanel
                applications={applications}
                isLoading={applicationsApi.isLoading}
                onRefresh={applicationsApi.refresh}
              />
            ) : null}

            {activeTab === 'hours' ? (
              <HoursPanel
                summary={summary}
                organisations={loggableOrganisations}
                isLoading={hoursApi.isLoading || organisationsApi.isLoading}
                onRefresh={() => {
                  applicationsApi.refresh();
                  shiftsApi.refresh();
                  hoursApi.refresh();
                  organisationsApi.refresh();
                  certificatesApi.refresh();
                  expensesApi.refresh();
                  givingDaysApi.refresh();
                  donationsApi.refresh();
                }}
              />
            ) : null}

            {activeTab === 'shifts' ? (
              <ShiftsPanel
                shifts={shifts}
                isLoading={shiftsApi.isLoading}
                onRefresh={shiftsApi.refresh}
              />
            ) : null}

            {activeTab === 'certificates' ? (
              <CertificatesPanel
                certificates={certificates}
                isLoading={certificatesApi.isLoading}
                onRefresh={certificatesApi.refresh}
              />
            ) : null}

            {activeTab === 'expenses' ? (
              <ExpensesPanel
                expenses={expenses}
                organisations={loggableOrganisations}
                isLoading={expensesApi.isLoading || organisationsApi.isLoading}
                onRefresh={expensesApi.refresh}
              />
            ) : null}

            {activeTab === 'donations' ? (
              <DonationsPanel
                givingDays={givingDays}
                donations={donations}
                isLoading={givingDaysApi.isLoading || donationsApi.isLoading}
                onRefresh={() => {
                  givingDaysApi.refresh();
                  donationsApi.refresh();
                }}
              />
            ) : null}

            {activeTab === 'opportunities' && opportunitiesApi.error ? (
              <HeroCard className="rounded-panel p-0">
                <HeroCard.Body className="items-center gap-3 p-6">
                  <Ionicons name="warning-outline" size={28} color={theme.error} />
                  <Text className="text-center text-sm" style={{ color: theme.textSecondary }}>
                    {opportunitiesApi.error}
                  </Text>
                  <HeroButton variant="secondary" onPress={opportunitiesApi.refresh}>
                    <HeroButton.Label>{t('tryAgain')}</HeroButton.Label>
                  </HeroButton>
                </HeroCard.Body>
              </HeroCard>
            ) : null}
          </View>
        }
        ListEmptyComponent={
          activeTab === 'opportunities' ? (
            opportunitiesApi.isLoading ? (
              <View className="py-10">
                <LoadingSpinner />
              </View>
            ) : !opportunitiesApi.error ? (
              <EmptyState icon="heart-outline" title={t('empty')} />
            ) : null
          ) : null
        }
        ListFooterComponent={
          activeTab === 'opportunities' && opportunitiesApi.isLoadingMore ? (
            <View className="py-4">
              <LoadingSpinner />
            </View>
          ) : null
        }
        contentContainerStyle={{ flexGrow: 1, paddingHorizontal: 16, paddingBottom: 32 }}
      />
    </SafeAreaView>
  );
}
