// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * VereinMembersImportPage — AG30
 *
 * Verein admin uploads a CSV (email, first_name, last_name, phone, role)
 * and gets a row-level preview before confirming the import.
 *
 * API:
 *   POST /api/v2/caring-community/vereine/{organizationId}/members/import/preview
 *   POST /api/v2/caring-community/vereine/{organizationId}/members/import
 *
 * Access is enforced server-side (verein_admin scoped to this organization).
 * If the caller does not have permission, the preview/import endpoint returns 403.
 */

import { ChangeEvent, useCallback, useState } from 'react';
import { useParams, Link } from 'react-router-dom';
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
  Textarea,
} from '@heroui/react';
import Upload from 'lucide-react/icons/upload';
import FileText from 'lucide-react/icons/file-text';
import CheckCircle2 from 'lucide-react/icons/circle-check';
import AlertTriangle from 'lucide-react/icons/triangle-alert';
import ArrowLeft from 'lucide-react/icons/arrow-left';
import { PageMeta } from '@/components/seo';
import { useTenant, useToast } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

interface PreviewItem {
  row: number;
  email: string;
  first_name: string;
  last_name: string;
  phone: string | null;
  role: string;
  action: 'create' | 'link_existing' | 'already_member' | 'invalid' | string;
  existing_user_id: number | null;
  errors: string[];
}

interface PreviewSummary {
  total_rows: number;
  ready_to_create: number;
  ready_to_link: number;
  duplicates: number;
  invalid: number;
}

interface PreviewResponse {
  organization: { id: number; name: string; org_type: string };
  summary: PreviewSummary;
  items: PreviewItem[];
}

interface ImportResultMember {
  user_id: number;
  email: string;
  created: boolean;
  temporary_password: string | null;
}

