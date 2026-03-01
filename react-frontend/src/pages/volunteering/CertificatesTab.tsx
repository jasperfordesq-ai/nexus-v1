// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * CertificatesTab - Generate and view volunteer impact certificates (V6)
 */

import { useState, useEffect, useCallback } from 'react';
import { motion } from 'framer-motion';
import {
  Button,
  Input,
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  useDisclosure,
  Chip,
} from '@heroui/react';
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
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';

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
  const [certificates, setCertificates] = useState<Certificate[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [isGenerating, setIsGenerating] = useState(false);

  const { isOpen, onOpen, onClose } = useDisclosure();
  const [startDate, setStartDate] = useState(new Date().getFullYear() + '-01-01');
  const [endDate, setEndDate] = useState(new Date().toISOString().split('T')[0]);

  const load = useCallback(async () => {
    try {
      setIsLoading(true);
      setError(null);

      const response = await api.get<{ data: { certificates: Certificate[] } }>(
        '/v2/volunteering/certificates'
      );

      if (response.success && response.data) {
        const data = response.data as { certificates?: Certificate[] };
        setCertificates(data.certificates ?? []);
      } else {
        setError('Failed to load certificates');
      }
    } catch (err) {
      logError('Failed to load certificates', err);
      setError('Unable to load certificates.');
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

      const response = await api.post('/v2/volunteering/certificates', {
        start_date: startDate,
        end_date: endDate,
      });

      if (response.success) {
        onClose();
        load();
      }
    } catch (err) {
      logError('Failed to generate certificate', err);
    } finally {
      setIsGenerating(false);
    }
  };

  const handleDownload = (code: string) => {
    const apiBase = import.meta.env.VITE_API_URL || '';
    window.open(`${apiBase}/v2/volunteering/certificates/${code}/html`, '_blank');
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
          <h2 className="text-lg font-semibold text-theme-primary">Impact Certificates</h2>
        </div>
        <Button
          size="sm"
          className="bg-gradient-to-r from-rose-500 to-pink-600 text-white"
          startContent={<Plus className="w-4 h-4" aria-hidden="true" />}
          onPress={onOpen}
        >
          Generate Certificate
        </Button>
      </div>

      {error && !isLoading && (
        <GlassCard className="p-8 text-center">
          <AlertTriangle className="w-12 h-12 text-amber-500 mx-auto mb-4" aria-hidden="true" />
          <p className="text-theme-muted mb-4">{error}</p>
          <Button className="bg-gradient-to-r from-rose-500 to-pink-600 text-white" onPress={load}>
            Try Again
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
          title="No certificates yet"
          description="Generate a certificate to showcase your volunteer hours."
          action={
            <Button
              className="bg-gradient-to-r from-rose-500 to-pink-600 text-white"
              onPress={onOpen}
            >
              Generate Certificate
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
                        {cert.total_hours} Verified Hours
                      </h3>
                    </div>

                    <div className="flex flex-wrap items-center gap-3 text-xs text-theme-subtle mb-3">
                      <span className="flex items-center gap-1">
                        <Calendar className="w-3 h-3" aria-hidden="true" />
                        {new Date(cert.date_range.start).toLocaleDateString()} - {new Date(cert.date_range.end).toLocaleDateString()}
                      </span>
                      <span className="flex items-center gap-1">
                        <Clock className="w-3 h-3" aria-hidden="true" />
                        Generated {new Date(cert.generated_at).toLocaleDateString()}
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
                      Download
                    </Button>
                    <Button
                      size="sm"
                      variant="flat"
                      className="bg-theme-elevated text-theme-muted"
                      startContent={<ExternalLink className="w-4 h-4" aria-hidden="true" />}
                      onPress={() => window.open(cert.verification_url, '_blank')}
                    >
                      Verify
                    </Button>
                  </div>
                </div>
              </GlassCard>
            </motion.div>
          ))}
        </motion.div>
      )}

      {/* Generate Certificate Modal */}
      <Modal isOpen={isOpen} onClose={onClose} size="lg" classNames={{
        base: 'bg-content1 border border-theme-default',
      }}>
        <ModalContent>
          <ModalHeader className="text-theme-primary">Generate Impact Certificate</ModalHeader>
          <ModalBody className="space-y-4">
            <p className="text-sm text-theme-muted">
              Generate a certificate showing your verified volunteer hours for a specific date range.
              Only approved hours will be included.
            </p>
            <Input
              type="date"
              label="Start Date"
              value={startDate}
              onChange={(e) => setStartDate(e.target.value)}
              classNames={{
                input: 'bg-transparent text-theme-primary',
                inputWrapper: 'bg-theme-elevated border-theme-default',
              }}
            />
            <Input
              type="date"
              label="End Date"
              value={endDate}
              onChange={(e) => setEndDate(e.target.value)}
              classNames={{
                input: 'bg-transparent text-theme-primary',
                inputWrapper: 'bg-theme-elevated border-theme-default',
              }}
            />
          </ModalBody>
          <ModalFooter>
            <Button variant="flat" onPress={onClose} className="text-theme-muted">Cancel</Button>
            <Button
              className="bg-gradient-to-r from-rose-500 to-pink-600 text-white"
              onPress={handleGenerate}
              isLoading={isGenerating}
              isDisabled={!startDate || !endDate}
            >
              Generate
            </Button>
          </ModalFooter>
        </ModalContent>
      </Modal>
    </div>
  );
}

export default CertificatesTab;
