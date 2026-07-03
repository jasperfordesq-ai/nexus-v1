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

const DEVICE_WIDTH = { desktop: 640, mobile: 375 };

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
        const body = format === 'plaintext' ? `<pre style="white-space:pre-wrap;font-family:sans-serif;">${escapePlainToHtml(content)}</pre>` : content;
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

  return (
    <div className="flex flex-col gap-2">
      <div className="flex flex-wrap items-center gap-2">
        <span className="text-sm font-semibold text-foreground">
          {t('newsletter_content_editor.preview_title')}
        </span>
        <div className="ml-auto flex items-center gap-2">
          <ToggleButtonGroup
            selectionMode="single"
            selectedKeys={new Set([device])}
            onSelectionChange={(keys) => {
              const next = Array.from(keys)[0] as 'desktop' | 'mobile' | undefined;
              if (next) setDevice(next);
            }}
            size="sm"
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
            className="h-8"
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

      <div className="rounded-lg border border-border bg-surface-secondary p-4 overflow-auto">
        <div className="mx-auto bg-white transition-all" style={{ maxWidth: DEVICE_WIDTH[device], width: '100%' }}>
          {loading && html === '' ? (
            <div className="flex items-center justify-center py-16">
              <Spinner size="sm" />
            </div>
          ) : (
            <iframe
              title={t('newsletter_content_editor.preview_title')}
              srcDoc={html}
              sandbox="allow-same-origin"
              className="w-full"
              style={{ height: 600, border: 'none' }}
            />
          )}
        </div>
      </div>
    </div>
  );
}

export default NewsletterPreviewPane;
