// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * PromotionSelector — Modal for selecting a promotion type for a marketplace listing.
 * Shows available promotion products, prices, durations, and a confirm button.
 */

import { useState, useEffect } from 'react';
import {
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
  Button,
  Card,
  CardBody,
  Chip,
  Spinner,
} from '@heroui/react';
import { Zap, Star, ArrowUpCircle, LayoutGrid, Clock, Check } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';
import type { MarketplacePromotionProduct } from '@/types/marketplace';

interface PromotionSelectorProps {
  isOpen: boolean;
  onClose: () => void;
  listingId: number;
  listingTitle: string;
  onPromoted?: () => void;
}

const TYPE_ICONS: Record<string, React.ElementType> = {
  bump: ArrowUpCircle,
  featured: Star,
  top_of_category: LayoutGrid,
  homepage_carousel: Zap,
};

const TYPE_COLORS: Record<string, 'primary' | 'warning' | 'secondary' | 'success'> = {
  bump: 'primary',
  featured: 'warning',
  top_of_category: 'secondary',
  homepage_carousel: 'success',
};

function formatDuration(hours: number): string {
  if (hours >= 24) {
    const days = Math.round(hours / 24);
    return `${days} day${days !== 1 ? 's' : ''}`;
  }
  return `${hours} hour${hours !== 1 ? 's' : ''}`;
}

export function PromotionSelector({
  isOpen,
  onClose,
  listingId,
  listingTitle,
  onPromoted,
}: PromotionSelectorProps) {
  const { t } = useTranslation('marketplace');
  const [products, setProducts] = useState<MarketplacePromotionProduct[]>([]);
  const [isLoading, setIsLoading] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [selectedType, setSelectedType] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  // Load products when modal opens
  useEffect(() => {
    if (!isOpen) return;
    let cancelled = false;

    const loadProducts = async () => {
      setIsLoading(true);
      setError(null);
      try {
        const response = await api.get<MarketplacePromotionProduct[]>('/v2/marketplace/promotions/products');
        if (!cancelled && response.success && response.data) {
          setProducts(response.data);
        }
      } catch (err) {
        logError('Failed to load promotion products', err);
        if (!cancelled) {
          setError(t('promotions.load_error', 'Failed to load promotion options.'));
        }
      } finally {
        if (!cancelled) setIsLoading(false);
      }
    };

    loadProducts();
    return () => { cancelled = true; };
  }, [isOpen, t]);

  const handleConfirm = async () => {
    if (!selectedType) return;
    setIsSubmitting(true);
    setError(null);

    try {
      const response = await api.post(`/v2/marketplace/listings/${listingId}/promote`, {
        promotion_type: selectedType,
      });
      if (response.success) {
        onPromoted?.();
        onClose();
      } else {
        setError(t('promotions.create_error', 'Failed to create promotion.'));
      }
    } catch (err) {
      logError('Failed to create promotion', err);
      setError(t('promotions.create_error', 'Failed to create promotion.'));
    } finally {
      setIsSubmitting(false);
    }
  };

  const selectedProduct = products.find((p) => p.type === selectedType);

  return (
    <Modal isOpen={isOpen} onClose={onClose} size="lg">
      <ModalContent>
        <ModalHeader className="flex flex-col gap-1">
          <span>{t('promotions.title', 'Promote Listing')}</span>
          <span className="text-sm font-normal text-default-500 truncate">{listingTitle}</span>
        </ModalHeader>

        <ModalBody>
          {isLoading ? (
            <div className="flex justify-center py-8">
              <Spinner size="lg" color="primary" />
            </div>
          ) : error && products.length === 0 ? (
            <p className="text-center text-danger py-4">{error}</p>
          ) : (
            <div className="space-y-3">
              {products.map((product) => {
                const Icon = TYPE_ICONS[product.type] ?? Zap;
                const color = TYPE_COLORS[product.type] ?? 'primary';
                const isSelected = selectedType === product.type;

                return (
                  <Card
                    key={product.type}
                    isPressable
                    onPress={() => setSelectedType(product.type)}
                    className={`border-2 transition-colors ${
                      isSelected
                        ? 'border-primary bg-primary/5'
                        : 'border-divider hover:border-default-300'
                    }`}
                  >
                    <CardBody className="p-4">
                      <div className="flex items-start gap-3">
                        <div className={`p-2 rounded-lg bg-${color}/10`}>
                          <Icon className={`w-5 h-5 text-${color}`} />
                        </div>
                        <div className="flex-1 min-w-0">
                          <div className="flex items-center justify-between gap-2">
                            <h4 className="font-semibold text-foreground">{product.label}</h4>
                            <div className="flex items-center gap-2">
                              <span className="font-bold text-foreground">
                                {product.price > 0
                                  ? `${product.currency} ${product.price.toFixed(2)}`
                                  : t('promotions.free_label', 'Free')}
                              </span>
                              {isSelected && <Check className="w-5 h-5 text-primary" />}
                            </div>
                          </div>
                          <p className="text-sm text-default-500 mt-1">{product.description}</p>
                          <div className="flex items-center gap-1 mt-2">
                            <Clock className="w-3.5 h-3.5 text-default-400" />
                            <span className="text-xs text-default-400">
                              {formatDuration(product.duration_hours)}
                            </span>
                          </div>
                        </div>
                      </div>
                    </CardBody>
                  </Card>
                );
              })}
            </div>
          )}

          {error && products.length > 0 && (
            <p className="text-sm text-danger mt-2">{error}</p>
          )}
        </ModalBody>

        <ModalFooter>
          <Button variant="flat" onPress={onClose}>
            {t('common.cancel', 'Cancel')}
          </Button>
          <Button
            color="primary"
            isDisabled={!selectedType}
            isLoading={isSubmitting}
            onPress={handleConfirm}
          >
            {selectedProduct
              ? t('promotions.confirm_promote', 'Promote for {{price}}', {
                  price: `${selectedProduct.currency} ${selectedProduct.price.toFixed(2)}`,
                })
              : t('promotions.select_type', 'Select a promotion')}
          </Button>
        </ModalFooter>
      </ModalContent>
    </Modal>
  );
}

export default PromotionSelector;
