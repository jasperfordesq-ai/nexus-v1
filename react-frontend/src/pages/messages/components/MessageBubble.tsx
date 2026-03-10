// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState, useEffect, useRef, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { motion } from 'framer-motion';
import { Button, Avatar, Input } from '@heroui/react';
import { SmilePlus, MoreVertical, Pencil, Trash2, Check, CheckCheck, FileText } from 'lucide-react';
import { resolveAvatarUrl } from '@/lib/helpers';
import type { Message } from '@/types/api';
import { VoiceMessagePlayer } from './VoiceMessagePlayer';

// Available reaction emojis
const REACTION_EMOJIS = ['👍', '❤️', '😂', '😮', '😢', '🙏'];

interface OtherUser {
  id: number;
  name: string;
  avatar_url?: string | null;
  avatar?: string | null;
}

export interface MessageBubbleProps {
  id?: string;
  message: Message;
  isOwn: boolean;
  showAvatar: boolean;
  otherUser: OtherUser;
  onReact?: (messageId: number, emoji: string) => void;
  isHighlighted?: boolean;
  highlightQuery?: string;
  onEdit?: (message: Message) => void;
  onDelete?: (messageId: number) => void;
  isEditing?: boolean;
  editingText?: string;
  onEditingTextChange?: (text: string) => void;
  onSaveEdit?: () => void;
  onCancelEdit?: () => void;
}

