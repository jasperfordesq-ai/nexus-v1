// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Mega Menu
 * Compatibility wrapper for the shared desktop navigation panel used by
 * the header's grouped "More" menu.
 */

import Menu from 'lucide-react/icons/menu';
import { useTranslation } from 'react-i18next';
import { DesktopNavPanel, type DesktopNavPanelItem, type DesktopNavPanelSection } from './DesktopNavPanel';

export type MegaMenuItem = DesktopNavPanelItem;
export type MegaMenuSection = DesktopNavPanelSection;

interface MegaMenuProps {
  isOpen: boolean;
  onOpenChange: (open: boolean) => void;
  isActive: boolean;
  leftSections: MegaMenuSection[];
  rightSections: MegaMenuSection[];
  onNavigate: (path: string) => void;
}

export function MegaMenu({
  isOpen,
  onOpenChange,
  isActive,
  leftSections,
  rightSections,
  onNavigate,
}: MegaMenuProps) {
  const { t } = useTranslation('common');

  return (
    <>
      <div className="sr-only" aria-live="polite" aria-atomic="true">
        {isOpen ? t('accessibility.menu_opened') : ''}
      </div>
      <DesktopNavPanel
        ariaLabel={t('aria.more_navigation')}
        isActive={isActive}
        isOpen={isOpen}
        leftSections={leftSections}
        onNavigate={onNavigate}
        onOpenChange={onOpenChange}
        rightSections={rightSections}
        triggerIcon={Menu}
        triggerLabel={t('nav.more')}
      />
    </>
  );
}

export default MegaMenu;
