// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * BuilderToolbar — the labelled top toolbar of the newsletter Design builder.
 *
 * GrapesJS's own default toolbar buttons render as blank squares in this app
 * (no Font Awesome; the app uses lucide-react), so we drive the editor from our
 * OWN lucide + HeroUI buttons instead. Every control has a tooltip + aria-label
 * and its action calls a handler wired to the editor by the orchestrator.
 */

import { Button, Tooltip } from '@/components/ui';
import Undo2 from 'lucide-react/icons/undo-2';
import Redo2 from 'lucide-react/icons/redo-2';
import Monitor from 'lucide-react/icons/monitor';
import Tablet from 'lucide-react/icons/tablet';
import Smartphone from 'lucide-react/icons/smartphone';
import Scan from 'lucide-react/icons/scan';
import CodeIcon from 'lucide-react/icons/code';
import Trash2 from 'lucide-react/icons/trash-2';
import ImageIcon from 'lucide-react/icons/image';
import Images from 'lucide-react/icons/images';
import LayoutTemplate from 'lucide-react/icons/layout-template';
import Eye from 'lucide-react/icons/eye';
import type { ReactNode } from 'react';

export type BuilderDevice = 'Desktop' | 'Tablet' | 'Mobile portrait';

interface BuilderToolbarProps {
  /** False until the editor instance is ready — keeps buttons inert. */
  ready: boolean;
  /** Already-sent newsletter — mutating actions are frozen. */
  readOnly?: boolean;
  device: BuilderDevice;
  showBorders: boolean;
  canUndo: boolean;
  canRedo: boolean;
  /** Image upload in flight — disables the Insert-image control. */
  insertingImage?: boolean;
  /** Show the Templates picker button (off when editing a template itself). */
  showTemplates?: boolean;
  onUndo: () => void;
  onRedo: () => void;
  onSetDevice: (device: BuilderDevice) => void;
  onToggleBorders: () => void;
  onInsertImage: () => void;
  onOpenLibrary: () => void;
  onOpenTemplates: () => void;
  onPreview: () => void;
  onViewCode: () => void;
  onClear: () => void;
  t: (key: string) => string;
  labels?: Partial<{
    toolbarLabel: string;
    deviceDesktop: string;
    deviceTablet: string;
    deviceMobile: string;
    undo: string;
    redo: string;
    borders: string;
    code: string;
    insertImage: string;
    library: string;
    preview: string;
    templates: string;
    clear: string;
  }>;
}

function ToolButton({
  label,
  onPress,
  disabled,
  active,
  children,
}: {
  label: string;
  onPress: () => void;
  disabled?: boolean;
  active?: boolean;
  children: ReactNode;
}) {
  return (
    <Tooltip content={label} placement="bottom">
      <Button
        isIconOnly
        size="sm"
        variant={active ? 'primary' : 'light'}
        isDisabled={disabled}
        onPress={onPress}
        aria-label={label}
        aria-pressed={active}
      >
        {children}
      </Button>
    </Tooltip>
  );
}

export function BuilderToolbar({
  ready,
  readOnly,
  device,
  showBorders,
  canUndo,
  canRedo,
  insertingImage,
  showTemplates,
  onUndo,
  onRedo,
  onSetDevice,
  onToggleBorders,
  onInsertImage,
  onOpenLibrary,
  onOpenTemplates,
  onPreview,
  onViewCode,
  onClear,
  t,
  labels,
}: BuilderToolbarProps) {
  const frozen = !ready || Boolean(readOnly);
  const devices: { id: BuilderDevice; icon: ReactNode; label: string }[] = [
    { id: 'Desktop', icon: <Monitor size={16} />, label: labels?.deviceDesktop ?? t('newsletter_content_editor.tip_device_desktop') },
    { id: 'Tablet', icon: <Tablet size={16} />, label: labels?.deviceTablet ?? t('newsletter_content_editor.tip_device_tablet') },
    { id: 'Mobile portrait', icon: <Smartphone size={16} />, label: labels?.deviceMobile ?? t('newsletter_content_editor.tip_device_mobile') },
  ];

  return (
    <div
      role="toolbar"
      aria-label={labels?.toolbarLabel ?? t('newsletter_content_editor.toolbar_label')}
      className="flex h-12 items-center gap-1 overflow-x-auto border-b border-border bg-surface px-2"
    >
      <ToolButton label={labels?.undo ?? t('newsletter_content_editor.tip_undo')} onPress={onUndo} disabled={frozen || !canUndo}>
        <Undo2 size={16} />
      </ToolButton>
      <ToolButton label={labels?.redo ?? t('newsletter_content_editor.tip_redo')} onPress={onRedo} disabled={frozen || !canRedo}>
        <Redo2 size={16} />
      </ToolButton>

      <span className="mx-1 h-6 w-px shrink-0 bg-border" aria-hidden="true" />

      {devices.map((d) => (
        <ToolButton
          key={d.id}
          label={d.label}
          onPress={() => onSetDevice(d.id)}
          disabled={!ready}
          active={device === d.id}
        >
          {d.icon}
        </ToolButton>
      ))}

      <span className="mx-1 h-6 w-px shrink-0 bg-border" aria-hidden="true" />

      <ToolButton
        label={labels?.borders ?? t('newsletter_content_editor.tip_borders')}
        onPress={onToggleBorders}
        disabled={!ready}
        active={showBorders}
      >
        <Scan size={16} />
      </ToolButton>
      <ToolButton label={labels?.code ?? t('newsletter_content_editor.tip_code')} onPress={onViewCode} disabled={!ready}>
        <CodeIcon size={16} />
      </ToolButton>

      <span className="mx-1 h-6 w-px shrink-0 bg-border" aria-hidden="true" />

      <ToolButton
        label={labels?.insertImage ?? t('newsletter_builder.insert_image')}
        onPress={onInsertImage}
        disabled={frozen || Boolean(insertingImage)}
      >
        <ImageIcon size={16} />
      </ToolButton>

      <ToolButton label={labels?.library ?? t('newsletter_builder.library_title')} onPress={onOpenLibrary} disabled={frozen}>
        <Images size={16} />
      </ToolButton>

      <Button
        size="sm"
        variant="light"
        startContent={<Eye size={16} />}
        isDisabled={!ready}
        onPress={onPreview}
        className="shrink-0"
      >
        {labels?.preview ?? t('newsletter_builder.preview')}
      </Button>

      {showTemplates && (
        <Button
          size="sm"
          variant="light"
          startContent={<LayoutTemplate size={16} />}
          isDisabled={frozen}
          onPress={onOpenTemplates}
          className="shrink-0"
        >
          {labels?.templates ?? t('newsletter_builder.templates')}
        </Button>
      )}

      <span className="flex-1" />

      <ToolButton label={labels?.clear ?? t('newsletter_content_editor.tip_clear')} onPress={onClear} disabled={frozen}>
        <Trash2 size={16} />
      </ToolButton>
    </div>
  );
}

export default BuilderToolbar;
