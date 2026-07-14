import { Card, CardBody, CardHeader, Input, Button, Spinner } from '@/components/ui';
import { useState, useEffect } from 'react';
import Stethoscope from 'lucide-react/icons/stethoscope';
import Search from 'lucide-react/icons/search';
import { useTranslation } from 'react-i18next';
import { useAdminPageMeta } from '../../AdminMetaContext';
import { useToast } from '@/contexts';
import { adminDiagnostics } from '../../api/adminApi';
import { PageHeader } from '../../components/PageHeader';
import { formatPercentValue } from '@/lib/helpers';
// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Matching Diagnostic
 * Diagnose and debug matching engine issues for specific users or listings.
 * Wired to adminDiagnostics API.
 */


interface DiagResult {
  [key: string]: unknown;
}

interface EngineStatus {
  overview?: {
    total_matches_today?: number;
    cache_entries?: number;
    avg_match_score?: number;
  };
  [key: string]: unknown;
}

export function MatchingDiagnostic() {
  const { t } = useTranslation('admin_diagnostics');
  useAdminPageMeta({ title: t('diagnostics.page_title') });
  const toast = useToast();

  const [userId, setUserId] = useState('');
  const [listingId, setListingId] = useState('');
  const [userResult, setUserResult] = useState<DiagResult | null>(null);
  const [listingResult, setListingResult] = useState<DiagResult | null>(null);
  const [engineStatus, setEngineStatus] = useState<EngineStatus | null>(null);
  const [loadingUser, setLoadingUser] = useState(false);
  const [loadingListing, setLoadingListing] = useState(false);
  const [loadingEngine, setLoadingEngine] = useState(true);

  useEffect(() => {
    adminDiagnostics.getMatchingStats()
      .then((res) => {
        if (res.success && res.data) {
          setEngineStatus(res.data as EngineStatus);
        }
      })
      .catch(() => toast.error(t('diagnostics.failed_to_load_engine_status')))
      .finally(() => setLoadingEngine(false));
  }, [t, toast])


  const handleDiagnoseUser = async () => {
    if (!userId) return;
    setLoadingUser(true);
    setUserResult(null);
    try {
      const res = await adminDiagnostics.diagnoseUser(Number(userId));
      if (res.success && res.data) {
        setUserResult(res.data as DiagResult);
      } else {
        toast.error(t('diagnostics.no_diagnostic_data_found_for_this_user'));
      }
    } catch {
      toast.error(t('diagnostics.failed_to_diagnose_user'));
    } finally {
      setLoadingUser(false);
    }
  };

  const handleDiagnoseListing = async () => {
    if (!listingId) return;
    setLoadingListing(true);
    setListingResult(null);
    try {
      const res = await adminDiagnostics.diagnoseListing(Number(listingId));
      if (res.success && res.data) {
        setListingResult(res.data as DiagResult);
      } else {
        toast.error(t('diagnostics.no_diagnostic_data_found_for_this_listin'));
      }
    } catch {
      toast.error(t('diagnostics.failed_to_diagnose_listing'));
    } finally {
      setLoadingListing(false);
    }
  };

  const overview = engineStatus?.overview;

  return (
    <div>
      <PageHeader title={t('diagnostics.matching_diagnostic_title')} description={t('diagnostics.matching_diagnostic_desc')} />

      <div className="space-y-4">
        <Card >
          <CardHeader><h3 className="text-lg font-semibold flex items-center gap-2"><Stethoscope size={20} aria-hidden="true" /> {t('diagnostics.diagnose_user_matches')}</h3></CardHeader>
          <CardBody className="gap-4">
            <p className="text-sm text-muted">{t('diagnostics.diagnose_user_matches_desc')}</p>
            <div className="flex gap-3">
              <Input label={t('diagnostics.label_user_i_d')} placeholder={t('diagnostics.user_id_placeholder')} type="number" value={userId} onValueChange={setUserId} variant="secondary" className="max-w-xs" />
              <Button
                startContent={loadingUser ? undefined : <Search size={16} />}
                className="self-end"
                isDisabled={!userId}
                isLoading={loadingUser}
                onPress={handleDiagnoseUser}
              >
                {t('diagnostics.diagnose')}
              </Button>
            </div>
            {userResult && (
              <Card className="mt-2 bg-surface-secondary">
                <CardBody>
                  <pre className="text-xs overflow-auto max-h-64 whitespace-pre-wrap">
                    {JSON.stringify(userResult, null, 2)}
                  </pre>
                </CardBody>
              </Card>
            )}
            {!userId && !userResult && (
              <p className="text-xs text-muted">{t('diagnostics.user_prompt')}</p>
            )}
          </CardBody>
        </Card>

        <Card >
          <CardHeader><h3 className="text-lg font-semibold">{t('diagnostics.diagnose_listing_matches')}</h3></CardHeader>
          <CardBody className="gap-4">
            <p className="text-sm text-muted">{t('diagnostics.diagnose_listing_matches_desc')}</p>
            <div className="flex gap-3">
              <Input label={t('diagnostics.label_listing_i_d')} placeholder={t('diagnostics.listing_id_placeholder')} type="number" value={listingId} onValueChange={setListingId} variant="secondary" className="max-w-xs" />
              <Button
                startContent={loadingListing ? undefined : <Search size={16} />}
                className="self-end"
                isDisabled={!listingId}
                isLoading={loadingListing}
                onPress={handleDiagnoseListing}
              >
                {t('diagnostics.diagnose')}
              </Button>
            </div>
            {listingResult && (
              <Card className="mt-2 bg-surface-secondary">
                <CardBody>
                  <pre className="text-xs overflow-auto max-h-64 whitespace-pre-wrap">
                    {JSON.stringify(listingResult, null, 2)}
                  </pre>
                </CardBody>
              </Card>
            )}
          </CardBody>
        </Card>

        <Card >
          <CardHeader><h3 className="text-lg font-semibold">{t('diagnostics.engine_status')}</h3></CardHeader>
          <CardBody>
            {loadingEngine ? (
              <div className="flex justify-center py-4" role="status" aria-busy="true" aria-label={t('common.loading')}><Spinner size="sm" /></div>
            ) : (
              <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div className="rounded-lg border border-border p-3 text-center">
                  <p className="text-sm text-muted">{t('diagnostics.matches_today')}</p>
                  <p className="font-medium">{overview?.total_matches_today ?? '--'}</p>
                </div>
                <div className="rounded-lg border border-border p-3 text-center">
                  <p className="text-sm text-muted">{t('diagnostics.cache_entries')}</p>
                  <p className="font-medium">{overview?.cache_entries ?? '--'}</p>
                </div>
                <div className="rounded-lg border border-border p-3 text-center">
                  <p className="text-sm text-muted">{t('diagnostics.avg_match_score')}</p>
                  <p className="font-medium">
                    {overview?.avg_match_score !== undefined
                      ? formatPercentValue(Number(overview.avg_match_score))
                      : '--'}
                  </p>
                </div>
              </div>
            )}
          </CardBody>
        </Card>
      </div>
    </div>
  );
}

export default MatchingDiagnostic;
