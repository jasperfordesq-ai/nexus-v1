// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * SEO Audit
 * Run and display SEO audit results for the platform.
 * Fetches real audit data from the API and supports triggering new audits.
 */

import { useState, useEffect, useCallback } from 'react';
import { Card, CardBody, CardHeader, Button, Chip, Spinner } from '@heroui/react';
import ClipboardCheck from 'lucide-react/icons/clipboard-check';
import Play from 'lucide-react/icons/play';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import { useTranslation } from 'react-i18next';
import { useToast } from '@/contexts';
import { useAdminPageMeta } from '../../AdminMetaContext';
import { PageHeader } from '../../components';
import { adminTools } from '../../api/adminApi';

interface AuditCheck {
  name: string;
  description: string;
  status: 'pass' | 'warning' | 'fail';
  details?: string;
}

const statusColorMap: Record<string, 'success' | 'warning' | 'danger' | 'default'> = {
  pass: 'success',
  warning: 'warning',
  fail: 'danger',
};

export function SeoAudit() {
  const { t } = useTranslation('admin');
  const { t: tNav } = useTranslation('admin_nav');
  useAdminPageMeta({ title: tNav('advanced') });
  const toast = useToast();

  const [checks, setChecks] = useState<AuditCheck[]>([]);
  const [lastRunAt, setLastRunAt] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [running, setRunning] = useState(false);

  /** Load the most recent audit results from the API */
  const loadAudit = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminTools.getSeoAudit();
      if (res.success && res.data) {
        const payload = res.data as unknown;
        if (payload && typeof payload === 'object') {
          const d = payload as { checks?: AuditCheck[]; last_run_at?: string | null };
          setChecks(d.checks ?? []);
          setLastRunAt(d.last_run_at ?? null);
        }
      }
    } catch {
      // No previous audit results available - that is fine
      setChecks([]);
      setLastRunAt(null);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    loadAudit();
  }, [loadAudit]);

  /** Trigger a new SEO audit via the API */
  const handleRunAudit = useCallback(async () => {
    setRunning(true);
    try {
      const res = await adminTools.runSeoAudit();
      if (res.success && res.data) {
        const payload = res.data as unknown;
        let newChecks: AuditCheck[] = [];
        if (Array.isArray(payload)) {
          newChecks = payload;
        } else if (payload && typeof payload === 'object') {
          const d = payload as { checks?: AuditCheck[]; data?: AuditCheck[] };
          newChecks = d.checks ?? d.data ?? [];
        }
        setChecks(newChecks);
        setLastRunAt(new Date().toISOString());

        const passCount = newChecks.filter(c => c.status === 'pass').length;
        const warnCount = newChecks.filter(c => c.status === 'warning').length;
        const failCount = newChecks.filter(c => c.status === 'fail').length;

        toast.success(
          t('seo_audit_complete'),
          t('seo_audit_summary_counts', { passCount, warnCount, failCount })
        );
      } else {
        toast.error(t('seo_audit_failed'), t('seo_audit_no_results'));
      }
    } catch {
      toast.error(t('seo_audit_failed'), t('seo_audit_error'));
    } finally {
      setRunning(false);
    }
  }, [t, toast])


  const passCount = checks.filter(c => c.status === 'pass').length;
  const warnCount = checks.filter(c => c.status === 'warning').length;
  const failCount = checks.filter(c => c.status === 'fail').length;
  const hasResults = checks.length > 0;

  if (loading) {
    return (
      <div>
        <PageHeader
          title={t('seo_audit_title')}
          description={t('seo_audit_desc')}
        />
        <div className="flex h-64 items-center justify-center">
          <Spinner size="lg" />
        </div>
      </div>
    );
  }

  return (
    <div>
      <PageHeader
        title={t('seo_audit_title')}
        description={t('seo_audit_desc')}
        actions={
          <div className="flex items-center gap-2">
            {hasResults && (
              <Button
                variant="flat"
                startContent={<RefreshCw size={16} />}
                onPress={loadAudit}
                size="sm"
              >
                {t('reload_results')}
              </Button>
            )}
            <Button
              color="primary"
              startContent={!running ? <Play size={16} /> : undefined}
              onPress={handleRunAudit}
              isLoading={running}
            >
              {t('run_audit')}
            </Button>
          </div>
        }
      />

      {hasResults && (
        <div className="flex flex-wrap items-center gap-2 mb-4">
          {passCount > 0 && <Chip color="success" variant="flat">{t('passed_count_with_value', { count: passCount })}</Chip>}
          {warnCount > 0 && <Chip color="warning" variant="flat">{t('warnings_count_with_value', { count: warnCount })}</Chip>}
          {failCount > 0 && <Chip color="danger" variant="flat">{t('failed_count_with_value', { count: failCount })}</Chip>}
          {lastRunAt && (
            <span className="text-xs text-default-400 ml-2">
              {t('last_run')}
            </span>
          )}
        </div>
      )}

      <Card shadow="sm">
        <CardHeader>
          <h3 className="text-lg font-semibold flex items-center gap-2">
            <ClipboardCheck size={20} /> {t('audit_results')}
          </h3>
        </CardHeader>
        <CardBody>
          {!hasResults ? (
            <div className="flex flex-col items-center py-8 text-default-400">
              <ClipboardCheck size={40} className="mb-2" />
              <p className="font-medium">{t('no_audit_results')}</p>
              <p className="text-sm mt-1">{t('no_audit_results_desc')}</p>
            </div>
          ) : (
            <div className="space-y-3">
              {checks.map((check) => (
                <div key={check.name} className="flex items-center justify-between rounded-lg border border-default-200 p-3">
                  <div className="flex-1 min-w-0">
                    <p className="font-medium">{check.name}</p>
                    <p className="text-xs text-default-400">{check.description}</p>
                    {check.details && (
                      <p className="text-xs text-default-500 mt-1">{check.details}</p>
                    )}
                  </div>
                  <Chip
                    size="sm"
                    variant="flat"
                    color={statusColorMap[check.status] ?? 'default'}
                    className="capitalize shrink-0 ml-3"
                  >
                    {t(`status_${check.status}`)}
                  </Chip>
                </div>
              ))}
            </div>
          )}
        </CardBody>
      </Card>
    </div>
  );
}

export default SeoAudit;
