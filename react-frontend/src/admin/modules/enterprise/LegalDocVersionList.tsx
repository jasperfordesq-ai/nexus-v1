// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState, useEffect, useCallback } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import {
  Card,
  CardBody,
  CardHeader,
  Button,
  Spinner,
  Chip,
  Tooltip,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  RadioGroup,
  Radio,
} from '@heroui/react';
import {
  Clock,
  FileText,
  Eye,
  GitCompare,
  Plus,
  CheckCircle2,
  Send,
  User,
  Calendar,
  AlertCircle,
  Trash2,
  Pencil,
} from 'lucide-react';
import { usePageTitle } from '@/hooks/usePageTitle';
import { useToast } from '@/contexts/ToastContext';
import { useTenant } from '@/contexts/TenantContext';
import { adminLegalDocs } from '@/admin/api/adminApi';
import type { LegalDocumentVersion } from '@/admin/api/types';
import LegalDocVersionForm from './LegalDocVersionForm';
import LegalDocVersionComparison from './LegalDocVersionComparison';
import { sanitizeRichText } from '@/lib/sanitize';

import { useTranslation } from 'react-i18next';
export default function LegalDocVersionList() {
  const { t } = useTranslation('admin');
  usePageTitle(t('enterprise.page_title'));

  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { tenantPath } = useTenant();
  const { success, error } = useToast();

  const [versions, setVersions] = useState<LegalDocumentVersion[]>([]);
  const [loading, setLoading] = useState(true);
  const [showFormModal, setShowFormModal] = useState(false);
  const [showCompareModal, setShowCompareModal] = useState(false);
  const [showPublishModal, setShowPublishModal] = useState(false);
  const [showNotifyModal, setShowNotifyModal] = useState(false);
  const [selectedVersion, setSelectedVersion] = useState<LegalDocumentVersion | null>(null);
  const [compareVersions, setCompareVersions] = useState<{ v1: number; v2: number } | null>(null);
  const [notifyTarget, setNotifyTarget] = useState<'all' | 'non_accepted'>('non_accepted');
  const [pendingCount, setPendingCount] = useState<number>(0);
  const [submitting, setSubmitting] = useState(false);
  const [showViewModal, setShowViewModal] = useState(false);
  const [viewingVersion, setViewingVersion] = useState<LegalDocumentVersion | null>(null);
  const [showDeleteModal, setShowDeleteModal] = useState(false);
  const [deleteTarget, setDeleteTarget] = useState<LegalDocumentVersion | null>(null);
  const [showEditModal, setShowEditModal] = useState(false);
  const [editTarget, setEditTarget] = useState<LegalDocumentVersion | null>(null);

  const documentId = parseInt(id || '0', 10);

  const loadVersions = useCallback(async () => {
    if (!documentId) return;

    try {
      setLoading(true);
      const response = await adminLegalDocs.getVersions(documentId);

      if (response.success && response.data) {
        setVersions(response.data);
      } else {
        error(response.error || 'Failed to load versions');
      }
    } catch {
      error('Failed to load versions');
    } finally {
      setLoading(false);
    }
  }, [documentId, error]);

  useEffect(() => {
    loadVersions();
  }, [loadVersions]);

  const handlePublish = async () => {
    if (!selectedVersion) return;

    try {
      setSubmitting(true);
      const response = await adminLegalDocs.publishVersion(selectedVersion.id);

      if (response.success) {
        success('Version published successfully');
        setShowPublishModal(false);
        setSelectedVersion(null);
        loadVersions();
      } else {
        error(response.error || 'Failed to publish version');
      }
    } catch {
      error('Failed to publish version');
    } finally {
      setSubmitting(false);
    }
  };

  const handleDeleteDraft = async () => {
    if (!deleteTarget) return;

    try {
      setSubmitting(true);
      const response = await adminLegalDocs.deleteVersion(documentId, deleteTarget.id);

      if (response.success) {
        success('Draft version deleted');
        setShowDeleteModal(false);
        setDeleteTarget(null);
        loadVersions();
      } else {
        error(response.error || 'Failed to delete version');
      }
    } catch {
      error('Failed to delete version');
    } finally {
      setSubmitting(false);
    }
  };

  const handleNotifyUsers = async () => {
    if (!selectedVersion) return;

    try {
      setSubmitting(true);
      const response = await adminLegalDocs.notifyUsers(documentId, selectedVersion.id, { target: notifyTarget });

      if (response.success) {
        success(`Notification sent to ${notifyTarget === 'all' ? 'all users' : 'non-accepted users'}`);
        setShowNotifyModal(false);
        setSelectedVersion(null);
      } else {
        error(response.error || 'Failed to send notifications');
      }
    } catch {
      error('Failed to send notifications');
    } finally {
      setSubmitting(false);
    }
  };

  const openNotifyModal = async (version: LegalDocumentVersion) => {
    setSelectedVersion(version);

    // Fetch pending count
    try {
      const response = await adminLegalDocs.getUsersPendingCount(documentId, version.id);
      if (response.success && response.data) {
        setPendingCount(response.data.count);
      }
    } catch {
      console.error('Failed to fetch pending count', error);
    }

    setShowNotifyModal(true);
  };

  const openCompareModal = (v1: LegalDocumentVersion, v2: LegalDocumentVersion) => {
    setCompareVersions({ v1: v1.id, v2: v2.id });
    setShowCompareModal(true);
  };

  if (loading) {
    return (
      <div className="flex justify-center items-center min-h-[400px]">
        <Spinner size="lg" />
      </div>
    );
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-3xl font-bold">{t('legal_versions.title')}</h1>
          <p className="text-[var(--color-text-secondary)] mt-1">
            {t('legal_versions.subtitle')}
          </p>
        </div>
        <div className="flex gap-2">
          <Button
            color="primary"
            startContent={<Plus size={18} />}
            onPress={() => setShowFormModal(true)}
          >
            {t('legal_versions.create_new_version')}
          </Button>
          <Button
            variant="bordered"
            onPress={() => navigate(tenantPath('/admin/legal-documents/compliance'))}
          >
            {t('legal_versions.compliance_dashboard')}
          </Button>
        </div>
      </div>

      {/* Version Timeline */}
      <div className="space-y-4">
        {versions.length === 0 ? (
          <Card>
            <CardBody className="text-center py-12">
              <FileText size={48} className="mx-auto text-[var(--color-text-tertiary)] mb-4" />
              <p className="text-lg text-[var(--color-text-secondary)]">{t('enterprise.no_versions_found')}</p>
              <p className="text-sm text-[var(--color-text-tertiary)] mt-1">
                Create a new version to get started
              </p>
            </CardBody>
          </Card>
        ) : (
          versions.map((version, index) => (
            <Card key={version.id} className="border-l-4 border-l-primary">
              <CardHeader className="flex justify-between items-start">
                <div className="flex gap-4">
                  <div className="flex-1">
                    <div className="flex items-center gap-2 mb-2">
                      <h3 className="text-xl font-semibold">
                        Version {version.version_number}
                      </h3>
                      {version.version_label && (
                        <Chip size="sm" color="default">
                          {version.version_label}
                        </Chip>
                      )}
                      {version.is_current && (
                        <Chip size="sm" color="success" startContent={<CheckCircle2 size={14} />}>
                          Current
                        </Chip>
                      )}
                      {version.is_draft && (
                        <Chip size="sm" color="warning">
                          Draft
                        </Chip>
                      )}
                    </div>

                    <div className="flex flex-wrap gap-4 text-sm text-[var(--color-text-secondary)]">
                      <div className="flex items-center gap-1">
                        <User size={14} />
                        <span>{t('enterprise.created_by', { name: version.created_by_name || t('enterprise.unknown') })}</span>
                      </div>
                      <div className="flex items-center gap-1">
                        <Calendar size={14} />
                        <span>{new Date(version.created_at).toLocaleDateString()}</span>
                      </div>
                      {version.effective_date && (
                        <div className="flex items-center gap-1">
                          <Clock size={14} />
                          <span>{t('enterprise.effective_date_label')} {new Date(version.effective_date).toLocaleDateString()}</span>
                        </div>
                      )}
                      {version.published_at && (
                        <div className="flex items-center gap-1">
                          <CheckCircle2 size={14} />
                          <span>{t('enterprise.published_date_label')} {new Date(version.published_at).toLocaleDateString()}</span>
                        </div>
                      )}
                    </div>

                    {version.summary_of_changes && (
                      <div className="mt-3 p-3 bg-[var(--color-surface)] rounded-lg">
                        <p className="text-sm font-medium mb-1">{t('enterprise.summary_of_changes')}</p>
                        <p className="text-sm text-[var(--color-text-secondary)]">
                          {version.summary_of_changes}
                        </p>
                      </div>
                    )}
                  </div>
                </div>

                <div className="flex gap-2">
                  {version.is_draft && (
                    <>
                      <Tooltip content={t('enterprise.tooltip_publish')}>
                        <Button
                          size="sm"
                          color="success"
                          variant="flat"
                          startContent={<CheckCircle2 size={16} />}
                          onPress={() => {
                            setSelectedVersion(version);
                            setShowPublishModal(true);
                          }}
                        >
                          {t('enterprise.btn_publish')}
                        </Button>
                      </Tooltip>
                      <Tooltip content={t('enterprise.tooltip_edit_draft')}>
                        <Button
                          size="sm"
                          variant="flat"
                          startContent={<Pencil size={16} />}
                          onPress={() => {
                            setEditTarget(version);
                            setShowEditModal(true);
                          }}
                        >
                          {t('enterprise.btn_edit')}
                        </Button>
                      </Tooltip>
                      <Tooltip content={t('enterprise.tooltip_delete_draft')}>
                        <Button
                          size="sm"
                          color="danger"
                          variant="flat"
                          startContent={<Trash2 size={16} />}
                          onPress={() => {
                            setDeleteTarget(version);
                            setShowDeleteModal(true);
                          }}
                        >
                          {t('enterprise.btn_delete')}
                        </Button>
                      </Tooltip>
                    </>
                  )}

                  {!version.is_draft && (
                    <Tooltip content={t('enterprise.tooltip_notify')}>
                      <Button
                        size="sm"
                        color="primary"
                        variant="flat"
                        startContent={<Send size={16} />}
                        onPress={() => openNotifyModal(version)}
                      >
                        {t('enterprise.btn_notify')}
                      </Button>
                    </Tooltip>
                  )}

                  {index < versions.length - 1 && (
                    <Tooltip content={t('enterprise.tooltip_compare')}>
                      <Button
                        size="sm"
                        variant="bordered"
                        startContent={<GitCompare size={16} />}
                        onPress={() => { const prev = versions[index + 1]; if (prev) openCompareModal(version, prev); }}
                      >
                        {t('enterprise.btn_compare')}
                      </Button>
                    </Tooltip>
                  )}

                  <Tooltip content={t('enterprise.tooltip_view_content')}>
                    <Button
                      size="sm"
                      variant="bordered"
                      isIconOnly
                      aria-label={t('enterprise.label_view_full_content')}
                      onPress={() => {
                        setViewingVersion(version);
                        setShowViewModal(true);
                      }}
                    >
                      <Eye size={16} />
                    </Button>
                  </Tooltip>
                </div>
              </CardHeader>
            </Card>
          ))
        )}
      </div>

      {/* Create Version Modal */}
      <Modal
        isOpen={showFormModal}
        onClose={() => setShowFormModal(false)}
        size="5xl"
        scrollBehavior="inside"
      >
        <ModalContent>
          {(onClose) => (
            <LegalDocVersionForm
              documentId={documentId}
              onSuccess={() => {
                onClose();
                loadVersions();
              }}
              onCancel={onClose}
            />
          )}
        </ModalContent>
      </Modal>

      {/* Edit Draft Version Modal */}
      {editTarget && (
        <Modal
          isOpen={showEditModal}
          onClose={() => { setShowEditModal(false); setEditTarget(null); }}
          size="5xl"
          scrollBehavior="inside"
        >
          <ModalContent>
            {(onClose) => (
              <LegalDocVersionForm
                documentId={documentId}
                editVersion={editTarget}
                onSuccess={() => {
                  onClose();
                  setEditTarget(null);
                  loadVersions();
                }}
                onCancel={onClose}
              />
            )}
          </ModalContent>
        </Modal>
      )}

      {/* Compare Modal */}
      {compareVersions && (
        <Modal
          isOpen={showCompareModal}
          onClose={() => setShowCompareModal(false)}
          size="5xl"
          scrollBehavior="inside"
        >
          <ModalContent>
            {(onClose) => (
              <LegalDocVersionComparison
                documentId={documentId}
                version1Id={compareVersions.v1}
                version2Id={compareVersions.v2}
                onClose={onClose}
              />
            )}
          </ModalContent>
        </Modal>
      )}

      {/* Publish Confirmation Modal */}
      <Modal isOpen={showPublishModal} onClose={() => setShowPublishModal(false)}>
        <ModalContent>
          {(onClose) => (
            <>
              <ModalHeader>{t('legal_versions.publish_version')}</ModalHeader>
              <ModalBody>
                <div className="space-y-4">
                  <div className="flex items-start gap-3 p-3 bg-warning-50 dark:bg-warning-900/20 rounded-lg">
                    <AlertCircle size={20} className="text-warning flex-shrink-0 mt-0.5" />
                    <div className="text-sm">
                      <p className="font-medium mb-1">{t('legal_versions.this_will')}:</p>
                      <ul className="list-disc list-inside space-y-1 text-[var(--color-text-secondary)]">
                        <li>{t('legal_versions.publish_step_1')}</li>
                        <li>{t('legal_versions.publish_step_2')}</li>
                        <li>{t('legal_versions.publish_step_3')}</li>
                        <li>{t('legal_versions.publish_step_4')}</li>
                      </ul>
                    </div>
                  </div>
                  <p>{t('enterprise.publish_version_confirm', { version: selectedVersion?.version_number })}</p>
                </div>
              </ModalBody>
              <ModalFooter>
                <Button variant="flat" onPress={onClose}>
                  Cancel
                </Button>
                <Button
                  color="success"
                  onPress={handlePublish}
                  isLoading={submitting}
                >
                  {t('legal_versions.publish_version')}
                </Button>
              </ModalFooter>
            </>
          )}
        </ModalContent>
      </Modal>

      {/* View Version Content Modal */}
      <Modal
        isOpen={showViewModal}
        onClose={() => { setShowViewModal(false); setViewingVersion(null); }}
        size="4xl"
        scrollBehavior="inside"
      >
        <ModalContent>
          {(onClose) => (
            <>
              <ModalHeader className="flex flex-col gap-1">
                <div className="flex items-center gap-2">
                  <FileText size={20} />
                  <span>
                    Version {viewingVersion?.version_number}
                    {viewingVersion?.version_label ? ` — ${viewingVersion.version_label}` : ''}
                  </span>
                </div>
              </ModalHeader>
              <ModalBody className="space-y-4">
                <div className="flex flex-wrap gap-4 text-sm text-(--color-text-secondary)">
                  {viewingVersion?.effective_date && (
                    <div className="flex items-center gap-1">
                      <Clock size={14} />
                      <span>{t('enterprise.effective_date_label')} {new Date(viewingVersion.effective_date).toLocaleDateString()}</span>
                    </div>
                  )}
                  {viewingVersion?.published_at && (
                    <div className="flex items-center gap-1">
                      <CheckCircle2 size={14} />
                      <span>{t('enterprise.published_date_label')} {new Date(viewingVersion.published_at).toLocaleDateString()}</span>
                    </div>
                  )}
                </div>
                {viewingVersion?.summary_of_changes && (
                  <div className="p-3 bg-(--color-surface) rounded-lg">
                    <p className="text-sm font-medium mb-1">{t('enterprise.summary_of_changes')}</p>
                    <p className="text-sm text-(--color-text-secondary)">
                      {viewingVersion.summary_of_changes}
                    </p>
                  </div>
                )}
                <div
                  className="prose prose-sm max-w-none dark:prose-invert border rounded-lg p-4 overflow-auto"
                  dangerouslySetInnerHTML={{ __html: sanitizeRichText(viewingVersion?.content ?? '') }}
                />
              </ModalBody>
              <ModalFooter>
                <Button variant="flat" onPress={onClose}>
                  Close
                </Button>
              </ModalFooter>
            </>
          )}
        </ModalContent>
      </Modal>

      {/* Notify Users Modal */}
      <Modal isOpen={showNotifyModal} onClose={() => setShowNotifyModal(false)}>
        <ModalContent>
          {(onClose) => (
            <>
              <ModalHeader>{t('legal_versions.send_notification')}</ModalHeader>
              <ModalBody>
                <div className="space-y-4">
                  <p className="text-sm text-[var(--color-text-secondary)]">
                    Choose who should receive email notifications about version {selectedVersion?.version_number}:
                  </p>

                  <RadioGroup
                    value={notifyTarget}
                    onValueChange={(val) => setNotifyTarget(val as 'all' | 'non_accepted')}
                    aria-label={t('enterprise.label_notification_target')}
                    classNames={{ wrapper: 'gap-3' }}
                  >
                    <Radio
                      value="non_accepted"
                      classNames={{
                        base: 'flex items-start gap-3 p-3 border border-[var(--color-border)] rounded-lg cursor-pointer hover:bg-[var(--color-surface)] max-w-full',
                        label: 'flex-1',
                      }}
                      description={pendingCount > 0 ? `${pendingCount} users` : 'Loading...'}
                    >
                      <span className="font-medium">{t('enterprise.notify_non_accepted')}</span>
                    </Radio>

                    <Radio
                      value="all"
                      classNames={{
                        base: 'flex items-start gap-3 p-3 border border-[var(--color-border)] rounded-lg cursor-pointer hover:bg-[var(--color-surface)] max-w-full',
                        label: 'flex-1',
                      }}
                      description={t('enterprise.desc_send_to_everyone_may_be_redundant')}
                    >
                      <span className="font-medium">{t('enterprise.notify_all_active')}</span>
                    </Radio>
                  </RadioGroup>
                </div>
              </ModalBody>
              <ModalFooter>
                <Button variant="flat" onPress={onClose}>
                  Cancel
                </Button>
                <Button
                  color="primary"
                  onPress={handleNotifyUsers}
                  isLoading={submitting}
                  startContent={<Send size={16} />}
                >
                  {t('legal_versions.send_notification')}
                </Button>
              </ModalFooter>
            </>
          )}
        </ModalContent>
      </Modal>

      {/* Delete Draft Confirmation Modal */}
      <Modal isOpen={showDeleteModal} onClose={() => { setShowDeleteModal(false); setDeleteTarget(null); }}>
        <ModalContent>
          {(onClose) => (
            <>
              <ModalHeader>{t('legal_versions.delete_draft_version')}</ModalHeader>
              <ModalBody>
                <div className="space-y-4">
                  <div className="flex items-start gap-3 p-3 bg-danger-50 dark:bg-danger-900/20 rounded-lg">
                    <AlertCircle size={20} className="text-danger shrink-0 mt-0.5" />
                    <div className="text-sm">
                      <p className="font-medium mb-1">{t('enterprise.action_cannot_be_undone')}</p>
                      <p className="text-[var(--color-text-secondary)]">
                        {t('enterprise.draft_will_be_deleted', { version: deleteTarget?.version_number })}
                      </p>
                    </div>
                  </div>
                </div>
              </ModalBody>
              <ModalFooter>
                <Button variant="flat" onPress={onClose}>
                  Cancel
                </Button>
                <Button
                  color="danger"
                  onPress={handleDeleteDraft}
                  isLoading={submitting}
                  startContent={<Trash2 size={16} />}
                >
                  {t('legal_versions.delete_draft')}
                </Button>
              </ModalFooter>
            </>
          )}
        </ModalContent>
      </Modal>
    </div>
  );
}
