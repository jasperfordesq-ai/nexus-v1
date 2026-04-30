// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useCallback, useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import {
  Button,
  Card,
  CardBody,
  CardHeader,
  Chip,
  Divider,
  RadioGroup,
  Radio,
  Spinner,
  Tooltip,
} from '@heroui/react';
import Newspaper from 'lucide-react/icons/newspaper';
import Save from 'lucide-react/icons/save';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import ExternalLink from 'lucide-react/icons/external-link';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { PageHeader } from '../../components';

type Cadence = 'off' | 'daily' | 'weekly';

interface CadenceResponse {
  cadence: Cadence;
}

const OPTIONS: { value: Cadence; label: string; description: string }[] = [
  {
    value: 'off',
    label: 'Off',
    description: 'No tenant default. Members can still opt in via their own preference.',
  },
  {
    value: 'daily',
    label: 'Daily',
    description: 'Members default to a daily civic digest of the freshest items.',
  },
  {
    value: 'weekly',
    label: 'Weekly',
    description: 'Members default to a weekly digest summarising the past 7 days.',
  },
];

export default function CivicDigestAdminPage() {
  usePageTitle('Civic Digest Cadence');
  const { showToast } = useToast();

  const [cadence, setCadence] = useState<Cadence>('off');
  const [draft, setDraft] = useState<Cadence>('off');
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const res = await api.get<CadenceResponse>('/v2/admin/caring-community/digest/cadence');
      const next = (res.data?.cadence as Cadence) ?? 'off';
      setCadence(next);
      setDraft(next);
    } catch {
      showToast('Failed to load digest cadence', 'error');
    } finally {
      setLoading(false);
    }
  }, [showToast]);

  useEffect(() => {
    load();
  }, [load]);

  const save = async () => {
    if (draft === cadence) return;
    setSaving(true);
    try {
      const res = await api.put<CadenceResponse>('/v2/admin/caring-community/digest/cadence', {
        cadence: draft,
      });
      const next = (res.data?.cadence as Cadence) ?? draft;
      setCadence(next);
      setDraft(next);
      showToast('Digest cadence saved', 'success');
    } catch (err) {
      const msg = (err as { message?: string })?.message ?? 'Failed to save cadence';
      showToast(msg, 'error');
    } finally {
      setSaving(false);
    }
  };

  const isDirty = draft !== cadence;

  return (
    <div className="space-y-6">
      <PageHeader
        title="Civic Digest Cadence"
        subtitle="AG97 — tenant default cadence for the AG90 personalised civic digest"
        icon={<Newspaper size={20} />}
        actions={
          <div className="flex items-center gap-2">
            <Button
              as={Link}
              to="/caring-community/civic-digest"
              size="sm"
              variant="flat"
              endContent={<ExternalLink size={14} />}
            >
              Preview member view
            </Button>
            <Tooltip content="Refresh">
              <Button
                isIconOnly
                size="sm"
                variant="flat"
                onPress={load}
                isLoading={loading}
                aria-label="Refresh"
              >
                <RefreshCw size={15} />
              </Button>
            </Tooltip>
          </div>
        }
      />

      {loading && (
        <div className="flex justify-center py-16">
          <Spinner size="lg" />
        </div>
      )}

      {!loading && (
        <>
          <Card className="border border-[var(--color-border)]">
            <CardHeader className="pb-2">
              <div className="flex flex-wrap items-center justify-between gap-3 w-full">
                <div>
                  <p className="text-sm font-semibold">Tenant default cadence</p>
                  <p className="text-xs text-default-500 mt-0.5">
                    Controls the default delivery cadence for new members. Existing members keep
                    whatever they have already chosen.
                  </p>
                </div>
                <Chip size="sm" variant="flat" color={cadence === 'off' ? 'default' : 'primary'}>
                  Current: {cadence}
                </Chip>
              </div>
            </CardHeader>
            <CardBody className="pt-0 space-y-4">
              <RadioGroup
                aria-label="Tenant default cadence"
                value={draft}
                onValueChange={(v) => setDraft(v as Cadence)}
              >
                {OPTIONS.map((opt) => (
                  <Radio key={opt.value} value={opt.value} description={opt.description}>
                    {opt.label}
                  </Radio>
                ))}
              </RadioGroup>

              <div className="flex items-center justify-end gap-2">
                <Button
                  variant="flat"
                  onPress={() => setDraft(cadence)}
                  isDisabled={!isDirty || saving}
                >
                  Reset
                </Button>
                <Button
                  color="primary"
                  startContent={<Save size={14} />}
                  onPress={save}
                  isLoading={saving}
                  isDisabled={!isDirty}
                >
                  Save cadence
                </Button>
              </div>
            </CardBody>
          </Card>

          <Card className="border border-[var(--color-border)]">
            <CardBody className="space-y-2">
              <p className="text-sm font-semibold">What the digest includes</p>
              <p className="text-xs text-default-500">
                Items are ranked per member by sub-region match, interest match, freshness, and
                source weight. Source mix:
              </p>
              <div className="flex flex-wrap gap-1.5 mt-1">
                {[
                  'Safety alerts',
                  'Project updates',
                  'Municipality announcements',
                  'Events',
                  'Vereine',
                  'Care providers',
                  'Marketplace',
                  'Help requests',
                  'Feed posts',
                ].map((tag) => (
                  <Chip key={tag} size="sm" variant="flat" color="default">
                    {tag}
                  </Chip>
                ))}
              </div>
            </CardBody>
          </Card>

          <Divider />
          <p className="text-xs text-default-500">
            Members can override cadence and opt out of individual sources from their own digest
            preferences page.
          </p>
        </>
      )}
    </div>
  );
}
