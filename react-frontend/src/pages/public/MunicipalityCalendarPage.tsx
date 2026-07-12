// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { formatDateValue, getFormattingLocale } from '@/lib/helpers';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { useSearchParams, Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import Calendar from 'lucide-react/icons/calendar';
import ChevronLeft from 'lucide-react/icons/chevron-left';
import ChevronRight from 'lucide-react/icons/chevron-right';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { Chip } from '@/components/ui/Chip';
import { Input } from '@/components/ui/Input';
import { Separator } from '@/components/ui/Separator';
import { Spinner } from '@/components/ui/Spinner';
import { PageMeta } from '@/components/seo';
import { useTenant, useToast } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';

/**
 * MunicipalityCalendarPage — AG55
 *
 * Public joint events calendar for all consenting Vereine in the same
 * municipality. Auto-detects municipality from `?code=` URL param.
 * Route: /municipality-calendar
 */


interface CalendarEventDto {
  id: number;
  title: string;
  start_time: string | null;
  image_url: string | null;
  organization_id: number;
  organization_name: string;
}

interface CalendarResponse {
  municipality_code: string;
  period: string;
  start: string;
  end: string;
  buckets: Record<string, CalendarEventDto[]>;
}

function localDateKey(date: Date): string {
  return [
    date.getFullYear(),
    String(date.getMonth() + 1).padStart(2, '0'),
    String(date.getDate()).padStart(2, '0'),
  ].join('-');
}

