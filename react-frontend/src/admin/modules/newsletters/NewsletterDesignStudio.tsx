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

import { lazy, Suspense, useCallback, useEffect, useRef, useState } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/Button';
import { Chip } from '@/components/ui/Chip';
import { Input } from '@/components/ui/Input';
import { Spinner } from '@/components/ui/Spinner';
import ArrowLeft from 'lucide-react/icons/arrow-left';
import Save from 'lucide-react/icons/save';
import Check from 'lucide-react/icons/check';
import Send from 'lucide-react/icons/send';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { logError } from '@/lib/logger';
import { adminNewsletters } from '../../api/adminApi';

const AUTOSAVE_DEBOUNCE_MS = 900;
type SaveState = 'idle' | 'dirty' | 'saving' | 'saved' | 'error';

const NewsletterBuilder = lazy(() =>
  import('../../components/NewsletterBuilder').then((module) => ({
    default: module.NewsletterBuilder,
  })),
);

/** Status chip appearance per save state (null = show nothing). */
const SAVE_CHIP: Record<SaveState, { color: 'default' | 'success' | 'warning' | 'danger'; key: string } | null> = {
  idle: null,
  dirty: { color: 'warning', key: 'newsletter_builder.status_unsaved' },
  saving: { color: 'default', key: 'newsletter_builder.status_saving' },
  saved: { color: 'success', key: 'newsletter_builder.status_saved' },
  error: { color: 'danger', key: 'newsletter_builder.status_error' },
};

