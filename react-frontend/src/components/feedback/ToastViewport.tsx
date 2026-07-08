// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { type Ref } from 'react';
import { useTranslation } from 'react-i18next';
import X from 'lucide-react/icons/x';
import CheckCircle from 'lucide-react/icons/circle-check-big';
import AlertCircle from 'lucide-react/icons/circle-alert';
import AlertTriangle from 'lucide-react/icons/triangle-alert';
import Info from 'lucide-react/icons/info';
import { motion, AnimatePresence } from '@/lib/motion';
import type { Toast, ToastType } from '@/contexts/ToastContext';

interface ToastViewportProps {
  toasts: Toast[];
  onRemove: (id: string) => void;
}

export default function ToastViewport({ toasts, onRemove }: ToastViewportProps) {
  const { t } = useTranslation('common');
  const urgentToasts = toasts.filter((toast) => toast.type === 'error' || toast.type === 'warning');
  const politeToasts = toasts.filter((toast) => toast.type === 'success' || toast.type === 'info');

  return (
    <div className="fixed bottom-4 right-4 z-[9999] flex flex-col gap-2 max-w-sm w-full pointer-events-none">
      <div
        role="alert"
        aria-live="assertive"
        aria-label={t('toast.aria_urgent_notifications')}
        aria-atomic="false"
        className="flex flex-col gap-2"
      >
        <AnimatePresence mode="popLayout">
          {urgentToasts.map((toast) => (
            <ToastItem key={toast.id} toast={toast} onRemove={onRemove} />
          ))}
        </AnimatePresence>
      </div>

      <div
        role="status"
        aria-label={t('toast.aria_notifications')}
        aria-live="polite"
        aria-atomic="false"
        className="flex flex-col gap-2"
      >
        <AnimatePresence mode="popLayout">
          {politeToasts.map((toast) => (
            <ToastItem key={toast.id} toast={toast} onRemove={onRemove} />
          ))}
        </AnimatePresence>
      </div>
    </div>
  );
}

interface ToastItemProps {
  toast: Toast;
  onRemove: (id: string) => void;
}

const config: Record<ToastType, {
  icon: typeof CheckCircle;
  borderColor: string;
  iconColor: string;
}> = {
  success: {
    icon: CheckCircle,
    borderColor: 'border-emerald-500/40',
    iconColor: 'text-emerald-400',
  },
  error: {
    icon: AlertCircle,
    borderColor: 'border-red-500/40',
    iconColor: 'text-red-400',
  },
  warning: {
    icon: AlertTriangle,
    borderColor: 'border-amber-500/40',
    iconColor: 'text-amber-400',
  },
  info: {
    icon: Info,
    borderColor: 'border-blue-500/40',
    iconColor: 'text-blue-400',
  },
};

function ToastItem({ toast, onRemove, ref }: ToastItemProps & { ref?: Ref<HTMLDivElement> }) {
  const { t } = useTranslation('common');
  const { icon: Icon, borderColor, iconColor } = config[toast.type];

  return (
    <motion.div
      ref={ref}
      initial={{ opacity: 0, y: 20, scale: 0.95 }}
      animate={{ opacity: 1, y: 0, scale: 1 }}
      exit={{ opacity: 0, x: 100, scale: 0.95 }}
      className={`pointer-events-auto bg-slate-900 ${borderColor} border rounded-lg p-4 shadow-lg`}
    >
      <div className="flex items-start gap-3">
        <Icon className={`w-5 h-5 ${iconColor} flex-shrink-0 mt-0.5`} aria-hidden="true" />
        <div className="flex-1 min-w-0">
          <p className="font-medium text-white text-sm">{toast.title}</p>
          {toast.message && (
            <p className="text-white/60 text-sm mt-1">{toast.message}</p>
          )}
        </div>
        <button
          type="button"
          onClick={() => onRemove(toast.id)}
          className="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full p-0 text-white/40 transition-colors hover:text-white focus:outline-none focus:ring-2 focus:ring-white/60"
          aria-label={t('toast.aria_dismiss_notification')}
        >
          <X className="w-4 h-4" aria-hidden="true" />
        </button>
      </div>
    </motion.div>
  );
}
