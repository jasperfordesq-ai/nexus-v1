// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * VereinFederationPanel — AG55
 *
 * Verein admin panel for federation consent, network browsing, event sharing,
 * and viewing incoming/outgoing shared events.
 *
 * Embed: <VereinFederationPanel organizationId={orgId} />
 */

import { useCallback, useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import {
  Button,
  Card,
  CardBody,
  CardHeader,
  Chip,
  Divider,
  Input,
  Modal,
  ModalBody,
  ModalContent,
  ModalFooter,
  ModalHeader,
  Select,
  SelectItem,
  Spinner,
  Switch,
  Tab,
  Table,
  TableBody,
  TableCell,
  TableColumn,
  TableHeader,
  TableRow,
  Tabs,
} from '@heroui/react';
import Network from 'lucide-react/icons/network';
import Share2 from 'lucide-react/icons/share-2';
import Calendar from 'lucide-react/icons/calendar';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';

interface ConsentDto {
  organization_id: number;
  sharing_scope: 'events' | 'members' | 'both' | 'none' | string;
  municipality_code: string | null;
  is_active: boolean;
}

interface NetworkVereinDto {
  organization_id: number;
  sharing_scope: string;
  municipality_code: string | null;
  name: string;
  slug?: string | null;
  logo_url?: string | null;
}

interface SharedEventDto {
  id: number;
  event_id: number;
  source_organization_id: number;
  target_organization_id: number;
  source_name: string | null;
  target_name: string | null;
  shared_at: string;
  title: string;
  start_time: string | null;
  location: string | null;
  image_url: string | null;
}

interface EventOptionDto {
  id: number;
  title: string;
  start_time: string | null;
}

interface Props {
  organizationId: number;
}

const SCOPES: ReadonlyArray<'none' | 'events' | 'members' | 'both'> = ['none', 'events', 'members', 'both'];

export default function VereinFederationPanel({ organizationId }: Props) {
  const { t } = useTranslation('common');
  const toast = useToast();

  const [consent, setConsent] = useState<ConsentDto | null>(null);
  const [network, setNetwork] = useState<NetworkVereinDto[]>([]);
  const [incoming, setIncoming] = useState<SharedEventDto[]>([]);
  const [outgoing, setOutgoing] = useState<SharedEventDto[]>([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);

  // Edit-buffer for consent form
  const [scope, setScope] = useState<'none' | 'events' | 'members' | 'both'>('none');
  const [municipality, setMunicipality] = useState('');

  // Share modal
  const [shareTarget, setShareTarget] = useState<NetworkVereinDto | null>(null);
  const [shareEvents, setShareEvents] = useState<EventOptionDto[]>([]);
  const [selectedEventId, setSelectedEventId] = useState('');
  const [shareSubmitting, setShareSubmitting] = useState(false);

  const loadAll = useCallback(async () => {
    setLoading(true);
    try {
      const [c, n, inRes, outRes] = await Promise.all([
        api.get<ConsentDto>(`/v2/vereine/${organizationId}/federation-consent`),
        api.get<NetworkVereinDto[]>(`/v2/vereine/${organizationId}/network`),
        api.get<SharedEventDto[]>(`/v2/vereine/${organizationId}/shared-events?direction=incoming`),
        api.get<SharedEventDto[]>(`/v2/vereine/${organizationId}/shared-events?direction=outgoing`),
      ]);
      if (c.success && c.data) {
        setConsent(c.data);
        setScope((c.data.sharing_scope as 'none' | 'events' | 'members' | 'both') ?? 'none');
        setMunicipality(c.data.municipality_code ?? '');
      }
      if (n.success && n.data) setNetwork(n.data);
      if (inRes.success && inRes.data) setIncoming(inRes.data);
      if (outRes.success && outRes.data) setOutgoing(outRes.data);
    } catch (err) {
      logError('VereinFederationPanel: load failed', err);
      toast.error(t('verein_federation.load_failed'));
    } finally {
      setLoading(false);
    }
  }, [organizationId, toast, t]);

  useEffect(() => {
    void loadAll();
  }, [loadAll]);

  const isActive = useMemo(() => scope !== 'none', [scope]);

  const handleSave = useCallback(async () => {
    setSaving(true);
    try {
      const res = await api.put<ConsentDto>(`/v2/vereine/${organizationId}/federation-consent`, {
        sharing_scope: scope,
        municipality_code: municipality.trim() || null,
      });
      if (res.success && res.data) {
        setConsent(res.data);
        toast.success(t('verein_federation.consent_saved'));
        await loadAll();
      } else {
        toast.error(res.error || t('verein_federation.consent_save_failed'));
      }
    } catch (err) {
      logError('VereinFederationPanel: save failed', err);
      toast.error(t('verein_federation.consent_save_failed'));
    } finally {
      setSaving(false);
    }
  }, [organizationId, scope, municipality, toast, t, loadAll]);

  const openShareModal = useCallback(async (target: NetworkVereinDto) => {
    setShareTarget(target);
    setSelectedEventId('');
    try {
      // Try to load events owned by current user (as Verein admin). Falls back gracefully.
      const res = await api.get<{ events?: EventOptionDto[] } | EventOptionDto[]>(
        `/v2/events?mine=1&limit=50`,
      );
      const list: EventOptionDto[] = Array.isArray(res.data)
        ? (res.data as EventOptionDto[])
        : ((res.data as { events?: EventOptionDto[] } | undefined)?.events ?? []);
      setShareEvents(list);
    } catch {
      setShareEvents([]);
    }
  }, []);

  const submitShare = useCallback(async () => {
    if (!shareTarget || !selectedEventId) return;
    setShareSubmitting(true);
    try {
      const res = await api.post<{ shared: number; skipped: number }>(
        `/v2/vereine/${organizationId}/share-event`,
        {
          event_id: Number(selectedEventId),
          target_organization_ids: [shareTarget.organization_id],
        },
      );
      if (res.success && res.data) {
        toast.success(
          t('verein_federation.share_event_done', {
            shared: res.data.shared,
            skipped: res.data.skipped,
          }),
        );
        setShareTarget(null);
        await loadAll();
      } else {
        toast.error(res.error || t('verein_federation.share_event_failed'));
      }
    } catch (err) {
      logError('VereinFederationPanel: share failed', err);
      toast.error(t('verein_federation.share_event_failed'));
    } finally {
      setShareSubmitting(false);
    }
  }, [shareTarget, selectedEventId, organizationId, toast, t, loadAll]);

  const handleWithdraw = useCallback(async (shareId: number) => {
    if (!window.confirm(t('verein_federation.withdraw_share_confirm'))) return;
    try {
      const res = await api.delete(`/v2/vereine/${organizationId}/event-shares/${shareId}`);
      if (res.success) {
        toast.success(t('verein_federation.withdraw_share_done'));
        await loadAll();
      } else {
        toast.error(res.error || t('verein_federation.share_event_failed'));
      }
    } catch (err) {
      logError('VereinFederationPanel: withdraw failed', err);
      toast.error(t('verein_federation.share_event_failed'));
    }
  }, [organizationId, toast, t, loadAll]);

  if (loading) {
    return (
      <div className="flex items-center justify-center py-10">
        <Spinner size="lg" />
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div>
        <h2 className="text-xl font-bold flex items-center gap-2">
          <Network className="w-5 h-5 text-primary" />
          {t('verein_federation.panel_title')}
        </h2>
        <p className="text-sm text-default-500 mt-1">{t('verein_federation.panel_subtitle')}</p>
      </div>

      {/* Consent */}
      <Card>
        <CardHeader>
          <h3 className="text-base font-semibold">{t('verein_federation.consent_title')}</h3>
        </CardHeader>
        <Divider />
        <CardBody className="space-y-4">
          <div className="flex items-center justify-between gap-4">
            <span className="text-sm">{t('verein_federation.consent_toggle_label')}</span>
            <Switch
              isSelected={isActive}
              onValueChange={(on) => setScope(on ? 'both' : 'none')}
              aria-label={t('verein_federation.consent_toggle_label')}
            />
          </div>

          <Select
            label={t('verein_federation.consent_scope_label')}
            selectedKeys={[scope]}
            onChange={(e) => setScope((e.target.value || 'none') as 'none' | 'events' | 'members' | 'both')}
            isDisabled={!isActive}
          >
            {SCOPES.map((s) => (
              <SelectItem key={s} textValue={t(`verein_federation.scope_${s}`)}>
                {t(`verein_federation.scope_${s}`)}
              </SelectItem>
            ))}
          </Select>

          <Input
            label={t('verein_federation.municipality_code_label')}
            description={t('verein_federation.municipality_code_help')}
            value={municipality}
            onValueChange={setMunicipality}
            placeholder="8001"
            isDisabled={!isActive}
          />

          <div className="flex justify-end">
            <Button color="primary" onPress={() => void handleSave()} isLoading={saving}>
              {t('verein_federation.save_consent')}
            </Button>
          </div>
        </CardBody>
      </Card>

      {/* Network */}
      <Card>
        <CardHeader>
          <h3 className="text-base font-semibold">{t('verein_federation.network_title')}</h3>
        </CardHeader>
        <Divider />
        <CardBody>
          {network.length === 0 ? (
            <p className="text-sm text-default-500 py-4 text-center">
              {t('verein_federation.network_empty')}
            </p>
          ) : (
            <Table aria-label={t('verein_federation.network_title')}>
              <TableHeader>
                <TableColumn>{t('verein_federation.network_column_name')}</TableColumn>
                <TableColumn>{t('verein_federation.network_column_scope')}</TableColumn>
                <TableColumn>{t('verein_federation.network_column_municipality')}</TableColumn>
                <TableColumn>{' '}</TableColumn>
              </TableHeader>
              <TableBody>
                {network.map((row) => (
                  <TableRow key={row.organization_id}>
                    <TableCell>{row.name}</TableCell>
                    <TableCell>
                      <Chip size="sm" variant="flat">{t(`verein_federation.scope_${row.sharing_scope}`)}</Chip>
                    </TableCell>
                    <TableCell>{row.municipality_code ?? '—'}</TableCell>
                    <TableCell>
                      {(consent?.sharing_scope === 'events' || consent?.sharing_scope === 'both') ? (
                        <Button
                          size="sm"
                          variant="flat"
                          color="primary"
                          startContent={<Share2 className="w-4 h-4" />}
                          onPress={() => void openShareModal(row)}
                        >
                          {t('verein_federation.network_action_share_event')}
                        </Button>
                      ) : null}
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          )}
        </CardBody>
      </Card>

      {/* Shared events */}
      <Card>
        <CardHeader>
          <h3 className="text-base font-semibold flex items-center gap-2">
            <Calendar className="w-4 h-4" />
            {t('verein_federation.shared_events_title')}
          </h3>
        </CardHeader>
        <Divider />
        <CardBody>
          <Tabs aria-label={t('verein_federation.shared_events_title')}>
            <Tab key="incoming" title={t('verein_federation.tab_incoming')}>
              {incoming.length === 0 ? (
                <p className="text-sm text-default-500 py-6 text-center">
                  {t('verein_federation.shared_events_empty_in')}
                </p>
              ) : (
                <ul className="divide-y divide-default-200">
                  {incoming.map((s) => (
                    <li key={s.id} className="py-3 flex items-start gap-3">
                      <div className="flex-1">
                        <p className="font-medium">{s.title}</p>
                        <p className="text-xs text-default-500">
                          {t('verein_federation.calendar.from_label')}: {s.source_name ?? '—'}
                          {s.start_time ? ` · ${new Date(s.start_time).toLocaleString()}` : ''}
                        </p>
                      </div>
                    </li>
                  ))}
                </ul>
              )}
            </Tab>
            <Tab key="outgoing" title={t('verein_federation.tab_outgoing')}>
              {outgoing.length === 0 ? (
                <p className="text-sm text-default-500 py-6 text-center">
                  {t('verein_federation.shared_events_empty_out')}
                </p>
              ) : (
                <ul className="divide-y divide-default-200">
                  {outgoing.map((s) => (
                    <li key={s.id} className="py-3 flex items-start gap-3">
                      <div className="flex-1">
                        <p className="font-medium">{s.title}</p>
                        <p className="text-xs text-default-500">
                          → {s.target_name ?? '—'}
                          {s.start_time ? ` · ${new Date(s.start_time).toLocaleString()}` : ''}
                        </p>
                      </div>
                      <Button size="sm" variant="flat" color="danger" onPress={() => void handleWithdraw(s.id)}>
                        {t('verein_federation.withdraw_share')}
                      </Button>
                    </li>
                  ))}
                </ul>
              )}
            </Tab>
          </Tabs>
        </CardBody>
      </Card>

      {/* Share-event modal */}
      <Modal isOpen={!!shareTarget} onClose={() => setShareTarget(null)} size="md">
        <ModalContent>
          {(close) => (
            <>
              <ModalHeader>
                {t('verein_federation.share_event_modal_title', { target: shareTarget?.name ?? '' })}
              </ModalHeader>
              <ModalBody>
                <Select
                  label={t('verein_federation.share_event_pick_label')}
                  selectedKeys={selectedEventId ? [selectedEventId] : []}
                  onChange={(e) => setSelectedEventId(e.target.value)}
                >
                  {shareEvents.map((ev) => (
                    <SelectItem key={String(ev.id)} textValue={ev.title}>
                      {ev.title}
                    </SelectItem>
                  ))}
                </Select>
              </ModalBody>
              <ModalFooter>
                <Button variant="flat" onPress={close}>
                  {t('cancel')}
                </Button>
                <Button color="primary" onPress={() => void submitShare()} isLoading={shareSubmitting} isDisabled={!selectedEventId}>
                  {t('verein_federation.share_event_submit')}
                </Button>
              </ModalFooter>
            </>
          )}
        </ModalContent>
      </Modal>
    </div>
  );
}
