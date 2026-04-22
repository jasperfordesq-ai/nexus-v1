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
import Download from 'lucide-react/icons/download';
import Upload from 'lucide-react/icons/upload';
import Trash2 from 'lucide-react/icons/trash-2';
import FileJson from 'lucide-react/icons/file-json';
import AlertTriangle from 'lucide-react/icons/triangle-alert';
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
  usePageTitle("Data Management");
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
      toast.success("Export succeeded");
    } catch {
      toast.error(t('federation.export_failed', 'Export failed'));
    }
    setExporting(false);
  }, [toast]);

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
          dryRun ? "Import Dry Run Ok" : "Import succeeded",
        );
      } else {
        toast.error(res.error || "Import failed");
      }
    } catch {
      toast.error("Import failed");
    }
    setImporting(false);
  }, [importFile, dryRun, t, toast]);

  const handlePurge = useCallback(async () => {
    setPurging(true);
    try {
      const res = await adminFederation.purgeFederationData(purgeDays);
      if (res.success && res.data) {
        toast.success(`Purge succeeded`);
        purgeModal.onClose();
      } else {
        toast.error(res.error || "Purge failed");
      }
    } catch {
      toast.error("Purge failed");
    }
    setPurging(false);
  }, [purgeDays, t, toast, purgeModal]);

  return (
    <div>
      <PageHeader
        title={"Data Management"}
        description={"Export and import federation data in CSV format"}
      />

      <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
        {/* Export */}
        <Card shadow="sm">
          <CardHeader>
            <h3 className="text-lg font-semibold flex items-center gap-2">
              <Download size={20} /> {"Export All"}
            </h3>
          </CardHeader>
          <CardBody className="flex flex-col gap-3">
            <p className="text-sm text-default-500">
              {"Export All."}
            </p>
            <ul className="text-xs text-default-500 list-disc pl-5 space-y-0.5">
              <li>{"Export Item Partnerships"}</li>
              <li>{"Export Item External Partners"}</li>
              <li>{"Export Item Reputation"}</li>
              <li>{"Export Item API Logs"}</li>
            </ul>
            <p className="text-xs text-warning flex items-center gap-1">
              <AlertTriangle size={12} /> {"Export Secrets"}
            </p>
            <Button
              color="primary"
              startContent={<FileJson size={16} />}
              isLoading={exporting}
              onPress={handleExport}
            >
              {"Export Download"}
            </Button>
            {exporting && (
              <Progress
                size="sm"
                isIndeterminate
                aria-label={"Export in Progress"}
              />
            )}
          </CardBody>
        </Card>

        {/* Import */}
        <Card shadow="sm">
          <CardHeader>
            <h3 className="text-lg font-semibold flex items-center gap-2">
              <Upload size={20} /> {"Import"}
            </h3>
          </CardHeader>
          <CardBody className="flex flex-col gap-3">
            <p className="text-sm text-default-500">{"Import."}</p>

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
              {importFile ? importFile.name : "Import Choose File"}
            </Button>

            <div className="flex items-center justify-between rounded-lg border border-default-200 p-2">
              <div>
                <p className="text-sm font-medium">{"Import Dry Run"}</p>
                <p className="text-xs text-default-400">
                  {"Import Dry Run."}
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
              {dryRun ? "Import Run Dry" : "Import Commit"}
            </Button>

            {importSummary && (
              <div className="mt-2 rounded-lg border border-default-200 p-3 text-sm">
                <p className="font-medium mb-2 flex items-center gap-2">
                  {"Import Summary"}
                  <Chip
                    size="sm"
                    color={importSummary.dry_run ? 'primary' : 'success'}
                    variant="flat"
                  >
                    {importSummary.dry_run
                      ? "Import Summary Dry Run"
                      : "Import Summary Committed"}
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
              <Trash2 size={20} /> {"Purge"}
            </h3>
          </CardHeader>
          <CardBody className="flex flex-col gap-3">
            <p className="text-sm text-default-500">{"Purge."}</p>
            <div className="flex items-center gap-3">
              <Input
                type="number"
                size="sm"
                label={"Purge Days"}
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
                {"Purge"}
              </Button>
            </div>
          </CardBody>
        </Card>
      </div>

      <Modal isOpen={purgeModal.isOpen} onClose={purgeModal.onClose}>
        <ModalContent>
          <ModalHeader className="flex items-center gap-2">
            <AlertTriangle className="text-warning" />
            {"Are you sure you want to purge title?"}
          </ModalHeader>
          <ModalBody>
            <p>{`Are you sure you want to purge body?`}</p>
          </ModalBody>
          <ModalFooter>
            <Button variant="light" onPress={purgeModal.onClose}>
              {"Cancel"}
            </Button>
            <Button color="danger" isLoading={purging} onPress={handlePurge}>
              {"Confirm Purge"}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}

export default DataManagement;
