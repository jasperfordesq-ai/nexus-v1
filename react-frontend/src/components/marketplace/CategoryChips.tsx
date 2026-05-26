import LayoutGrid from 'lucide-react/icons/layout-grid';
import { useTranslation } from 'react-i18next';
import type { Key } from '@heroui/react';
import { ToggleButton, ToggleButtonGroup } from '@heroui/react';
import type { MarketplaceCategory } from '@/types/marketplace';

interface CategoryChipsProps {
  categories: MarketplaceCategory[];
  activeId?: number;
  onSelect: (id: number | null) => void;
}

export function CategoryChips({ categories, activeId, onSelect }: CategoryChipsProps) {
  const { t } = useTranslation('marketplace');
  const selectedKey = activeId == null ? 'all' : String(activeId);

  const handleSelectionChange = (keys: Set<Key>) => {
    const nextKey = Array.from(keys)[0];
    if (nextKey === 'all' || nextKey == null) {
      onSelect(null);
      return;
    }
    onSelect(Number(nextKey));
  };

  return (
    <ToggleButtonGroup
      aria-label={t('categories.label')}
      className="flex flex-wrap gap-2"
      disallowEmptySelection
      isDetached
      selectedKeys={[selectedKey]}
      selectionMode="single"
      size="sm"
      onSelectionChange={handleSelectionChange}
    >
      <ToggleButton id="all">
        <LayoutGrid className="w-3.5 h-3.5" aria-hidden="true" />
        {t('categories.all')}
      </ToggleButton>

      {categories.map((category) => (
        <ToggleButton
          key={category.id}
          id={String(category.id)}
        >
          {category.name}
        </ToggleButton>
      ))}
    </ToggleButtonGroup>
  );
}

export default CategoryChips;
