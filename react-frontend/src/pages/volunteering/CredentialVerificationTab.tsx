// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * CredentialVerificationTab - Manage volunteer credentials/certifications (V5)
 *
 * Upload, view, and track status of volunteer credentials such as
 * Police Check, First Aid, Background Check, Safeguarding, etc.
 */

import { useState, useEffect, useCallback, useRef } from 'react';
import { motion } from 'framer-motion';
import {
  Button,
  Input,
  Chip,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Select,
  SelectItem,
  useDisclosure,
} from '@heroui/react';
import {
  ShieldCheck,
  Upload,
  FileText,
  Clock,
  AlertTriangle,
  RefreshCw,
  CheckCircle,
  XCircle,
  Hourglass,
  Calendar,
  Trash2,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { EmptyState } from '@/components/feedback';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';

/* ───────────────────────── Types ───────────────────────── */

interface Credential {
  id: number;
  type: string;
  type_label: string;
  document_name: string;
  upload_date: string;
  expiry_date: string | null;
  status: 'pending' | 'verified' | 'expired' | 'rejected';
  rejection_reason: string | null;
}

/* ───────────────────────── Credential Type Options ───────────────────────── */

const CREDENTIAL_TYPE_KEYS = [
  'police_check',
  'first_aid',
  'background_check',
  'safeguarding',
  'manual_handling',
  'food_hygiene',
  'driving_licence',
  'professional_registration',
  'other',
] as const;

/* ───────────────────────── Status Helpers ───────────────────────── */

function getStatusColor(status: string): 'warning' | 'success' | 'danger' | 'default' {
  switch (status) {
    case 'pending': return 'warning';
    case 'verified': return 'success';
    case 'expired': return 'danger';
    case 'rejected': return 'danger';
    default: return 'default';
  }
}

function getStatusIcon(status: string) {
  switch (status) {
    case 'pending': return <Hourglass className="w-3 h-3" />;
    case 'verified': return <CheckCircle className="w-3 h-3" />;
    case 'expired': return <Clock className="w-3 h-3" />;
    case 'rejected': return <XCircle className="w-3 h-3" />;
    default: return <Hourglass className="w-3 h-3" />;
  }
}

function getStatusLabel(status: string, t: (key: string, defaultValue: string) => string): string {
  switch (status) {
    case 'pending': return t('credentials.status_pending', 'Pending Review');
    case 'verified': return t('credentials.status_verified', 'Verified');
    case 'expired': return t('credentials.status_expired', 'Expired');
    case 'rejected': return t('credentials.status_rejected', 'Rejected');
    default: return status;
  }
}

/* ───────────────────────── Main Component ───────────────────────── */

export function CredentialVerificationTab() {
  const { t } = useTranslation('community');
  const [credentials, setCredentials] = useState<Credential[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // Upload modal state
  const { isOpen, onOpen, onClose } = useDisclosure();
  const [uploadForm, setUploadForm] = useState({
    type: '',
    expiry_date: '',
  });
  const [selectedFile, setSelectedFile] = useState<File | null>(null);
  const [isUploading, setIsUploading] = useState(false);
  const [uploadError, setUploadError] = useState<string | null>(null);
  const fileInputRef = useRef<HTMLInputElement>(null);

  const load = useCallback(async () => {
    try {
      setIsLoading(true);
      setError(null);

      const response = await api.get<{ credentials?: Credential[] }>('/v2/volunteering/credentials');

      if (response.success && response.data) {
        const payload = response.data as { credentials?: Credential[] } | Credential[];
        const items = Array.isArray(payload) ? payload : (payload.credentials ?? []);
        setCredentials(items);
      } else {
        setError(t('credentials.load_failed', 'Failed to load credentials.'));
      }
    } catch (err) {
      logError('Failed to load credentials', err);
      setError(t('credentials.load_error', 'Unable to load credentials. Please try again.'));
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0] ?? null;
    if (file) {
      // Validate file size (10MB max)
      if (file.size > 10 * 1024 * 1024) {
        setUploadError(t('credentials.file_too_large', 'File size must be under 10MB.'));
        setSelectedFile(null);
        return;
      }
      // Validate file type
      const allowed = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];
      if (!allowed.includes(file.type)) {
        setUploadError(t('credentials.invalid_file_type', 'Please upload a PDF or image file (JPG, PNG, WebP).'));
        setSelectedFile(null);
        return;
      }
      setUploadError(null);
      setSelectedFile(file);
    }
  };

  const handleUpload = async () => {
    if (!uploadForm.type || !selectedFile) return;

    try {
      setIsUploading(true);
      setUploadError(null);

      const formData = new FormData();
      formData.append('credential_type', uploadForm.type);
      formData.append('file', selectedFile);
      if (uploadForm.expiry_date) {
        formData.append('expires_at', uploadForm.expiry_date);
      }

      const response = await api.upload('/v2/volunteering/credentials', formData);

      if (response.success) {
        onClose();
        resetUploadForm();
        load();
      } else {
        setUploadError(t('credentials.upload_failed', 'Failed to upload credential. Please try again.'));
      }
    } catch (err) {
      logError('Failed to upload credential', err);
      setUploadError(t('credentials.upload_error', 'An error occurred while uploading. Please try again.'));
    } finally {
      setIsUploading(false);
    }
  };

  const resetUploadForm = () => {
    setUploadForm({ type: '', expiry_date: '' });
    setSelectedFile(null);
    setUploadError(null);
    if (fileInputRef.current) {
      fileInputRef.current.value = '';
    }
  };

  const handleModalClose = () => {
    onClose();
    resetUploadForm();
  };

  const containerVariants = {
    hidden: { opacity: 0 },
    visible: { opacity: 1, transition: { staggerChildren: 0.05 } },
  };

  const itemVariants = {
    hidden: { opacity: 0, y: 20 },
    visible: { opacity: 1, y: 0 },
  };

  // Separate credentials by status for summary
  const verified = credentials.filter((c) => c.status === 'verified');
  const pending = credentials.filter((c) => c.status === 'pending');
  const expiring = credentials.filter((c) => {
    if (!c.expiry_date || c.status !== 'verified') return false;
    const expiryDate = new Date(c.expiry_date);
    const now = new Date();
    const thirtyDaysFromNow = new Date(now.getTime() + 30 * 24 * 60 * 60 * 1000);
    return expiryDate <= thirtyDaysFromNow && expiryDate > now;
  });

  return (
    <div className="space-y-4">
      {/* Header */}
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-2">
          <ShieldCheck className="w-5 h-5 text-emerald-400" aria-hidden="true" />
          <h2 className="text-lg font-semibold text-theme-primary">{t('credentials.heading', 'Credential Verification')}</h2>
        </div>
        <Button
          size="sm"
          className="bg-gradient-to-r from-rose-500 to-pink-600 text-white"
          startContent={<Upload className="w-4 h-4" aria-hidden="true" />}
          onPress={onOpen}
        >
          {t('credentials.upload_new', 'Upload New Credential')}
        </Button>
      </div>

      {/* Error */}
      {error && !isLoading && (
        <GlassCard className="p-8 text-center">
          <AlertTriangle className="w-12 h-12 text-amber-500 mx-auto mb-4" aria-hidden="true" />
          <p className="text-theme-muted mb-4">{error}</p>
          <Button
            className="bg-gradient-to-r from-rose-500 to-pink-600 text-white"
            startContent={<RefreshCw className="w-4 h-4" aria-hidden="true" />}
            onPress={load}
          >
            {t('credentials.try_again', 'Try Again')}
          </Button>
        </GlassCard>
      )}

      {/* Loading */}
      {!error && isLoading && (
        <div className="space-y-4">
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
            {[1, 2, 3].map((i) => (
              <GlassCard key={i} className="p-5 animate-pulse">
                <div className="h-8 bg-theme-hover rounded w-1/2 mb-2" />
                <div className="h-3 bg-theme-hover rounded w-3/4" />
              </GlassCard>
            ))}
          </div>
          {[1, 2].map((i) => (
            <GlassCard key={i} className="p-5 animate-pulse">
              <div className="h-5 bg-theme-hover rounded w-1/3 mb-3" />
              <div className="h-3 bg-theme-hover rounded w-2/3 mb-3" />
              <div className="h-3 bg-theme-hover rounded w-1/4" />
            </GlassCard>
          ))}
        </div>
      )}

      {/* Empty State */}
      {!error && !isLoading && credentials.length === 0 && (
        <EmptyState
          icon={<ShieldCheck className="w-12 h-12" aria-hidden="true" />}
          title={t('credentials.no_credentials_title', 'No credentials uploaded')}
          description={t('credentials.no_credentials_desc', 'Upload your volunteer credentials to get them verified and boost your profile.')}
          action={
            <Button
              className="bg-gradient-to-r from-rose-500 to-pink-600 text-white"
              startContent={<Upload className="w-4 h-4" aria-hidden="true" />}
              onPress={onOpen}
            >
              {t('credentials.upload_new', 'Upload New Credential')}
            </Button>
          }
        />
      )}

      {/* Dashboard Content */}
      {!error && !isLoading && credentials.length > 0 && (
        <motion.div
          variants={containerVariants}
          initial="hidden"
          animate="visible"
          className="space-y-6"
        >
          {/* Summary Cards */}
          <motion.div variants={itemVariants} className="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <GlassCard className="p-5">
              <div className="flex items-center gap-3">
                <div className="w-10 h-10 rounded-xl bg-emerald-500/10 flex items-center justify-center">
                  <CheckCircle className="w-5 h-5 text-emerald-400" aria-hidden="true" />
                </div>
                <div>
                  <p className="text-2xl font-bold text-theme-primary">{verified.length}</p>
                  <p className="text-xs text-theme-muted">{t('credentials.status_verified', 'Verified')}</p>
                </div>
              </div>
            </GlassCard>

            <GlassCard className="p-5">
              <div className="flex items-center gap-3">
                <div className="w-10 h-10 rounded-xl bg-amber-500/10 flex items-center justify-center">
                  <Hourglass className="w-5 h-5 text-amber-400" aria-hidden="true" />
                </div>
                <div>
                  <p className="text-2xl font-bold text-theme-primary">{pending.length}</p>
                  <p className="text-xs text-theme-muted">{t('credentials.status_pending', 'Pending Review')}</p>
                </div>
              </div>
            </GlassCard>

            <GlassCard className="p-5">
              <div className="flex items-center gap-3">
                <div className="w-10 h-10 rounded-xl bg-rose-500/10 flex items-center justify-center">
                  <AlertTriangle className="w-5 h-5 text-rose-400" aria-hidden="true" />
                </div>
                <div>
                  <p className="text-2xl font-bold text-theme-primary">{expiring.length}</p>
                  <p className="text-xs text-theme-muted">{t('credentials.expiring_soon', 'Expiring Soon')}</p>
                </div>
              </div>
            </GlassCard>
          </motion.div>

          {/* Expiring Soon Warning */}
          {expiring.length > 0 && (
            <motion.div variants={itemVariants}>
              <GlassCard className="p-4 border-l-4 border-amber-500">
                <div className="flex items-start gap-3">
                  <AlertTriangle className="w-5 h-5 text-amber-400 flex-shrink-0 mt-0.5" aria-hidden="true" />
                  <div>
                    <p className="text-sm font-medium text-theme-primary">{t('credentials.expiring_soon_title', 'Credentials Expiring Soon')}</p>
                    <p className="text-sm text-theme-muted">
                      {t('credentials.expiring_soon_desc', '{{count}} credential(s) will expire within 30 days. Consider renewing them to maintain your verified status.', { count: expiring.length })}
                    </p>
                  </div>
                </div>
              </GlassCard>
            </motion.div>
          )}

          {/* Credentials List */}
          {credentials.map((credential) => (
            <motion.div key={credential.id} variants={itemVariants}>
              <GlassCard className="p-5">
                <div className="flex items-start justify-between gap-4">
                  <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2 mb-2 flex-wrap">
                      <FileText className="w-5 h-5 text-indigo-400 flex-shrink-0" aria-hidden="true" />
                      <h3 className="font-semibold text-theme-primary">{credential.type_label}</h3>
                      <Chip
                        size="sm"
                        color={getStatusColor(credential.status)}
                        variant="flat"
                        startContent={getStatusIcon(credential.status)}
                      >
                        {getStatusLabel(credential.status, t)}
                      </Chip>
                    </div>

                    <div className="flex flex-wrap items-center gap-3 text-xs text-theme-subtle mb-2">
                      <span className="flex items-center gap-1">
                        <FileText className="w-3 h-3" aria-hidden="true" />
                        {credential.document_name}
                      </span>
                      <span className="flex items-center gap-1">
                        <Calendar className="w-3 h-3" aria-hidden="true" />
                        {t('credentials.uploaded', 'Uploaded')} {new Date(credential.upload_date).toLocaleDateString()}
                      </span>
                      {credential.expiry_date && (
                        <span className="flex items-center gap-1">
                          <Clock className="w-3 h-3" aria-hidden="true" />
                          {t('credentials.expires', 'Expires')} {new Date(credential.expiry_date).toLocaleDateString()}
                        </span>
                      )}
                    </div>

                    {credential.status === 'rejected' && credential.rejection_reason && (
                      <div className="mt-2 p-2 rounded-lg bg-rose-500/10">
                        <p className="text-xs text-rose-400">
                          <strong>{t('credentials.reason', 'Reason')}:</strong> {credential.rejection_reason}
                        </p>
                      </div>
                    )}
                  </div>

                  {/* Actions */}
                  <div className="flex flex-col gap-2 flex-shrink-0">
                    {(credential.status === 'expired' || credential.status === 'rejected') && (
                      <Button
                        size="sm"
                        className="bg-gradient-to-r from-rose-500 to-pink-600 text-white"
                        startContent={<Upload className="w-4 h-4" aria-hidden="true" />}
                        onPress={() => {
                          setUploadForm((prev) => ({ ...prev, type: credential.type }));
                          onOpen();
                        }}
                      >
                        {t('credentials.re_upload', 'Re-upload')}
                      </Button>
                    )}
                  </div>
                </div>
              </GlassCard>
            </motion.div>
          ))}
        </motion.div>
      )}

      {/* Upload Credential Modal */}
      <Modal isOpen={isOpen} onClose={handleModalClose} size="lg" classNames={{
        base: 'bg-content1 border border-theme-default',
      }}>
        <ModalContent>
          <ModalHeader className="text-theme-primary">
            <div className="flex items-center gap-2">
              <Upload className="w-5 h-5 text-rose-400" aria-hidden="true" />
              {t('credentials.upload_new', 'Upload New Credential')}
            </div>
          </ModalHeader>
          <ModalBody className="space-y-4">
            <p className="text-sm text-theme-muted">
              {t('credentials.upload_instructions', 'Upload a document to verify your credential. Accepted formats: PDF, JPG, PNG, WebP (max 10MB).')}
            </p>

            {/* Credential Type */}
            <Select
              label="Credential Type"
              placeholder="Select credential type"
              selectedKeys={uploadForm.type ? [uploadForm.type] : []}
              onChange={(e) => setUploadForm((prev) => ({ ...prev, type: e.target.value }))}
              isRequired
              classNames={{
                trigger: 'bg-theme-elevated border-theme-default',
                value: 'text-theme-primary',
              }}
              startContent={<ShieldCheck className="w-4 h-4 text-theme-subtle" aria-hidden="true" />}
            >
              {CREDENTIAL_TYPE_KEYS.map((key) => (
                <SelectItem key={key}>{t(`credentials.type_${key}`, key)}</SelectItem>
              ))}
            </Select>

            {/* File Upload */}
            <div>
              <label className="block text-sm font-medium text-theme-primary mb-2">
                Document <span className="text-rose-400">*</span>
              </label>
              <div
                className={`relative overflow-hidden border-2 border-dashed rounded-xl p-6 text-center transition-colors ${
                  selectedFile
                    ? 'border-emerald-500/50 bg-emerald-500/5'
                    : 'border-theme-default bg-theme-elevated hover:border-rose-500/50'
                }`}
              >
                {selectedFile ? (
                  <div className="flex items-center justify-center gap-3">
                    <FileText className="w-8 h-8 text-emerald-400" aria-hidden="true" />
                    <div className="text-left">
                      <p className="text-sm font-medium text-theme-primary">{selectedFile.name}</p>
                      <p className="text-xs text-theme-muted">
                        {(selectedFile.size / 1024 / 1024).toFixed(2)} MB
                      </p>
                    </div>
                    <Button
                      size="sm"
                      isIconOnly
                      variant="flat"
                      className="text-theme-muted"
                      onPress={() => {
                        setSelectedFile(null);
                        if (fileInputRef.current) fileInputRef.current.value = '';
                      }}
                      aria-label="Remove selected file"
                    >
                      <Trash2 className="w-4 h-4" />
                    </Button>
                  </div>
                ) : (
                  <div>
                    <Upload className="w-8 h-8 text-theme-muted mx-auto mb-2" aria-hidden="true" />
                    <p className="text-sm text-theme-muted mb-2">{t('credentials.click_to_browse', 'Click to browse or drag and drop')}</p>
                    <p className="text-xs text-theme-subtle">{t('credentials.file_types', 'PDF, JPG, PNG, or WebP up to 10MB')}</p>
                  </div>
                )}
                <input
                  ref={fileInputRef}
                  type="file"
                  accept=".pdf,.jpg,.jpeg,.png,.webp"
                  onChange={handleFileChange}
                  className={selectedFile ? 'hidden' : 'absolute inset-0 w-full h-full opacity-0 cursor-pointer'}
                  aria-label="Upload credential document"
                />
              </div>
            </div>

            {/* Expiry Date */}
            <Input
              type="date"
              label="Expiry Date (optional)"
              placeholder="Leave blank if no expiry"
              value={uploadForm.expiry_date}
              onChange={(e) => setUploadForm((prev) => ({ ...prev, expiry_date: e.target.value }))}
              classNames={{
                input: 'bg-transparent text-theme-primary',
                inputWrapper: 'bg-theme-elevated border-theme-default',
              }}
            />

            {/* Upload Error */}
            {uploadError && (
              <div className="p-3 rounded-lg bg-rose-500/10 border border-rose-500/30">
                <p className="text-sm text-rose-400">{uploadError}</p>
              </div>
            )}
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={handleModalClose} className="text-theme-muted">{t('credentials.cancel', 'Cancel')}</Button>
            <Button
              className="bg-gradient-to-r from-rose-500 to-pink-600 text-white"
              onPress={handleUpload}
              isLoading={isUploading}
              isDisabled={!uploadForm.type || !selectedFile}
              startContent={!isUploading ? <Upload className="w-4 h-4" aria-hidden="true" /> : undefined}
            >
              {t('credentials.upload_credential', 'Upload Credential')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}

export default CredentialVerificationTab;
