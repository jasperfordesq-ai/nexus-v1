// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * ShareButton — Share content to feed or copy link.
 */

import { useState } from 'react';
import {
  Button,
  Dropdown,
  DropdownTrigger,
  DropdownMenu,
  DropdownItem,
} from '@heroui/react';
import {
  Share2,
  Link2,
  Repeat2,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { useToast } from '@/contexts';
import { logError } from '@/lib/logger';

export interface ShareButtonProps {
  shareToFeed: (content?: string) => Promise<boolean>;
  title?: string;
  description?: string;
  className?: string;
  isAuthenticated: boolean;
}

export function ShareButton({
  shareToFeed,
  title,
  description,
  className = '',
  isAuthenticated,
}: ShareButtonProps) {
  const { t } = useTranslation('social');
  const toast = useToast();
  const [isSharing, setIsSharing] = useState(false);

  const handleShareToFeed = async () => {
    if (!isAuthenticated) return;
    setIsSharing(true);
    try {
      const ok = await shareToFeed();
      if (ok) {
        toast.success(t('shared_to_feed', 'Shared to your feed'));
      } else {
        toast.error(t('share_failed', 'Failed to share'));
      }
    } catch (err) {
      logError('Failed to share to feed', err);
      toast.error(t('share_failed', 'Failed to share'));
    } finally {
      setIsSharing(false);
    }
  };

  const handleCopyLink = async () => {
    try {
      await navigator.clipboard.writeText(window.location.href);
      toast.success(t('link_copied', 'Link copied to clipboard'));
    } catch {
      toast.error(t('copy_failed', 'Failed to copy link'));
    }
  };

  const handleNativeShare = async () => {
    const shareData = {
      title: title ?? document.title,
      text: description?.slice(0, 100),
      url: window.location.href,
    };

    if (navigator.share && navigator.canShare?.(shareData)) {
      try {
        await navigator.share(shareData);
      } catch (err) {
        if ((err as Error).name !== 'AbortError') {
          logError('Share failed', err);
        }
      }
    } else {
      await handleCopyLink();
    }
  };

  return (
    <Dropdown placement="bottom-end">
      <DropdownTrigger>
        <Button
          variant="flat"
          className={`bg-theme-elevated text-theme-primary ${className}`}
          startContent={<Share2 className="w-4 h-4" aria-hidden="true" />}
          isLoading={isSharing}
        >
          {t('share', 'Share')}
        </Button>
      </DropdownTrigger>
      <DropdownMenu aria-label={t('share_options', 'Share options')}>
        {isAuthenticated ? (
          <DropdownItem
            key="feed"
            startContent={<Repeat2 className="w-4 h-4" aria-hidden="true" />}
            onPress={handleShareToFeed}
          >
            {t('share_to_feed', 'Share to Feed')}
          </DropdownItem>
        ) : (
          <DropdownItem
            key="feed-disabled"
            startContent={<Repeat2 className="w-4 h-4" aria-hidden="true" />}
            isDisabled
          >
            {t('login_to_share', 'Log in to share')}
          </DropdownItem>
        )}
        <DropdownItem
          key="link"
          startContent={<Link2 className="w-4 h-4" aria-hidden="true" />}
          onPress={handleNativeShare}
        >
          {t('copy_link', 'Copy Link')}
        </DropdownItem>
      </DropdownMenu>
    </Dropdown>
  );
}

export default ShareButton;
