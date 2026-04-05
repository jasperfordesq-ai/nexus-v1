// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * ModuleCard
 * Displays a single module in the configuration grid with toggle and configure actions.
 */

import { Card, CardBody, Switch, Button, Chip } from '@heroui/react';
import { Settings2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import type { ModuleDefinition } from './moduleRegistry';

interface ModuleCardProps {
  module: ModuleDefinition;
  enabled: boolean;
  onToggle: (id: string, enabled: boolean) => void;
  onConfigure: (module: ModuleDefinition) => void;
  toggling: boolean;
}

export default function ModuleCard({ module, enabled, onToggle, onConfigure, toggling }: ModuleCardProps) {
  const { t } = useTranslation('admin');
  const Icon = module.icon;
  const optionCount = module.configOptions.length;
  const liveCount = module.configOptions.filter(o => !o.comingSoon).length;

  return (
    <Card shadow="sm" className="h-full">
      <CardBody className={`p-4 flex flex-col gap-3 ${!enabled ? 'opacity-60' : ''}`}>
        {/* Header: icon + name + toggle */}
        <div className="flex items-start gap-3">
          <div
            className={`flex-shrink-0 w-10 h-10 rounded-lg flex items-center justify-center ${
              module.type === 'core'
                ? 'bg-secondary/10 text-secondary'
                : 'bg-primary/10 text-primary'
            }`}
          >
            <Icon size={20} />
          </div>
          <div className="flex-1 min-w-0">
            <div className="flex items-center justify-between gap-2">
              <h3 className="text-sm font-semibold truncate">{module.name}</h3>
              <Switch
                size="sm"
                isSelected={enabled}
                isDisabled={toggling}
                onValueChange={(val) => onToggle(module.id, val)}
                aria-label={t('config.toggle_module', { name: module.name })}
                className="flex-shrink-0"
              />
            </div>
            <p className="text-xs text-default-500 line-clamp-2 mt-0.5">{module.description}</p>
          </div>
        </div>

        {/* Footer: option count + configure button */}
        <div className="flex items-center justify-between mt-auto pt-2">
          <div className="flex items-center gap-1.5">
            {optionCount > 0 ? (
              <Chip size="sm" variant="flat" color={liveCount > 0 ? 'primary' : 'default'}>
                {liveCount > 0
                  ? t('config.option_count', { count: liveCount })
                  : t('config.planned_count', { count: optionCount })}
              </Chip>
            ) : (
              <Chip size="sm" variant="flat" color="default">{t('config.no_options')}</Chip>
            )}
            {module.type === 'core' && (
              <Chip size="sm" variant="flat" color="secondary">{t('config.core')}</Chip>
            )}
          </div>
          {optionCount > 0 && (
            <Button
              size="sm"
              variant="flat"
              startContent={<Settings2 size={14} />}
              onPress={() => onConfigure(module)}
            >
              {t('config.configure')}
            </Button>
          )}
        </div>
      </CardBody>
    </Card>
  );
}