interface ImportResponse {
  organization: { id: number; name: string };
  created: number;
  linked: number;
  skipped: number;
  members: ImportResultMember[];
}

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export default function VereinMembersImportPage() {
  const { id } = useParams<{ id: string }>();
  const orgId = parseInt(id ?? '0', 10);
  const { t } = useTranslation('common');
  const { tenantPath } = useTenant();
  const toast = useToast();
  usePageTitle(t('verein_import.title', 'Import club members'));

  const [csv, setCsv] = useState('');
  const [preview, setPreview] = useState<PreviewResponse | null>(null);
  const [loadingPreview, setLoadingPreview] = useState(false);
  const [importing, setImporting] = useState(false);
  const [importResult, setImportResult] = useState<ImportResponse | null>(null);

  const handleFile = useCallback((e: ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = () => setCsv(String(reader.result ?? ''));
    reader.onerror = () => toast.error(t('verein_import.errors.read_failed', 'Could not read file'));
    reader.readAsText(file);
  }, [toast, t]);

  const runPreview = useCallback(async () => {
    if (!csv.trim()) {
      toast.error(t('verein_import.errors.empty_csv', 'Paste or upload a CSV first'));
      return;
    }
    setLoadingPreview(true);
    setImportResult(null);
    try {
      const res = await api.post<PreviewResponse>(
        `/v2/caring-community/vereine/${orgId}/members/import/preview`,
        { csv },
      );
      if (res.success && res.data) {
        setPreview(res.data);
      } else {
        toast.error(res.error || t('verein_import.errors.preview_failed', 'Preview failed'));
        setPreview(null);
      }
    } catch (err) {
      logError('VereinMembersImportPage: preview failed', err);
      toast.error(t('verein_import.errors.preview_failed', 'Preview failed'));
    } finally {
      setLoadingPreview(false);
    }
  }, [csv, orgId, toast, t]);

  const runImport = useCallback(async () => {
    if (!preview) return;
    if (preview.summary.invalid > 0) {
      toast.error(t('verein_import.errors.has_invalid', 'Fix invalid rows before importing'));
      return;
    }
    setImporting(true);
    try {
      const res = await api.post<ImportResponse>(
        `/v2/caring-community/vereine/${orgId}/members/import`,
        { csv },
      );
      if (res.success && res.data) {
        setImportResult(res.data);
        toast.success(
          t('verein_import.success', 'Import complete: {{created}} created, {{linked}} linked', {
            created: res.data.created,
            linked: res.data.linked,
          }),
        );
        setPreview(null);
      } else {
        toast.error(res.error || t('verein_import.errors.import_failed', 'Import failed'));
      }
    } catch (err) {
      logError('VereinMembersImportPage: import failed', err);
      toast.error(t('verein_import.errors.import_failed', 'Import failed'));
    } finally {
      setImporting(false);
    }
  }, [csv, orgId, preview, toast, t]);

  const actionChip = (action: string) => {
    if (action === 'create') return <Chip size="sm" color="success" variant="flat">create</Chip>;
    if (action === 'link_existing') return <Chip size="sm" color="primary" variant="flat">link</Chip>;
    if (action === 'already_member') return <Chip size="sm" color="warning" variant="flat">already</Chip>;
    return <Chip size="sm" color="danger" variant="flat">invalid</Chip>;
  };

  return (
    <div className="mx-auto max-w-5xl px-4 py-6 space-y-6">
      <PageMeta title={t('verein_import.title', 'Import club members')} />

      <div className="flex items-center justify-between flex-wrap gap-2">
        <div>
          <h1 className="text-2xl font-bold">{t('verein_import.title', 'Import club members')}</h1>
          <p className="text-sm text-default-500 mt-1">
            {t(
              'verein_import.subtitle',
              'Bulk-add members from a CSV. Preview before confirming — no users are created until you click Import.',
            )}
          </p>
        </div>
        <Button
          as={Link}
          to={tenantPath('/clubs')}
          size="sm"
          variant="flat"
          startContent={<ArrowLeft className="w-4 h-4" />}
        >
          {t('common.back', 'Back')}
        </Button>
      </div>

      {/* Step 1: upload */}
      <Card>
        <CardHeader className="flex items-center gap-2">
          <Upload className="w-5 h-5 text-primary" />
          <h2 className="text-base font-semibold">
            {t('verein_import.step1', '1. Upload CSV')}
          </h2>
        </CardHeader>
        <Divider />
        <CardBody className="space-y-3">
          <p className="text-sm text-default-600">
            {t(
              'verein_import.csv_format',
              'Required header row: email,first_name,last_name,phone,role',
            )}
          </p>
          <Input
            type="file"
            accept=".csv,text/csv"
            onChange={handleFile}
            label={t('verein_import.file_label', 'CSV file')}
          />
          <Textarea
            label={t('verein_import.csv_label', 'Or paste CSV')}
            value={csv}
            onValueChange={setCsv}
            minRows={4}
            maxRows={10}
            placeholder={'email,first_name,last_name,phone,role\nalice@example.org,Alice,Smith,+41 79 …,member'}
          />
          <div className="flex justify-end">
            <Button
              color="primary"
              startContent={<FileText className="w-4 h-4" />}
              onPress={() => void runPreview()}
              isLoading={loadingPreview}
            >
              {t('verein_import.preview', 'Preview')}
            </Button>
          </div>
        </CardBody>
      </Card>

      {/* Step 2: preview */}
      {loadingPreview && (
        <div className="flex items-center justify-center py-8">
          <Spinner size="lg" />
        </div>
      )}

      {preview && (
        <Card>
          <CardHeader className="flex items-center gap-2 flex-wrap">
            <FileText className="w-5 h-5 text-primary" />
            <h2 className="text-base font-semibold">
              {t('verein_import.step2', '2. Review and confirm')}
            </h2>
            <span className="text-sm text-default-500 ml-2">{preview.organization.name}</span>
          </CardHeader>
          <Divider />
          <CardBody className="space-y-4">
            {/* Summary */}
            <div className="grid grid-cols-2 md:grid-cols-5 gap-3">
              <div className="text-center p-3 rounded-lg bg-default-50">
                <p className="text-xs text-default-500 uppercase">{t('verein_import.summary.total', 'Total')}</p>
                <p className="text-2xl font-bold">{preview.summary.total_rows}</p>
              </div>
              <div className="text-center p-3 rounded-lg bg-success-50">
                <p className="text-xs text-success-700 uppercase">{t('verein_import.summary.create', 'Create')}</p>
                <p className="text-2xl font-bold text-success">{preview.summary.ready_to_create}</p>
              </div>
              <div className="text-center p-3 rounded-lg bg-primary-50">
                <p className="text-xs text-primary-700 uppercase">{t('verein_import.summary.link', 'Link')}</p>
                <p className="text-2xl font-bold text-primary">{preview.summary.ready_to_link}</p>
              </div>
              <div className="text-center p-3 rounded-lg bg-warning-50">
                <p className="text-xs text-warning-700 uppercase">{t('verein_import.summary.duplicates', 'Duplicates')}</p>
                <p className="text-2xl font-bold text-warning">{preview.summary.duplicates}</p>
              </div>
              <div className="text-center p-3 rounded-lg bg-danger-50">
                <p className="text-xs text-danger-700 uppercase">{t('verein_import.summary.invalid', 'Invalid')}</p>
                <p className="text-2xl font-bold text-danger">{preview.summary.invalid}</p>
              </div>
            </div>

            {preview.summary.invalid > 0 && (
              <div className="flex items-center gap-2 p-3 bg-danger-50 border border-danger-200 rounded-lg text-sm text-danger-800">
                <AlertTriangle className="w-4 h-4 flex-shrink-0" />
                <span>
                  {t(
                    'verein_import.errors.has_invalid_summary',
                    'Some rows are invalid. Fix them in your CSV and re-run the preview before importing.',
                  )}
                </span>
              </div>
            )}

            {/* Row table */}
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead className="bg-default-50">
                  <tr className="text-xs text-default-500 uppercase tracking-wide">
                    <th className="text-left px-3 py-2">{t('verein_import.row.row', 'Row')}</th>
                    <th className="text-left px-3 py-2">{t('verein_import.row.action', 'Action')}</th>
                    <th className="text-left px-3 py-2">{t('verein_import.row.email', 'Email')}</th>
                    <th className="text-left px-3 py-2 hidden md:table-cell">{t('verein_import.row.name', 'Name')}</th>
                    <th className="text-left px-3 py-2 hidden md:table-cell">{t('verein_import.row.role', 'Role')}</th>
                    <th className="text-left px-3 py-2">{t('verein_import.row.errors', 'Errors')}</th>
                  </tr>
                </thead>
                <tbody>
                  {preview.items.map((row) => (
                    <tr
                      key={row.row}
                      className={`border-t border-default-200 ${
                        row.errors.length ? 'bg-danger-50/40' : ''
                      }`}
                    >
                      <td className="px-3 py-2 text-default-500 tabular-nums">{row.row}</td>
                      <td className="px-3 py-2">{actionChip(row.action)}</td>
                      <td className="px-3 py-2">{row.email || '—'}</td>
                      <td className="px-3 py-2 hidden md:table-cell">
                        {[row.first_name, row.last_name].filter(Boolean).join(' ') || '—'}
                      </td>
                      <td className="px-3 py-2 hidden md:table-cell">{row.role}</td>
                      <td className="px-3 py-2 text-danger-700">
                        {row.errors.length > 0 ? row.errors.join('; ') : ''}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            <div className="flex justify-end gap-2">
              <Button variant="flat" onPress={() => setPreview(null)}>
                {t('common.cancel', 'Cancel')}
              </Button>
              <Button
                color="primary"
                startContent={<CheckCircle2 className="w-4 h-4" />}
                onPress={() => void runImport()}
                isLoading={importing}
                isDisabled={preview.summary.invalid > 0}
              >
                {t('verein_import.confirm', 'Import members')}
              </Button>
            </div>
          </CardBody>
        </Card>
      )}

      {/* Step 3: result */}
      {importResult && (
        <Card>
          <CardHeader className="flex items-center gap-2">
            <CheckCircle2 className="w-5 h-5 text-success" />
            <h2 className="text-base font-semibold">
              {t('verein_import.result.title', 'Import complete')}
            </h2>
          </CardHeader>
          <Divider />
          <CardBody className="space-y-3">
            <p className="text-sm">
              {t('verein_import.result.summary', 'Created {{created}} new members, linked {{linked}} existing, skipped {{skipped}}', {
                created: importResult.created,
                linked: importResult.linked,
                skipped: importResult.skipped,
              })}
            </p>
            {importResult.members.some((m) => m.temporary_password) && (
              <div className="p-3 bg-warning-50 border border-warning-200 rounded-lg text-sm space-y-2">
                <p className="font-medium">
                  {t('verein_import.result.passwords_title', 'Temporary passwords (share securely):')}
                </p>
                <ul className="list-disc list-inside space-y-1 font-mono text-xs">
                  {importResult.members
                    .filter((m) => m.temporary_password)
                    .map((m) => (
                      <li key={m.user_id}>
                        {m.email} — <span className="select-all">{m.temporary_password}</span>
                      </li>
                    ))}
                </ul>
                <p className="text-xs text-warning-700">
                  {t(
                    'verein_import.result.passwords_warning',
                    'These will not be shown again. Share securely; members will be prompted to change on first login.',
                  )}
                </p>
              </div>
            )}
          </CardBody>
        </Card>
      )}
    </div>
  );
}
