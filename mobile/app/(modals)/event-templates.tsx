// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useCallback, useEffect, useState } from 'react';
import { ScrollView, Text, View } from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { router } from 'expo-router';
import { useTranslation } from 'react-i18next';
import {
  Alert,
  Button,
  Card,
  Input,
  Label,
  Spinner,
  TextField,
} from 'heroui-native';

import AppTopBar from '@/components/ui/AppTopBar';
import ModalErrorBoundary from '@/components/ModalErrorBoundary';
import { useAppToast } from '@/components/ui/AppToast';
import {
  getEventTemplates,
  materializeEventTemplate,
  previewEventTemplate,
  type MobileEventTemplate,
  type MobileEventTemplateInput,
  type MobileEventTemplatePreview,
} from '@/lib/api/eventTemplates';
import { useTheme } from '@/lib/hooks/useTheme';

function defaultStart(): string {
  const date = new Date(Date.now() + 24 * 60 * 60 * 1000);
  date.setMinutes(0, 0, 0);
  return date.toISOString().slice(0, 16);
}

function idempotencyKey(): string {
  if (typeof globalThis.crypto?.randomUUID === 'function') {
    return globalThis.crypto.randomUUID();
  }
  return `mobile-event-template-${Date.now()}-${Math.random().toString(36).slice(2)}`;
}

export default function EventTemplatesScreen() {
  return (
    <ModalErrorBoundary>
      <EventTemplatesScreenInner />
    </ModalErrorBoundary>
  );
}

