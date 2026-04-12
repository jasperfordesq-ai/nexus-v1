// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Federation Data Management
 * Full-JSON export, JSON import (with dry-run), and stale log purge.
 */

import { useState, useCallback, useRef } from 'react';
import {
  Card,
  CardBody,
  CardHeader,
  Button,
  Progress,
  Input,
  Switch,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Chip,
  useDisclosure,
} from '@heroui/react';
import { Download, Upload, Trash2, FileJson, AlertTriangle } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { adminFederation } from '../../api/adminApi';
import { PageHeader } from '../../components';

interface ImportSummary {
  dry_run: boolean;
  partnerships: { new: number; skipped: number; invalid: number };
  external_partners: { new: number; skipped: number; invalid: number };
}

export function DataManagement() {
  const { t } = useTranslation('admin');
  usePageTitle(t('federation.data_management_title'));
  const toast = useToast();

  const [exporting, setExporting] = useState(false);
  const [importing, setImporting] = useState(false);
  const [purging, setPurging] = useState(false);
  const [importFile, setImportFile] = useState<File | null>(null);
  const [dryRun, setDryRun] = useState(true);
  const [importSummary, setImportSummary] = useState<ImportSummary | null>(null);
  const [purgeDays, setPurgeDays] = useState(365);
  const fileInputRef = useRef<HTMLInputElement | null>(null);

  const purgeModal = useDisclosure();

  const handleExport = useCallback(async () => {
    setExporting(true);
    try {
      await adminFederation.exportFederationData();
      toast.success(t('federation.export_success'));
    } catch {
      toast.error(t('federation.export_failed', 'Export failed'));
    }
    setExporting(false);
  }, [t, toast]);

  const handleFileChange = useCallback((e: React.ChangeEvent<HTMLInputElement>) => {
    const f = e.target.files?.[0];
    setImportFile(f ?? null);
    setImportSummary(null);
  }, []);

  const handleImport = useCallback(async () => {
    if (!importFile) return;
    setImporting(true);
    try {
      const res = await adminFederation.importFederationData(importFile, dryRun);
      if (res.success && res.data) {
        setImportSummary(res.data);
        toast.success(
          dryRun ? t('federation.import_dry_run_ok') : t('federation.import_success'),
        );
      } else {
        toast.error(res.error || t('federation.import_failed'));
      }
    } catch {
      toast.error(t('federation.import_failed'));
    }
    setImporting(false);
  }, [importFile, dryRun, t, toast]);

  const handlePurge = useCallback(async () => {
    setPurging(true);
    try {
      const res = await adminFederation.purgeFederationData(purgeDays);
      if (res.success && res.data) {
        toast.success(t('federation.purge_success', { count: res.data.deleted }));
        purgeModal.onClose();
      } else {
        toast.error(res.error || t('federation.purge_failed'));
      }
    } catch {
      toast.error(t('federation.purge_failed'));
    }
    setPurging(false);
  }, [purgeDays, t, toast, purgeModal]);

  return (
    <div>
      <PageHeader
        title={t('federation.data_management_title')}
        description={t('federation.data_management_desc')}
      />

      <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
        {/* Export */}
        <Card shadow="sm">
          <CardHeader>
            <h3 className="text-lg font-semibold flex items-center gap-2">
              <Download size={20} /> {t('federation.export_all_title')}
            </h3>
          </CardHeader>
          <CardBody className="flex flex-col gap-3">
            <p className="text-sm text-default-500">
              {t('federation.export_all_desc')}
            </p>
            <ul className="text-xs text-default-500 list-disc pl-5 space-y-0.5">
              <li>{t('federation.export_item_partnerships')}</li>
              <li>{t('federation.export_item_external_partners')}</li>
              <li>{t('federation.export_item_reputation')}</li>
              <li>{t('federation.export_item_api_logs')}</li>
            </ul>
            <p className="text-xs text-warning flex items-center gap-1">
              <AlertTriangle size={12} /> {t('federation.export_secrets_note')}
            </p>
            <Button
              color="primary"
              startContent={<FileJson size={16} />}
              isLoading={exporting}
              onPress={handleExport}
            >
              {t('federation.export_download_button')}
            </Button>
            {exporting && (
              <Progress
                size="sm"
                isIndeterminate
                aria-label={t('federation.export_in_progress')}
              />
            )}
          </CardBody>
        </Card>

        {/* Import */}
        <Card shadow="sm">
          <CardHeader>
            <h3 className="text-lg font-semibold flex items-center gap-2">
              <Upload size={20} /> {t('federation.import_title')}
            </h3>
          </CardHeader>
          <CardBody className="flex flex-col gap-3">
            <p className="text-sm text-default-500">{t('federation.import_desc')}</p>

            <input
              ref={fileInputRef}
              type="file"
              accept="application/json,.json"
              className="hidden"
              onChange={handleFileChange}
            />

            <Button
              variant="flat"
              startContent={<Upload size={16} />}
              onPress={() => fileInputRef.current?.click()}
            >
              {importFile ? importFile.name : t('federation.import_choose_file')}
            </Button>

            <div className="flex items-center justify-between rounded-lg border border-default-200 p-2">
              <div>
                <p className="text-sm font-medium">{t('federation.import_dry_run_label')}</p>
                <p className="text-xs text-default-400">
                  {t('federation.import_dry_run_hint')}
                </p>
              </div>
              <Switch isSelected={dryRun} onValueChange={setDryRun} size="sm" />
            </div>

            <Button
              color={dryRun ? 'primary' : 'warning'}
              isDisabled={!importFile}
              isLoading={importing}
              onPress={handleImport}
            >
              {dryRun ? t('federation.import_run_dry') : t('federation.import_commit')}
            </Button>

            {importSummary && (
              <div className="mt-2 rounded-lg border border-default-200 p-3 text-sm">
                <p className="font-medium mb-2 flex items-center gap-2">
                  {t('federation.import_summary_heading')}
                  <Chip
                    size="sm"
                    color={importSummary.dry_run ? 'primary' : 'success'}
                    variant="flat"
                  >
                    {importSummary.dry_run
                      ? t('federation.import_summary_dry_run')
                      : t('federation.import_summary_committed')}
                  </Chip>
                </p>
                <ul className="text-xs space-y-1">
                  <li>
                    {t('federation.import_summary_partnerships', {
                      new: importSummary.partnerships.new,
                      skipped: importSummary.partnerships.skipped,
                      invalid: importSummary.partnerships.invalid,
                    })}
                  </li>
                  <li>
                    {t('federation.import_summary_external_partners', {
                      new: importSummary.external_partners.new,
                      skipped: importSummary.external_partners.skipped,
                      invalid: importSummary.external_partners.invalid,
                    })}
                  </li>
                </ul>
              </div>
            )}
          </CardBody>
        </Card>

        {/* Purge */}
        <Card shadow="sm" className="lg:col-span-2">
          <CardHeader>
            <h3 className="text-lg font-semibold flex items-center gap-2">
              <Trash2 size={20} /> {t('federation.purge_title')}
            </h3>
          </CardHeader>
          <CardBody className="flex flex-col gap-3">
            <p className="text-sm text-default-500">{t('federation.purge_desc')}</p>
            <div className="flex items-center gap-3">
              <Input
                type="number"
                size="sm"
                label={t('federation.purge_days_label')}
                value={String(purgeDays)}
                onChange={(e) => setPurgeDays(Math.max(30, Math.min(3650, Number(e.target.value) || 0)))}
                className="max-w-xs"
                min={30}
                max={3650}
              />
              <Button
                color="danger"
                variant="flat"
                startContent={<Trash2 size={16} />}
                onPress={purgeModal.onOpen}
              >
                {t('federation.purge_button')}
              </Button>
            </div>
          </CardBody>
        </Card>
      </div>

      <Modal isOpen={purgeModal.isOpen} onClose={purgeModal.onClose}>
        <ModalContent>
          <ModalHeader className="flex items-center gap-2">
            <AlertTriangle className="text-warning" />
            {t('federation.confirm_purge_title')}
          </ModalHeader>
          <ModalBody>
            <p>{t('federation.confirm_purge_body', { days: purgeDays })}</p>
          </ModalBody>
          <ModalFooter>
            <Button variant="light" onPress={purgeModal.onClose}>
              {t('federation.cancel')}
            </Button>
            <Button color="danger" isLoading={purging} onPress={handlePurge}>
              {t('federation.confirm_purge_button')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}

export default DataManagement;
