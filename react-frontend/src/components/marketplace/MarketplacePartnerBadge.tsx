import ShieldCheck from 'lucide-react/icons/shield-check';
import { useTranslation } from 'react-i18next';
import { Chip } from '@/components/ui';

interface MarketplacePartnerBadgeProps {
  grantedAt?: string | null;
  size?: 'sm' | 'md' | 'lg';
}

export function MarketplacePartnerBadge({ grantedAt, size = 'sm' }: MarketplacePartnerBadgeProps) {
  const { t } = useTranslation('common');

  if (!grantedAt) return null;

  return (
    <Chip
      color="primary"
      variant="flat"
      size={size}
      startContent={<ShieldCheck className="w-3.5 h-3.5" aria-hidden="true" />}
    >
      {t('marketplace.onboarding.partner_badge')}
    </Chip>
  );
}

export default MarketplacePartnerBadge;
