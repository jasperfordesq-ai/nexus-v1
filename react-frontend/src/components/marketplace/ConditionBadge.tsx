import { useTranslation } from 'react-i18next';
import { Chip } from '@heroui/react';

interface ConditionBadgeProps {
  condition: string | null;
}

const CONDITION_CONFIG: Record<string, { color: 'success' | 'accent' | 'default' | 'warning' | 'danger'; label: string }> = {
  new: { color: 'success', label: 'New' },
  like_new: { color: 'accent', label: 'Like New' },
  good: { color: 'default', label: 'Good' },
  fair: { color: 'warning', label: 'Fair' },
  poor: { color: 'danger', label: 'Poor' },
};

export function ConditionBadge({ condition }: ConditionBadgeProps) {
  const { t } = useTranslation('marketplace');

  if (!condition) {
    return null;
  }

  const config = CONDITION_CONFIG[condition];
  if (!config) {
    return null;
  }

  return (
    <Chip
      color={config.color}
      variant="tertiary"
      size="sm"
      aria-label={t(`condition.${condition}`, config.label)}
    >
      {t(`condition.${condition}`, config.label)}
    </Chip>
  );
}

export default ConditionBadge;
