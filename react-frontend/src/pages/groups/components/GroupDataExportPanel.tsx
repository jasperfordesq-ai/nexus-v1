// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useEffect, useState } from 'react';

import Download from 'lucide-react/icons/download';
import FileArchive from 'lucide-react/icons/file-archive';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/Button';
import { Chip } from '@/components/ui/Chip';
import { GlassCard } from '@/components/ui/GlassCard';
import { useToast } from '@/contexts';
import { formatDateValue } from '@/lib/helpers';
import {
  downloadGroupDataExport,
  getGroupDataExport,
  requestGroupDataExport,
  type GroupDataExportRecord,
} from '../api/dataExport';

interface GroupDataExportPanelProps {
  groupId: number;
  isAdmin: boolean;
}

export function GroupDataExportPanel({ groupId, isAdmin }: GroupDataExportPanelProps) {
  const { t } = useTranslation('groups');
  const toast = useToast();
  const [record, setRecord] = useState<GroupDataExportRecord | null>(null);
  const [requesting, setRequesting] = useState(false);
  const [downloading, setDownloading] = useState(false);
  const [refreshFailed, setRefreshFailed] = useState(false);

  useEffect(() => {
    if (!record || !['queued', 'processing'].includes(record.status)) return undefined;

    const controller = new AbortController();
    const timer = window.setTimeout(async () => {
      try {
        const next = await getGroupDataExport(groupId, record.id, { signal: controller.signal });
        if (!controller.signal.aborted) {
          setRecord(next);
          setRefreshFailed(false);
        }
      } catch {
        if (!controller.signal.aborted) setRefreshFailed(true);
      }
    }, 2500);

    return () => {
      controller.abort();
      window.clearTimeout(timer);
    };
  }, [groupId, record]);

  if (!isAdmin) return null;

  const generate = async () => {
    setRequesting(true);
    setRefreshFailed(false);
    try {
      setRecord(await requestGroupDataExport(groupId));
      toast.success(t('data_export.requested'));
    } catch {
      toast.error(t('data_export.request_failed'));
    } finally {
      setRequesting(false);
    }
  };

  const refresh = async () => {
    if (!record) return;
    setRequesting(true);
    try {
      setRecord(await getGroupDataExport(groupId, record.id));
      setRefreshFailed(false);
    } catch {
      setRefreshFailed(true);
      toast.error(t('data_export.refresh_failed'));
    } finally {
      setRequesting(false);
    }
  };

  const download = async () => {
    if (!record || record.status !== 'completed') return;
    setDownloading(true);
    try {
      await downloadGroupDataExport(groupId, record.id);
    } catch {
      toast.error(t('data_export.download_failed'));
    } finally {
      setDownloading(false);
    }
  };

  const statusColor = record?.status === 'completed'
    ? 'success'
    : record?.status === 'failed' || record?.status === 'expired'
      ? 'danger'
      : 'warning';

  return (
    <GlassCard className="space-y-4 p-5">
      <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div className="flex min-w-0 items-start gap-2">
          <FileArchive className="mt-0.5 h-5 w-5 flex-shrink-0 text-accent" aria-hidden="true" />
          <div>
            <h3 className="font-semibold text-theme-primary">{t('data_export.title')}</h3>
            <p className="mt-1 text-sm text-theme-muted">{t('data_export.description')}</p>
          </div>
        </div>
        <Button
          className="w-full flex-shrink-0 sm:w-auto"
          variant="flat"
          isLoading={requesting && record === null}
          onPress={() => void generate()}
        >
          {t(record ? 'data_export.generate_again' : 'data_export.generate')}
        </Button>
      </div>

      {record && (
        <div className="rounded-lg border border-theme-default bg-theme-elevated p-4">
          <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div className="min-w-0 space-y-1">
              <Chip size="sm" variant="flat" color={statusColor}>
                {t(`data_export.status_${record.status}`)}
              </Chip>
              {record.expires_at && (
                <p className="text-xs text-theme-subtle">
                  {t('data_export.expires', { date: formatDateValue(record.expires_at) })}
                </p>
              )}
              {refreshFailed && (
                <p role="alert" className="text-xs text-danger">{t('data_export.refresh_failed')}</p>
              )}
            </div>
            <div className="flex flex-col gap-2 sm:flex-row">
              {['queued', 'processing'].includes(record.status) && (
                <Button
                  variant="tertiary"
                  startContent={<RefreshCw className="h-4 w-4" aria-hidden="true" />}
                  isLoading={requesting}
                  onPress={() => void refresh()}
                >
                  {t('data_export.refresh')}
                </Button>
              )}
              {record.status === 'completed' && (
                <Button
                  color="primary"
                  startContent={<Download className="h-4 w-4" aria-hidden="true" />}
                  isLoading={downloading}
                  onPress={() => void download()}
                >
                  {t('data_export.download')}
                </Button>
              )}
            </div>
          </div>
        </div>
      )}
    </GlassCard>
  );
}

export default GroupDataExportPanel;
