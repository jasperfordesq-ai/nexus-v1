// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Matching Diagnostic
 * Diagnose and debug matching engine issues for specific users or listings.
 * Wired to adminDiagnostics API.
 */

import { useState, useEffect } from 'react';
import { Card, CardBody, CardHeader, Input, Button, Spinner } from '@heroui/react';
import { Stethoscope, Search } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { adminDiagnostics } from '../../api/adminApi';
import { PageHeader } from '../../components';

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
  usePageTitle('Admin - Matching Diagnostic');
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
      .catch(() => toast.error('Failed to load engine status'))
      .finally(() => setLoadingEngine(false));
  }, []);

  const handleDiagnoseUser = async () => {
    if (!userId) return;
    setLoadingUser(true);
    setUserResult(null);
    try {
      const res = await adminDiagnostics.diagnoseUser(Number(userId));
      if (res.success && res.data) {
        setUserResult(res.data as DiagResult);
      } else {
        toast.error('No diagnostic data found for this user');
      }
    } catch {
      toast.error('Failed to diagnose user');
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
        toast.error('No diagnostic data found for this listing');
      }
    } catch {
      toast.error('Failed to diagnose listing');
    } finally {
      setLoadingListing(false);
    }
  };

  const overview = engineStatus?.overview;

  return (
    <div>
      <PageHeader title="Matching Diagnostic" description="Debug and analyze matching engine results for specific users or listings" />

      <div className="space-y-4">
        <Card shadow="sm">
          <CardHeader><h3 className="text-lg font-semibold flex items-center gap-2"><Stethoscope size={20} /> Diagnose User Matches</h3></CardHeader>
          <CardBody className="gap-4">
            <p className="text-sm text-default-500">Enter a user ID to see their match results, scores, and the factors contributing to each match.</p>
            <div className="flex gap-3">
              <Input label="User ID" placeholder="e.g., 42" type="number" value={userId} onValueChange={setUserId} variant="bordered" className="max-w-xs" />
              <Button
                color="primary"
                startContent={loadingUser ? undefined : <Search size={16} />}
                className="self-end"
                isDisabled={!userId}
                isLoading={loadingUser}
                onPress={handleDiagnoseUser}
              >
                Diagnose
              </Button>
            </div>
            {userResult && (
              <Card className="mt-2 bg-default-50">
                <CardBody>
                  <pre className="text-xs overflow-auto max-h-64 whitespace-pre-wrap">
                    {JSON.stringify(userResult, null, 2)}
                  </pre>
                </CardBody>
              </Card>
            )}
            {!userId && !userResult && (
              <p className="text-xs text-default-400">Enter a user ID and click Diagnose to see their matching analysis.</p>
            )}
          </CardBody>
        </Card>

        <Card shadow="sm">
          <CardHeader><h3 className="text-lg font-semibold">Diagnose Listing Matches</h3></CardHeader>
          <CardBody className="gap-4">
            <p className="text-sm text-default-500">Enter a listing ID to see which users were matched and why.</p>
            <div className="flex gap-3">
              <Input label="Listing ID" placeholder="e.g., 105" type="number" value={listingId} onValueChange={setListingId} variant="bordered" className="max-w-xs" />
              <Button
                color="primary"
                startContent={loadingListing ? undefined : <Search size={16} />}
                className="self-end"
                isDisabled={!listingId}
                isLoading={loadingListing}
                onPress={handleDiagnoseListing}
              >
                Diagnose
              </Button>
            </div>
            {listingResult && (
              <Card className="mt-2 bg-default-50">
                <CardBody>
                  <pre className="text-xs overflow-auto max-h-64 whitespace-pre-wrap">
                    {JSON.stringify(listingResult, null, 2)}
                  </pre>
                </CardBody>
              </Card>
            )}
          </CardBody>
        </Card>

        <Card shadow="sm">
          <CardHeader><h3 className="text-lg font-semibold">Engine Status</h3></CardHeader>
          <CardBody>
            {loadingEngine ? (
              <div className="flex justify-center py-4"><Spinner size="sm" /></div>
            ) : (
              <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div className="rounded-lg border border-default-200 p-3 text-center">
                  <p className="text-sm text-default-500">Matches Today</p>
                  <p className="font-medium">{overview?.total_matches_today ?? '--'}</p>
                </div>
                <div className="rounded-lg border border-default-200 p-3 text-center">
                  <p className="text-sm text-default-500">Cache Entries</p>
                  <p className="font-medium">{overview?.cache_entries ?? '--'}</p>
                </div>
                <div className="rounded-lg border border-default-200 p-3 text-center">
                  <p className="text-sm text-default-500">Avg Match Score</p>
                  <p className="font-medium">
                    {overview?.avg_match_score !== undefined
                      ? `${Number(overview.avg_match_score).toFixed(1)}%`
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
