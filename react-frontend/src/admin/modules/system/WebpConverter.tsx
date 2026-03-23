// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * WebP Converter
 * Bulk convert images to WebP format for performance optimization.
 */

import { useState, useEffect, useCallback } from 'react';
import { Card, CardBody, CardHeader, Button, Spinner } from '@heroui/react';
import { Image, Play } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { PageHeader } from '../../components';
import { adminTools } from '../../api/adminApi';

import { useTranslation } from 'react-i18next';
interface WebpStats {
  total_images: number;
  webp_images: number;
  pending_conversion: number;
}

export function WebpConverter() {
  const { t } = useTranslation('admin');
  usePageTitle(t('system.page_title'));
  const toast = useToast();

  const [stats, setStats] = useState<WebpStats | null>(null);
  const [loading, setLoading] = useState(true);
  const [converting, setConverting] = useState(false);

  const fetchStats = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminTools.getWebpStats();
      setStats(res.data ?? null);
    } catch {
      toast.error(t('system.failed_to_load_web_p_stats'));
    } finally {
      setLoading(false);
    }
  }, [toast]);

  useEffect(() => {
    fetchStats();
  }, [fetchStats]);

  const handleConvert = async () => {
    setConverting(true);
    try {
      await adminTools.runWebpConversion();
      toast.success('WebP conversion complete', 'All eligible images have been converted.');
      await fetchStats();
    } catch {
      toast.error('Conversion failed', 'An error occurred during WebP conversion.');
    } finally {
      setConverting(false);
    }
  };

  return (
    <div>
      <PageHeader
        title={t('system.webp_converter_title')}
        description={t('system.webp_converter_desc')}
        actions={
          <Button
            color="primary"
            startContent={!converting ? <Play size={16} /> : undefined}
            onPress={handleConvert}
            isLoading={converting}
            isDisabled={loading || (stats !== null && stats.pending_conversion === 0)}
          >
            Start Conversion
          </Button>
        }
      />
      <Card shadow="sm">
        <CardHeader>
          <h3 className="text-lg font-semibold flex items-center gap-2">
            <Image size={20} /> Image Conversion
          </h3>
        </CardHeader>
        <CardBody>
          {loading ? (
            <div className="flex justify-center py-8">
              <Spinner size="lg" />
            </div>
          ) : (
            <div className="space-y-4">
              <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div className="rounded-lg border border-default-200 p-4 text-center">
                  <p className="text-2xl font-bold text-foreground">
                    {stats?.total_images?.toLocaleString() ?? '--'}
                  </p>
                  <p className="text-sm text-default-500">Total Images</p>
                </div>
                <div className="rounded-lg border border-default-200 p-4 text-center">
                  <p className="text-2xl font-bold text-success">
                    {stats?.webp_images?.toLocaleString() ?? '--'}
                  </p>
                  <p className="text-sm text-default-500">Already WebP</p>
                </div>
                <div className="rounded-lg border border-default-200 p-4 text-center">
                  <p className="text-2xl font-bold text-warning">
                    {stats?.pending_conversion?.toLocaleString() ?? '--'}
                  </p>
                  <p className="text-sm text-default-500">Pending Conversion</p>
                </div>
              </div>
              <p className="text-sm text-default-400">
                WebP images are typically 25-35% smaller than comparable JPEG or PNG files while maintaining similar quality.
                Running conversion will process all non-WebP images in the uploads directory.
              </p>
            </div>
          )}
        </CardBody>
      </Card>
    </div>
  );
}

export default WebpConverter;
