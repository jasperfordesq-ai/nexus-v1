// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * NewsletterPreviewPane — live desktop/mobile preview rendered by the REAL
 * send pipeline (POST /v2/admin/newsletters/preview), so what the admin sees
 * is what recipients get: merge tokens resolved, CSS inlined, unsubscribe
 * injected. Falls back to an approximate client-only render if the endpoint
 * is unavailable. Always sandboxed (no allow-scripts) + DOMPurify'd.
 *
 * The preview renders inside a device "frame" (a browser window for desktop,
 * a phone shell for mobile) on a neutral canvas so it reads as a real email.
 */

import { useEffect, useRef, useState } from 'react';
import DOMPurify from 'dompurify';
import { Button, ToggleButtonGroup, ToggleButton, Alert, Spinner } from '@/components/ui';
import Monitor from 'lucide-react/icons/monitor';
import Smartphone from 'lucide-react/icons/smartphone';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import { useTranslation } from 'react-i18next';
import type { ContentFormat } from './contentFormat';
import { escapePlainToHtml } from './contentFormat';

interface PreviewRequest {
  content: string;
  content_format: ContentFormat;
  subject?: string;
  preview_text?: string;
}

interface NewsletterPreviewPaneProps {
  content: string;
  format: ContentFormat;
  subject?: string;
  previewText?: string;
  /** Server render (preview == send). Returns rendered HTML. */
  onRequestPreview: (req: PreviewRequest) => Promise<string>;
}

export function NewsletterPreviewPane({
  content,
  format,
  subject,
  previewText,
  onRequestPreview,
}: NewsletterPreviewPaneProps) {
  const { t } = useTranslation('admin');
  const [device, setDevice] = useState<'desktop' | 'mobile'>('desktop');
  const [html, setHtml] = useState('');
  const [loading, setLoading] = useState(false);
  const [approximate, setApproximate] = useState(false);
  const abortRef = useRef<AbortController | null>(null);
  const [nonce, setNonce] = useState(0);

  useEffect(() => {
    let cancelled = false;
    const controller = new AbortController();
    abortRef.current?.abort();
    abortRef.current = controller;

    const run = async () => {
      setLoading(true);
      try {
        const rendered = await onRequestPreview({
          content,
          content_format: format,
          subject,
          preview_text: previewText,
        });
        if (cancelled) return;
        setHtml(DOMPurify.sanitize(rendered, { WHOLE_DOCUMENT: true }));
        setApproximate(false);
      } catch {
        if (cancelled) return;
        // Client-only fallback — approximate, can't show merge/inlining.
        const body =
          format === 'plaintext'
            ? `<pre style="white-space:pre-wrap;font-family:sans-serif;padding:24px;">${escapePlainToHtml(content)}</pre>`
            : content;
        setHtml(DOMPurify.sanitize(body, { WHOLE_DOCUMENT: true }));
        setApproximate(true);
      } finally {
        if (!cancelled) setLoading(false);
      }
    };

    // Debounce content-driven refreshes; refresh immediately on manual bump.
    const timer = setTimeout(run, 400);
    return () => {
      cancelled = true;
      clearTimeout(timer);
      controller.abort();
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps -- nonce forces manual refresh
  }, [content, format, subject, previewText, nonce]);

  const iframe = (
    <iframe
      title={t('newsletter_content_editor.preview_title')}
      srcDoc={html}
      sandbox="allow-same-origin"
      className="block w-full bg-white"
      style={{ height: device === 'mobile' ? 620 : 560, border: 'none' }}
    />
  );

  return (
    <div className="flex flex-col gap-3">
      <div className="flex flex-wrap items-center gap-2">
        <span className="text-sm font-semibold text-foreground">
          {t('newsletter_content_editor.preview_title')}
        </span>
        {loading && <Spinner size="sm" />}
        <div className="ml-auto flex items-center gap-2">
          <ToggleButtonGroup
            selectionMode="single"
            selectedKeys={new Set([device])}
            onSelectionChange={(keys) => {
              const next = Array.from(keys)[0] as 'desktop' | 'mobile' | undefined;
              if (next) setDevice(next);
            }}
          >
            <ToggleButton id="desktop" aria-label={t('newsletter_content_editor.preview_desktop')}>
              <Monitor size={15} />
            </ToggleButton>
            <ToggleButton id="mobile" aria-label={t('newsletter_content_editor.preview_mobile')}>
              <Smartphone size={15} />
            </ToggleButton>
          </ToggleButtonGroup>
          <Button
            size="sm"
            variant="tertiary"
            onPress={() => setNonce((n) => n + 1)}
            isLoading={loading}
            startContent={!loading ? <RefreshCw size={15} /> : undefined}
            aria-label={t('newsletter_content_editor.preview_refresh')}
          >
            {t('newsletter_content_editor.preview_refresh')}
          </Button>
        </div>
      </div>

      {approximate && (
        <Alert color="warning" className="text-xs">
          {t('newsletter_content_editor.preview_approximate')}
        </Alert>
      )}

      {/* Neutral canvas + device frame */}
      <div className="flex justify-center overflow-auto rounded-xl bg-surface-secondary p-4 sm:p-6">
        {device === 'desktop' ? (
          <div
            className="overflow-hidden rounded-lg bg-white shadow-lg"
            style={{ width: '100%', maxWidth: 620 }}
          >
            {/* Browser chrome */}
            <div className="flex h-9 items-center gap-2 border-b border-neutral-200 bg-neutral-100 px-3">
              <span className="h-3 w-3 rounded-full" style={{ backgroundColor: '#f87171' }} />
              <span className="h-3 w-3 rounded-full" style={{ backgroundColor: '#fbbf24' }} />
              <span className="h-3 w-3 rounded-full" style={{ backgroundColor: '#34d399' }} />
              <span className="ml-2 truncate rounded-md bg-white px-3 py-1 text-[11px] text-neutral-400">
                {t('newsletter_content_editor.preview_inbox')}
              </span>
            </div>
            {iframe}
          </div>
        ) : (
          <div
            className="rounded-[2.25rem] p-2.5 shadow-xl"
            style={{ width: 340, backgroundColor: '#111827' }}
          >
            <div className="overflow-hidden rounded-[1.75rem] bg-white">
              {/* Phone status notch */}
              <div className="flex h-6 items-center justify-center" style={{ backgroundColor: '#111827' }}>
                <span className="h-1.5 w-16 rounded-full" style={{ backgroundColor: '#374151' }} />
              </div>
              {iframe}
            </div>
          </div>
        )}
      </div>
    </div>
  );
}

export default NewsletterPreviewPane;
