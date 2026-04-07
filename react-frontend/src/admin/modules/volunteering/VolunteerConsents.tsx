// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Volunteer Guardian Consents
 * Admin read-only view of guardian consent records for minor volunteers.
 */

import { useState, useCallback, useEffect, useMemo } from 'react';
import { Button, Chip, Card, CardBody } from '@heroui/react';
import { ShieldCheck, RefreshCw, AlertTriangle, Mail } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { adminVolunteering } from '../../api/adminApi';
import { DataTable, PageHeader, EmptyState, type Column } from '../../components';
import { useTranslation } from 'react-i18next';

interface GuardianConsent {
  id: number;
  minor_name: string;
  guardian_name: string;
  guardian_email: string;
  relationship: string;
  opportunity_title: string;
  status: 'pending' | 'active' | 'expired' | 'withdrawn';
  consent_date: string;
  expires_date: string | null;
}

const statusColorMap: Record<string, 'success' | 'warning' | 'danger' | 'default'> = {
  active: 'success',
  pending: 'warning',
  expired: 'danger',
  withdrawn: 'default',
};

export default function VolunteerConsents() {
  const { t } = useTranslation('admin');
  usePageTitle(t('volunteering.consents_title', 'Guardian Consents'));
  const toast = useToast();

  const [consents, setConsents] = useState<GuardianConsent[]>([]);
  const [loading, setLoading] = useState(true);

  const loadData = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminVolunteering.getGuardianConsents();
      if (res.success && res.data) {
        const payload = res.data as unknown;
        if (Array.isArray(payload)) {
          setConsents(payload);
        } else if (payload && typeof payload === 'object' && 'data' in payload) {
          setConsents((payload as { data: GuardianConsent[] }).data || []);
        }
      }
    } catch {
      toast.error(t('volunteering.failed_to_load_consents', 'Failed to load guardian consents'));
      setConsents([]);
    }
    setLoading(false);
  }, [toast, t]);

  useEffect(() => { loadData(); }, [loadData]);

  // Find consents expiring within 30 days
  const now = new Date();
  const thirtyDaysFromNow = new Date(now.getTime() + 30 * 24 * 60 * 60 * 1000);
  const expiringConsents = useMemo(
    () =>
      consents.filter((c) => {
        if (!c.expires_date || c.status === 'expired' || c.status === 'withdrawn') return false;
        const expiresAt = new Date(c.expires_date);
        return expiresAt > now && expiresAt <= thirtyDaysFromNow;
      }),
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [consents],
  );

  const columns: Column<GuardianConsent>[] = [
    {
      key: 'minor_name',
      label: t('volunteering.col_minor_name', 'Minor Name'),
      sortable: true,
    },
    {
      key: 'guardian_name',
      label: t('volunteering.col_guardian_name', 'Guardian Name'),
      sortable: true,
    },
    {
      key: 'guardian_email',
      label: t('volunteering.col_guardian_email', 'Guardian Email'),
      sortable: true,
    },
    {
      key: 'relationship',
      label: t('volunteering.col_relationship', 'Relationship'),
    },
    {
      key: 'opportunity_title',
      label: t('volunteering.col_opportunity', 'Opportunity'),
      sortable: true,
    },
    {
      key: 'status',
      label: t('volunteering.col_status', 'Status'),
      sortable: true,
      render: (row) => (
        <Chip size="sm" color={statusColorMap[row.status] || 'default'} variant="flat">
          {t(`volunteering.status_${row.status}`, row.status)}
        </Chip>
      ),
    },
    {
      key: 'consent_date',
      label: t('volunteering.col_consent_date', 'Consent Date'),
      sortable: true,
      render: (row) => (
        <span>{row.consent_date ? new Date(row.consent_date).toLocaleDateString() : '-'}</span>
      ),
    },
    {
      key: 'expires_date',
      label: t('volunteering.col_expires_date', 'Expires'),
      sortable: true,
      render: (row) => {
        if (!row.expires_date) return <span>-</span>;
        const expiresAt = new Date(row.expires_date);
        const isExpiringSoon = expiresAt > now && expiresAt <= thirtyDaysFromNow;
        return (
          <span className={isExpiringSoon ? 'text-warning font-medium' : ''}>
            {expiresAt.toLocaleDateString()}
          </span>
        );
      },
    },
    {
      key: 'actions' as keyof GuardianConsent,
      label: t('common.actions', 'Actions'),
      render: (row) => {
        if (row.status !== 'expired') return null;
        const subject = encodeURIComponent(
          t('volunteering.consent_renewal_subject', 'Guardian Consent Renewal Required'),
        );
        const body = encodeURIComponent(
          t(
            'volunteering.consent_renewal_body',
            `Dear ${row.guardian_name},\n\nThe guardian consent for ${row.minor_name}'s participation in "${row.opportunity_title}" has expired. Please renew the consent at your earliest convenience.\n\nThank you.`,
          ),
        );
        return (
          <Button
            as="a"
            href={`mailto:${row.guardian_email}?subject=${subject}&body=${body}`}
            size="sm"
            variant="flat"
            color="warning"
            startContent={<Mail size={14} />}
          >
            {t('volunteering.re_request', 'Re-request')}
          </Button>
        );
      },
    },
  ];

  return (
    <div>
      <PageHeader
        title={t('volunteering.consents_title', 'Guardian Consents')}
        description={t('volunteering.consents_desc', 'Monitor guardian consent records for minor volunteers. This is a read-only view.')}
        actions={
          <Button variant="flat" startContent={<RefreshCw size={16} />} onPress={loadData} isLoading={loading}>
            {t('common.refresh', 'Refresh')}
          </Button>
        }
      />

      {/* Expiry Warning Banner */}
      {expiringConsents.length > 0 && (
        <Card className="mb-6 border-warning/50 bg-warning-50/30">
          <CardBody className="p-4">
            <div className="flex items-start gap-3">
              <AlertTriangle size={20} className="text-warning mt-0.5 shrink-0" />
              <div>
                <p className="font-semibold text-warning-700">
                  {t('volunteering.expiring_consents_warning', '{{count}} consent(s) expiring within 30 days', {
                    count: expiringConsents.length,
                  })}
                </p>
                <ul className="mt-2 space-y-1">
                  {expiringConsents.map((c) => (
                    <li key={c.id} className="text-sm text-default-600">
                      <span className="font-medium">{c.minor_name}</span>
                      {' '}&mdash;{' '}
                      {c.opportunity_title}
                      {' '}&mdash;{' '}
                      {t('volunteering.expires_on', 'expires')} {new Date(c.expires_date!).toLocaleDateString()}
                      {' '}
                      <a
                        href={`mailto:${c.guardian_email}?subject=${encodeURIComponent('Guardian Consent Renewal')}`}
                        className="text-warning-600 underline hover:text-warning-700"
                      >
                        ({t('volunteering.contact_guardian', 'contact guardian')})
                      </a>
                    </li>
                  ))}
                </ul>
              </div>
            </div>
          </CardBody>
        </Card>
      )}

      {consents.length === 0 && !loading ? (
        <EmptyState
          icon={ShieldCheck}
          title={t('volunteering.no_consents', 'No guardian consents')}
          description={t('volunteering.no_consents_desc', 'No guardian consent records have been created yet.')}
        />
      ) : (
        <DataTable columns={columns} data={consents} isLoading={loading} />
      )}
    </div>
  );
}
