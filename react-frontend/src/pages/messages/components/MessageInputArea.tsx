// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import type { RefObject, ChangeEvent, FormEvent } from 'react';
import { useTranslation } from 'react-i18next';
import { Button, Textarea } from '@heroui/react';
import { Send, Mic, Square, Paperclip, X, FileText, AlertTriangle } from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import { useTenant } from '@/contexts';
import { VoiceMessagePlayer } from './VoiceMessagePlayer';

export interface AttachmentPreview {
  file: File;
  preview: string;
  type: 'image' | 'file';
}

export interface MessageInputAreaProps {
  isDirectMessagingEnabled: boolean;
  messagingRestriction: {
    messaging_disabled: boolean;
    under_monitoring: boolean;
    restriction_reason: string | null;
  } | null;
  // Text message state
  newMessage: string;
  onNewMessageChange: (value: string) => void;
  onSendMessage: (e: FormEvent) => void;
  isSending: boolean;
  // Typing indicator
  onTypingIndicator: (value: string) => void;
  onBlurTypingStop: () => void;
  // Voice recording
  isRecording: boolean;
  recordingTime: number;
  audioBlob: Blob | null;
  onStartRecording: () => void;
  onStopRecording: () => void;
  onCancelRecording: () => void;
  onSendVoiceMessage: () => void;
  onClearAudioBlob: () => void;
  // Attachments
  attachments: File[];
  attachmentPreviews: AttachmentPreview[];
  fileInputRef: RefObject<HTMLInputElement>;
  onFileSelect: (e: ChangeEvent<HTMLInputElement>) => void;
  onRemoveAttachment: (index: number) => void;
}

/**
 * Format recording time as mm:ss
 */
function formatRecordingTime(seconds: number): string {
  const mins = Math.floor(seconds / 60);
  const secs = seconds % 60;
  return `${mins}:${secs.toString().padStart(2, '0')}`;
}

/**
 * Message input area — text input, attachments, voice recording controls
 */
