// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * ModuleConfigModal
 * Modal for viewing and editing a module's granular configuration options.
 * Behavior varies by configSource:
 * - broker_config: editable exchange workflow config via adminBroker API
 * - group_config: editable group config (tabs, policies) via adminConfig group API
 * - onboarding_config: link-out to dedicated onboarding settings page
 * - none/others: "Coming Soon" placeholders
 */

import { useState, useEffect, useCallback } from 'react';
import {
  Modal, ModalContent, ModalHeader, ModalBody, ModalFooter,
  Card, CardBody, Switch, Input, Select, SelectItem,
  Button, Chip, Spinner, Divider,
} from '@heroui/react';
import { ExternalLink, Save, Info, Construction } from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { useToast, useTenant } from '@/contexts';
import { adminBroker, adminConfig } from '../../api/adminApi';
import type { BrokerConfig } from '../../api/types';
import type { ModuleDefinition, ConfigOption } from './moduleRegistry';
import { getOptionCategories } from './moduleRegistry';

interface ModuleConfigModalProps {
  module: ModuleDefinition | null;
  isOpen: boolean;
  onClose: () => void;
}

export default function ModuleConfigModal({ module, isOpen, onClose }: ModuleConfigModalProps) {
  const { t } = useTranslation('admin');
  const toast = useToast();
  const navigate = useNavigate();

  const nameKey = module ? `config.module_name_${module.id}` : '';
  const descKey = module ? `config.module_desc_${module.id}` : '';
  const translatedName = t(nameKey);
  const translatedDesc = t(descKey);
  const moduleName = module ? (translatedName === nameKey ? module.name : translatedName) : '';
  const moduleDesc = module ? (translatedDesc === descKey ? module.description : translatedDesc) : '';
  const { tenantPath, refreshTenant } = useTenant();
  const [loading, setLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [hasChanges, setHasChanges] = useState(false);

  // Broker config state
  const [brokerConfig, setBrokerConfig] = useState<BrokerConfig | null>(null);

  // Group config state
  const [groupConfig, setGroupConfig] = useState<Record<string, boolean | number | string> | null>(null);

  // Listing config state
  const [listingConfig, setListingConfig] = useState<Record<string, boolean | number | string> | null>(null);

  // Volunteering config state
  const [volunteeringConfig, setVolunteeringConfig] = useState<Record<string, boolean | number | string> | null>(null);

  // Job config state
  const [jobConfig, setJobConfig] = useState<Record<string, boolean | number | string> | null>(null);

  // Identity verification config state
  const [identityConfig, setIdentityConfig] = useState<Record<string, boolean | number | string> | null>(null);

  // ── Loaders ───────────────────────────────────────────────────────────────

  const loadBrokerConfig = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminBroker.getConfiguration();
      if (res.success && res.data) {
        setBrokerConfig(res.data);
      }
    } catch {
      toast.error("Modal Broker Load failed");
    } finally {
      setLoading(false);
    }
  }, [toast])


  const loadGroupConfig = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminConfig.getGroupConfig();
      if (res.success && res.data) {
        setGroupConfig(res.data.config);
      }
    } catch {
      toast.error("Modal Group Load failed");
    } finally {
      setLoading(false);
    }
  }, [toast])


  const loadListingConfig = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminConfig.getListingConfig();
      if (res.success && res.data) {
        setListingConfig(res.data.config);
      }
    } catch {
      toast.error("Modal Listing Load failed");
    } finally {
      setLoading(false);
    }
  }, [toast])


  const loadVolunteeringConfig = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminConfig.getVolunteeringConfig();
      if (res.success && res.data) {
        setVolunteeringConfig(res.data.config);
      }
    } catch {
      toast.error("Modal Volunteering Load failed");
    } finally {
      setLoading(false);
    }
  }, [toast])


  const loadJobConfig = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminConfig.getJobConfig();
      if (res.success && res.data) {
        setJobConfig(res.data.config);
      }
    } catch {
      toast.error("Modal Job Load failed");
    } finally {
      setLoading(false);
    }
  }, [toast])


  const loadIdentityConfig = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminConfig.getIdentityConfig();
      if (res.success && res.data) {
        setIdentityConfig(res.data.config);
      }
    } catch {
      toast.error("Modal Identity Load failed");
    } finally {
      setLoading(false);
    }
  }, [toast])


  useEffect(() => {
    if (!isOpen || !module) {
      setHasChanges(false);
      setBrokerConfig(null);
      setGroupConfig(null);
      setListingConfig(null);
      setVolunteeringConfig(null);
      setJobConfig(null);
      setIdentityConfig(null);
      return;
    }
    if (module.configSource === 'broker_config') {
      loadBrokerConfig();
    } else if (module.configSource === 'group_config') {
      loadGroupConfig();
    } else if (module.configSource === 'listing_config') {
      loadListingConfig();
    } else if (module.configSource === 'volunteering_config') {
      loadVolunteeringConfig();
    } else if (module.configSource === 'job_config') {
      loadJobConfig();
    } else if (module.configSource === 'identity_config') {
      loadIdentityConfig();
    }
  }, [isOpen, module, loadBrokerConfig, loadGroupConfig, loadListingConfig, loadVolunteeringConfig, loadJobConfig, loadIdentityConfig]);

  // ── Save handlers ─────────────────────────────────────────────────────────

  async function handleSaveBrokerConfig() {
    if (!brokerConfig) return;
    setSaving(true);
    try {
      const res = await adminBroker.saveConfiguration(brokerConfig);
      if (res.success) {
        toast.success("Modal Broker saved");
        setHasChanges(false);
        onClose();
      } else {
        toast.error("Modal Save failed");
      }
    } catch {
      toast.error("Modal Save failed");
    } finally {
      setSaving(false);
    }
  }

  async function handleSaveGroupConfig() {
    if (!groupConfig) return;
    setSaving(true);
    try {
      const res = await adminConfig.updateGroupConfigBulk(groupConfig);
      if (res.success) {
        toast.success("Modal Group saved");
        setHasChanges(false);
        refreshTenant();
        onClose();
      } else {
        toast.error("Modal Save failed");
      }
    } catch {
      toast.error("Modal Save failed");
    } finally {
      setSaving(false);
    }
  }

  async function handleSaveListingConfig() {
    if (!listingConfig) return;
    setSaving(true);
    try {
      const res = await adminConfig.updateListingConfigBulk(listingConfig);
      if (res.success) {
        toast.success("Modal Listing saved");
        setHasChanges(false);
        refreshTenant();
        onClose();
      } else {
        toast.error("Modal Save failed");
      }
    } catch {
      toast.error("Modal Save failed");
    } finally {
      setSaving(false);
    }
  }

  async function handleSaveVolunteeringConfig() {
    if (!volunteeringConfig) return;
    setSaving(true);
    try {
      const res = await adminConfig.updateVolunteeringConfigBulk(volunteeringConfig);
      if (res.success) {
        toast.success("Modal Volunteering saved");
        setHasChanges(false);
        refreshTenant();
        onClose();
      } else {
        toast.error("Modal Save failed");
      }
    } catch {
      toast.error("Modal Save failed");
    } finally {
      setSaving(false);
    }
  }

  async function handleSaveJobConfig() {
    if (!jobConfig) return;
    setSaving(true);
    try {
      const res = await adminConfig.updateJobConfigBulk(jobConfig);
      if (res.success) {
        toast.success("Modal Job saved");
        setHasChanges(false);
        refreshTenant();
        onClose();
      } else {
        toast.error("Modal Save failed");
      }
    } catch {
      toast.error("Modal Save failed");
    } finally {
      setSaving(false);
    }
  }

  async function handleSaveIdentityConfig() {
    if (!identityConfig) return;
    setSaving(true);
    try {
      const res = await adminConfig.updateIdentityConfigBulk(identityConfig);
      if (res.success) {
        toast.success("Modal Identity saved");
        setHasChanges(false);
        refreshTenant();
      } else {
        toast.error("Modal Save failed");
      }
    } catch {
      toast.error("Modal Save failed");
    } finally {
      setSaving(false);
    }
  }

  // ── Value updaters ────────────────────────────────────────────────────────

  function updateBrokerValue<K extends keyof BrokerConfig>(key: K, value: BrokerConfig[K]) {
    setBrokerConfig(prev => prev ? { ...prev, [key]: value } : prev);
    setHasChanges(true);
  }

  function updateGroupValue(key: string, value: boolean | number | string) {
    setGroupConfig(prev => prev ? { ...prev, [key]: value } : prev);
    setHasChanges(true);
  }

  function updateListingValue(key: string, value: boolean | number | string) {
    setListingConfig(prev => prev ? { ...prev, [key]: value } : prev);
    setHasChanges(true);
  }

  function updateVolunteeringValue(key: string, value: boolean | number | string) {
    setVolunteeringConfig(prev => prev ? { ...prev, [key]: value } : prev);
    setHasChanges(true);
  }

  function updateJobValue(key: string, value: boolean | number | string) {
    setJobConfig(prev => prev ? { ...prev, [key]: value } : prev);
    setHasChanges(true);
  }

  function updateIdentityValue(key: string, value: boolean | number | string) {
    setIdentityConfig(prev => prev ? { ...prev, [key]: value } : prev);
    setHasChanges(true);
  }

  function handleNavigateToDetail() {
    if (module?.detailPageUrl) {
      onClose();
      navigate(tenantPath(module.detailPageUrl));
    }
  }

  if (!module) return null;

  const Icon = module.icon;
  const categories = getOptionCategories(module);
  const allComingSoon = module.configOptions.every(o => o.comingSoon);
  const isLinkOut = module.configSource === 'onboarding_config';
  const isBroker = module.configSource === 'broker_config';
  const isGroupConfig = module.configSource === 'group_config';
  const isListingConfig = module.configSource === 'listing_config';
  const isVolunteeringConfig = module.configSource === 'volunteering_config';
  const isJobConfig = module.configSource === 'job_config';
  const isIdentityConfig = module.configSource === 'identity_config';
  const isEditable = isBroker || isGroupConfig || isListingConfig || isVolunteeringConfig || isJobConfig || isIdentityConfig;

  return (
    <Modal size="4xl" isOpen={isOpen} onClose={onClose} scrollBehavior="inside">
      <ModalContent>
        <ModalHeader className="flex items-center gap-3">
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
            <div className="flex items-center gap-2">
              <span>{`Modal`}</span>
              {!isEditable && (
                <Chip size="sm" variant="flat" color="warning" startContent={<Construction size={12} />}>
                  {"Beta"}
                </Chip>
              )}
            </div>
            <p className="text-sm font-normal text-default-500">{moduleDesc}</p>
          </div>
        </ModalHeader>

        <ModalBody>
          {/* Loading state */}
          {isEditable && loading && (
            <div className="flex justify-center py-8">
              <Spinner size="lg" />
            </div>
          )}

          {/* Link-out: onboarding */}
          {isLinkOut && (
            <Card className="bg-default-50">
              <CardBody className="flex flex-col items-center gap-4 py-8">
                <Info size={40} className="text-default-400" />
                <div className="text-center">
                  <p className="text-sm text-default-600">
                    {"Modal Onboarding."}
                  </p>
                </div>
                {module.detailPageUrl && (
                  <Button
                    color="primary"
                    variant="flat"
                    startContent={<ExternalLink size={16} />}
                    onPress={handleNavigateToDetail}
                  >
                    {"Modal Go to Onboarding"}
                  </Button>
                )}
              </CardBody>
            </Card>
          )}

          {/* All coming-soon notice */}
          {!isLinkOut && !isEditable && allComingSoon && module.configOptions.length > 0 && (
            <Card className="bg-warning-50 dark:bg-warning-50/10 mb-4">
              <CardBody className="flex flex-row items-center gap-3 py-3">
                <Construction size={18} className="text-warning flex-shrink-0" />
                <p className="text-sm text-warning-700 dark:text-warning-400">
                  {"Modal Coming Soon Notice"}
                </p>
              </CardBody>
            </Card>
          )}

          {/* Config options grouped by category */}
          {!isLinkOut && (!isEditable || !loading) && categories.map(category => {
            const categoryOptions = module.configOptions.filter(o => o.category === category);
            return (
              <div key={category} className="mb-5 rounded-lg border border-default-200 bg-default-50/50">
                <div className="px-5 pt-4 pb-1">
                  <h4 className="text-sm font-semibold text-default-700">{category}</h4>
                </div>
                <div className="px-5 pb-4">
                  {categoryOptions.map((option, idx) => {
                    // Determine the current value based on config source
                    let currentValue: boolean | number | string = option.defaultValue;
                    if (isBroker && brokerConfig) {
                      currentValue = brokerConfig[option.key as keyof BrokerConfig];
                    } else if (isGroupConfig && groupConfig) {
                      currentValue = groupConfig[option.key] ?? option.defaultValue;
                    } else if (isListingConfig && listingConfig) {
                      currentValue = listingConfig[option.key] ?? option.defaultValue;
                    } else if (isVolunteeringConfig && volunteeringConfig) {
                      currentValue = volunteeringConfig[option.key] ?? option.defaultValue;
                    } else if (isJobConfig && jobConfig) {
                      currentValue = jobConfig[option.key] ?? option.defaultValue;
                    } else if (isIdentityConfig && identityConfig) {
                      currentValue = identityConfig[option.key] ?? option.defaultValue;
                    }

                    return (
                      <div key={option.key}>
                        {idx > 0 && <Divider className="my-2" />}
                        <ConfigOptionRow
                          option={option}
                          value={currentValue}
                          onChange={(val) => {
                            if (isBroker) {
                              updateBrokerValue(option.key as keyof BrokerConfig, val as never);
                            } else if (isGroupConfig) {
                              updateGroupValue(option.key, val);
                            } else if (isListingConfig) {
                              updateListingValue(option.key, val);
                            } else if (isVolunteeringConfig) {
                              updateVolunteeringValue(option.key, val);
                            } else if (isJobConfig) {
                              updateJobValue(option.key, val);
                            } else if (isIdentityConfig) {
                              updateIdentityValue(option.key, val);
                            }
                          }}
                          disabled={option.comingSoon === true || (isBroker && !brokerConfig) || (isGroupConfig && !groupConfig) || (isListingConfig && !listingConfig) || (isVolunteeringConfig && !volunteeringConfig) || (isJobConfig && !jobConfig) || (isIdentityConfig && !identityConfig)}
                        />
                      </div>
                    );
                  })}
                </div>
              </div>
            );
          })}

          {/* Detail page link for broker config */}
          {isBroker && module.detailPageUrl && (
            <div className="flex justify-center pt-2">
              <Button
                variant="light"
                size="sm"
                startContent={<ExternalLink size={14} />}
                onPress={handleNavigateToDetail}
              >
                {"Modal Open Broker Page"}
              </Button>
            </div>
          )}
        </ModalBody>

        <ModalFooter>
          <Button variant="flat" onPress={onClose}>
            {isEditable && hasChanges ? "Cancel" : "Close"}
          </Button>
          {isEditable && (
            <Button
              color="primary"
              startContent={<Save size={16} />}
              isLoading={saving}
              isDisabled={!hasChanges || (isBroker && !brokerConfig) || (isGroupConfig && !groupConfig) || (isListingConfig && !listingConfig) || (isVolunteeringConfig && !volunteeringConfig) || (isJobConfig && !jobConfig) || (isIdentityConfig && !identityConfig)}
              onPress={isBroker ? handleSaveBrokerConfig : isListingConfig ? handleSaveListingConfig : isVolunteeringConfig ? handleSaveVolunteeringConfig : isJobConfig ? handleSaveJobConfig : isIdentityConfig ? handleSaveIdentityConfig : handleSaveGroupConfig}
            >
              {"Save Changes"}
            </Button>
          )}
        </ModalFooter>
      </ModalContent>
    </Modal>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Config Option Row
// ─────────────────────────────────────────────────────────────────────────────

interface ConfigOptionRowProps {
  option: ConfigOption;
  value: boolean | number | string;
  onChange: (value: boolean | number | string) => void;
  disabled: boolean;
}

function ConfigOptionRow({ option, value, onChange, disabled }: ConfigOptionRowProps) {
  const { t } = useTranslation('admin');
  return (
    <div className="flex items-start justify-between gap-6 py-2.5">
      <div className="flex-1 min-w-0">
        <div className="flex items-center gap-2 flex-wrap">
          <span className="text-sm font-medium">{option.label}</span>
          {option.comingSoon && (
            <Chip size="sm" variant="flat" color="warning">{"Coming Soon"}</Chip>
          )}
        </div>
        <p className="text-xs text-default-500 mt-1 leading-relaxed">{option.description}</p>
      </div>
      <div className="flex-shrink-0 pt-0.5">
        {option.type === 'boolean' && (
          <Switch
            size="sm"
            isSelected={value as boolean}
            isDisabled={disabled}
            onValueChange={(val) => onChange(val)}
            aria-label={option.label}
          />
        )}
        {option.type === 'number' && (
          <Input
            type="number"
            size="sm"
            variant="bordered"
            className="w-28"
            value={String(value)}
            min={option.min}
            max={option.max}
            isDisabled={disabled}
            onValueChange={(val) => onChange(Number(val) || 0)}
            aria-label={option.label}
          />
        )}
        {option.type === 'string' && (
          <Input
            size="sm"
            variant="bordered"
            className="w-56"
            value={value as string}
            isDisabled={disabled}
            onValueChange={(val) => onChange(val)}
            aria-label={option.label}
          />
        )}
        {option.type === 'select' && option.choices && (
          <Select
            size="sm"
            variant="bordered"
            className="w-40"
            selectedKeys={[value as string]}
            isDisabled={disabled}
            onSelectionChange={(keys) => {
              const selected = Array.from(keys)[0];
              if (selected) onChange(selected as string);
            }}
            aria-label={option.label}
          >
            {option.choices.map(c => (
              <SelectItem key={c.value}>{c.label}</SelectItem>
            ))}
          </Select>
        )}
      </div>
    </div>
  );
}
