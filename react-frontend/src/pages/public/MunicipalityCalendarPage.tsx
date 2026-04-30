// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * MunicipalityCalendarPage — AG55
 *
 * Public joint events calendar for all consenting Vereine in the same
 * municipality. Auto-detects municipality from `?code=` URL param.
 * Route: /municipality-calendar
 */

import { useCallback, useEffect, useMemo, useState } from 'react';
import { useSearchParams, Link } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import {
  Button,
  Card,
  CardBody,
  CardHeader,
  Chip,
  Divider,
  Input,
  Spinner,
} from '@heroui/react';
import Calendar from 'lucide-react/icons/calendar';
import ChevronLeft from 'lucide-react/icons/chevron-left';
import ChevronRight from 'lucide-react/icons/chevron-right';
import { PageMeta } from '@/components/seo';
import { useTenant, useToast } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';

interface CalendarEventDto {
  id: number;
  title: string;
  start_time: string | null;
  location: string | null;
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

export default function MunicipalityCalendarPage() {
  const { t } = useTranslation('common');
  const { tenantPath } = useTenant();
  const toast = useToast();
  const [params, setParams] = useSearchParams();
  usePageTitle(t('verein_federation.calendar.title'));

  const code = params.get('code') ?? '';
  const [inputCode, setInputCode] = useState(code);
  const [data, setData] = useState<CalendarResponse | null>(null);
  const [loading, setLoading] = useState(false);
  const [monthOffset, setMonthOffset] = useState(0);

  const load = useCallback(async (mc: string) => {
    if (!mc) {
      setData(null);
      return;
    }
    setLoading(true);
    try {
      const res = await api.get<CalendarResponse>(
        `/v2/municipality/${encodeURIComponent(mc)}/events-calendar?period=month`,
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
    void load(code);
  }, [code, load]);

  const handleSubmitCode = useCallback(() => {
    const next = inputCode.trim();
    if (!next) return;
    setMonthOffset(0);
    setParams((p) => {
      p.set('code', next);
      return p;
    });
  }, [inputCode, setParams]);

  // Compute days for the current month view (with offset)
  const monthStart = useMemo(() => {
    const d = new Date();
    d.setDate(1);
    d.setHours(0, 0, 0, 0);
    d.setMonth(d.getMonth() + monthOffset);
    return d;
  }, [monthOffset]);

  const monthLabel = useMemo(() => {
    return monthStart.toLocaleString(undefined, { month: 'long', year: 'numeric' });
  }, [monthStart]);

  const daysInMonth = useMemo(() => {
    const next = new Date(monthStart);
    next.setMonth(next.getMonth() + 1);
    const last = new Date(next.getTime() - 86400000);
    return last.getDate();
  }, [monthStart]);

  const renderDayCell = (day: number) => {
    const date = new Date(monthStart);
    date.setDate(day);
    const key = date.toISOString().slice(0, 10);
    const events = data?.buckets?.[key] ?? [];
    return (
      <div key={day} className="border border-default-200 rounded-md p-2 min-h-[80px] text-xs">
        <div className="font-semibold text-default-600">{day}</div>
        <div className="space-y-1 mt-1">
          {events.map((ev) => (
            <Link
              key={ev.id}
              to={tenantPath(`/events/${ev.id}`)}
              className="block bg-primary-50 hover:bg-primary-100 dark:bg-primary-900/30 rounded px-1 py-0.5 truncate"
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
      <PageMeta title={t('verein_federation.calendar.title')} />

      <div>
        <h1 className="text-2xl font-bold flex items-center gap-2">
          <Calendar className="w-6 h-6 text-primary" />
          {t('verein_federation.calendar.title')}
        </h1>
        {code ? (
          <p className="text-sm text-default-500 mt-1">
            {t('verein_federation.calendar.subtitle', { municipality: code })}
          </p>
        ) : null}
      </div>

      <Card>
        <CardHeader className="flex flex-wrap items-end gap-3">
          <Input
            label={t('verein_federation.municipality_code_label')}
            value={inputCode}
            onValueChange={setInputCode}
            className="max-w-xs"
            placeholder="8001"
          />
          <Button color="primary" onPress={handleSubmitCode}>
            {t('apply', 'Apply')}
          </Button>

          <div className="ml-auto flex items-center gap-2">
            <Button
              isIconOnly
              variant="flat"
              size="sm"
              onPress={() => setMonthOffset((m) => m - 1)}
              aria-label={t('verein_federation.calendar.prev')}
            >
              <ChevronLeft className="w-4 h-4" />
            </Button>
            <Chip variant="flat">{monthLabel}</Chip>
            <Button
              isIconOnly
              variant="flat"
              size="sm"
              onPress={() => setMonthOffset((m) => m + 1)}
              aria-label={t('verein_federation.calendar.next')}
            >
              <ChevronRight className="w-4 h-4" />
            </Button>
            <Button size="sm" variant="flat" onPress={() => setMonthOffset(0)}>
              {t('verein_federation.calendar.today')}
            </Button>
          </div>
        </CardHeader>
        <Divider />
        <CardBody>
          {!code ? (
            <p className="text-sm text-default-500 py-8 text-center">
              {t('verein_federation.calendar.no_municipality')}
            </p>
          ) : loading ? (
            <div className="flex items-center justify-center py-8">
              <Spinner size="lg" />
            </div>
          ) : data && Object.keys(data.buckets).length === 0 ? (
            <p className="text-sm text-default-500 py-8 text-center">
              {t('verein_federation.calendar.empty')}
            </p>
          ) : (
            <div className="grid grid-cols-7 gap-1">
              {Array.from({ length: daysInMonth }, (_, i) => renderDayCell(i + 1))}
            </div>
          )}
        </CardBody>
      </Card>
    </div>
  );
}
