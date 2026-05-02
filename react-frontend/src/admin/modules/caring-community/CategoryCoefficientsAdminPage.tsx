// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Category Substitution Coefficients Admin Page
 *
 * Per-category coefficients used by the Pflege-CHF computation: each
 * category has a multiplier (0.00 – 9.99) representing how much one hour
 * of that activity substitutes for one hour of formal Spitex/Pflege care.
 *
 * Backed by the `substitution_coefficient` column on the `categories` table.
 */

import { useCallback, useEffect, useState } from 'react';
import {
  Button,
  Card,
  CardBody,
  Chip,
  Input,
  Spinner,
  Table,
  TableBody,
  TableCell,
  TableColumn,
  TableHeader,
  TableRow,
} from '@heroui/react';
import AlertTriangle from 'lucide-react/icons/triangle-alert';
import Info from 'lucide-react/icons/info';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import Save from 'lucide-react/icons/save';
import Sliders from 'lucide-react/icons/sliders';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { PageHeader } from '../../components';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

interface CategoryCoefficient {
  id: number;
  name: string;
  substitution_coefficient: number;
  source_table: string;
}

interface ListResponse {
  categories: CategoryCoefficient[];
  migration_pending: boolean;
}

interface UpdateResponse {
  id: number;
  substitution_coefficient: number;
  source_table: string;
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

const COEFFICIENT_MIN = 0;
const COEFFICIENT_MAX = 9.99;

function clampCoefficient(value: number): number {
  if (Number.isNaN(value)) return 0;
  if (value < COEFFICIENT_MIN) return COEFFICIENT_MIN;
  if (value > COEFFICIENT_MAX) return COEFFICIENT_MAX;
  return Math.round(value * 100) / 100;
}

// ---------------------------------------------------------------------------
// Main Page
// ---------------------------------------------------------------------------

export default function CategoryCoefficientsAdminPage() {
  usePageTitle('Category Substitution Coefficients');
  const { showToast } = useToast();

  const [rows, setRows] = useState<CategoryCoefficient[]>([]);
  const [drafts, setDrafts] = useState<Record<number, string>>({});
  const [savingId, setSavingId] = useState<number | null>(null);
  const [loading, setLoading] = useState(true);
  const [migrationPending, setMigrationPending] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const res = await api.get<ListResponse>(
        '/v2/admin/caring-community/category-coefficients',
      );
      const payload = res.data;
      const list = payload?.categories ?? [];
      setRows(list);
      setMigrationPending(payload?.migration_pending === true);

      // Reset drafts to current saved values.
      const next: Record<number, string> = {};
      for (const r of list) {
        next[r.id] = r.substitution_coefficient.toFixed(2);
      }
      setDrafts(next);
    } catch {
      showToast('Failed to load category coefficients', 'error');
    } finally {
      setLoading(false);
    }
  }, [showToast]);

  useEffect(() => {
    load();
  }, [load]);

  const handleDraftChange = (id: number, value: string) => {
    setDrafts((prev) => ({ ...prev, [id]: value }));
  };

  const handleSave = async (row: CategoryCoefficient) => {
    const draft = drafts[row.id] ?? '';
    const parsed = Number.parseFloat(draft);
    if (Number.isNaN(parsed)) {
      showToast('Coefficient must be a number', 'error');
      return;
    }
    const value = clampCoefficient(parsed);

    setSavingId(row.id);
    try {
      const res = await api.put<UpdateResponse>(
        `/v2/admin/caring-community/category-coefficients/${row.id}`,
        {
          substitution_coefficient: value,
          source_table: row.source_table,
        },
      );
      const updated = res.data;
      const newCoeff = updated?.substitution_coefficient ?? value;

      setRows((prev) =>
        prev.map((r) =>
          r.id === row.id ? { ...r, substitution_coefficient: newCoeff } : r,
        ),
      );
      setDrafts((prev) => ({ ...prev, [row.id]: newCoeff.toFixed(2) }));
      showToast(`Saved coefficient for ${row.name}`, 'success');
    } catch {
      showToast(`Failed to save coefficient for ${row.name}`, 'error');
    } finally {
      setSavingId(null);
    }
  };

  return (
    <div className="space-y-6">
      <PageHeader
        title="Category Substitution Coefficients"
        subtitle="How much each hour of community care substitutes for formal Spitex/Pflege hours. Used in Municipal Impact Report calculations."
        icon={<Sliders size={20} />}
        actions={
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
        }
      />

      {/* Intro card */}
      <Card className="border-l-4 border-l-primary bg-primary-50 dark:bg-primary-900/20" shadow="none">
        <CardBody className="px-4 py-3">
          <div className="flex gap-3">
            <Info className="mt-0.5 h-4 w-4 shrink-0 text-primary" aria-hidden="true" />
            <div className="space-y-1 text-sm">
              <p className="font-semibold text-primary-800 dark:text-primary-200">About this page</p>
              <p className="text-default-600">
                Substitution Coefficients control how much social value each care category is worth
                in the cost-offset calculation. A coefficient of 1.0 means one hour equals one hour
                of formal Spitex/Pflege care (CHF&nbsp;35). A coefficient of 0.5 means it substitutes
                for half an hour. A coefficient of 2.0 means it substitutes for two hours of formal
                care — for example, intensive personal care or specialist support. The
                Age-Stiftung/KISS methodology recommends starting all categories at 1.0 and adjusting
                based on observed care intensity.
              </p>
              <p className="text-default-500">
                Values range from 0.00 (no care substitution value) to 9.99. Changes take effect on
                the next ROI report refresh.
              </p>
            </div>
          </div>
        </CardBody>
      </Card>

      {/* Migration pending warning */}
      {migrationPending && (
        <Card className="border border-warning-200 bg-warning-50">
          <CardBody className="flex flex-row items-start gap-3 py-3">
            <AlertTriangle size={18} className="mt-0.5 shrink-0 text-warning-600" />
            <div className="text-sm text-warning-800">
              <p className="font-semibold">Migration pending</p>
              <p className="mt-1">
                The <code className="rounded bg-warning-100 px-1">substitution_coefficient</code>{' '}
                column has not been migrated yet. Run{' '}
                <code className="rounded bg-warning-100 px-1">
                  docker exec nexus-php-app php artisan migrate
                </code>{' '}
                to enable per-category coefficients.
              </p>
              <p className="mt-1">
                A database migration is pending — save your changes after the migration completes to
                avoid them being overwritten.
              </p>
            </div>
          </CardBody>
        </Card>
      )}

      {/* Loading state */}
      {loading && (
        <div className="flex justify-center py-16">
          <Spinner size="lg" />
        </div>
      )}

      {/* Table */}
      {!loading && !migrationPending && (
        <Card>
          <CardBody className="p-0">
            <Table aria-label="Category substitution coefficients" removeWrapper>
              <TableHeader>
                <TableColumn>Category</TableColumn>
                <TableColumn>Source</TableColumn>
                <TableColumn>Coefficient</TableColumn>
                <TableColumn align="end">Action</TableColumn>
              </TableHeader>
              <TableBody emptyContent="No categories found.">
                {rows.map((row) => {
                  const draft = drafts[row.id] ?? row.substitution_coefficient.toFixed(2);
                  const isSaving = savingId === row.id;
                  const dirty = Number.parseFloat(draft) !== row.substitution_coefficient;
                  return (
                    <TableRow key={`${row.source_table}-${row.id}`}>
                      <TableCell>
                        <span className="font-medium">{row.name}</span>
                      </TableCell>
                      <TableCell>
                        <Chip size="sm" variant="flat">
                          {row.source_table}
                        </Chip>
                      </TableCell>
                      <TableCell>
                        <Input
                          type="number"
                          size="sm"
                          step="0.05"
                          min={COEFFICIENT_MIN}
                          max={COEFFICIENT_MAX}
                          value={draft}
                          onValueChange={(v) => handleDraftChange(row.id, v)}
                          aria-label={`Coefficient for ${row.name}`}
                          className="max-w-[140px]"
                        />
                      </TableCell>
                      <TableCell className="text-right">
                        <Button
                          size="sm"
                          color="primary"
                          variant={dirty ? 'solid' : 'flat'}
                          startContent={!isSaving && <Save size={14} />}
                          onPress={() => handleSave(row)}
                          isLoading={isSaving}
                          isDisabled={!dirty || isSaving}
                        >
                          Save
                        </Button>
                      </TableCell>
                    </TableRow>
                  );
                })}
              </TableBody>
            </Table>
          </CardBody>
        </Card>
      )}
    </div>
  );
}
