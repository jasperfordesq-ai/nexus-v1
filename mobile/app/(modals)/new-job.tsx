// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState } from 'react';
import { Alert, ScrollView, TextInput, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { router } from 'expo-router';
import { Ionicons } from '@expo/vector-icons';
import { Button as HeroButton, Card as HeroCard, Text } from 'heroui-native';
import * as Haptics from '@/lib/haptics';
import { useTranslation } from 'react-i18next';

import { createJob, type CreateJobPayload } from '@/lib/api/jobs';
import { usePrimaryColor } from '@/lib/hooks/useTenant';
import { useTheme } from '@/lib/hooks/useTheme';
import { withAlpha } from '@/lib/utils/color';
import AppTopBar from '@/components/ui/AppTopBar';
import FormActionFooter from '@/components/ui/FormActionFooter';
import ModalErrorBoundary from '@/components/ModalErrorBoundary';

type JobType = CreateJobPayload['type'];
type Commitment = CreateJobPayload['commitment'];

const jobTypes: JobType[] = ['volunteer', 'timebank', 'paid'];
const commitments: Commitment[] = ['flexible', 'part_time', 'full_time', 'one_off'];

export default function NewJobRoute() {
  return (
    <ModalErrorBoundary>
      <NewJobScreen />
    </ModalErrorBoundary>
  );
}

function NewJobScreen() {
  const { t } = useTranslation(['jobs', 'common']);
  const primary = usePrimaryColor();
  const theme = useTheme();
  const [title, setTitle] = useState('');
  const [description, setDescription] = useState('');
  const [type, setType] = useState<JobType>('volunteer');
  const [commitment, setCommitment] = useState<Commitment>('flexible');
  const [location, setLocation] = useState('');
  const [category, setCategory] = useState('');
  const [skills, setSkills] = useState('');
  const [hours, setHours] = useState('');
  const [credits, setCredits] = useState('');
  const [deadline, setDeadline] = useState('');
  const [isRemote, setIsRemote] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);

  async function submit() {
    if (!title.trim() || !description.trim()) {
      Alert.alert(t('create.validationTitle'), t('create.validationRequired'));
      return;
    }

    setIsSubmitting(true);
    try {
      const result = await createJob({
        title: title.trim(),
        description: description.trim(),
        type,
        commitment,
        location: location.trim() || null,
        is_remote: isRemote,
        category: category.trim() || null,
        skills_required: skills.split(',').map((skill) => skill.trim()).filter(Boolean),
        hours_per_week: hours.trim() ? Number(hours) : null,
        time_credits: credits.trim() ? Number(credits) : null,
        deadline: deadline.trim() || null,
        salary_negotiable: true,
        status: 'open',
      });
      await Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
      const id = result.data?.id;
      if (id) {
        router.replace({ pathname: '/(modals)/job-detail', params: { id: String(id) } });
      } else {
        router.back();
      }
    } catch (error) {
      Alert.alert(t('create.failedTitle'), error instanceof Error ? error.message : t('create.failedDescription'));
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <SafeAreaView className="flex-1 bg-background">
      <AppTopBar title={t('create.title')} backLabel={t('common:back')} fallbackHref="/(modals)/jobs" />
      <ScrollView className="flex-1" contentContainerStyle={{ padding: 16, paddingBottom: 120 }}>
        <HeroCard className="mb-4 overflow-hidden rounded-panel p-0">
          <View className="h-1.5" style={{ backgroundColor: '#06b6d4' }} />
          <HeroCard.Body className="gap-4 p-4">
            <View className="flex-row items-start gap-3">
              <View className="size-13 items-center justify-center rounded-3xl" style={{ backgroundColor: withAlpha('#06b6d4', 0.14) }}>
                <Ionicons name="briefcase-outline" size={25} color="#06b6d4" />
              </View>
              <View className="min-w-0 flex-1">
                <Text className="text-xs font-bold uppercase" style={{ color: theme.textSecondary }}>{t('create.eyebrow')}</Text>
                <Text className="text-2xl font-bold" style={{ color: theme.text }}>{t('create.title')}</Text>
                <Text className="text-sm leading-5" style={{ color: theme.textSecondary }}>{t('create.subtitle')}</Text>
              </View>
            </View>
          </HeroCard.Body>
        </HeroCard>

        <HeroCard className="rounded-panel p-0">
          <HeroCard.Body className="gap-4 p-4">
            <FormField label={t('create.titleLabel')} value={title} onChangeText={setTitle} placeholder={t('create.titlePlaceholder')} theme={theme} />
            <FormField label={t('create.descriptionLabel')} value={description} onChangeText={setDescription} placeholder={t('create.descriptionPlaceholder')} theme={theme} multiline />
            <ButtonGroup label={t('create.typeLabel')} values={jobTypes} selected={type} onSelect={setType} labelFor={(value) => t(`filters.type.${value}`)} primary={primary} theme={theme} />
            <ButtonGroup label={t('create.commitmentLabel')} values={commitments} selected={commitment} onSelect={setCommitment} labelFor={(value) => t(`filters.commitment.${value}`)} primary={primary} theme={theme} />
            <FormField label={t('create.locationLabel')} value={location} onChangeText={setLocation} placeholder={t('create.locationPlaceholder')} theme={theme} />
            <FormField label={t('create.categoryLabel')} value={category} onChangeText={setCategory} placeholder={t('create.categoryPlaceholder')} theme={theme} />
            <FormField label={t('create.skillsLabel')} value={skills} onChangeText={setSkills} placeholder={t('create.skillsPlaceholder')} theme={theme} />
            <FormField label={t('create.hoursLabel')} value={hours} onChangeText={setHours} placeholder={t('create.hoursPlaceholder')} theme={theme} keyboardType="decimal-pad" />
            <FormField label={t('create.creditsLabel')} value={credits} onChangeText={setCredits} placeholder={t('create.creditsPlaceholder')} theme={theme} keyboardType="decimal-pad" />
            <FormField label={t('create.deadlineLabel')} value={deadline} onChangeText={setDeadline} placeholder={t('create.deadlinePlaceholder')} theme={theme} />

            <HeroButton variant={isRemote ? 'primary' : 'secondary'} onPress={() => setIsRemote((value) => !value)} style={isRemote ? { backgroundColor: primary } : undefined}>
              <Ionicons name="globe-outline" size={15} color={isRemote ? '#fff' : primary} />
              <HeroButton.Label>{t('create.remote')}</HeroButton.Label>
            </HeroButton>

          </HeroCard.Body>
        </HeroCard>
      </ScrollView>
      <FormActionFooter
        title={t('create.reviewTitle')}
        subtitle={t('create.reviewSubtitle')}
        submitLabel={t('create.submit')}
        primary={primary}
        isSubmitting={isSubmitting}
        onSubmit={submit}
      />
    </SafeAreaView>
  );
}

function ButtonGroup<T extends string>({
  label,
  values,
  selected,
  onSelect,
  labelFor,
  primary,
  theme,
}: {
  label: string;
  values: T[];
  selected: T;
  onSelect: (value: T) => void;
  labelFor: (value: T) => string;
  primary: string;
  theme: ReturnType<typeof useTheme>;
}) {
  return (
    <View className="gap-2">
      <Text className="text-xs font-bold uppercase" style={{ color: theme.textSecondary }}>{label}</Text>
      <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={{ gap: 8 }}>
        {values.map((value) => (
          <HeroButton key={value} size="sm" variant={selected === value ? 'primary' : 'secondary'} onPress={() => onSelect(value)} style={selected === value ? { backgroundColor: primary } : undefined}>
            <HeroButton.Label>{labelFor(value)}</HeroButton.Label>
          </HeroButton>
        ))}
      </ScrollView>
    </View>
  );
}

function FormField({
  label,
  value,
  onChangeText,
  placeholder,
  theme,
  multiline = false,
  keyboardType,
}: {
  label: string;
  value: string;
  onChangeText: (value: string) => void;
  placeholder: string;
  theme: ReturnType<typeof useTheme>;
  multiline?: boolean;
  keyboardType?: 'default' | 'decimal-pad';
}) {
  return (
    <View className="gap-2">
      <Text className="text-xs font-bold uppercase" style={{ color: theme.textSecondary }}>{label}</Text>
      <TextInput
        className={`${multiline ? 'min-h-28 py-3' : 'min-h-12'} rounded-panel-inner border px-3 text-sm`}
        style={{ borderColor: theme.border, color: theme.text, backgroundColor: theme.bg, textAlignVertical: multiline ? 'top' : 'center' }}
        placeholder={placeholder}
        placeholderTextColor={theme.textMuted}
        value={value}
        onChangeText={onChangeText}
        multiline={multiline}
        keyboardType={keyboardType}
      />
    </View>
  );
}