export default function MunicipalityCalendarPage() {
  const { t } = useTranslation('common');
  const { tenantPath } = useTenant();
  const toast = useToast();
  const [params, setParams] = useSearchParams();
  usePageTitle(t('verein_federation.calendar.title'));

  const code = params.get('code') ?? '';
  const normalizedCode = code.trim();
  const [inputCode, setInputCode] = useState(code);
  const [data, setData] = useState<CalendarResponse | null>(null);
  const [loading, setLoading] = useState(false);

  const requestedMonth = params.get('month');
  const monthStart = useMemo(() => {
    const match = requestedMonth?.match(/^(\d{4})-(\d{2})$/);
    const value = match
      ? new Date(Number(match[1]), Number(match[2]) - 1, 1)
      : new Date(new Date().getFullYear(), new Date().getMonth(), 1);
    return Number.isNaN(value.getTime()) ? new Date(new Date().getFullYear(), new Date().getMonth(), 1) : value;
  }, [requestedMonth]);

  const monthEnd = useMemo(() => {
    return new Date(monthStart.getFullYear(), monthStart.getMonth() + 1, 1);
  }, [monthStart]);

  const load = useCallback(async (mc: string, from: string, until: string) => {
    if (!mc) {
      setData(null);
      return;
    }
    setLoading(true);
    try {
      const res = await api.get<CalendarResponse>(
        `/v2/municipality/${encodeURIComponent(mc)}/events-calendar?from=${from}&to=${until}`,
      );
      if (res.success && res.data) {
        setData(res.data);
      } else {
        toast.error(res.error || t('verein_federation.calendar.load_failed'));
        setData(null);
      }
    } catch (err) {
      logError('MunicipalityCalendarPage: load failed', err);
      toast.error(t('verein_federation.calendar.load_failed'));
    } finally {
      setLoading(false);
    }
  }, [toast, t]);

  useEffect(() => {
    void load(code, localDateKey(monthStart), localDateKey(monthEnd));
  }, [code, load, monthStart, monthEnd]);

  const handleSubmitCode = useCallback(() => {
    const next = inputCode.trim();
    if (!next) return;
    setParams((p) => {
      p.set('code', next);
      p.set('month', localDateKey(new Date()).slice(0, 7));
      return p;
    });
  }, [inputCode, setParams]);

  const setCalendarMonth = useCallback((date: Date) => {
    setParams((current) => {
      const next = new URLSearchParams(current);
      next.set('month', localDateKey(new Date(date.getFullYear(), date.getMonth(), 1)).slice(0, 7));
      return next;
    }, { replace: true });
  }, [setParams]);

  const monthLabel = useMemo(() => {
    return monthStart.toLocaleString(getFormattingLocale(), { month: 'long', year: 'numeric' });
  }, [monthStart]);

  const daysInMonth = useMemo(() => {
    const next = new Date(monthStart);
    next.setMonth(next.getMonth() + 1);
    const last = new Date(next.getTime() - 86400000);
    return last.getDate();
  }, [monthStart]);

  const locale = getFormattingLocale();
  const firstDay = useMemo(() => {
    const localeWithWeekInfo = new Intl.Locale(locale) as Intl.Locale & {
      getWeekInfo?: () => { firstDay: number };
      weekInfo?: { firstDay: number };
    };
    return localeWithWeekInfo.getWeekInfo?.().firstDay
      ?? localeWithWeekInfo.weekInfo?.firstDay
      ?? 1;
  }, [locale]);
  const firstDayJs = firstDay % 7;
  const leadingBlankDays = (monthStart.getDay() - firstDayJs + 7) % 7;
  const weekdayLabels = useMemo(() => Array.from({ length: 7 }, (_, index) => {
    const jsDay = (firstDayJs + index) % 7;
    const date = new Date(2024, 0, 7 + jsDay);
    return date.toLocaleDateString(getFormattingLocale(), { weekday: 'short' });
  }), [firstDayJs, locale]);

  const renderDayCell = (day: number) => {
    const date = new Date(monthStart);
    date.setDate(day);
    const key = localDateKey(date);
    const events = data?.buckets?.[key] ?? [];
    return (
      <div key={day} role="gridcell" aria-label={formatDateValue(date)} className="border border-border rounded-md p-2 min-h-[80px] text-xs">
        <div className="font-semibold text-foreground">{day}</div>
        <div className="space-y-1 mt-1">
          {events.map((ev) => (
            <Link
              key={ev.id}
              to={tenantPath(`/events/${ev.id}`)}
              className="block bg-accent-soft hover:bg-accent-soft dark:bg-accent-soft rounded px-1 py-0.5 truncate"
            >
              <span className="font-medium">{ev.organization_name}</span>: {ev.title}
            </Link>
          ))}
        </div>
      </div>
    );
  };

  return (
    <div className="mx-auto max-w-6xl px-4 py-6 space-y-4">
      <PageMeta
        title={t('verein_federation.calendar.title')}
        description={normalizedCode
          ? t('verein_federation.calendar.subtitle', { municipality: normalizedCode })
          : t('verein_federation.calendar.no_municipality')}
        url={normalizedCode
          ? tenantPath(`/municipality-calendar?code=${encodeURIComponent(normalizedCode)}`)
          : tenantPath('/municipality-calendar')}
        noIndex={!normalizedCode}
      />

      <div>
        <h1 className="text-2xl font-bold flex items-center gap-2">
          <Calendar className="w-6 h-6 text-accent" aria-hidden="true" />
          {t('verein_federation.calendar.title')}
        </h1>
        {code ? (
          <p className="text-sm text-muted mt-1">
            {t('verein_federation.calendar.subtitle', { municipality: code })}
          </p>
        ) : null}
      </div>

      <Card>
        <Card.Header className="flex flex-wrap items-end gap-3">
          <Input
            label={t('verein_federation.municipality_code_label')}
            value={inputCode}
            onValueChange={setInputCode}
            className="max-w-xs"
          />
          <Button onPress={handleSubmitCode}>
            {t('apply')}
          </Button>

          <div className="ml-auto flex items-center gap-2">
            <Button
              isIconOnly
              variant="tertiary"
              size="sm"
              onPress={() => setCalendarMonth(new Date(monthStart.getFullYear(), monthStart.getMonth() - 1, 1))}
              aria-label={t('verein_federation.calendar.prev')}
            >
              <ChevronLeft className="w-4 h-4" aria-hidden="true" />
            </Button>
            <Chip variant="tertiary">{monthLabel}</Chip>
            <Button
              isIconOnly
              variant="tertiary"
              size="sm"
              onPress={() => setCalendarMonth(new Date(monthStart.getFullYear(), monthStart.getMonth() + 1, 1))}
              aria-label={t('verein_federation.calendar.next')}
            >
              <ChevronRight className="w-4 h-4" aria-hidden="true" />
            </Button>
            <Button size="sm" variant="tertiary" onPress={() => setCalendarMonth(new Date())}>
              {t('verein_federation.calendar.today')}
            </Button>
          </div>
        </Card.Header>
        <Separator />
        <Card.Content>
          {!code ? (
            <p className="text-sm text-muted py-8 text-center">
              {t('verein_federation.calendar.no_municipality')}
            </p>
          ) : loading ? (
            <div role="status" aria-busy="true" aria-label={t('common.loading')} className="flex items-center justify-center py-8">
              <Spinner size="lg" />
            </div>
          ) : data && Object.keys(data.buckets).length === 0 ? (
            <p className="text-sm text-muted py-8 text-center">
              {t('verein_federation.calendar.empty')}
            </p>
          ) : (
            <div role="grid" aria-label={monthLabel} className="grid grid-cols-7 gap-1">
              {weekdayLabels.map((label, index) => (
                <div key={`${label}-${index}`} role="columnheader" className="p-2 text-center text-xs font-semibold text-muted">
                  {label}
                </div>
              ))}
              {Array.from({ length: leadingBlankDays }, (_, index) => (
                <div key={`blank-${index}`} role="gridcell" aria-hidden="true" />
              ))}
              {Array.from({ length: daysInMonth }, (_, i) => renderDayCell(i + 1))}
            </div>
          )}
        </Card.Content>
      </Card>
    </div>
  );
}