export function MessageBubble({
  id,
  message,
  isOwn,
  showAvatar,
  otherUser,
  onReact,
  isHighlighted,
  highlightQuery,
  onEdit,
  onDelete,
  isEditing,
  editingText,
  onEditingTextChange,
  onSaveEdit,
  onCancelEdit,
}: MessageBubbleProps) {
  const { t } = useTranslation('messages');
  const [showReactionPicker, setShowReactionPicker] = useState(false);
  const [showMessageMenu, setShowMessageMenu] = useState(false);
  const reactionPickerRef = useRef<HTMLDivElement>(null);
  const messageMenuRef = useRef<HTMLDivElement>(null);
  const isVoiceMessage = message.is_voice || message.audio_url;
  const isDeleted = message.is_deleted;

  // Close popups when clicking outside
  useEffect(() => {
    function handleClickOutside(event: MouseEvent) {
      if (showReactionPicker && reactionPickerRef.current && !reactionPickerRef.current.contains(event.target as Node)) {
        setShowReactionPicker(false);
      }
      if (showMessageMenu && messageMenuRef.current && !messageMenuRef.current.contains(event.target as Node)) {
        setShowMessageMenu(false);
      }
    }

    if (showReactionPicker || showMessageMenu) {
      document.addEventListener('mousedown', handleClickOutside);
      return () => document.removeEventListener('mousedown', handleClickOutside);
    }
  }, [showReactionPicker, showMessageMenu]);

  // Parse reactions from message (format: { emoji: count, ... } or array)
  const reactions = message.reactions || {};
  const hasReactions = Object.keys(reactions).length > 0;

  // Highlight search terms in message body
  function highlightText(text: string): ReactNode {
    if (!highlightQuery || !text) return text;
    const parts = text.split(new RegExp(`(${highlightQuery.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi'));
    return parts.map((part, i) =>
      part.toLowerCase() === highlightQuery.toLowerCase() ? (
        <mark key={i} className="bg-yellow-400/40 text-gray-900 dark:text-white rounded px-0.5">{part}</mark>
      ) : part
    );
  }

  return (
    <motion.div
      id={id}
      initial={{ opacity: 0, y: 10 }}
      animate={{ opacity: 1, y: 0 }}
      exit={{ opacity: 0, y: -10 }}
      className={`flex gap-3 ${isOwn ? 'flex-row-reverse' : ''} group transition-all duration-300 ${isHighlighted ? 'ring-2 ring-yellow-400/30 rounded-lg' : ''}`}
    >
      {showAvatar && !isOwn ? (
        <Avatar
          src={resolveAvatarUrl(otherUser.avatar_url || otherUser.avatar)}
          name={otherUser.name}
          size="sm"
          className="flex-shrink-0"
        />
      ) : (
        <div className="w-8" />
      )}

      <div className={`max-w-[70%] ${isOwn ? 'text-right' : ''} relative`}>
        <div
          className={`
            inline-block px-4 py-2 rounded-2xl relative
            ${isOwn
              ? 'bg-gradient-to-r from-indigo-500 to-purple-600 text-white rounded-br-md'
              : 'bg-theme-elevated text-theme-primary rounded-bl-md'
            }
          `}
        >
          {isEditing ? (
            /* Editing mode */
            <div className="min-w-[200px]">
              <Input
                value={editingText}
                onChange={(e) => onEditingTextChange?.(e.target.value)}
                aria-label="Edit message"
                classNames={{
                  input: 'bg-transparent text-inherit placeholder:text-inherit/40',
                  inputWrapper: 'bg-black/10 dark:bg-white/10 border-black/20 dark:border-white/20',
                }}
                autoFocus
                onKeyDown={(e) => {
                  if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    onSaveEdit?.();
                  } else if (e.key === 'Escape') {
                    onCancelEdit?.();
                  }
                }}
              />
              <div className="flex gap-2 mt-2 justify-end">
                <Button size="sm" variant="flat" className="bg-black/10 dark:bg-white/10 text-inherit/70" onPress={onCancelEdit}>
                  {t('cancel')}
                </Button>
                <Button size="sm" className="bg-black/20 dark:bg-white/20 text-inherit" onPress={onSaveEdit}>
                  {t('save')}
                </Button>
              </div>
            </div>
          ) : isDeleted ? (
            /* Deleted message */
            <p className="text-sm opacity-40 italic">{t('message_deleted_placeholder')}</p>
          ) : isVoiceMessage ? (
            <VoiceMessagePlayer audioUrl={message.audio_url} />
          ) : (
            <>
              {(message.body || message.content) && (
                <p className="text-sm whitespace-pre-wrap">{highlightText(message.body || message.content || '')}</p>
              )}
              {/* Edited indicator */}
              {message.is_edited && (
                <span className="text-[10px] opacity-40 ml-1">{t('message_edited_indicator')}</span>
              )}
              {/* Attachments */}
              {message.attachments && message.attachments.length > 0 && (
                <div className={`flex flex-wrap gap-2 ${message.body ? 'mt-2' : ''}`}>
                  {message.attachments.map((attachment) => (
                    <a
                      key={attachment.id}
                      href={attachment.url}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="block"
                    >
                      {attachment.type === 'image' ? (
                        <img
                          src={attachment.url}
                          alt={attachment.name}
                          className="max-w-[200px] max-h-[200px] rounded-lg object-cover hover:opacity-90 transition-opacity"
                          loading="lazy"
                        />
                      ) : (
                        <div className="flex items-center gap-2 px-3 py-2 bg-black/10 dark:bg-white/10 rounded-lg hover:bg-black/20 dark:hover:bg-white/20 transition-colors">
                          <FileText className="w-4 h-4 opacity-60" />
                          <div className="flex flex-col">
                            <span className="text-xs opacity-80 truncate max-w-[150px]">{attachment.name}</span>
                            <span className="text-[10px] opacity-40">
                              {(attachment.size / 1024).toFixed(1)} KB
                            </span>
                          </div>
                        </div>
                      )}
                    </a>
                  ))}
                </div>
              )}
            </>
          )}

          {/* Action buttons - shows on hover (only when not editing) */}
          {!isEditing && !isDeleted && (
            <div className={`absolute -bottom-2 ${isOwn ? '-left-12' : '-right-12'} flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity`}>
              {/* Reaction button */}
              <Button
                isIconOnly
                size="sm"
                variant="light"
                className="w-5 h-5 min-w-0 bg-theme-elevated rounded-full border border-theme-default"
                onPress={() => setShowReactionPicker(!showReactionPicker)}
                aria-label={t('aria_add_reaction')}
              >
                <SmilePlus className="w-3 h-3 text-theme-muted" aria-hidden="true" />
              </Button>

              {/* Edit/Delete button (only for own messages) */}
              {isOwn && !isVoiceMessage && (
                <Button
                  isIconOnly
                  size="sm"
                  variant="light"
                  className="w-5 h-5 min-w-0 bg-theme-elevated rounded-full border border-theme-default"
                  onPress={() => setShowMessageMenu(!showMessageMenu)}
                  aria-label={t('aria_message_options')}
                >
                  <MoreVertical className="w-3 h-3 text-theme-muted" aria-hidden="true" />
                </Button>
              )}
            </div>
          )}

          {/* Reaction picker */}
          {showReactionPicker && (
            <div
              ref={reactionPickerRef}
              className={`
                absolute ${isOwn ? 'left-0' : 'right-0'} -top-10
                flex gap-1 p-1.5 bg-theme-card rounded-full border border-theme-default
                shadow-lg z-10
              `}
              role="menu"
              aria-label={t('aria_add_reaction')}
            >
              {REACTION_EMOJIS.map((emoji) => (
                <Button
                  key={emoji}
                  isIconOnly
                  size="sm"
                  variant="light"
                  className="w-7 h-7 min-w-0 rounded-full"
                  onPress={() => {
                    onReact?.(message.id, emoji);
                    setShowReactionPicker(false);
                  }}
                  aria-label={t('aria_react_with', { emoji })}
                >
                  {emoji}
                </Button>
              ))}
            </div>
          )}

          {/* Message menu (edit/delete) */}
          {showMessageMenu && isOwn && (
            <div
              ref={messageMenuRef}
              className={`
                absolute ${isOwn ? 'left-0' : 'right-0'} -top-16
                flex flex-col p-1 bg-theme-card rounded-lg border border-theme-default
                shadow-lg z-10 min-w-[100px]
              `}
              role="menu"
              aria-label={t('aria_message_options')}
            >
              <Button
                variant="light"
                size="sm"
                className="justify-start text-sm text-theme-muted"
                startContent={<Pencil className="w-3 h-3" aria-hidden="true" />}
                onPress={() => {
                  onEdit?.(message);
                  setShowMessageMenu(false);
                }}
                role="menuitem"
              >
                {t('message_edit')}
              </Button>
              <Button
                variant="light"
                size="sm"
                className="justify-start text-sm text-red-600 dark:text-red-400"
                startContent={<Trash2 className="w-3 h-3" aria-hidden="true" />}
                onPress={() => {
                  onDelete?.(message.id);
                  setShowMessageMenu(false);
                }}
                role="menuitem"
              >
                {t('message_delete')}
              </Button>
            </div>
          )}
        </div>

        {/* Display existing reactions */}
        {hasReactions && (
          <div className={`flex gap-1 mt-1 ${isOwn ? 'justify-end' : 'justify-start'} px-2`}>
            {Object.entries(reactions).map(([emoji, count]) => (
              <Button
                key={emoji}
                size="sm"
                variant="light"
                className="min-w-0 h-auto px-1.5 py-0.5 bg-theme-elevated rounded-full text-xs gap-0.5"
                onPress={() => onReact?.(message.id, emoji)}
                aria-label={t('aria_toggle_reaction', { emoji })}
              >
                <span>{emoji}</span>
                {typeof count === 'number' && count > 1 && (
                  <span className="text-theme-subtle">{count}</span>
                )}
              </Button>
            ))}
          </div>
        )}

        <div className={`flex items-center gap-1 mt-1 px-2 ${isOwn ? 'justify-end' : 'justify-start'}`}>
          <span className="text-xs text-gray-400 dark:text-white/30">
            {new Date(message.created_at || message.sent_at || Date.now()).toLocaleTimeString([], {
              hour: '2-digit',
              minute: '2-digit',
            })}
          </span>
          {/* Read receipts - only show for own messages */}
          {isOwn && (
            <span className="text-gray-400 dark:text-white/40">
              {message.is_read || message.read_at ? (
                <CheckCheck className="w-3.5 h-3.5 text-blue-600 dark:text-blue-400" />
              ) : (
                <Check className="w-3.5 h-3.5" />
              )}
            </span>
          )}
        </div>
      </div>
    </motion.div>
  );
}