export function NewsletterDesignStudio() {
  const { id } = useParams<{ id: string }>();
  const { t } = useTranslation('admin_newsletters');
  const navigate = useNavigate();
  const { tenantPath } = useTenant();
  const toast = useToast();

  const [loading, setLoading] = useState(true);
  const [subject, setSubject] = useState('');
  const [preheader, setPreheader] = useState('');
  const [designJson, setDesignJson] = useState<string | null>(null);
  const [initialMjml, setInitialMjml] = useState<string | null>(null);
  const [readOnly, setReadOnly] = useState(false);
  const [saveState, setSaveState] = useState<SaveState>('idle');
  const [sendingTest, setSendingTest] = useState(false);

  usePageTitle(t('newsletter_builder.studio_title'));

  // Latest builder output, persisted by the debounced autosave.
  const latestRef = useRef<{ html: string; designJson: string } | null>(null);
  // Latest subject/preheader, so persist() saves them without re-creating itself.
  const metaRef = useRef<{ subject: string; preheader: string }>({ subject: '', preheader: '' });
  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  useEffect(() => {
    if (!id) return;
    (async () => {
      try {
        const res = await adminNewsletters.get(Number(id));
        if (res.success && res.data) {
          const d = res.data as Record<string, unknown>;
          const loadedSubject = (d.subject as string) || '';
          const loadedPreheader = (d.preview_text as string) || '';
          setSubject(loadedSubject);
          setPreheader(loadedPreheader);
          metaRef.current = { subject: loadedSubject, preheader: loadedPreheader };
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
    if (!id) return true;
    const payload = latestRef.current;
    setSaveState('saving');
    try {
      // Partial update: subject/preheader always; content only once the builder
      // has produced output. Targeting/scheduling on the form is untouched.
      const body: Record<string, unknown> = {
        subject: metaRef.current.subject,
        preview_text: metaRef.current.preheader,
      };
      if (payload) {
        body.content = payload.html;
        body.content_format = 'builder';
        body.design_json = payload.designJson;
      }
      const res = await adminNewsletters.update(Number(id), body);
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

  // Debounced dirty→save, shared by builder edits and subject/preheader edits.
  const queueSave = useCallback(() => {
    if (readOnly) return;
    setSaveState('dirty');
    if (debounceRef.current) clearTimeout(debounceRef.current);
    debounceRef.current = setTimeout(() => void persist(), AUTOSAVE_DEBOUNCE_MS);
  }, [persist, readOnly]);

  const handleBuilderChange = useCallback(
    (payload: { html: string; designJson: string }) => {
      latestRef.current = payload;
      queueSave();
    },
    [queueSave],
  );

  const handleSubjectChange = useCallback(
    (v: string) => {
      setSubject(v);
      metaRef.current.subject = v;
      queueSave();
    },
    [queueSave],
  );

  const handlePreheaderChange = useCallback(
    (v: string) => {
      setPreheader(v);
      metaRef.current.preheader = v;
      queueSave();
    },
    [queueSave],
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

  const handleSendTest = async () => {
    if (!id) return;
    if (debounceRef.current) clearTimeout(debounceRef.current);
    setSendingTest(true);
    try {
      // Save the latest design first so the test reflects what's on the canvas.
      if (!readOnly) await persist();
      const res = await adminNewsletters.sendTest(Number(id));
      if (res.success && res.data?.sent_to) {
        toast.success(t('newsletter_builder.send_test_success', { email: res.data.sent_to }));
      } else {
        toast.error(t('newsletter_builder.send_test_failed'));
      }
    } catch (err) {
      logError('NewsletterDesignStudio: send test failed', err);
      toast.error(t('newsletter_builder.send_test_failed'));
    } finally {
      setSendingTest(false);
    }
  };

  const chip = SAVE_CHIP[saveState];

  return (
    <div className="fixed inset-0 z-50 flex flex-col bg-surface">
      {/* Studio header */}
      <header className="flex shrink-0 items-center gap-3 border-b border-border bg-surface px-3 py-2">
        <Button size="sm" variant="light" startContent={<ArrowLeft size={16} />} onPress={backToForm} className="shrink-0">
          {t('newsletter_builder.back_to_settings')}
        </Button>
        {/* Subject + preheader are editable right here, so authors never have to
            jump back to the form to name the email. */}
        <div className="flex min-w-0 flex-1 flex-col gap-1.5 sm:flex-row">
          <Input
            aria-label={t('newsletter_form.label_subject_line')}
            placeholder={t('newsletter_form.subject_placeholder')}
            value={subject}
            onValueChange={handleSubjectChange}
            variant="secondary"
            size="sm"
            isDisabled={readOnly}
            className="min-w-0 flex-1"
          />
          <Input
            aria-label={t('newsletter_form.label_preview_text')}
            placeholder={t('newsletter_form.preview_text_placeholder')}
            value={preheader}
            onValueChange={handlePreheaderChange}
            variant="secondary"
            size="sm"
            isDisabled={readOnly}
            className="min-w-0 flex-1"
          />
        </div>
        {chip && (
          <Chip size="sm" color={chip.color} variant="soft" className="hidden shrink-0 sm:flex" aria-live="polite">
            {t(chip.key)}
          </Chip>
        )}
        <Button
          size="sm"
          variant="light"
          startContent={<Send size={16} />}
          isLoading={sendingTest}
          onPress={handleSendTest}
          className="shrink-0"
        >
          {t('newsletter_builder.send_test')}
        </Button>
        {!readOnly && (
          <Button
            size="sm"
            variant="secondary"
            startContent={<Save size={16} />}
            isLoading={saveState === 'saving'}
            onPress={handleSaveNow}
            className="shrink-0"
          >
            {t('newsletter_builder.save')}
          </Button>
        )}
        <Button size="sm" variant="primary" startContent={<Check size={16} />} onPress={handleDone} className="shrink-0">
          {t('newsletter_builder.done')}
        </Button>
      </header>

      {/* Builder fills the rest of the viewport */}
      {loading ? (
        <div className="flex flex-1 items-center justify-center" role="status" aria-busy="true">
          <Spinner size="sm" />
        </div>
      ) : (
        <Suspense
          fallback={
            <div className="flex flex-1 items-center justify-center" role="status" aria-busy="true">
              <Spinner size="sm" />
            </div>
          }
        >
          <NewsletterBuilder
            fill
            enableTemplates
            html=""
            designJson={designJson}
            initialMjml={initialMjml}
            readOnly={readOnly}
            onChange={handleBuilderChange}
          />
        </Suspense>
      )}
    </div>
  );
}

export default NewsletterDesignStudio;