export function MessageInputArea({
  isDirectMessagingEnabled,
  messagingRestriction,
  newMessage,
  onNewMessageChange,
  onSendMessage,
  isSending,
  onTypingIndicator,
  onBlurTypingStop,
  isRecording,
  recordingTime,
  audioBlob,
  onStartRecording,
  onStopRecording,
  onCancelRecording,
  onSendVoiceMessage,
  onClearAudioBlob,
  attachments,
  attachmentPreviews,
  fileInputRef,
  onFileSelect,
  onRemoveAttachment,
}: MessageInputAreaProps) {
  const { t } = useTranslation('messages');
  const navigate = useNavigate();
  const { tenantPath } = useTenant();

  return (
    <div className="p-4 border-t border-theme-default" style={{ paddingBottom: 'max(1rem, env(safe-area-inset-bottom, 0px))' }}>
      {/* Messaging disabled notice (feature flag) */}
      {!isDirectMessagingEnabled && (
        <div className="flex items-center gap-3 p-3 bg-amber-500/10 border border-amber-500/30 rounded-lg text-center">
          <span className="text-amber-600 dark:text-amber-400 text-sm flex-1">
            {t('disabled_inline')}
          </span>
          <Button
            size="sm"
            className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white"
            onPress={() => navigate(tenantPath('/exchanges'))}
          >
            {t('exchanges_link')}
          </Button>
        </div>
      )}

      {/* Messaging restricted by broker/admin */}
      {isDirectMessagingEnabled && messagingRestriction?.messaging_disabled && (
        <div className="flex items-start gap-3 p-3 bg-red-500/10 border border-red-500/30 rounded-lg" role="alert">
          <AlertTriangle className="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" aria-hidden="true" />
          <div className="flex-1">
            <p className="text-red-700 dark:text-red-300 text-sm font-medium">
              {t('messaging_restricted_title')}
            </p>
            <p className="text-red-600/80 dark:text-red-400/80 text-xs mt-1">
              {t('messaging_restricted_contact')}
            </p>
          </div>
        </div>
      )}

      {/* Voice recording preview */}
      {isDirectMessagingEnabled && !messagingRestriction?.messaging_disabled && audioBlob && !isRecording && (
        <div className="flex items-center gap-3 mb-3 p-3 bg-theme-elevated rounded-lg">
          <VoiceMessagePlayer audioBlob={audioBlob} />
          <div className="flex gap-2 ml-auto">
            <Button
              size="sm"
              variant="flat"
              className="bg-theme-elevated text-theme-muted"
              onPress={onClearAudioBlob}
            >
              {t('cancel')}
            </Button>
            <Button
              size="sm"
              className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white dark:text-white"
              onPress={onSendVoiceMessage}
              isLoading={isSending}
            >
              {t('send')}
            </Button>
          </div>
        </div>
      )}

      {/* Recording indicator */}
      {isDirectMessagingEnabled && !messagingRestriction?.messaging_disabled && isRecording && (
        <div className="flex items-center gap-3 mb-3 p-3 bg-red-500/10 rounded-lg border border-red-500/20">
          <div className="w-3 h-3 bg-red-500 rounded-full animate-pulse" />
          <span className="text-theme-primary font-medium">{formatRecordingTime(recordingTime)}</span>
          <span className="text-theme-subtle text-sm">{t('recording')}</span>
          <div className="ml-auto flex gap-2">
            <Button
              size="sm"
              variant="flat"
              className="bg-theme-elevated text-theme-muted"
              onPress={onCancelRecording}
            >
              {t('cancel')}
            </Button>
            <Button
              size="sm"
              color="danger"
              onPress={onStopRecording}
              startContent={<Square className="w-3 h-3" />}
            >
              {t('stop_recording')}
            </Button>
          </div>
        </div>
      )}

      {/* Attachment previews */}
      {isDirectMessagingEnabled && !messagingRestriction?.messaging_disabled && attachmentPreviews.length > 0 && (
        <div className="flex gap-2 mb-3 flex-wrap">
          {attachmentPreviews.map((item, index) => (
            <div key={index} className="relative group">
              {item.type === 'image' ? (
                <img
                  src={item.preview}
                  alt={item.file.name}
                  className="w-16 h-16 object-cover rounded-lg border border-theme-default"
                />
              ) : (
                <div className="w-16 h-16 flex flex-col items-center justify-center bg-theme-elevated rounded-lg border border-theme-default">
                  <FileText className="w-6 h-6 text-theme-subtle" />
                  <span className="text-[10px] text-theme-subtle truncate max-w-14 px-1">
                    {item.file.name.split('.').pop()?.toUpperCase()}
                  </span>
                </div>
              )}
              <Button
                isIconOnly
                size="sm"
                className="absolute -top-1 -right-1 w-4 h-4 min-w-0 bg-red-500 rounded-full opacity-0 group-hover:opacity-100 transition-opacity"
                onPress={() => onRemoveAttachment(index)}
                aria-label={`Remove ${item.file.name}`}
              >
                <X className="w-2.5 h-2.5 text-white" aria-hidden="true" />
              </Button>
            </div>
          ))}
        </div>
      )}

      {/* Text input form */}
      {isDirectMessagingEnabled && !messagingRestriction?.messaging_disabled && !isRecording && !audioBlob && (
        <form onSubmit={onSendMessage} className="flex gap-3">
          {/* Hidden file input */}
          <input
            ref={fileInputRef}
            type="file"
            multiple
            accept="image/*,.pdf,.doc,.docx,.txt"
            className="hidden"
            onChange={onFileSelect}
          />
          {/* Attachment button */}
          <Button
            type="button"
            isIconOnly
            variant="flat"
            className="bg-theme-elevated text-theme-muted hover:text-theme-primary"
            onPress={() => fileInputRef.current?.click()}
            aria-label={t('aria_add_attachment')}
            isDisabled={attachments.length >= 5}
          >
            <Paperclip className="w-4 h-4" />
          </Button>
          <Textarea
            placeholder={t('type_placeholder')}
            value={newMessage}
            onChange={(e) => {
              onNewMessageChange(e.target.value);
              onTypingIndicator(e.target.value);
            }}
            onBlur={onBlurTypingStop}
            onKeyDown={(e) => {
              // Enter sends message, Shift+Enter adds newline
              if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                if (newMessage.trim() || attachments.length > 0) {
                  const form = e.currentTarget.closest('form');
                  if (form) form.requestSubmit();
                }
              }
            }}
            minRows={1}
            maxRows={4}
            classNames={{
              input: 'bg-transparent text-theme-primary placeholder:text-theme-subtle',
              inputWrapper: 'bg-theme-elevated border-theme-default hover:bg-theme-hover',
            }}
            aria-label={t('aria_message_input')}
          />
          {/* Voice recording button - show when no text and no attachments */}
          {!newMessage.trim() && attachments.length === 0 && (
            <Button
              type="button"
              isIconOnly
              variant="flat"
              className="bg-theme-elevated text-theme-muted hover:text-theme-primary"
              onPress={onStartRecording}
              aria-label={t('aria_record_voice')}
            >
              <Mic className="w-4 h-4" />
            </Button>
          )}
          {/* Send button - show when there's text or attachments */}
          {(newMessage.trim() || attachments.length > 0) && (
            <Button
              type="submit"
              isIconOnly
              aria-label="Send message"
              className="bg-gradient-to-r from-indigo-500 to-purple-600 text-white dark:text-white"
              isLoading={isSending}
            >
              <Send className="w-4 h-4" />
            </Button>
          )}
        </form>
      )}
    </div>
  );
}
