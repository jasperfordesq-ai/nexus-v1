// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * AG58 — Admin: Member Premium Tier management.
 * English-only per project convention for /admin/.
 */

import { useCallback, useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import {
  Card,
  CardBody,
  Button,
  Chip,
  Spinner,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Input,
  Textarea,
  Switch,
  useDisclosure,
  Table,
  TableHeader,
  TableColumn,
  TableBody,
  TableRow,
  TableCell,
} from '@heroui/react';
import Crown from 'lucide-react/icons/crown';
import Plus from 'lucide-react/icons/plus';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import Trash from 'lucide-react/icons/trash';
import Pencil from 'lucide-react/icons/pencil';
import Users from 'lucide-react/icons/users';
import { useToast, useTenant } from '@/contexts';
import { usePageTitle } from '@/hooks';
import {
  memberPremiumAdminApi,
  type MemberPremiumTier,
  type TierUpsertPayload,
} from '../../api/memberPremiumApi';
import { PageHeader } from '../../components';

interface FormState {
  id: number | null;
  slug: string;
  name: string;
  description: string;
  monthly_price: string;
  yearly_price: string;
  features: string;
  sort_order: number;
  is_active: boolean;
}

const EMPTY_FORM: FormState = {
  id: null,
  slug: '',
  name: '',
  description: '',
  monthly_price: '0.00',
  yearly_price: '0.00',
  features: '',
  sort_order: 0,
  is_active: true,
};

function centsToInput(c: number): string {
  return (c / 100).toFixed(2);
}

function inputToCents(v: string): number {
  const n = Number.parseFloat(v);
  if (Number.isNaN(n) || n < 0) return 0;
  return Math.round(n * 100);
}

export function MemberPremiumAdminPage() {
  usePageTitle('Member Premium Tiers');
  const toast = useToast();
  const { tenantPath } = useTenant();
  const { isOpen, onOpen, onClose } = useDisclosure();

  const [tiers, setTiers] = useState<MemberPremiumTier[]>([]);
  const [loading, setLoading] = useState(true);
  const [form, setForm] = useState<FormState>(EMPTY_FORM);
  const [saving, setSaving] = useState(false);
  const [syncing, setSyncing] = useState<number | null>(null);
  const [deleting, setDeleting] = useState<number | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const res = await memberPremiumAdminApi.listTiers();
      setTiers(res.tiers ?? []);
    } catch {
      toast.error('Failed to load tiers');
    } finally {
      setLoading(false);
    }
  }, [toast]);

  useEffect(() => {
    load();
  }, [load]);

  const openCreate = () => {
    setForm(EMPTY_FORM);
    onOpen();
  };

  const openEdit = (tier: MemberPremiumTier) => {
    setForm({
      id: tier.id,
      slug: tier.slug,
      name: tier.name,
      description: tier.description ?? '',
      monthly_price: centsToInput(tier.monthly_price_cents),
      yearly_price: centsToInput(tier.yearly_price_cents),
      features: (tier.features ?? []).join(', '),
      sort_order: tier.sort_order,
      is_active: tier.is_active,
    });
    onOpen();
  };

  const save = async () => {
    if (!form.slug.trim() || !form.name.trim()) {
      toast.error('Slug and Name are required');
      return;
    }
    if (!/^[a-z0-9][a-z0-9_-]{0,79}$/.test(form.slug.trim())) {
      toast.error('Slug must be lowercase a-z, 0-9, dashes/underscores');
      return;
    }
    setSaving(true);
    const payload: TierUpsertPayload = {
      slug: form.slug.trim(),
      name: form.name.trim(),
      description: form.description.trim() || null,
      monthly_price_cents: inputToCents(form.monthly_price),
      yearly_price_cents: inputToCents(form.yearly_price),
      features: form.features
        .split(',')
        .map((f) => f.trim())
        .filter(Boolean),
      sort_order: Number(form.sort_order) || 0,
      is_active: form.is_active,
    };

    try {
      if (form.id) {
        await memberPremiumAdminApi.updateTier(form.id, payload);
        toast.success('Tier updated');
      } else {
        await memberPremiumAdminApi.createTier(payload);
        toast.success('Tier created');
      }
      onClose();
      await load();
    } catch (err: unknown) {
      toast.error(err instanceof Error ? err.message : 'Save failed');
    } finally {
      setSaving(false);
    }
  };

  const syncTier = async (tier: MemberPremiumTier) => {
    setSyncing(tier.id);
    try {
      await memberPremiumAdminApi.syncStripe(tier.id);
      toast.success(`Synced "${tier.name}" to Stripe`);
      await load();
    } catch (err: unknown) {
      toast.error(err instanceof Error ? err.message : 'Stripe sync failed');
    } finally {
      setSyncing(null);
    }
  };

  const deleteTier = async (tier: MemberPremiumTier) => {
    if (!window.confirm(`Delete tier "${tier.name}"? This cannot be undone.`)) return;
    setDeleting(tier.id);
    try {
      await memberPremiumAdminApi.deleteTier(tier.id);
      toast.success('Tier deleted');
      await load();
    } catch (err: unknown) {
      toast.error(err instanceof Error ? err.message : 'Delete failed');
    } finally {
      setDeleting(null);
    }
  };

  return (
    <div className="space-y-6">
      <PageHeader
        title="Member Premium Tiers"
        description="Define paid premium tiers that members can subscribe to. Each tier unlocks specific feature keys."
        icon={<Crown size={24} />}
        actions={
          <div className="flex gap-2">
            <Button as={Link} to={tenantPath('/admin/member-premium/subscribers')} variant="flat" startContent={<Users size={16} />}>
              Subscribers
            </Button>
            <Button color="primary" startContent={<Plus size={16} />} onPress={openCreate}>
              New Tier
            </Button>
          </div>
        }
      />

      {loading ? (
        <div className="flex justify-center py-10">
          <Spinner />
        </div>
      ) : tiers.length === 0 ? (
        <Card>
          <CardBody className="text-center py-10 text-default-500">
            No tiers yet. Create your first premium tier above.
          </CardBody>
        </Card>
      ) : (
        <Card>
          <CardBody className="p-0">
            <Table removeWrapper aria-label="Premium tiers">
              <TableHeader>
                <TableColumn>NAME</TableColumn>
                <TableColumn>SLUG</TableColumn>
                <TableColumn>PRICE / MONTH</TableColumn>
                <TableColumn>PRICE / YEAR</TableColumn>
                <TableColumn>FEATURES</TableColumn>
                <TableColumn>STATUS</TableColumn>
                <TableColumn>SUBSCRIBERS</TableColumn>
                <TableColumn>STRIPE</TableColumn>
                <TableColumn>ACTIONS</TableColumn>
              </TableHeader>
              <TableBody>
                {tiers.map((tier) => {
                  const monthSynced = !!tier.stripe_price_id_monthly || tier.monthly_price_cents === 0;
                  const yearSynced = !!tier.stripe_price_id_yearly || tier.yearly_price_cents === 0;
                  const fullySynced = monthSynced && yearSynced;
                  return (
                    <TableRow key={tier.id}>
                      <TableCell>{tier.name}</TableCell>
                      <TableCell><code className="text-xs">{tier.slug}</code></TableCell>
                      <TableCell>{centsToInput(tier.monthly_price_cents)}</TableCell>
                      <TableCell>{centsToInput(tier.yearly_price_cents)}</TableCell>
                      <TableCell>
                        <span className="text-xs">
                          {(tier.features ?? []).slice(0, 3).join(', ') || '—'}
                          {(tier.features ?? []).length > 3 ? `, +${tier.features.length - 3}` : ''}
                        </span>
                      </TableCell>
                      <TableCell>
                        <Chip size="sm" color={tier.is_active ? 'success' : 'default'} variant="flat">
                          {tier.is_active ? 'Active' : 'Inactive'}
                        </Chip>
                      </TableCell>
                      <TableCell>{tier.active_subscriber_count ?? 0}</TableCell>
                      <TableCell>
                        <Chip size="sm" color={fullySynced ? 'success' : 'warning'} variant="flat">
                          {fullySynced ? 'Synced' : 'Needs sync'}
                        </Chip>
                      </TableCell>
                      <TableCell>
                        <div className="flex gap-1">
                          <Button
                            size="sm"
                            variant="flat"
                            isIconOnly
                            onPress={() => openEdit(tier)}
                            aria-label="Edit"
                          >
                            <Pencil size={14} />
                          </Button>
                          <Button
                            size="sm"
                            variant="flat"
                            color="primary"
                            isIconOnly
                            isLoading={syncing === tier.id}
                            onPress={() => syncTier(tier)}
                            aria-label="Sync to Stripe"
                          >
                            <RefreshCw size={14} />
                          </Button>
                          <Button
                            size="sm"
                            variant="flat"
                            color="danger"
                            isIconOnly
                            isLoading={deleting === tier.id}
                            onPress={() => deleteTier(tier)}
                            aria-label="Delete"
                          >
                            <Trash size={14} />
                          </Button>
                        </div>
                      </TableCell>
                    </TableRow>
                  );
                })}
              </TableBody>
            </Table>
          </CardBody>
        </Card>
      )}

      <Modal isOpen={isOpen} onClose={onClose} size="2xl">
        <ModalContent>
          <ModalHeader>{form.id ? 'Edit Tier' : 'New Tier'}</ModalHeader>
          <ModalBody>
            <div className="grid gap-4 sm:grid-cols-2">
              <Input
                label="Name"
                value={form.name}
                onValueChange={(v) => setForm({ ...form, name: v })}
                isRequired
              />
              <Input
                label="Slug"
                value={form.slug}
                onValueChange={(v) => setForm({ ...form, slug: v })}
                description="Lowercase letters, numbers, dashes/underscores"
                isRequired
              />
            </div>
            <Textarea
              label="Description"
              value={form.description}
              onValueChange={(v) => setForm({ ...form, description: v })}
            />
            <div className="grid gap-4 sm:grid-cols-2">
              <Input
                type="number"
                step="0.01"
                min="0"
                label="Price / month"
                value={form.monthly_price}
                onValueChange={(v) => setForm({ ...form, monthly_price: v })}
              />
              <Input
                type="number"
                step="0.01"
                min="0"
                label="Price / year"
                value={form.yearly_price}
                onValueChange={(v) => setForm({ ...form, yearly_price: v })}
              />
            </div>
            <Textarea
              label="Feature keys (comma-separated)"
              value={form.features}
              onValueChange={(v) => setForm({ ...form, features: v })}
              description="e.g. verified_badge, priority_matching, advanced_search, ad_free"
            />
            <div className="grid gap-4 sm:grid-cols-2 items-center">
              <Input
                type="number"
                label="Sort order"
                value={String(form.sort_order)}
                onValueChange={(v) => setForm({ ...form, sort_order: Number(v) || 0 })}
              />
              <Switch
                isSelected={form.is_active}
                onValueChange={(v) => setForm({ ...form, is_active: v })}
              >
                Active
              </Switch>
            </div>
            {form.id && (
              <p className="text-xs text-warning-600 mt-2">
                Note: changing prices clears the Stripe Price IDs. Run "Sync to Stripe" after saving so checkout works again.
              </p>
            )}
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={onClose} isDisabled={saving}>Cancel</Button>
            <Button color="primary" onPress={save} isLoading={saving}>
              {form.id ? 'Save changes' : 'Create tier'}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}

export default MemberPremiumAdminPage;
