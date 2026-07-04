// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * NewsletterDesignStudio — the dedicated FULL-SCREEN home of the GrapesJS+MJML
 * email builder (route: /admin/newsletters/edit/:id/design).
 *
 * The builder was previously crammed into a 2/3-width form column beside the
 * targeting sidebar, which left ~250px of canvas — unusable. Here it fills the
 * whole viewport (over the admin chrome) so columns, images and layout are
 * actually workable. It loads the newsletter by id, hands `design_json` to the
 * builder, background-autosaves edits, and returns to the settings form on Done.
 *
 * Persistence uses the PARTIAL update endpoint — only content/content_format/
 * design_json are sent, so targeting/scheduling set on the form is untouched.
 */

import { useCallback, useEffect, useRef, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { Button, Spinner } from '@/components/ui';
import ArrowLeft from 'lucide-react/icons/arrow-left';
import Save from 'lucide-react/icons/save';
import Check from 'lucide-react/icons/check';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { logError } from '@/lib/logger';
import { adminNewsletters } from '../../api/adminApi';
import { NewsletterBuilder } from '../../components/NewsletterBuilder';

const AUTOSAVE_DEBOUNCE_MS = 900;
type SaveState = 'idle' | 'dirty' | 'saving' | 'saved' | 'error';

export function NewsletterDesignStudio() {
  const { id } = useParams<{ id: string }>();
  const { t } = useTranslation('admin');
  const navigate = useNavigate();
  const { tenantPath } = useTenant();
  const toast = useToast();

  const [loading, setLoading] = useState(true);
  const [subject, setSubject] = useState('');
  const [designJson, setDesignJson] = useState<string | null>(null);
  const [initialMjml, setInitialMjml] = useState<string | null>(null);
  const [readOnly, setReadOnly] = useState(false);
  const [saveState, setSaveState] = useState<SaveState>('idle');

  usePageTitle(t('newsletter_builder.studio_title'));

  // Latest builder output, persisted by the debounced autosave.
  const latestRef = useRef<{ html: string; designJson: string } | null>(null);
  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  useEffect(() => {
    if (!id) return;
    (async () => {
      try {
        const res = await adminNewsletters.get(Number(id));
        if (res.success && res.data) {
          const d = res.data as Record<string, unknown>;
          setSubject((d.subject as string) || '');
          setDesignJson((d.design_json as string) || null);
          // Seed from MJML markup only (starter templates); compiled HTML must
          // NOT be re-parsed into the MJML editor (that yields broken exports).
          const content = (d.content as string) || '';
          setInitialMjml(content.trim().startsWith('<mjml') ? content : null);
          const status = (d.status as string) || 'draft';
          setReadOnly(status === 'sent' || status === 'sending');
        }
      } catch (err) {
        logError('NewsletterDesignStudio: failed to load newsletter', err);
        toast.error(t('newsletter_form.failed_to_load'));
      }
      setLoading(false);
    })();
  }, [id, t, toast]);

  const persist = useCallback(async (): Promise<boolean> => {
    const payload = latestRef.current;
    if (!id || !payload) return true;
    setSaveState('saving');
    try {
      const res = await adminNewsletters.update(Number(id), {
        content: payload.html,
        content_format: 'builder',
        design_json: payload.designJson,
      });
      if (res.success) {
        setSaveState('saved');
        return true;
      }
      setSaveState('error');
      return false;
    } catch (err) {
      logError('NewsletterDesignStudio: autosave failed', err);
      setSaveState('error');
      return false;
    }
  }, [id]);

  const handleBuilderChange = useCallback(
    (payload: { html: string; designJson: string }) => {
      latestRef.current = payload;
      if (readOnly) return;
      setSaveState('dirty');
      if (debounceRef.current) clearTimeout(debounceRef.current);
      debounceRef.current = setTimeout(() => void persist(), AUTOSAVE_DEBOUNCE_MS);
    },
    [persist, readOnly],
  );

  useEffect(() => () => { if (debounceRef.current) clearTimeout(debounceRef.current); }, []);

  const backToForm = () => navigate(tenantPath(`/admin/newsletters/edit/${id}`));

  const handleSaveNow = async () => {
    if (debounceRef.current) clearTimeout(debounceRef.current);
    const ok = await persist();
    if (ok) toast.success(t('newsletter_builder.saved_toast'));
  };

  const handleDone = async () => {
    if (debounceRef.current) clearTimeout(debounceRef.current);
    if (!readOnly) await persist();
    backToForm();
  };

  const saveLabel =
    saveState === 'saving'
      ? t('newsletter_builder.status_saving')
      : saveState === 'saved'
        ? t('newsletter_builder.status_saved')
        : saveState === 'error'
          ? t('newsletter_builder.status_error')
          : saveState === 'dirty'
            ? t('newsletter_builder.status_unsaved')
            : '';

  return (
    <div className="fixed inset-0 z-50 flex flex-col bg-surface">
      {/* Studio header */}
      <header className="flex h-14 shrink-0 items-center gap-3 border-b border-border bg-surface px-3">
        <Button size="sm" variant="light" startContent={<ArrowLeft size={16} />} onPress={backToForm}>
          {t('newsletter_builder.back_to_settings')}
        </Button>
        <div className="min-w-0 flex-1">
          <p className="truncate text-sm font-semibold text-foreground">
            {subject || t('newsletter_builder.untitled')}
          </p>
          <p className="truncate text-xs text-muted">{t('newsletter_builder.studio_subtitle')}</p>
        </div>
        {saveLabel && (
          <span
            className={`hidden text-xs sm:inline ${saveState === 'error' ? 'text-danger' : 'text-muted'}`}
            aria-live="polite"
          >
            {saveLabel}
          </span>
        )}
        {!readOnly && (
          <Button
            size="sm"
            variant="secondary"
            startContent={<Save size={16} />}
            isLoading={saveState === 'saving'}
            onPress={handleSaveNow}
          >
            {t('newsletter_builder.save')}
          </Button>
        )}
        <Button size="sm" variant="primary" startContent={<Check size={16} />} onPress={handleDone}>
          {t('newsletter_builder.done')}
        </Button>
      </header>

      {/* Builder fills the rest of the viewport */}
      {loading ? (
        <div className="flex flex-1 items-center justify-center" role="status" aria-busy="true">
          <Spinner size="sm" />
        </div>
      ) : (
        <NewsletterBuilder
          fill
          enableTemplates
          html=""
          designJson={designJson}
          initialMjml={initialMjml}
          readOnly={readOnly}
          onChange={handleBuilderChange}
        />
      )}
    </div>
  );
}

export default NewsletterDesignStudio;
