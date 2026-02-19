// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState, useEffect } from 'react';
import {
  Card,
  CardBody,
  CardHeader,
  Button,
  Spinner,
  Table,
  TableHeader,
  TableColumn,
  TableBody,
  TableRow,
  TableCell,
  Progress,
  Input,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
} from '@heroui/react';
import {
  Users,
  CheckCircle2,
  AlertCircle,
  Download,
  Eye,
  TrendingUp,
} from 'lucide-react';
import { usePageTitle } from '@/hooks/usePageTitle';
import { useToast } from '@/contexts/ToastContext';
import { adminLegalDocs } from '@/admin/api/adminApi';
import type { ComplianceStats, UserAcceptance } from '@/admin/api/types';

export default function LegalDocComplianceDashboard() {
  usePageTitle('Legal Compliance Dashboard');

  const { success, error } = useToast();

  const [stats, setStats] = useState<ComplianceStats | null>(null);
  const [loading, setLoading] = useState(true);
  const [showAcceptancesModal, setShowAcceptancesModal] = useState(false);
  const [selectedDocId, setSelectedDocId] = useState<number | null>(null);
  const [acceptances, setAcceptances] = useState<UserAcceptance[]>([]);
  const [loadingAcceptances, setLoadingAcceptances] = useState(false);
  const [dateRange, setDateRange] = useState({ start: '', end: '' });
  const [exportingDocId, setExportingDocId] = useState<number | null>(null);

  useEffect(() => {
    loadStats();
  }, []);

  const loadStats = async () => {
    try {
      setLoading(true);
      const response = await adminLegalDocs.getComplianceStats();

      if (response.success && response.data) {
        setStats(response.data);
      } else {
        error(response.error || 'Failed to load compliance stats');
      }
    } catch (err) {
      error('Failed to load compliance stats');
    } finally {
      setLoading(false);
    }
  };

  const loadAcceptances = async (docId: number, versionId: number) => {
    try {
      setLoadingAcceptances(true);
      const response = await adminLegalDocs.getAcceptances(versionId, 100, 0);

      if (response.success && response.data) {
        setAcceptances(response.data);
        setSelectedDocId(docId);
        setShowAcceptancesModal(true);
      } else {
        error(response.error || 'Failed to load acceptances');
      }
    } catch (err) {
      error('Failed to load acceptances');
    } finally {
      setLoadingAcceptances(false);
    }
  };

  const handleExport = async (docId: number) => {
    try {
      setExportingDocId(docId);
      const response = await adminLegalDocs.exportAcceptances(
        docId,
        dateRange.start || undefined,
        dateRange.end || undefined
      );

      if (response) {
        // Create download link for blob
        const url = window.URL.createObjectURL(response as unknown as Blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `acceptances_${docId}_${Date.now()}.csv`;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);

        success('Export downloaded successfully');
      } else {
        error('Failed to export acceptances');
      }
    } catch (err) {
      error('Failed to export acceptances');
    } finally {
      setExportingDocId(null);
    }
  };

  const getComplianceColor = (rate: number): 'success' | 'warning' | 'danger' => {
    if (rate >= 90) return 'success';
    if (rate >= 70) return 'warning';
    return 'danger';
  };

  if (loading) {
    return (
      <div className="flex justify-center items-center min-h-[400px]">
        <Spinner size="lg" />
      </div>
    );
  }

  if (!stats) {
    return (
      <div className="text-center py-12">
        <AlertCircle size={48} className="mx-auto text-[var(--color-text-tertiary)] mb-4" />
        <p className="text-[var(--color-text-secondary)]">Failed to load compliance data</p>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div>
        <h1 className="text-3xl font-bold">Legal Compliance Dashboard</h1>
        <p className="text-[var(--color-text-secondary)] mt-1">
          Track user acceptance rates and compliance metrics
        </p>
      </div>

      {/* Stats Cards */}
      <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
        <Card>
          <CardBody>
            <div className="flex items-center gap-3">
              <div className="p-3 bg-primary-100 dark:bg-primary-900/30 rounded-lg">
                <Users size={24} className="text-primary" />
              </div>
              <div>
                <p className="text-sm text-[var(--color-text-secondary)]">Total Users</p>
                <p className="text-2xl font-bold">{stats.total_users.toLocaleString()}</p>
              </div>
            </div>
          </CardBody>
        </Card>

        <Card>
          <CardBody>
            <div className="flex items-center gap-3">
              <div className="p-3 bg-success-100 dark:bg-success-900/30 rounded-lg">
                <CheckCircle2 size={24} className="text-success" />
              </div>
              <div>
                <p className="text-sm text-[var(--color-text-secondary)]">Fully Compliant</p>
                <p className="text-2xl font-bold">
                  {(stats.total_users - stats.users_pending_acceptance).toLocaleString()}
                </p>
              </div>
            </div>
          </CardBody>
        </Card>

        <Card>
          <CardBody>
            <div className="flex items-center gap-3">
              <div className="p-3 bg-warning-100 dark:bg-warning-900/30 rounded-lg">
                <AlertCircle size={24} className="text-warning" />
              </div>
              <div>
                <p className="text-sm text-[var(--color-text-secondary)]">Pending</p>
                <p className="text-2xl font-bold">
                  {stats.users_pending_acceptance.toLocaleString()}
                </p>
              </div>
            </div>
          </CardBody>
        </Card>

        <Card>
          <CardBody>
            <div className="flex items-center gap-3">
              <div className="p-3 bg-primary-100 dark:bg-primary-900/30 rounded-lg">
                <TrendingUp size={24} className="text-primary" />
              </div>
              <div>
                <p className="text-sm text-[var(--color-text-secondary)]">Overall Compliance</p>
                <p className="text-2xl font-bold">{stats.overall_compliance_rate.toFixed(1)}%</p>
              </div>
            </div>
          </CardBody>
        </Card>
      </div>

      {/* Per-Document Breakdown */}
      <Card>
        <CardHeader>
          <h2 className="text-xl font-semibold">Document Acceptance Rates</h2>
        </CardHeader>
        <CardBody>
          {stats.documents.length === 0 ? (
            <div className="text-center py-8">
              <AlertCircle size={40} className="mx-auto text-[var(--color-text-tertiary)] mb-3" />
              <p className="text-[var(--color-text-secondary)]">No legal documents found</p>
            </div>
          ) : (
            <Table aria-label="Document compliance table">
              <TableHeader>
                <TableColumn>Document</TableColumn>
                <TableColumn>Version</TableColumn>
                <TableColumn>Effective Date</TableColumn>
                <TableColumn>Acceptance Rate</TableColumn>
                <TableColumn>Users Accepted</TableColumn>
                <TableColumn>Users Pending</TableColumn>
                <TableColumn>Actions</TableColumn>
              </TableHeader>
              <TableBody>
                {stats.documents.map((doc) => (
                  <TableRow key={doc.id}>
                    <TableCell>
                      <div>
                        <p className="font-medium">{doc.title}</p>
                        <p className="text-sm text-[var(--color-text-secondary)]">
                          {doc.document_type}
                        </p>
                      </div>
                    </TableCell>
                    <TableCell>{doc.version_number || 'N/A'}</TableCell>
                    <TableCell>
                      {doc.effective_date
                        ? new Date(doc.effective_date).toLocaleDateString()
                        : 'N/A'}
                    </TableCell>
                    <TableCell>
                      <div className="space-y-2">
                        <div className="flex items-center gap-2">
                          <Progress
                            value={doc.acceptance_rate}
                            color={getComplianceColor(doc.acceptance_rate)}
                            className="flex-1"
                            size="sm"
                          />
                          <span className="text-sm font-medium w-12 text-right">
                            {doc.acceptance_rate.toFixed(1)}%
                          </span>
                        </div>
                      </div>
                    </TableCell>
                    <TableCell>
                      <span className="text-success font-medium">
                        {doc.users_accepted.toLocaleString()}
                      </span>
                    </TableCell>
                    <TableCell>
                      <span className="text-warning font-medium">
                        {doc.users_not_accepted.toLocaleString()}
                      </span>
                    </TableCell>
                    <TableCell>
                      <div className="flex gap-2">
                        <Button
                          size="sm"
                          variant="flat"
                          startContent={<Eye size={16} />}
                          onPress={() => {
                            if (doc.current_version_id) {
                              loadAcceptances(doc.id, doc.current_version_id);
                            }
                          }}
                          isDisabled={!doc.current_version_id}
                          isLoading={loadingAcceptances && selectedDocId === doc.id}
                        >
                          View
                        </Button>
                        <Button
                          size="sm"
                          variant="bordered"
                          startContent={<Download size={16} />}
                          onPress={() => handleExport(doc.id)}
                          isLoading={exportingDocId === doc.id}
                        >
                          Export
                        </Button>
                      </div>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          )}
        </CardBody>
      </Card>

      {/* Date Range Filter */}
      <Card>
        <CardHeader>
          <h2 className="text-xl font-semibold">Export Options</h2>
        </CardHeader>
        <CardBody>
          <div className="flex items-end gap-4">
            <Input
              type="date"
              label="Start Date"
              value={dateRange.start}
              onChange={(e) => setDateRange({ ...dateRange, start: e.target.value })}
              className="flex-1"
            />
            <Input
              type="date"
              label="End Date"
              value={dateRange.end}
              onChange={(e) => setDateRange({ ...dateRange, end: e.target.value })}
              className="flex-1"
            />
            <Button
              color="default"
              variant="flat"
              onPress={() => setDateRange({ start: '', end: '' })}
            >
              Clear
            </Button>
          </div>
          <p className="text-sm text-[var(--color-text-secondary)] mt-2">
            Use the date range to filter acceptance records when exporting
          </p>
        </CardBody>
      </Card>

      {/* Acceptances Modal */}
      <Modal
        isOpen={showAcceptancesModal}
        onClose={() => setShowAcceptancesModal(false)}
        size="5xl"
        scrollBehavior="inside"
      >
        <ModalContent>
          {(onClose) => (
            <>
              <ModalHeader>User Acceptances</ModalHeader>
              <ModalBody>
                {acceptances.length === 0 ? (
                  <div className="text-center py-8">
                    <AlertCircle size={40} className="mx-auto text-[var(--color-text-tertiary)] mb-3" />
                    <p className="text-[var(--color-text-secondary)]">No acceptances found</p>
                  </div>
                ) : (
                  <Table aria-label="User acceptances">
                    <TableHeader>
                      <TableColumn>User</TableColumn>
                      <TableColumn>Email</TableColumn>
                      <TableColumn>Version</TableColumn>
                      <TableColumn>Accepted At</TableColumn>
                      <TableColumn>Method</TableColumn>
                      <TableColumn>IP Address</TableColumn>
                    </TableHeader>
                    <TableBody>
                      {acceptances.map((acceptance, idx) => (
                        <TableRow key={idx}>
                          <TableCell>{acceptance.user_name}</TableCell>
                          <TableCell>{acceptance.user_email}</TableCell>
                          <TableCell>{acceptance.version_number}</TableCell>
                          <TableCell>
                            {new Date(acceptance.accepted_at).toLocaleString()}
                          </TableCell>
                          <TableCell>{acceptance.acceptance_method}</TableCell>
                          <TableCell>
                            <span className="font-mono text-xs">
                              {acceptance.ip_address || 'N/A'}
                            </span>
                          </TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                )}
              </ModalBody>
              <ModalFooter>
                <Button color="primary" onPress={onClose}>
                  Close
                </Button>
              </ModalFooter>
            </>
          )}
        </ModalContent>
      </Modal>
    </div>
  );
}
