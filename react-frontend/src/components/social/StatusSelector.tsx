// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * StatusSelector — Dropdown to set user presence status.
 *
 * Accessible from the user menu in the navbar.
 * Provides options: Online, Away, Do Not Disturb, and Custom status.
 */

import { useState, useCallback } from 'react';
import {
  Dropdown,
  DropdownTrigger,
  DropdownMenu,
  DropdownItem,
  DropdownSection,
  Input,
  Button,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
} from '@heroui/react';
import { Circle, Moon, MinusCircle, MessageSquare, X } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { usePresenceOptional, type PresenceStatus } from '@/contexts/PresenceContext';
import { useAuth } from '@/contexts/AuthContext';

interface StatusSelectorProps {
  /** Optional trigger element (defaults to a small status dot button) */
  children?: React.ReactNode;
}

/**
 * Status option definitions — labels resolved at render time via t().
 */
const STATUS_OPTIONS: Array<{
  key: PresenceStatus;
  labelKey: string;
  icon: React.ReactNode;
  color: string;
  descriptionKey: string;
}> = [
  {
    key: 'online',
    labelKey: 'status.online',
    icon: <Circle className="w-3 h-3 fill-green-500 text-green-500" />,
    color: 'text-green-500',
    descriptionKey: 'status.online_desc',
  },
  {
    key: 'away',
    labelKey: 'status.away',
    icon: <Moon className="w-3 h-3 text-yellow-500" />,
    color: 'text-yellow-500',
    descriptionKey: 'status.away_desc',
  },
  {
    key: 'dnd',
    labelKey: 'status.dnd',
    icon: <MinusCircle className="w-3 h-3 text-red-500" />,
    color: 'text-red-500',
    descriptionKey: 'status.dnd_desc',
  },
];

export function StatusSelector({ children }: StatusSelectorProps) {
  const { t } = useTranslation('social');
  const presence = usePresenceOptional();
  const { user } = useAuth();
  const [isCustomModalOpen, setIsCustomModalOpen] = useState(false);
  const [customText, setCustomText] = useState('');
  const [customEmoji, setCustomEmoji] = useState('');

  const currentStatus = user?.id ? presence?.getPresence(user.id) : null;

  const handleStatusChange = useCallback(
    async (status: PresenceStatus) => {
      if (!presence) return;
      await presence.setStatus(status);
    },
    [presence]
  );

  const handleCustomStatusSubmit = useCallback(async () => {
    if (!presence) return;

    const status: PresenceStatus = currentStatus?.status === 'dnd' ? 'dnd' : 'online';
    await presence.setStatus(
      status,
      customText.trim() || undefined,
      customEmoji.trim() || undefined
    );
    setIsCustomModalOpen(false);
  }, [presence, customText, customEmoji, currentStatus?.status]);

  const handleClearCustomStatus = useCallback(async () => {
    if (!presence) return;
    const status: PresenceStatus = currentStatus?.status ?? 'online';
    await presence.setStatus(status);
  }, [presence, currentStatus?.status]);

  if (!presence) {
    return <>{children}</>;
  }

  const trigger = children ?? (
    <Button
      variant="light"
      size="sm"
      className="min-w-0 px-2 gap-1"
      aria-label={t('status.set_status', 'Set status')}
    >
      <Circle
        className={`w-2.5 h-2.5 ${
          currentStatus?.status === 'online'
            ? 'fill-green-500 text-green-500'
            : currentStatus?.status === 'away'
              ? 'fill-yellow-500 text-yellow-500'
              : currentStatus?.status === 'dnd'
                ? 'fill-red-500 text-red-500'
                : 'fill-gray-400 text-gray-400'
        }`}
      />
    </Button>
  );

  return (
    <>
      <Dropdown placement="bottom-end" shouldBlockScroll={false}>
        <DropdownTrigger>{trigger}</DropdownTrigger>
        <DropdownMenu
          aria-label={t('status.set_status', 'Set status')}
          onAction={(key) => {
            if (key === 'custom') {
              setCustomText(currentStatus?.custom_status ?? '');
              setCustomEmoji(currentStatus?.status_emoji ?? '');
              setIsCustomModalOpen(true);
            } else if (key === 'clear-custom') {
              handleClearCustomStatus();
            } else {
              handleStatusChange(key as PresenceStatus);
            }
          }}
        >
          <DropdownSection title={t('status.section_status', 'Status')} showDivider>
            {STATUS_OPTIONS.map((option) => (
              <DropdownItem
                key={option.key}
                startContent={option.icon}
                description={t(option.descriptionKey)}
                className={currentStatus?.status === option.key ? 'bg-theme-hover' : ''}
              >
                {t(option.labelKey)}
              </DropdownItem>
            ))}
          </DropdownSection>
          <DropdownSection title={t('status.section_custom', 'Custom')}>
            <DropdownItem
              key="custom"
              startContent={<MessageSquare className="w-3 h-3 text-theme-subtle" />}
              description={t('status.set_custom_desc', 'Set a custom status message')}
            >
              {currentStatus?.custom_status
                ? `${currentStatus.status_emoji ?? ''} ${currentStatus.custom_status}`.trim()
                : t('status.set_custom', 'Set custom status...')}
            </DropdownItem>
            {currentStatus?.custom_status ? (
              <DropdownItem
                key="clear-custom"
                startContent={<X className="w-3 h-3 text-theme-subtle" />}
                className="text-danger"
              >
                {t('status.clear_custom', 'Clear custom status')}
              </DropdownItem>
            ) : (
              // HeroUI DropdownSection requires at least the items declared;
              // render a hidden placeholder when there's nothing to clear
              <DropdownItem key="noop-clear" className="hidden">
                {' '}
              </DropdownItem>
            )}
          </DropdownSection>
        </DropdownMenu>
      </Dropdown>

      {/* Custom Status Modal */}
      <Modal
        isOpen={isCustomModalOpen}
        onOpenChange={setIsCustomModalOpen}
        size="sm"
        placement="center"
      >
        <ModalContent>
          <ModalHeader>{t('status.set_custom_title', 'Set Custom Status')}</ModalHeader>
          <ModalBody>
            <div className="flex gap-2">
              <Input
                label={t('status.emoji_label', 'Emoji')}
                placeholder="📅"
                value={customEmoji}
                onValueChange={setCustomEmoji}
                maxLength={10}
                className="w-20"
                variant="bordered"
              />
              <Input
                label={t('status.status_label', 'Status')}
                placeholder={t('status.status_placeholder', 'In a meeting')}
                value={customText}
                onValueChange={setCustomText}
                maxLength={80}
                className="flex-1"
                variant="bordered"
                autoFocus
              />
            </div>
            <p className="text-xs text-theme-subtle">
              {t('status.char_count', '{{current}}/80 characters', { current: customText.length })}
            </p>
          </ModalBody>
          <ModalFooter>
            <Button
              variant="flat"
              onPress={() => setIsCustomModalOpen(false)}
            >
              {t('cancel', 'Cancel')}
            </Button>
            <Button
              color="primary"
              onPress={handleCustomStatusSubmit}
            >
              {t('save', 'Save')}
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </>
  );
}

export default StatusSelector;
