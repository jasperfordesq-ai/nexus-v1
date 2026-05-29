import { useEffect, useRef } from 'react';
import { FocusScope } from '@react-aria/focus';
import { Separator } from '@/components/ui';
import { Link } from 'react-router-dom';
import X from 'lucide-react/icons/x';
import LightbulbIcon from 'lucide-react/icons/lightbulb';
import TriangleAlertIcon from 'lucide-react/icons/triangle-alert';
import { useTranslation } from 'react-i18next';
import type { HelpArticle } from '../data/helpContent';
import { Button, Chip } from '@/components/ui';

interface AdminHelpDrawerProps {
  article: HelpArticle;
  isOpen: boolean;
  onClose: () => void;
}

export function AdminHelpDrawer({ article, isOpen, onClose }: AdminHelpDrawerProps) {
  const { t } = useTranslation('admin');
  const panelRef = useRef<HTMLDivElement>(null);

  // Move focus into the close button when the drawer opens; close on Escape
  useEffect(() => {
    if (!isOpen) return;

    // Focus the first focusable element in the panel (close button)
    const timer = setTimeout(() => {
      const firstFocusable = panelRef.current?.querySelector<HTMLElement>(
        'button:not([disabled]), a[href], input:not([disabled]), [tabindex]:not([tabindex="-1"])'
      );
      firstFocusable?.focus();
    }, 50); // slight delay to allow CSS transition to start

    const handleKeyDown = (e: KeyboardEvent) => {
      if (e.key === 'Escape') {
        e.preventDefault();
        onClose();
      }
    };
    document.addEventListener('keydown', handleKeyDown, true);
    return () => {
      clearTimeout(timer);
      document.removeEventListener('keydown', handleKeyDown, true);
    };
  }, [isOpen, onClose]);

  return (
    <>
      {/* Backdrop */}
      <div
        className={`fixed inset-0 z-40 bg-black/40 transition-opacity duration-300 ${
          isOpen ? 'opacity-100 pointer-events-auto' : 'opacity-0 pointer-events-none'
        }`}
        aria-hidden="true"
        onClick={onClose}
      />

      {/* Drawer panel — inert when off-screen so keyboard users cannot reach hidden content */}
      <div
        ref={panelRef}
        role="dialog"
        aria-modal="true"
        aria-label={t('help_drawer.aria_label', { title: article.title })}
        inert={!isOpen || undefined}
        className={`fixed inset-y-0 right-0 z-50 flex w-full max-w-[min(24rem,calc(100dvw-var(--safe-area-left)-var(--safe-area-right)))] flex-col bg-overlay shadow-xl transition-transform duration-300 ${
          isOpen ? 'translate-x-0' : 'translate-x-full'
        }`}
      >
        {/* Header */}
        <div className="flex shrink-0 items-start justify-between gap-3 px-5 py-4 border-b border-divider pt-[calc(var(--safe-area-top)+1rem)]">
          <div className="min-w-0">
            <p className="text-xs font-semibold uppercase tracking-wider text-muted mb-0.5">
              {t('help_drawer.label')}
            </p>
            <h2 className="text-base font-bold text-foreground leading-snug">
              {article.title}
            </h2>
          </div>
          <Button
            isIconOnly
            size="sm"
            variant="tertiary"
            onPress={onClose}
            className="mt-0.5 shrink-0 text-muted"
            aria-label={t('help_drawer.close_panel')}
          >
            <X size={18} />
          </Button>
        </div>

        {/* Scrollable body */}
        <div className="flex-1 overflow-y-auto px-5 py-4 pb-[calc(var(--safe-area-bottom)+1rem)] space-y-5">

          {/* Summary */}
          <p className="text-sm text-muted leading-relaxed">
            {article.summary}
          </p>

          {/* Steps */}
          {article.steps && article.steps.length > 0 && (
            <>
              <Separator />
              <div>
                <h3 className="text-xs font-semibold uppercase tracking-wider text-muted mb-3">
                  {t('help_drawer.how_to_use')}
                </h3>
                <ol className="space-y-3">
                  {article.steps.map((step, idx) => (
                    <li key={idx} className="flex gap-3">
                      <span className="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-accent/10 text-xs font-bold text-accent">
                        {idx + 1}
                      </span>
                      <div className="min-w-0">
                        <p className="text-sm font-medium text-foreground leading-snug">
                          {step.label}
                        </p>
                        {step.detail && (
                          <p className="mt-0.5 text-xs text-muted leading-relaxed">
                            {step.detail}
                          </p>
                        )}
                      </div>
                    </li>
                  ))}
                </ol>
              </div>
            </>
          )}

          {/* Tips */}
          {article.tips && article.tips.length > 0 && (
            <>
              <Separator />
              <div>
                <h3 className="text-xs font-semibold uppercase tracking-wider text-muted mb-3">
                  {t('help_drawer.tips')}
                </h3>
                <ul className="space-y-2">
                  {article.tips.map((tip, idx) => (
                    <li
                      key={idx}
                      className="flex gap-2.5 rounded-lg bg-surface px-3 py-2.5 text-xs text-muted leading-relaxed"
                    >
                      <LightbulbIcon
                        size={14}
                        className="mt-0.5 shrink-0 text-warning"
                        aria-hidden="true"
                      />
                      <span>{tip}</span>
                    </li>
                  ))}
                </ul>
              </div>
            </>
          )}

          {/* Caution */}
          {article.caution && (
            <>
              <Separator />
              <div className="flex gap-2.5 rounded-lg border border-danger-200 bg-danger-50 px-3 py-3 text-xs text-danger-700 leading-relaxed">
                <TriangleAlertIcon
                  size={14}
                  className="mt-0.5 shrink-0 text-danger"
                  aria-hidden="true"
                />
                <span>{article.caution}</span>
              </div>
            </>
          )}

          {/* Related pages */}
          {article.relatedPaths && article.relatedPaths.length > 0 && (
            <>
              <Separator />
              <div>
                <h3 className="text-xs font-semibold uppercase tracking-wider text-muted mb-3">
                  {t('help_drawer.related_pages')}
                </h3>
                <div className="flex flex-wrap gap-2">
                  {article.relatedPaths.map((rel) => (
                    <Chip
                      key={rel.path}
                      as={Link}
                      to={rel.path}
                      size="sm"
                      variant="secondary"
                      className="cursor-pointer"
                      onClick={onClose}
                    >
                      {rel.label}
                    </Chip>
                  ))}
                </div>
              </div>
            </>
          )}

          {/* Bottom padding so content doesn't sit right against the edge */}
          <div className="h-4" />
        </div>
      </div>
    </>
  );
}

export default AdminHelpDrawer;
