// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useRef } from 'react';
import {
  Button,
  Chip,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Textarea,
  Progress,
  Spinner,
} from '@heroui/react';
import {
  Target,
  CheckCircle,
  XCircle,
  Upload,
  FileText as FileTextIcon,
  Sparkles,
  X,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import type { QualificationResult } from './JobDetailTypes';

interface ApplyModalProps {
  isOpen: boolean;
  onOpenChange: (isOpen: boolean) => void;
  applyMessage: string;
  setApplyMessage: (v: string) => void;
  cvFile: File | null;
  setCvFile: (f: File | null) => void;
  cvParsed: { skills: string[]; summary?: string } | null;
  setCvParsed: (v: { skills: string[]; summary?: string } | null) => void;
  isSubmitting: boolean;
  savedProfile: { cv_filename?: string; cover_text?: string } | null;
  setSavedProfile: (v: { cv_filename?: string; cover_text?: string } | null) => void;
  usingSavedProfile: boolean;
  setUsingSavedProfile: (v: boolean) => void;
  onApply: () => void;
  onCvDrop: (e: React.DragEvent<HTMLDivElement>) => void;
  onCvTooBig: () => void;
}

export function ApplyModal({
  isOpen,
  onOpenChange,
  applyMessage,
  setApplyMessage,
  cvFile,
  setCvFile,
  cvParsed,
  setCvParsed,
  isSubmitting,
  savedProfile,
  setSavedProfile,
  usingSavedProfile,
  setUsingSavedProfile,
  onApply,
  onCvDrop,
  onCvTooBig,
}: ApplyModalProps) {
  const { t } = useTranslation('jobs');
  const cvInputRef = useRef<HTMLInputElement>(null);

  return (
    <Modal isOpen={isOpen} onOpenChange={(open) => {
      if (!open) {
        setApplyMessage('');
        setCvFile(null);
        setCvParsed(null);
        setUsingSavedProfile(false);
      }
      onOpenChange(open);
    }}>
      <ModalContent>
        {(onClose) => (
          <>
            <ModalHeader>{t('apply.title')}</ModalHeader>
            <ModalBody>
              <div className="space-y-4">
                {savedProfile && !usingSavedProfile && (
                  <div className="flex items-center gap-3 p-3 rounded-lg bg-primary/5 border border-primary/20 text-sm">
                    <div className="flex-1 text-theme-primary">
                      {t('saved_profile.found', 'Saved application profile found')}
                      {savedProfile.cv_filename && (
                        <span className="ml-1 text-xs text-theme-subtle">— CV: {savedProfile.cv_filename}</span>
                      )}
                    </div>
                    <Button
                      size="sm"
                      color="primary"
                      variant="flat"
                      onPress={() => {
                        if (savedProfile.cover_text) setApplyMessage(savedProfile.cover_text);
                        setUsingSavedProfile(true);
                      }}
                    >
                      {t('saved_profile.use', 'Use Saved Profile')}
                    </Button>
                    <Button
                      size="sm"
                      variant="flat"
                      className="text-theme-muted"
                      onPress={() => setSavedProfile(null)}
                    >
                      {t('saved_profile.start_fresh', 'Start Fresh')}
                    </Button>
                  </div>
                )}
                {usingSavedProfile && savedProfile?.cv_filename && (
                  <Chip size="sm" variant="flat" color="primary">
                    {t('saved_profile.cv_label', { defaultValue: 'Saved CV: {{filename}}', filename: savedProfile.cv_filename })}
                  </Chip>
                )}

                <Textarea
                  label={t('apply.message_label')}
                  placeholder={t('apply.message_placeholder')}
                  value={applyMessage}
                  onValueChange={setApplyMessage}
                  minRows={4}
                  classNames={{
                    input: 'bg-transparent text-theme-primary',
                    inputWrapper: 'bg-theme-elevated border-theme-default',
                  }}
                />

                <div className="space-y-1">
                  <label className="text-sm font-medium text-foreground">
                    {t('apply.cv_label', 'CV / Resume')}{' '}
                    <span className="text-default-400 text-xs">
                      {t('apply.cv_hint', '(optional — PDF, DOC, DOCX, max 5MB)')}
                    </span>
                  </label>
                  <div
                    className="border-2 border-dashed border-default-200 rounded-lg p-4 text-center cursor-pointer hover:border-primary transition-colors"
                    onClick={() => cvInputRef.current?.click()}
                    onDrop={onCvDrop}
                    onDragOver={(e) => e.preventDefault()}
                    role="button"
                    tabIndex={0}
                    aria-label={t('apply.cv_dropzone_aria', 'Click or drop file to upload CV')}
                    onKeyDown={(e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); cvInputRef.current?.click(); } }}
                  >
                    {cvFile ? (
                      <div className="flex items-center justify-center gap-2 text-sm text-foreground">
                        <FileTextIcon size={16} aria-hidden="true" />
                        <span>{cvFile.name}</span>
                        <span className="text-default-400">({(cvFile.size / 1024).toFixed(0)} KB)</span>
                        <Button
                          size="sm"
                          variant="light"
                          color="danger"
                          isIconOnly
                          onClick={(e) => { e.stopPropagation(); setCvFile(null); }}
                          aria-label={t('apply.cv_remove', 'Remove CV')}
                        >
                          <X size={14} aria-hidden="true" />
                        </Button>
                      </div>
                    ) : (
                      <div className="text-default-400 text-sm">
                        <Upload size={20} className="mx-auto mb-1" aria-hidden="true" />
                        {t('apply.cv_drop_prompt', 'Drop CV here or click to browse')}
                      </div>
                    )}
                  </div>
                  <input
                    ref={cvInputRef}
                    type="file"
                    accept=".pdf,.doc,.docx"
                    className="hidden"
                    onChange={(e) => {
                      const file = e.target.files?.[0];
                      if (file) {
                        if (file.size > 5 * 1024 * 1024) {
                          onCvTooBig();
                        } else {
                          setCvFile(file);
                          setCvParsed(null);
                        }
                      }
                    }}
                  />
                </div>

                {cvFile && !cvParsed && (
                  <p className="text-xs text-default-400 flex items-center gap-1 mt-1">
                    <Sparkles size={12} aria-hidden="true" />
                    {t('cv.parse', 'Skills will be extracted after submission')}
                  </p>
                )}
                {cvParsed && cvParsed.skills && cvParsed.skills.length > 0 && (
                  <div className="p-2 rounded-lg bg-secondary-50 border border-secondary-200 text-xs mt-1">
                    <div className="font-medium text-secondary-700 mb-1 flex items-center gap-1">
                      <Sparkles size={12} aria-hidden="true" />
                      {t('cv.detected', 'Skills detected from CV')}
                    </div>
                    <div className="flex flex-wrap gap-1">
                      {cvParsed.skills.map((skill) => (
                        <Chip key={skill} size="sm" variant="flat" color="secondary">{skill}</Chip>
                      ))}
                    </div>
                    {cvParsed.summary && (
                      <p className="text-default-600 mt-1 italic">{cvParsed.summary}</p>
                    )}
                  </div>
                )}
              </div>
            </ModalBody>
            <ModalFooter>
              <Button variant="flat" onPress={onClose}>
                {t('apply.cancel')}
              </Button>
              <Button
                className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                onPress={onApply}
                isLoading={isSubmitting}
              >
                {t('apply.submit')}
              </Button>
            </ModalFooter>
          </>
        )}
      </ModalContent>
    </Modal>
  );
}

