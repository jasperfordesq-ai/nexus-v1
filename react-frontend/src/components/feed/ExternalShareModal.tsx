// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * ExternalShareModal — fallback share UI when Web Share API is not available.
 * Shows a grid of share targets (copy link, email, WhatsApp, X, Facebook, LinkedIn).
 */

import {
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  Button,
  Tooltip,
} from '@heroui/react';
import {
  Copy,
  Mail,
  Check,
} from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useToast } from '@/contexts';

interface ExternalShareModalProps {
  isOpen: boolean;
  onClose: () => void;
  url: string;
  title: string;
  text: string;
}

interface ShareTarget {
  key: string;
  label: string;
  icon: React.ReactNode;
  color: string;
  getUrl: (url: string, title: string, text: string) => string;
}

const SHARE_TARGETS: ShareTarget[] = [
  {
    key: 'email',
    label: 'Email',
    icon: <Mail className="w-5 h-5" />,
    color: 'bg-gray-500',
    getUrl: (url, title, text) =>
      `mailto:?subject=${encodeURIComponent(title)}&body=${encodeURIComponent(text + '\n\n' + url)}`,
  },
  {
    key: 'whatsapp',
    label: 'WhatsApp',
    icon: (
      <svg viewBox="0 0 24 24" className="w-5 h-5 fill-current" aria-hidden="true">
        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z" />
      </svg>
    ),
    color: 'bg-green-500',
    getUrl: (url, _title, text) =>
      `https://wa.me/?text=${encodeURIComponent(text + ' ' + url)}`,
  },
  {
    key: 'x',
    label: 'X',
    icon: (
      <svg viewBox="0 0 24 24" className="w-5 h-5 fill-current" aria-hidden="true">
        <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z" />
      </svg>
    ),
    color: 'bg-black dark:bg-white dark:text-black',
    getUrl: (url, _title, text) =>
      `https://twitter.com/intent/tweet?text=${encodeURIComponent(text)}&url=${encodeURIComponent(url)}`,
  },
  {
    key: 'facebook',
    label: 'Facebook',
    icon: (
      <svg viewBox="0 0 24 24" className="w-5 h-5 fill-current" aria-hidden="true">
        <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z" />
      </svg>
    ),
    color: 'bg-blue-600',
    getUrl: (url) =>
      `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(url)}`,
  },
  {
    key: 'linkedin',
    label: 'LinkedIn',
    icon: (
      <svg viewBox="0 0 24 24" className="w-5 h-5 fill-current" aria-hidden="true">
        <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z" />
      </svg>
    ),
    color: 'bg-blue-700',
    getUrl: (url, _title) =>
      `https://www.linkedin.com/sharing/share-offsite/?url=${encodeURIComponent(url)}`,
  },
];

export function ExternalShareModal({ isOpen, onClose, url, title, text }: ExternalShareModalProps) {
  const { t } = useTranslation('feed');
  const toast = useToast();
  const [copied, setCopied] = useState(false);

  const handleCopyLink = async () => {
    try {
      await navigator.clipboard.writeText(url);
      setCopied(true);
      toast.success(t('share.link_copied', 'Link copied to clipboard'));
      setTimeout(() => setCopied(false), 2000);
    } catch {
      toast.error(t('share.copy_failed', 'Failed to copy link'));
    }
  };

  const handleShareTarget = (target: ShareTarget) => {
    const shareUrl = target.getUrl(url, title, text);
    if (target.key === 'email') {
      window.location.href = shareUrl;
    } else {
      window.open(shareUrl, '_blank', 'noopener,noreferrer,width=600,height=400');
    }
    onClose();
  };

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      size="sm"
      classNames={{
        base: 'bg-[var(--color-surface)] border border-[var(--border-default)]',
        header: 'border-b border-[var(--border-default)]',
      }}
    >
      <ModalContent>
        <ModalHeader className="text-[var(--text-primary)]">
          {t('share.external_title', 'Share Post')}
        </ModalHeader>
        <ModalBody className="pb-6">
          {/* Copy link button */}
          <Button
            variant="bordered"
            className="w-full justify-start gap-3 text-[var(--text-primary)] border-[var(--border-default)]"
            startContent={
              copied ? (
                <Check className="w-5 h-5 text-emerald-500" />
              ) : (
                <Copy className="w-5 h-5 text-[var(--text-muted)]" />
              )
            }
            onPress={handleCopyLink}
          >
            {copied
              ? t('share.link_copied', 'Link copied to clipboard')
              : t('share.copy_link', 'Copy link')}
          </Button>

          {/* Share target grid */}
          <div className="grid grid-cols-5 gap-3 mt-3">
            {SHARE_TARGETS.map((target) => (
              <Tooltip key={target.key} content={target.label} delay={300} closeDelay={0} size="sm">
                <button
                  type="button"
                  className={`flex flex-col items-center gap-1.5 p-3 rounded-xl ${target.color} text-white hover:opacity-80 transition-opacity`}
                  onClick={() => handleShareTarget(target)}
                  aria-label={t('share.share_via', 'Share via {{platform}}', { platform: target.label })}
                >
                  {target.icon}
                </button>
              </Tooltip>
            ))}
          </div>
        </ModalBody>
      </ModalContent>
    </Modal>
  );
}

export default ExternalShareModal;
