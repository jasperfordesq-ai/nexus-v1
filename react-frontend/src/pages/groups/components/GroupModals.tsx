// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * GroupModals — all dialog overlays for GroupDetailPage:
 * - NewDiscussionModal
 * - GroupSettingsModal
 * - GroupLeaveModal
 * - GroupDeleteModal
 * - GroupInviteModal
 * - GroupReportModal
 */

import { lazy, Suspense } from 'react';
import { useTranslation } from 'react-i18next';
import {
  Button,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Input,
  Textarea,
  Switch,
  Spinner,
} from '@heroui/react';
import MessageSquare from 'lucide-react/icons/message-square';
import Settings from 'lucide-react/icons/settings';
import Lock from 'lucide-react/icons/lock';
import Globe from 'lucide-react/icons/globe';
import UserMinus from 'lucide-react/icons/user-minus';
import UserPlus from 'lucide-react/icons/user-plus';
import Trash2 from 'lucide-react/icons/trash-2';
import AlertCircle from 'lucide-react/icons/circle-alert';
import FileText from 'lucide-react/icons/file-text';
import Upload from 'lucide-react/icons/upload';
import Image from 'lucide-react/icons/image';
import MapPin from 'lucide-react/icons/map-pin';
import Flag from 'lucide-react/icons/flag';
import { ErrorBoundary } from '@/components/feedback';
import type { Group } from '@/types/api';

const RichTextEditor = lazy(() => import('@/admin/components/RichTextEditor'));

// ─────────────────────────────────────────────────────────────────────────────
// New Discussion Modal
// ─────────────────────────────────────────────────────────────────────────────

interface NewDiscussionModalProps {
  isOpen: boolean;
  onOpenChange: (open: boolean) => void;
  newDiscussionTitle: string;
  newDiscussionContent: string;
  creatingDiscussion: boolean;
  onTitleChange: (value: string) => void;
  onContentChange: (value: string) => void;
  onSubmit: () => void;
}