interface QualificationModalProps {
  isOpen: boolean;
  onOpenChange: (isOpen: boolean) => void;
  qualification: QualificationResult | null;
  isLoading: boolean;
  hasApplied: boolean;
  vacancyStatus: string;
  onApplyOpen: () => void;
}

export function QualificationModal({
  isOpen,
  onOpenChange,
  qualification,
  isLoading,
  hasApplied,
  vacancyStatus,
  onApplyOpen,
}: QualificationModalProps) {
  const { t } = useTranslation('jobs');

  return (
    <Modal isOpen={isOpen} onOpenChange={onOpenChange} size="lg">
      <ModalContent>
        {(onClose) => (
          <>
            <ModalHeader>
              <div className="flex items-center gap-2">
                <Target className="w-5 h-5 text-primary" aria-hidden="true" />
                {t('qualified.title')}
              </div>
            </ModalHeader>
            <ModalBody>
              {isLoading ? (
                <div className="space-y-4 animate-pulse">
                  <div className="h-4 bg-theme-hover rounded w-3/4" />
                  <div className="h-8 bg-theme-hover rounded" />
                  <div className="h-4 bg-theme-hover rounded w-1/2" />
                </div>
              ) : qualification ? (
                <div className="space-y-5">
                  <div className="text-center">
                    <div className="text-4xl font-bold text-theme-primary mb-1">
                      {qualification.percentage}%
                    </div>
                    <p className="text-sm text-theme-muted">
                      {t(`qualified.level_${qualification.level}`)}
                    </p>
                    <p className="text-sm text-theme-subtle mt-1">
                      {t('qualified.matched_count', {
                        matched: qualification.total_matched,
                        total: qualification.total_required,
                      })}
                    </p>
                  </div>

                  <Progress
                    value={qualification.percentage}
                    color={
                      qualification.percentage >= 80 ? 'success' :
                      qualification.percentage >= 60 ? 'primary' :
                      qualification.percentage >= 40 ? 'warning' : 'danger'
                    }
                    className="max-w-full"
                    aria-label={t('aria.qualification_percentage', 'Qualification percentage')}
                  />

                  <div className="space-y-2">
                    {qualification.breakdown.map((item, idx) => (
                      <div
                        key={idx}
                        className={`flex items-center gap-3 p-3 rounded-lg ${
                          item.matched ? 'bg-success/5 border border-success/20' : 'bg-danger/5 border border-danger/20'
                        }`}
                      >
                        {item.matched ? (
                          <CheckCircle className="w-5 h-5 text-success flex-shrink-0" aria-hidden="true" />
                        ) : (
                          <XCircle className="w-5 h-5 text-danger flex-shrink-0" aria-hidden="true" />
                        )}
                        <span className={`text-sm ${item.matched ? 'text-success' : 'text-danger'}`}>
                          {item.skill}
                        </span>
                      </div>
                    ))}
                  </div>
                </div>
              ) : (
                <div className="flex justify-center py-8">
                  <Spinner />
                </div>
              )}
            </ModalBody>
            <ModalFooter>
              <Button variant="flat" onPress={onClose}>
                {t('apply.cancel')}
              </Button>
              {qualification && qualification.percentage > 0 && !hasApplied && vacancyStatus === 'open' && (
                <Button
                  className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                  onPress={() => {
                    onClose();
                    onApplyOpen();
                  }}
                >
                  {t('apply.button')}
                </Button>
              )}
            </ModalFooter>
          </>
        )}
      </ModalContent>
    </Modal>
  );
}