function EventTemplatesScreenInner() {
  const { t } = useTranslation(['event_templates', 'common']);
  const theme = useTheme();
  const { show: showToast } = useAppToast();
  const [templates, setTemplates] = useState<MobileEventTemplate[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [loadFailed, setLoadFailed] = useState(false);
  const [selected, setSelected] = useState<MobileEventTemplate | null>(null);
  const [title, setTitle] = useState('');
  const [startTime, setStartTime] = useState(defaultStart());
  const [endTime, setEndTime] = useState('');
  const [timezone, setTimezone] = useState('UTC');
  const [preview, setPreview] = useState<MobileEventTemplatePreview | null>(null);
  const [confirmedInput, setConfirmedInput] = useState<MobileEventTemplateInput | null>(null);
  const [isPreviewing, setIsPreviewing] = useState(false);
  const [isCreating, setIsCreating] = useState(false);

  const load = useCallback(async () => {
    setIsLoading(true);
    setLoadFailed(false);
    try {
      const response = await getEventTemplates();
      setTemplates(response.data);
    } catch {
      setLoadFailed(true);
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    void load();
  }, [load]);

  function selectTemplate(template: MobileEventTemplate) {
    setSelected(template);
    setTitle(template.version.configuration.title);
    setTimezone(template.version.configuration.timezone);
    setStartTime(defaultStart());
    setEndTime('');
    setPreview(null);
    setConfirmedInput(null);
  }

  function inputForSelection(): MobileEventTemplateInput | null {
    if (!selected || !title.trim() || !startTime.trim() || !timezone.trim()) return null;
    if (endTime.trim() && endTime.trim() <= startTime.trim()) return null;
    const overrides: MobileEventTemplateInput['overrides'] = {};
    if (title.trim() !== selected.version.configuration.title) overrides.title = title.trim();
    if (timezone.trim() !== selected.version.configuration.timezone) overrides.timezone = timezone.trim();

    return {
      template_version: selected.current_version,
      start_time: startTime.trim(),
      end_time: endTime.trim() || null,
      overrides,
    };
  }

  async function reviewDraft() {
    if (!selected) return;
    const input = inputForSelection();
    if (!input) {
      showToast({
        title: t('templates.mobile.validationTitle'),
        description: t('templates.mobile.validationDescription'),
        variant: 'warning',
      });
      return;
    }
    setIsPreviewing(true);
    try {
      const result = await previewEventTemplate(selected.id, input);
      setConfirmedInput(input);
      setPreview(result);
    } catch {
      showToast({
        title: t('templates.mobile.previewFailedTitle'),
        description: t('templates.mobile.previewFailedDescription'),
        variant: 'danger',
      });
    } finally {
      setIsPreviewing(false);
    }
  }

  async function createDraft() {
    if (!selected || !confirmedInput) return;
    setIsCreating(true);
    try {
      const result = await materializeEventTemplate(
        selected.id,
        confirmedInput,
        idempotencyKey(),
      );
      showToast({
        title: t('templates.mobile.createdTitle'),
        description: t('templates.mobile.createdDescription'),
        variant: 'success',
      });
      router.replace({
        pathname: '/(modals)/edit-event',
        params: { id: String(result.created_event.id) },
      });
    } catch {
      showToast({
        title: t('templates.mobile.createFailedTitle'),
        description: t('templates.mobile.createFailedDescription'),
        variant: 'danger',
      });
    } finally {
      setIsCreating(false);
    }
  }

  return (
    <SafeAreaView className="flex-1 bg-background" edges={['top', 'bottom']}>
      <AppTopBar
        title={t('templates.mobile.title')}
        backLabel={t('common:back')}
        fallbackHref="/(tabs)/events"
      />
      <ScrollView contentContainerClassName="gap-4 px-4 pb-10">
        <Alert status="accent">
          <Alert.Indicator />
          <Alert.Content>
            <Alert.Title>{t('templates.mobile.safetyTitle')}</Alert.Title>
            <Alert.Description>{t('templates.mobile.safetyDescription')}</Alert.Description>
          </Alert.Content>
        </Alert>

        {isLoading ? (
          <View className="items-center py-16" accessibilityLabel={t('templates.mobile.loading')}>
            <Spinner size="lg" />
          </View>
        ) : loadFailed ? (
          <Alert status="danger">
            <Alert.Indicator />
            <Alert.Content>
              <Alert.Title>{t('templates.mobile.loadFailedTitle')}</Alert.Title>
              <Alert.Description>{t('templates.mobile.loadFailedDescription')}</Alert.Description>
            </Alert.Content>
            <Button size="sm" variant="danger" onPress={() => void load()}>
              {t('common:retry')}
            </Button>
          </Alert>
        ) : templates.length === 0 ? (
          <Card>
            <Card.Body>
              <Card.Title>{t('templates.mobile.emptyTitle')}</Card.Title>
              <Card.Description>{t('templates.mobile.emptyDescription')}</Card.Description>
            </Card.Body>
          </Card>
        ) : selected === null ? (
          templates.map((template) => (
            <Card key={template.id}>
              <Card.Body>
                <Card.Title>{template.version.configuration.title}</Card.Title>
                <Card.Description>
                  {t('templates.mobile.source', { title: template.source_event.title })}
                </Card.Description>
                <Text className="mt-2 text-sm" style={{ color: theme.textMuted }}>
                  {t('templates.mobile.versionAndUses', {
                    version: template.current_version,
                    count: template.usage.materialization_count,
                  })}
                </Text>
              </Card.Body>
              <Card.Footer>
                <Button
                  variant="primary"
                  isDisabled={!template.capabilities.materialize}
                  onPress={() => selectTemplate(template)}
                >
                  {t('templates.mobile.useTemplate')}
                </Button>
              </Card.Footer>
            </Card>
          ))
        ) : preview === null ? (
          <Card>
            <Card.Body className="gap-4">
              <Card.Title>{t('templates.mobile.scheduleTitle')}</Card.Title>
              <Card.Description>{t('templates.mobile.scheduleDescription')}</Card.Description>
              <TextField isRequired>
                <Label>{t('templates.mobile.eventTitle')}</Label>
                <Input value={title} onChangeText={setTitle} maxLength={255} />
              </TextField>
              <TextField isRequired>
                <Label>{t('templates.mobile.start')}</Label>
                <Input
                  testID="event-template-start"
                  value={startTime}
                  onChangeText={setStartTime}
                  placeholder={t('templates.mobile.datePlaceholder')}
                  autoCapitalize="none"
                />
              </TextField>
              <TextField>
                <Label>{t('templates.mobile.end')}</Label>
                <Input
                  testID="event-template-end"
                  value={endTime}
                  onChangeText={setEndTime}
                  placeholder={t('templates.mobile.optionalDatePlaceholder')}
                  autoCapitalize="none"
                />
              </TextField>
              <TextField isRequired>
                <Label>{t('templates.mobile.timezone')}</Label>
                <Input value={timezone} onChangeText={setTimezone} autoCapitalize="none" />
              </TextField>
            </Card.Body>
            <Card.Footer className="gap-3">
              <Button variant="secondary" onPress={() => setSelected(null)}>
                {t('common:buttons.cancel')}
              </Button>
              <Button isDisabled={isPreviewing} onPress={() => void reviewDraft()}>
                {isPreviewing ? <Spinner size="sm" /> : t('templates.mobile.review')}
              </Button>
            </Card.Footer>
          </Card>
        ) : (
          <Card>
            <Card.Body className="gap-4">
              <Card.Title>{t('templates.mobile.reviewTitle')}</Card.Title>
              <Card.Description>{t('templates.mobile.reviewDescription')}</Card.Description>
              <Alert status="success">
                <Alert.Indicator />
                <Alert.Content>
                  <Alert.Title>{t('templates.mobile.readyTitle')}</Alert.Title>
                  <Alert.Description>{t('templates.mobile.readyDescription')}</Alert.Description>
                </Alert.Content>
              </Alert>
              <View className="gap-2">
                <Text className="font-semibold" style={{ color: theme.text }}>
                  {preview.configuration.title}
                </Text>
                <Text style={{ color: theme.textMuted }}>
                  {t('templates.mobile.draftState')}
                </Text>
                {preview.checklist.map((item) => (
                  <Text key={item.code} className="text-sm" style={{ color: theme.text }}>
                    {t(`templates.checks.${item.code}`)}
                  </Text>
                ))}
              </View>
            </Card.Body>
            <Card.Footer className="gap-3">
              <Button
                variant="secondary"
                isDisabled={isCreating}
                onPress={() => {
                  setPreview(null);
                  setConfirmedInput(null);
                }}
              >
                {t('templates.mobile.changeDetails')}
              </Button>
              <Button isDisabled={isCreating} onPress={() => void createDraft()}>
                {isCreating ? <Spinner size="sm" /> : t('templates.mobile.createDraft')}
              </Button>
            </Card.Footer>
          </Card>
        )}
      </ScrollView>
    </SafeAreaView>
  );
}
