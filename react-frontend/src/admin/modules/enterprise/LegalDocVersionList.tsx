import { useState, useEffect } from 'react';
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
} from 'lucide-react';
import { usePageTitle } from '@/hooks/usePageTitle';
import { useToast } from '@/contexts/ToastContext';
import { useTenant } from '@/contexts/TenantContext';
import { adminLegalDocs } from '@/admin/api/adminApi';
import type { LegalDocumentVersion } from '@/admin/api/types';
import LegalDocVersionForm from './LegalDocVersionForm';
import LegalDocVersionComparison from './LegalDocVersionComparison';

export default function LegalDocVersionList() {
  usePageTitle('Legal Document Versions');

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

  const documentId = parseInt(id || '0', 10);

  useEffect(() => {
    loadVersions();
  }, [documentId]);

  const loadVersions = async () => {
    if (!documentId) return;

    try {
      setLoading(true);
      const response = await adminLegalDocs.getVersions(documentId);

      if (response.success && response.data) {
        setVersions(response.data);
      } else {
        error(response.error || 'Failed to load versions');
      }
    } catch (err) {
      error('Failed to load versions');
    } finally {
      setLoading(false);
    }
  };

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
    } catch (err) {
      error('Failed to publish version');
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
    } catch (err) {
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
    } catch (err) {
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
          <h1 className="text-3xl font-bold">Legal Document Versions</h1>
          <p className="text-[var(--color-text-secondary)] mt-1">
            Manage version history and compliance tracking
          </p>
        </div>
        <div className="flex gap-2">
          <Button
            color="primary"
            startContent={<Plus size={18} />}
            onPress={() => setShowFormModal(true)}
          >
            Create New Version
          </Button>
          <Button
            variant="bordered"
            onPress={() => navigate(tenantPath('/admin/legal-documents/compliance'))}
          >
            Compliance Dashboard
          </Button>
        </div>
      </div>

      {/* Version Timeline */}
      <div className="space-y-4">
        {versions.length === 0 ? (
          <Card>
            <CardBody className="text-center py-12">
              <FileText size={48} className="mx-auto text-[var(--color-text-tertiary)] mb-4" />
              <p className="text-lg text-[var(--color-text-secondary)]">No versions found</p>
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
                        <span>Created by {version.created_by_name || 'Unknown'}</span>
                      </div>
                      <div className="flex items-center gap-1">
                        <Calendar size={14} />
                        <span>{new Date(version.created_at).toLocaleDateString()}</span>
                      </div>
                      {version.effective_date && (
                        <div className="flex items-center gap-1">
                          <Clock size={14} />
                          <span>Effective: {new Date(version.effective_date).toLocaleDateString()}</span>
                        </div>
                      )}
                      {version.published_at && (
                        <div className="flex items-center gap-1">
                          <CheckCircle2 size={14} />
                          <span>Published: {new Date(version.published_at).toLocaleDateString()}</span>
                        </div>
                      )}
                    </div>

                    {version.summary_of_changes && (
                      <div className="mt-3 p-3 bg-[var(--color-surface)] rounded-lg">
                        <p className="text-sm font-medium mb-1">Summary of Changes:</p>
                        <p className="text-sm text-[var(--color-text-secondary)]">
                          {version.summary_of_changes}
                        </p>
                      </div>
                    )}
                  </div>
                </div>

                <div className="flex gap-2">
                  {version.is_draft && (
                    <Tooltip content="Publish this version">
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
                        Publish
                      </Button>
                    </Tooltip>
                  )}

                  {!version.is_draft && (
                    <Tooltip content="Notify users about this version">
                      <Button
                        size="sm"
                        color="primary"
                        variant="flat"
                        startContent={<Send size={16} />}
                        onPress={() => openNotifyModal(version)}
                      >
                        Notify
                      </Button>
                    </Tooltip>
                  )}

                  {index < versions.length - 1 && (
                    <Tooltip content="Compare with previous version">
                      <Button
                        size="sm"
                        variant="bordered"
                        startContent={<GitCompare size={16} />}
                        onPress={() => openCompareModal(version, versions[index + 1])}
                      >
                        Compare
                      </Button>
                    </Tooltip>
                  )}

                  <Tooltip content="View full content">
                    <Button
                      size="sm"
                      variant="bordered"
                      isIconOnly
                      onPress={() => {
                        // TODO: Navigate to view page or show in modal
                        // View feature placeholder
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
              <ModalHeader>Publish Version</ModalHeader>
              <ModalBody>
                <div className="space-y-4">
                  <div className="flex items-start gap-3 p-3 bg-warning-50 dark:bg-warning-900/20 rounded-lg">
                    <AlertCircle size={20} className="text-warning flex-shrink-0 mt-0.5" />
                    <div className="text-sm">
                      <p className="font-medium mb-1">This will:</p>
                      <ul className="list-disc list-inside space-y-1 text-[var(--color-text-secondary)]">
                        <li>Set this version as the current version</li>
                        <li>Mark all other versions as non-current</li>
                        <li>Users who accepted old versions will need to re-accept</li>
                        <li>Sync with the GDPR consent system</li>
                      </ul>
                    </div>
                  </div>
                  <p>Are you sure you want to publish version {selectedVersion?.version_number}?</p>
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
                  Publish Version
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
              <ModalHeader>Send Notification</ModalHeader>
              <ModalBody>
                <div className="space-y-4">
                  <p className="text-sm text-[var(--color-text-secondary)]">
                    Choose who should receive email notifications about version {selectedVersion?.version_number}:
                  </p>

                  <div className="space-y-2">
                    <label className="flex items-center gap-3 p-3 border rounded-lg cursor-pointer hover:bg-[var(--color-surface)]">
                      <input
                        type="radio"
                        name="notify-target"
                        value="non_accepted"
                        checked={notifyTarget === 'non_accepted'}
                        onChange={(e) => setNotifyTarget(e.target.value as 'all' | 'non_accepted')}
                        className="w-4 h-4"
                      />
                      <div className="flex-1">
                        <p className="font-medium">Non-accepted users only</p>
                        <p className="text-sm text-[var(--color-text-secondary)]">
                          {pendingCount > 0 ? `${pendingCount} users` : 'Loading...'}
                        </p>
                      </div>
                    </label>

                    <label className="flex items-center gap-3 p-3 border rounded-lg cursor-pointer hover:bg-[var(--color-surface)]">
                      <input
                        type="radio"
                        name="notify-target"
                        value="all"
                        checked={notifyTarget === 'all'}
                        onChange={(e) => setNotifyTarget(e.target.value as 'all' | 'non_accepted')}
                        className="w-4 h-4"
                      />
                      <div className="flex-1">
                        <p className="font-medium">All active users</p>
                        <p className="text-sm text-[var(--color-text-secondary)]">
                          Send to everyone (may be redundant)
                        </p>
                      </div>
                    </label>
                  </div>
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
                  Send Notification
                </Button>
              </ModalFooter>
            </>
          )}
        </ModalContent>
      </Modal>
    </div>
  );
}