interface RenewModalProps {
  isOpen: boolean;
  onOpenChange: (isOpen: boolean) => void;
  renewDays: number;
  setRenewDays: (d: number) => void;
  isRenewing: boolean;
  onRenew: () => void;
}

export function RenewModal({
  isOpen,
  onOpenChange,
  renewDays,
  setRenewDays,
  isRenewing,
  onRenew,
}: RenewModalProps) {
  const { t } = useTranslation('jobs');

  return (
    <Modal isOpen={isOpen} onOpenChange={onOpenChange}>
      <ModalContent>
        {(onClose) => (
          <>
            <ModalHeader>{t('renew.title')}</ModalHeader>
            <ModalBody>
              <p className="text-theme-muted mb-4">{t('renew.description')}</p>
              <div className="flex gap-2">
                {[7, 14, 30, 60].map((d) => (
                  <Button
                    key={d}
                    size="sm"
                    variant={renewDays === d ? 'solid' : 'flat'}
                    color={renewDays === d ? 'primary' : 'default'}
                    onPress={() => setRenewDays(d)}
                  >
                    {d} {t('analytics.days')}
                  </Button>
                ))}
              </div>
            </ModalBody>
            <ModalFooter>
              <Button variant="flat" onPress={onClose}>
                {t('apply.cancel')}
              </Button>
              <Button
                className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                onPress={onRenew}
                isLoading={isRenewing}
              >
                {t('renew.button')}
              </Button>
            </ModalFooter>
          </>
        )}
      </ModalContent>
    </Modal>
  );
}

interface DeleteModalProps {
  isOpen: boolean;
  onOpenChange: (isOpen: boolean) => void;
  onDelete: () => void;
}

export function DeleteModal({ isOpen, onOpenChange, onDelete }: DeleteModalProps) {
  const { t } = useTranslation('jobs');

  return (
    <Modal isOpen={isOpen} onOpenChange={onOpenChange} size="sm">
      <ModalContent>
        {(onClose) => (
          <>
            <ModalHeader>{t('detail.confirm_delete_title')}</ModalHeader>
            <ModalBody>
              <p className="text-theme-muted">{t('detail.confirm_delete')}</p>
            </ModalBody>
            <ModalFooter>
              <Button variant="flat" onPress={onClose}>
                {t('apply.cancel')}
              </Button>
              <Button color="danger" onPress={onDelete}>
                {t('detail.delete')}
              </Button>
            </ModalFooter>
          </>
        )}
      </ModalContent>
    </Modal>
  );
}

interface DeclineModalProps {
  isOpen: boolean;
  titleKey: string;
  titleDefault: string;
  notesLabelKey: string;
  notesLabelDefault: string;
  notesPlaceholderKey: string;
  notesPlaceholderDefault: string;
  declineNotes: string;
  setDeclineNotes: (v: string) => void;
  isLoading: boolean;
  onClose: () => void;
  onConfirm: () => void;
}

export function DeclineModal({
  isOpen,
  titleKey,
  titleDefault,
  notesLabelKey,
  notesLabelDefault,
  notesPlaceholderKey,
  notesPlaceholderDefault,
  declineNotes,
  setDeclineNotes,
  isLoading,
  onClose,
  onConfirm,
}: DeclineModalProps) {
  const { t } = useTranslation('jobs');

  return (
    <Modal isOpen={isOpen} onClose={onClose} size="sm">
      <ModalContent>
        <ModalHeader>{t(titleKey, titleDefault)}</ModalHeader>
        <ModalBody>
          <Textarea
            label={t(notesLabelKey, notesLabelDefault)}
            placeholder={t(notesPlaceholderKey, notesPlaceholderDefault)}
            value={declineNotes}
            onValueChange={setDeclineNotes}
            minRows={3}
            classNames={{
              input: 'bg-transparent text-theme-primary',
              inputWrapper: 'bg-theme-elevated border-theme-default',
            }}
          />
        </ModalBody>
        <ModalFooter>
          <Button variant="flat" onPress={onClose}>
            {t('apply.cancel')}
          </Button>
          <Button color="danger" isLoading={isLoading} onPress={onConfirm}>
            {t(titleKey, titleDefault)}
          </Button>
        </ModalFooter>
      </ModalContent>
    </Modal>
  );
}