export function NewDiscussionModal({
  isOpen,
  onOpenChange,
  newDiscussionTitle,
  newDiscussionContent,
  creatingDiscussion,
  onTitleChange,
  onContentChange,
  onSubmit,
}: NewDiscussionModalProps) {
  const { t } = useTranslation('groups');

  return (
    <Modal
      isOpen={isOpen}
      onOpenChange={onOpenChange}
      classNames={{
        base: 'bg-content1 border border-theme-default',
        header: 'border-b border-theme-default',
        footer: 'border-t border-theme-default',
      }}
    >
      <ModalContent>
        {(onClose) => (
          <>
            <ModalHeader className="text-theme-primary flex items-center gap-2">
              <MessageSquare className="w-5 h-5 text-purple-400" aria-hidden="true" />
              {t('detail.new_discussion_modal_title')}
            </ModalHeader>
            <ModalBody className="gap-4">
              <div>
                <Input
                  label={t('detail.discussion_title_label')}
                  placeholder={t('detail.discussion_title_placeholder')}
                  value={newDiscussionTitle}
                  maxLength={255}
                  onChange={(e) => onTitleChange(e.target.value)}
                  startContent={<FileText className="w-4 h-4 text-theme-subtle" aria-hidden="true" />}
                  classNames={{
                    input: 'bg-transparent text-theme-primary',
                    inputWrapper: 'bg-theme-elevated border-theme-default',
                    label: 'text-theme-muted',
                  }}
                />
                {newDiscussionTitle.length > Math.floor(255 * 0.8) && (
                  <p className={`text-xs mt-0.5 text-right ${newDiscussionTitle.length >= 255 ? 'text-danger' : 'text-default-400'}`}>
                    {newDiscussionTitle.length}/255
                  </p>
                )}
              </div>
              <div>
                <label className="text-sm text-theme-muted mb-1 block">
                  {t('detail.discussion_content_label')}
                </label>
                <ErrorBoundary
                  fallback={
                    <Textarea
                      placeholder={t('detail.discussion_content_placeholder')}
                      minRows={4}
                      value={newDiscussionContent}
                      onChange={(e) => onContentChange(e.target.value)}
                      classNames={{ input: 'bg-transparent text-theme-primary', inputWrapper: 'bg-theme-elevated border-theme-default' }}
                    />
                  }
                >
                  <Suspense fallback={<div className="flex justify-center py-4"><Spinner size="sm" /></div>}>
                    <RichTextEditor
                      value={newDiscussionContent}
                      onChange={onContentChange}
                      placeholder={t('detail.discussion_content_placeholder')}
                    />
                  </Suspense>
                </ErrorBoundary>
              </div>
            </ModalBody>
            <ModalFooter>
              <Button variant="flat" className="bg-theme-elevated text-theme-primary" onPress={onClose}>
                {t('detail.cancel')}
              </Button>
              <Button
                className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                isLoading={creatingDiscussion}
                isDisabled={!newDiscussionTitle.trim() || !newDiscussionContent.trim()}
                onPress={onSubmit}
              >
                {t('detail.create_discussion_btn')}
              </Button>
            </ModalFooter>
          </>
        )}
      </ModalContent>
    </Modal>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Settings Modal
// ─────────────────────────────────────────────────────────────────────────────

interface GroupSettingsModalProps {
  isOpen: boolean;
  onOpenChange: (open: boolean) => void;
  group: Group;
  settingsName: string;
  settingsDescription: string;
  settingsPrivate: boolean;
  settingsLocation: string;
  uploadingImage: boolean;
  savingSettings: boolean;
  onNameChange: (value: string) => void;
  onDescriptionChange: (value: string) => void;
  onPrivateChange: (value: boolean) => void;
  onLocationChange: (value: string) => void;
  onImageUpload: (e: React.ChangeEvent<HTMLInputElement>, type: 'avatar' | 'cover') => void;
  onSave: () => void;
}

export function GroupSettingsModal({
  isOpen,
  onOpenChange,
  group,
  settingsName,
  settingsDescription,
  settingsPrivate,
  settingsLocation,
  uploadingImage,
  savingSettings,
  onNameChange,
  onDescriptionChange,
  onPrivateChange,
  onLocationChange,
  onImageUpload,
  onSave,
}: GroupSettingsModalProps) {
  const { t } = useTranslation('groups');

  return (
    <Modal
      isOpen={isOpen}
      onOpenChange={onOpenChange}
      size="lg"
      classNames={{
        base: 'bg-content1 border border-theme-default',
        header: 'border-b border-theme-default',
        footer: 'border-t border-theme-default',
      }}
    >
      <ModalContent>
        {(onClose) => (
          <>
            <ModalHeader className="text-theme-primary flex items-center gap-2">
              <Settings className="w-5 h-5 text-purple-400" aria-hidden="true" />
              {t('detail.settings_modal_title')}
            </ModalHeader>
            <ModalBody className="gap-4">
              <div>
                <Input
                  label={t('detail.settings_name_label')}
                  placeholder={t('detail.settings_name_placeholder')}
                  value={settingsName}
                  maxLength={255}
                  onChange={(e) => onNameChange(e.target.value)}
                  startContent={<FileText className="w-4 h-4 text-theme-subtle" aria-hidden="true" />}
                  classNames={{
                    input: 'bg-transparent text-theme-primary',
                    inputWrapper: 'bg-theme-elevated border-theme-default',
                    label: 'text-theme-muted',
                  }}
                />
                {settingsName.length > Math.floor(255 * 0.8) && (
                  <p className={`text-xs mt-0.5 text-right ${settingsName.length >= 255 ? 'text-danger' : 'text-default-400'}`}>
                    {settingsName.length}/255
                  </p>
                )}
              </div>
              <div>
                <Textarea
                  label={t('detail.settings_desc_label')}
                  placeholder={t('detail.settings_desc_placeholder')}
                  value={settingsDescription}
                  maxLength={2000}
                  onChange={(e) => onDescriptionChange(e.target.value)}
                  minRows={3}
                  classNames={{
                    input: 'bg-transparent text-theme-primary',
                    inputWrapper: 'bg-theme-elevated border-theme-default',
                    label: 'text-theme-muted',
                  }}
                />
                {settingsDescription.length > Math.floor(2000 * 0.8) && (
                  <p className={`text-xs mt-0.5 text-right ${settingsDescription.length >= 2000 ? 'text-danger' : 'text-default-400'}`}>
                    {settingsDescription.length}/2000
                  </p>
                )}
              </div>
              <div>
                <Input
                  label={t('detail.settings_location_label')}
                  placeholder={t('detail.settings_location_placeholder')}
                  value={settingsLocation}
                  maxLength={255}
                  onChange={(e) => onLocationChange(e.target.value)}
                  startContent={<MapPin className="w-4 h-4 text-theme-subtle" aria-hidden="true" />}
                  classNames={{
                    input: 'bg-transparent text-theme-primary',
                    inputWrapper: 'bg-theme-elevated border-theme-default',
                    label: 'text-theme-muted',
                  }}
                />
                {settingsLocation.length > Math.floor(255 * 0.8) && (
                  <p className={`text-xs mt-0.5 text-right ${settingsLocation.length >= 255 ? 'text-danger' : 'text-default-400'}`}>
                    {settingsLocation.length}/255
                  </p>
                )}
              </div>
              {/* Images */}
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div className="p-3 rounded-lg bg-theme-elevated border border-theme-default">
                  <p className="text-sm font-medium text-theme-primary mb-2 flex items-center gap-1.5">
                    <Image className="w-4 h-4" aria-hidden="true" />
                    {t('detail.settings_image_label')}
                  </p>
                  {group?.image_url && (
                    <img src={group.image_url} alt={t('detail.image_alt_group', 'Group')} className="w-12 h-12 rounded-full object-cover mb-2" width={48} height={48} loading="lazy" />
                  )}
                  <label className="flex items-center gap-1.5 text-xs text-primary cursor-pointer hover:underline">
                    <Upload className="w-3 h-3" aria-hidden="true" />
                    {uploadingImage ? t('detail.uploading') : t('detail.upload_image')}
                    <input
                      type="file"
                      accept="image/*"
                      className="hidden"
                      disabled={uploadingImage}
                      onChange={(e) => onImageUpload(e, 'avatar')}
                    />
                  </label>
                </div>
                <div className="p-3 rounded-lg bg-theme-elevated border border-theme-default">
                  <p className="text-sm font-medium text-theme-primary mb-2 flex items-center gap-1.5">
                    <Image className="w-4 h-4" aria-hidden="true" />
                    {t('detail.settings_cover_label')}
                  </p>
                  {group?.cover_image_url && (
                    <img src={group.cover_image_url} alt={t('detail.image_alt_cover', 'Cover')} className="w-full h-10 rounded object-cover mb-2" width={400} height={40} loading="lazy" />
                  )}
                  <label className="flex items-center gap-1.5 text-xs text-primary cursor-pointer hover:underline">
                    <Upload className="w-3 h-3" aria-hidden="true" />
                    {uploadingImage ? t('detail.uploading') : t('detail.upload_cover')}
                    <input
                      type="file"
                      accept="image/*"
                      className="hidden"
                      disabled={uploadingImage}
                      onChange={(e) => onImageUpload(e, 'cover')}
                    />
                  </label>
                </div>
              </div>
              <div className="p-4 rounded-lg bg-theme-elevated border border-theme-default">
                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-3">
                    {settingsPrivate ? (
                      <Lock className="w-5 h-5 text-amber-600 dark:text-amber-400" aria-hidden="true" />
                    ) : (
                      <Globe className="w-5 h-5 text-emerald-600 dark:text-emerald-400" aria-hidden="true" />
                    )}
                    <div>
                      <p className="font-medium text-theme-primary">
                        {settingsPrivate ? t('detail.private_group') : t('detail.public_group')}
                      </p>
                      <p className="text-sm text-theme-subtle">
                        {settingsPrivate
                          ? t('detail.private_desc')
                          : t('detail.public_desc')}
                      </p>
                    </div>
                  </div>
                  <Switch
                    aria-label={settingsPrivate ? t('detail.make_public_aria') : t('detail.make_private_aria')}
                    isSelected={settingsPrivate}
                    onValueChange={onPrivateChange}
                    classNames={{
                      wrapper: 'group-data-[selected=true]:bg-amber-500',
                    }}
                  />
                </div>
              </div>
            </ModalBody>
            <ModalFooter>
              <Button variant="flat" className="bg-theme-elevated text-theme-primary" onPress={onClose}>
                {t('detail.cancel')}
              </Button>
              <Button
                className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
                isLoading={savingSettings}
                isDisabled={!settingsName.trim()}
                onPress={onSave}
              >
                {t('detail.save_changes')}
              </Button>
            </ModalFooter>
          </>
        )}
      </ModalContent>
    </Modal>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Leave Confirmation Modal
// ─────────────────────────────────────────────────────────────────────────────

interface GroupLeaveModalProps {
  isOpen: boolean;
  onOpenChange: (open: boolean) => void;
  groupName: string;
  isLoading: boolean;
  onConfirm: () => void;
}

export function GroupLeaveModal({
  isOpen,
  onOpenChange,
  groupName,
  isLoading,
  onConfirm,
}: GroupLeaveModalProps) {
  const { t } = useTranslation('groups');

  return (
    <Modal
      isOpen={isOpen}
      onOpenChange={onOpenChange}
      classNames={{
        base: 'bg-content1 border border-theme-default',
        header: 'border-b border-theme-default',
        footer: 'border-t border-theme-default',
      }}
    >
      <ModalContent>
        {(onClose) => (
          <>
            <ModalHeader className="text-theme-primary flex items-center gap-2">
              <UserMinus className="w-5 h-5" aria-hidden="true" />
              {t('detail.leave_group_title', 'Leave Group')}
            </ModalHeader>
            <ModalBody>
              <p className="text-theme-secondary">
                {t('detail.leave_group_confirm', 'Are you sure you want to leave {{name}}? You will lose access to group discussions and files.', { name: groupName })}
              </p>
            </ModalBody>
            <ModalFooter>
              <Button variant="flat" className="bg-theme-elevated text-theme-primary" onPress={onClose}>
                {t('detail.cancel')}
              </Button>
              <Button
                color="danger"
                isLoading={isLoading}
                onPress={onConfirm}
              >
                {t('detail.leave_group')}
              </Button>
            </ModalFooter>
          </>
        )}
      </ModalContent>
    </Modal>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Delete Confirmation Modal
// ─────────────────────────────────────────────────────────────────────────────

interface GroupDeleteModalProps {
  isOpen: boolean;
  onOpenChange: (open: boolean) => void;
  groupName: string;
  isLoading: boolean;
  onConfirm: () => void;
}

export function GroupDeleteModal({
  isOpen,
  onOpenChange,
  groupName,
  isLoading,
  onConfirm,
}: GroupDeleteModalProps) {
  const { t } = useTranslation('groups');

  return (
    <Modal
      isOpen={isOpen}
      onOpenChange={onOpenChange}
      classNames={{
        base: 'bg-content1 border border-theme-default',
        header: 'border-b border-theme-default',
        footer: 'border-t border-theme-default',
      }}
    >
      <ModalContent>
        {(onClose) => (
          <>
            <ModalHeader className="text-[var(--color-error)] flex items-center gap-2">
              <Trash2 className="w-5 h-5" aria-hidden="true" />
              {t('detail.delete_modal_title')}
            </ModalHeader>
            <ModalBody>
              <div className="text-center py-4">
                <div className="w-16 h-16 mx-auto mb-4 rounded-full bg-red-500/10 flex items-center justify-center">
                  <AlertCircle className="w-8 h-8 text-[var(--color-error)]" aria-hidden="true" />
                </div>
                <p className="text-theme-primary font-medium mb-2">
                  {t('detail.delete_confirm', { name: groupName })}
                </p>
                <p className="text-sm text-theme-muted">
                  {t('detail.delete_desc')}
                </p>
              </div>
            </ModalBody>
            <ModalFooter>
              <Button variant="flat" className="bg-theme-elevated text-theme-primary" onPress={onClose}>
                {t('detail.cancel')}
              </Button>
              <Button
                className="bg-red-500 text-white"
                isLoading={isLoading}
                onPress={onConfirm}
                startContent={<Trash2 className="w-4 h-4" aria-hidden="true" />}
              >
                {t('detail.delete_btn')}
              </Button>
            </ModalFooter>
          </>
        )}
      </ModalContent>
    </Modal>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Invite Modal
// ─────────────────────────────────────────────────────────────────────────────

interface GroupInviteModalProps {
  isOpen: boolean;
  onOpenChange: (open: boolean) => void;
  inviteLink: string | null;
  inviteEmails: string;
  inviteMessage: string;
  sendingInvites: boolean;
  onGenerateLink: () => void;
  onEmailsChange: (value: string) => void;
  onMessageChange: (value: string) => void;
  onSendInvites: () => void;
  onCopyLink: (link: string) => void;
}

export function GroupInviteModal({
  isOpen,
  onOpenChange,
  inviteLink,
  inviteEmails,
  inviteMessage,
  sendingInvites,
  onGenerateLink,
  onEmailsChange,
  onMessageChange,
  onSendInvites,
  onCopyLink,
}: GroupInviteModalProps) {
  const { t } = useTranslation('groups');

  return (
    <Modal
      isOpen={isOpen}
      onOpenChange={onOpenChange}
      classNames={{
        base: 'bg-content1 border border-theme-default',
        header: 'border-b border-theme-default',
        footer: 'border-t border-theme-default',
      }}
      size="lg"
    >
      <ModalContent>
        {(onClose) => (
          <>
            <ModalHeader className="text-theme-primary flex items-center gap-2">
              <UserPlus className="w-5 h-5 text-purple-400" aria-hidden="true" />
              {t('detail.invite_members', 'Invite Members')}
            </ModalHeader>
            <ModalBody>
              <div className="space-y-4">
                {/* Invite link */}
                <div>
                  <p className="text-sm text-theme-subtle mb-2">{t('detail.invite_link_desc', 'Share a link anyone can use to join:')}</p>
                  {inviteLink ? (
                    <div className="flex items-center gap-2">
                      <Input value={inviteLink} readOnly size="sm" className="flex-1" />
                      <Button size="sm" variant="flat" onPress={() => onCopyLink(inviteLink)}>
                        {t('detail.copy', 'Copy')}
                      </Button>
                    </div>
                  ) : (
                    <Button size="sm" variant="bordered" onPress={onGenerateLink}>
                      {t('detail.generate_link', 'Generate Invite Link')}
                    </Button>
                  )}
                </div>

                <div className="border-t border-theme-default pt-4">
                  <p className="text-sm text-theme-subtle mb-2">{t('detail.invite_email_desc', 'Or invite by email (comma-separated):')}</p>
                  <Textarea
                    placeholder={t('detail.invite_email_placeholder', 'email1@example.com, email2@example.com')}
                    value={inviteEmails}
                    onValueChange={onEmailsChange}
                    minRows={2}
                    size="sm"
                    aria-label={t('detail.invite_emails_aria', 'Email addresses to invite')}
                  />
                  <Input
                    label={t('detail.invite_message_label', 'Personal message (optional)')}
                    placeholder={t('detail.invite_message_placeholder', 'Join our group!')}
                    value={inviteMessage}
                    onValueChange={onMessageChange}
                    size="sm"
                    className="mt-2"
                  />
                </div>
              </div>
            </ModalBody>
            <ModalFooter>
              <Button variant="flat" onPress={onClose}>
                {t('detail.cancel', 'Cancel')}
              </Button>
              <Button
                color="primary"
                onPress={onSendInvites}
                isLoading={sendingInvites}
                isDisabled={!inviteEmails.trim()}
              >
                {t('detail.send_invites', 'Send Invitations')}
              </Button>
            </ModalFooter>
          </>
        )}
      </ModalContent>
    </Modal>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Report Post Modal
// ─────────────────────────────────────────────────────────────────────────────

interface GroupReportModalProps {
  isOpen: boolean;
  onClose: () => void;
  reportReason: string;
  isReporting: boolean;
  onReasonChange: (value: string) => void;
  onSubmit: () => void;
}

export function GroupReportModal({
  isOpen,
  onClose,
  reportReason,
  isReporting,
  onReasonChange,
  onSubmit,
}: GroupReportModalProps) {
  const { t } = useTranslation('groups');

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      classNames={{
        base: 'bg-[var(--glass-bg)] backdrop-blur-xl border border-[var(--glass-border)]',
        backdrop: 'bg-black/60 backdrop-blur-sm',
      }}
    >
      <ModalContent>
        <ModalHeader className="text-[var(--text-primary)]">
          <div className="flex items-center gap-3">
            <div className="w-8 h-8 rounded-lg bg-danger/10 flex items-center justify-center">
              <Flag className="w-4 h-4 text-danger" aria-hidden="true" />
            </div>
            {t('detail.report_title', 'Report Post')}
          </div>
        </ModalHeader>
        <ModalBody>
          <p className="text-sm text-[var(--text-muted)] mb-3">
            {t('detail.report_description', 'Help us understand why you are reporting this post.')}
          </p>
          <Textarea
            label={t('detail.report_reason_label', 'Reason')}
            placeholder={t('detail.report_reason_placeholder', 'Describe why this post is inappropriate...')}
            value={reportReason}
            onChange={(e) => onReasonChange(e.target.value)}
            minRows={3}
            classNames={{
              input: 'bg-transparent text-[var(--text-primary)]',
              inputWrapper: 'bg-[var(--surface-elevated)] border-[var(--border-default)]',
            }}
            autoFocus
          />
        </ModalBody>
        <ModalFooter>
          <Button
            variant="flat"
            onPress={onClose}
            className="text-[var(--text-muted)]"
          >
            {t('detail.cancel')}
          </Button>
          <Button
            color="danger"
            variant="flat"
            onPress={onSubmit}
            isLoading={isReporting}
            isDisabled={!reportReason.trim()}
            className="font-medium"
          >
            {t('detail.report_submit', 'Report')}
          </Button>
        </ModalFooter>
      </ModalContent>
    </Modal>
  );
}
