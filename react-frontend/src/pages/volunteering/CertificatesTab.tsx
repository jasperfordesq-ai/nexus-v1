// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * CertificatesTab - Generate and view volunteer impact certificates
 */

import { useState, useEffect, useCallback, useRef } from 'react';
import { useTranslation } from 'react-i18next';
import { motion } from 'framer-motion';
import { Button, Chip } from '@heroui/react';
import {
  Award,
  Download,
  Plus,
  ExternalLink,
  Calendar,
  Clock,
  Building2,
  AlertTriangle,
  QrCode,
} from 'lucide-react';
import { GlassCard } from '@/components/ui';
import { EmptyState } from '@/components/feedback';
import { api, API_BASE } from '@/lib/api';
import { logError } from '@/lib/logger';
import { useToast } from '@/contexts';

interface Certificate {
  id: number;
  verification_code: string;
  verification_url: string;
  total_hours: number;
  date_range: {
    start: string;
    end: string;
  };
  organizations: { name: string; hours: number; shifts: number }[];
  generated_at: string;
  downloaded_at: string | null;
}

export function CertificatesTab() {
  const { t } = useTranslation('volunteering');
  const [certificates, setCertificates] = useState<Certificate[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [isGenerating, setIsGenerating] = useState(false);

  const toast = useToast();

  // AbortController ref to cancel stale requests
  const abortRef = useRef<AbortController | null>(null);

  // Stable refs for t/toast — avoids re-creating callbacks when i18n namespace loads
  const tRef = useRef(t);
  tRef.current = t;
  const toastRef = useRef(toast);
  toastRef.current = toast;

  const load = useCallback(async () => {
    abortRef.current?.abort();
    const controller = new AbortController();
    abortRef.current = controller;

    try {
      setIsLoading(true);
      setError(null);

      const response = await api.get<{ certificates?: Certificate[] }>(
        '/v2/volunteering/certificates'
      );

      if (controller.signal.aborted) return;
      if (response.success && response.data) {
        const payload = response.data as Record<string, unknown>;
        const items = Array.isArray(payload.items)
          ? (payload.items as Certificate[])
          : Array.isArray(payload.certificates)
            ? (payload.certificates as Certificate[])
            : Array.isArray(response.data)
              ? (response.data as unknown as Certificate[])
              : [];
        setCertificates(items);
      } else {
        setError(tRef.current('certificates.error_load', 'Failed to load certificates'));
      }
    } catch (err) {
      if (controller.signal.aborted) return;
      logError('Failed to load certificates', err);
      setError(tRef.current('certificates.error_load_generic', 'Unable to load certificates.'));
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  const handleGenerate = async () => {
    try {
      setIsGenerating(true);

      const response = await api.post('/v2/volunteering/certificates', {});

      if (response.success) {
        toastRef.current.success(tRef.current('certificates.generated_success', 'Certificate generated!'));
        load();
      } else {
        toastRef.current.error(
          response.error ||
          tRef.current('certificates.no_verified_hours', 'No verified volunteer hours found. Hours must be approved before generating a certificate.')
        );
      }
    } catch (err) {
      logError('Failed to generate certificate', err);
      toastRef.current.error(tRef.current('certificates.generated_error', 'Failed to generate certificate. Please try again.'));
    } finally {
      setIsGenerating(false);
    }
  };

  const handleDownload = (code: string) => {
    window.open(`${API_BASE}/v2/volunteering/certificates/${code}/html`, '_blank');
  };

  const containerVariants = {
    hidden: { opacity: 0 },
    visible: { opacity: 1, transition: { staggerChildren: 0.05 } },
  };

  const itemVariants = {
    hidden: { opacity: 0, y: 20 },
    visible: { opacity: 1, y: 0 },
  };

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-2">
          <Award className="w-5 h-5 text-amber-400" aria-hidden="true" />
          <h2 className="text-lg font-semibold text-theme-primary">{t('certificates.title', 'Impact Certificates')}</h2>
        </div>
        <Button
          size="sm"
          className="bg-gradient-to-r from-rose-500 to-pink-600 text-white"
          startContent={<Plus className="w-4 h-4" aria-hidden="true" />}
          onPress={handleGenerate}
          isLoading={isGenerating}
        >
          {t('certificates.generate', 'Generate Certificate')}
        </Button>
      </div>


      <p className="text-sm text-theme-muted">
        {t('certificates.description', 'Certificates include all of your approved volunteer hours across every organization.')}
      </p>
      {error && !isLoading && (
        <GlassCard className="p-8 text-center">
          <AlertTriangle className="w-12 h-12 text-amber-500 mx-auto mb-4" aria-hidden="true" />
          <p className="text-theme-muted mb-4">{error}</p>
          <Button className="bg-gradient-to-r from-rose-500 to-pink-600 text-white" onPress={load}>
            {t('common.try_again', 'Try Again')}
          </Button>
        </GlassCard>
      )}

      {!error && isLoading && (
        <div className="space-y-4">
          {[1, 2].map((i) => (
            <GlassCard key={i} className="p-5 animate-pulse">
              <div className="h-5 bg-theme-hover rounded w-1/3 mb-3" />
              <div className="h-3 bg-theme-hover rounded w-2/3 mb-3" />
              <div className="h-3 bg-theme-hover rounded w-1/4" />
            </GlassCard>
          ))}
        </div>
      )}

      {!error && !isLoading && certificates.length === 0 && (
        <EmptyState
          icon={<Award className="w-12 h-12" aria-hidden="true" />}
          title={t('certificates.empty_title', 'No certificates yet')}
          description={t('certificates.empty_description', 'Generate a certificate to showcase your volunteer hours.')}
          action={
            <Button
              className="bg-gradient-to-r from-rose-500 to-pink-600 text-white"
              onPress={handleGenerate}
            >
              {t('certificates.generate', 'Generate Certificate')}
            </Button>
          }
        />
      )}

      {!error && !isLoading && certificates.length > 0 && (
        <motion.div
          variants={containerVariants}
          initial="hidden"
          animate="visible"
          className="space-y-4"
        >
          {certificates.map((cert) => (
            <motion.div key={cert.id} variants={itemVariants}>
              <GlassCard className="p-5">
                <div className="flex items-start justify-between gap-4">
                  <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2 mb-2">
                      <Award className="w-5 h-5 text-amber-400" aria-hidden="true" />
                      <h3 className="font-semibold text-theme-primary text-lg">
                        {t('certificates.verified_hours', '{{count}} Verified Hours', { count: cert.total_hours })}
                      </h3>
                    </div>

                    <div className="flex flex-wrap items-center gap-3 text-xs text-theme-subtle mb-3">
                      <span className="flex items-center gap-1">
                        <Calendar className="w-3 h-3" aria-hidden="true" />
                        {new Date(cert.date_range.start).toLocaleDateString()} - {new Date(cert.date_range.end).toLocaleDateString()}
                      </span>
                      <span className="flex items-center gap-1">
                        <Clock className="w-3 h-3" aria-hidden="true" />
                        {t('certificates.generated_on', 'Generated')} {new Date(cert.generated_at).toLocaleDateString()}
                      </span>
                      <span className="flex items-center gap-1">
                        <QrCode className="w-3 h-3" aria-hidden="true" />
                        {cert.verification_code}
                      </span>
                    </div>

                    {cert.organizations.length > 0 && (
                      <div className="flex flex-wrap gap-2">
                        {cert.organizations.map((org, i) => (
                          <Chip key={i} size="sm" variant="flat" startContent={<Building2 className="w-3 h-3" />}>
                            {org.name} ({org.hours}h)
                          </Chip>
                        ))}
                      </div>
                    )}
                  </div>

                  <div className="flex flex-col gap-2 flex-shrink-0">
                    <Button
                      size="sm"
                      className="bg-gradient-to-r from-rose-500 to-pink-600 text-white"
                      startContent={<Download className="w-4 h-4" aria-hidden="true" />}
                      onPress={() => handleDownload(cert.verification_code)}
                    >
                      {t('certificates.download', 'Download')}
                    </Button>
                    <Button
                      size="sm"
                      variant="flat"
                      className="bg-theme-elevated text-theme-muted"
                      startContent={<ExternalLink className="w-4 h-4" aria-hidden="true" />}
                      onPress={() => window.open(cert.verification_url, '_blank')}
                    >
                      {t('certificates.verify', 'Verify')}
                    </Button>
                  </div>
                </div>
              </GlassCard>
            </motion.div>
          ))}
        </motion.div>
      )}

    </div>
  );
}

export default CertificatesTab;
