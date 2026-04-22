// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Translation Configuration (INT9 + INT10)
 * Per-tenant translation settings and glossary management.
 */

import { useState, useCallback, useEffect } from 'react';
import {
  Card, CardBody, CardHeader, Switch, Spinner, Button, Divider,
  Select, SelectItem, Input,
  Table, TableHeader, TableColumn, TableBody, TableRow, TableCell,
} from '@heroui/react';
import { Settings, BookOpen, Trash2, Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { PageHeader } from '../../components';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

interface GlossaryEntry {
  id: number;
  source_term: string;
  target_term: string;
  target_language: string;
}

type ConfigValue = string | number | boolean;

// ─────────────────────────────────────────────────────────────────────────────
// Constants
// ─────────────────────────────────────────────────────────────────────────────

const ENGINE_OPTIONS = [
  { key: 'openai', label: 'OpenAI' },
  { key: 'deepl', label: 'DeepL' },
  { key: 'google', label: 'Google Translate' },
];

const LANGUAGES = [
  { code: 'en', label: 'English' },
  { code: 'fr', label: 'Fran\u00e7ais' },
  { code: 'de', label: 'Deutsch' },
  { code: 'es', label: 'Espa\u00f1ol' },
  { code: 'it', label: 'Italiano' },
  { code: 'pt', label: 'Portugu\u00eas' },
  { code: 'ga', label: 'Gaeilge' },
  { code: 'nl', label: 'Nederlands' },
  { code: 'pl', label: 'Polski' },
  { code: 'ja', label: '\u65E5\u672C\u8A9E' },
  { code: 'ar', label: '\u0627\u0644\u0639\u0631\u0628\u064A\u0629' },
];

const CONFIG_KEYS: Record<string, { labelKey: string; descKey: string }> = {
  'translation.enabled': {
    labelKey: 'config.translation_enabled_label',
    descKey: 'config.translation_enabled_desc',
  },
  'translation.engine': {
    labelKey: 'config.translation_engine_label',
    descKey: 'config.translation_engine_desc',
  },
  'translation.context_aware': {
    labelKey: 'config.translation_context_aware_label',
    descKey: 'config.translation_context_aware_desc',
  },
  'translation.context_messages': {
    labelKey: 'config.translation_context_messages_label',
    descKey: 'config.translation_context_messages_desc',
  },
  'translation.auto_translate_default': {
    labelKey: 'config.translation_auto_translate_label',
    descKey: 'config.translation_auto_translate_desc',
  },
  'translation.max_per_user_per_hour': {
    labelKey: 'config.translation_rate_limit_label',
    descKey: 'config.translation_rate_limit_desc',
  },
  'translation.glossary_enabled': {
    labelKey: 'config.translation_glossary_enabled_label',
    descKey: 'config.translation_glossary_enabled_desc',
  },
};

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export function TranslationConfig() {
  const { t } = useTranslation('admin');
  usePageTitle("Translation Settings");
  const toast = useToast();

  const meta = (key: string) => {
    const entry = CONFIG_KEYS[key];
    if (!entry) return { label: key, description: '' };
    return { label: t(entry.labelKey), description: t(entry.descKey) };
  };

  // Config state
  const [config, setConfig] = useState<Record<string, ConfigValue>>({});
  const [defaults, setDefaults] = useState<Record<string, ConfigValue>>({});
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState<string | null>(null);

  // Glossary state
  const [glossary, setGlossary] = useState<GlossaryEntry[]>([]);
  const [glossaryLoading, setGlossaryLoading] = useState(false);
  const [newSource, setNewSource] = useState('');
  const [newTarget, setNewTarget] = useState('');
  const [newLang, setNewLang] = useState('');
  const [addingEntry, setAddingEntry] = useState(false);
  const [deletingId, setDeletingId] = useState<number | null>(null);

  // ── Data loading ──────────────────────────────────────────────────────────

  const loadConfig = useCallback(async () => {
    try {
      setLoading(true);
      const response = await api.get<{ config: Record<string, ConfigValue>; defaults: Record<string, ConfigValue> }>('/v2/admin/config/translation');
      // API client already unwraps response.data.data → response.data
      const payload = response.data as { config?: Record<string, ConfigValue>; defaults?: Record<string, ConfigValue> } | undefined;
      if (payload) {
        setConfig(payload.config || {});
        setDefaults(payload.defaults || {});
      }
    } catch {
      toast.error("Failed to load translation settings");
    } finally {
      setLoading(false);
    }
  }, [toast, t])

  const loadGlossary = useCallback(async () => {
    try {
      setGlossaryLoading(true);
      const response = await api.get<{ items: GlossaryEntry[]; total: number }>('/v2/admin/translation/glossary');
      const payload = response.data as { items?: GlossaryEntry[] } | undefined;
      if (payload) {
        setGlossary(payload.items || []);
      }
    } catch {
      toast.error("Failed to load glossary");
    } finally {
      setGlossaryLoading(false);
    }
  }, [toast, t])

  useEffect(() => {
    loadConfig();
  }, [loadConfig]);

  const isGlossaryEnabled = config['translation.glossary_enabled'];

  useEffect(() => {
    if (isGlossaryEnabled) {
      loadGlossary();
    }
  }, [isGlossaryEnabled, loadGlossary]);

  // ── Config save ───────────────────────────────────────────────────────────

  const getValue = (key: string): ConfigValue => {
    return config[key] ?? defaults[key] ?? '';
  };

  const saveSetting = async (key: string, value: ConfigValue) => {
    setSaving(key);
    try {
      await api.put('/v2/admin/config/translation', { key, value });
      setConfig((prev) => ({ ...prev, [key]: value }));
      toast.success(`${meta(key).label} updated`);
    } catch {
      toast.error(`Failed to update ${meta(key).label}`);
    } finally {
      setSaving(null);
    }
  };

  // ── Glossary actions ──────────────────────────────────────────────────────

  const handleAddEntry = async () => {
    if (!newSource.trim() || !newTarget.trim() || !newLang) {
      toast.error("All fields are required");
      return;
    }
    setAddingEntry(true);
    try {
      await api.post('/v2/admin/translation/glossary', {
        source_term: newSource.trim(),
        target_term: newTarget.trim(),
        target_language: newLang,
      });
      toast.success("Glossary entry added");
      setNewSource('');
      setNewTarget('');
      setNewLang('');
      loadGlossary();
    } catch {
      toast.error("Failed to add glossary entry");
    } finally {
      setAddingEntry(false);
    }
  };

  const handleDeleteEntry = async (id: number) => {
    setDeletingId(id);
    try {
      await api.delete(`/v2/admin/translation/glossary/${id}`);
      setGlossary((prev) => prev.filter((e) => e.id !== id));
      toast.success("Glossary entry removed");
    } catch {
      toast.error("Failed to delete glossary entry");
    } finally {
      setDeletingId(null);
    }
  };

  // ── Render ────────────────────────────────────────────────────────────────

  if (loading) {
    return (
      <div className="flex h-64 items-center justify-center">
        <Spinner size="lg" />
      </div>
    );
  }

  const glossaryEnabled = !!getValue('translation.glossary_enabled');

  return (
    <div>
      <PageHeader
        title={"Translation Settings"}
        description={"Configure automatic translation, glossary, and per-tenant translation preferences"}
      />

      <div className="space-y-6">
        {/* General Settings */}
        <Card shadow="sm">
          <CardHeader className="flex items-center gap-2 px-4 pt-4 pb-0">
            <Settings size={18} className="text-primary" />
            <h3 className="font-semibold">{"General Settings"}</h3>
          </CardHeader>
          <CardBody className="divide-y divide-divider px-4">
            {/* translation.enabled */}
            <div className="flex items-center justify-between py-3">
              <div>
                <p className="font-medium">{meta('translation.enabled').label}</p>
                <p className="text-sm text-default-500">{meta('translation.enabled').description}</p>
              </div>
              <Switch
                isSelected={!!getValue('translation.enabled')}
                onValueChange={(val) => saveSetting('translation.enabled', val)}
                isDisabled={saving === 'translation.enabled'}
                size="sm"
              />
            </div>

            {/* translation.engine */}
            <div className="flex items-center justify-between py-3">
              <div>
                <p className="font-medium">{meta('translation.engine').label}</p>
                <p className="text-sm text-default-500">{meta('translation.engine').description}</p>
              </div>
              <Select
                aria-label={"Translation engine"}
                selectedKeys={[String(getValue('translation.engine') || 'openai')]}
                onSelectionChange={(keys) => {
                  const val = Array.from(keys)[0] as string;
                  if (val) saveSetting('translation.engine', val);
                }}
                className="max-w-[180px]"
                size="sm"
                isDisabled={saving === 'translation.engine'}
              >
                {ENGINE_OPTIONS.map((opt) => (
                  <SelectItem key={opt.key}>{opt.label}</SelectItem>
                ))}
              </Select>
            </div>

            {/* translation.context_aware */}
            <div className="flex items-center justify-between py-3">
              <div>
                <p className="font-medium">{meta('translation.context_aware').label}</p>
                <p className="text-sm text-default-500">{meta('translation.context_aware').description}</p>
              </div>
              <Switch
                isSelected={!!getValue('translation.context_aware')}
                onValueChange={(val) => saveSetting('translation.context_aware', val)}
                isDisabled={saving === 'translation.context_aware'}
                size="sm"
              />
            </div>

            {/* translation.context_messages */}
            <div className="flex items-center justify-between py-3">
              <div>
                <p className="font-medium">{meta('translation.context_messages').label}</p>
                <p className="text-sm text-default-500">{meta('translation.context_messages').description}</p>
              </div>
              <Input
                aria-label={meta('translation.context_messages').label}
                type="number"
                min={1}
                max={20}
                value={String(getValue('translation.context_messages') || 5)}
                onBlur={(e) => {
                  const num = Math.min(20, Math.max(1, parseInt(e.target.value) || 5));
                  saveSetting('translation.context_messages', num);
                }}
                className="max-w-[100px]"
                size="sm"
                isDisabled={saving === 'translation.context_messages'}
              />
            </div>

            {/* translation.auto_translate_default */}
            <div className="flex items-center justify-between py-3">
              <div>
                <p className="font-medium">{meta('translation.auto_translate_default').label}</p>
                <p className="text-sm text-default-500">{meta('translation.auto_translate_default').description}</p>
              </div>
              <Switch
                isSelected={!!getValue('translation.auto_translate_default')}
                onValueChange={(val) => saveSetting('translation.auto_translate_default', val)}
                isDisabled={saving === 'translation.auto_translate_default'}
                size="sm"
              />
            </div>

            {/* translation.max_per_user_per_hour */}
            <div className="flex items-center justify-between py-3">
              <div>
                <p className="font-medium">{meta('translation.max_per_user_per_hour').label}</p>
                <p className="text-sm text-default-500">{meta('translation.max_per_user_per_hour').description}</p>
              </div>
              <Input
                aria-label={meta('translation.max_per_user_per_hour').label}
                type="number"
                min={10}
                max={1000}
                value={String(getValue('translation.max_per_user_per_hour') || 100)}
                onBlur={(e) => {
                  const num = Math.min(1000, Math.max(10, parseInt(e.target.value) || 100));
                  saveSetting('translation.max_per_user_per_hour', num);
                }}
                className="max-w-[100px]"
                size="sm"
                isDisabled={saving === 'translation.max_per_user_per_hour'}
              />
            </div>

            <Divider />

            {/* translation.glossary_enabled */}
            <div className="flex items-center justify-between py-3">
              <div>
                <p className="font-medium">{meta('translation.glossary_enabled').label}</p>
                <p className="text-sm text-default-500">{meta('translation.glossary_enabled').description}</p>
              </div>
              <Switch
                isSelected={glossaryEnabled}
                onValueChange={(val) => saveSetting('translation.glossary_enabled', val)}
                isDisabled={saving === 'translation.glossary_enabled'}
                size="sm"
              />
            </div>
          </CardBody>
        </Card>

        {/* Glossary Management — only when glossary is enabled */}
        {glossaryEnabled && (
          <Card shadow="sm">
            <CardHeader className="flex items-center gap-2 px-4 pt-4 pb-0">
              <BookOpen size={18} className="text-secondary" />
              <h3 className="font-semibold">{"Glossary Management"}</h3>
              <span className="text-sm text-default-400">{"Add custom translations for specific terms"}</span>
            </CardHeader>
            <CardBody className="px-4 pb-4 space-y-4">
              {/* Add entry form */}
              <div className="flex flex-wrap items-end gap-3">
                <Input
                  label={"Source term"}
                  placeholder={"e.g. timebank"}
                  value={newSource}
                  onValueChange={setNewSource}
                  className="min-w-[160px] flex-1"
                  size="sm"
                />
                <Input
                  label={"Target term"}
                  placeholder={"Preferred translation"}
                  value={newTarget}
                  onValueChange={setNewTarget}
                  className="min-w-[160px] flex-1"
                  size="sm"
                />
                <Select
                  label={"Language"}
                  aria-label={"Target language"}
                  selectedKeys={newLang ? [newLang] : []}
                  onSelectionChange={(keys) => {
                    const val = Array.from(keys)[0] as string;
                    if (val) setNewLang(val);
                  }}
                  className="min-w-[150px] flex-1"
                  size="sm"
                >
                  {LANGUAGES.map((lang) => (
                    <SelectItem key={lang.code}>{lang.label}</SelectItem>
                  ))}
                </Select>
                <Button
                  color="primary"
                  startContent={<Plus size={16} />}
                  onPress={handleAddEntry}
                  isLoading={addingEntry}
                  isDisabled={addingEntry}
                  size="sm"
                >
                  {"Add"}
                </Button>
              </div>

              <Divider />

              {/* Glossary table */}
              {glossaryLoading ? (
                <div className="flex h-32 items-center justify-center">
                  <Spinner size="sm" />
                </div>
              ) : glossary.length === 0 ? (
                <p className="py-4 text-center text-sm text-default-400">
                  {"No glossary entries yet"}
                </p>
              ) : (
                <Table aria-label={"Glossary entries"} removeWrapper>
                  <TableHeader>
                    <TableColumn>{"Source term"}</TableColumn>
                    <TableColumn>{"Target term"}</TableColumn>
                    <TableColumn>{"Language"}</TableColumn>
                    <TableColumn width={60}>{''}</TableColumn>
                  </TableHeader>
                  <TableBody>
                    {glossary.map((entry) => {
                      const langLabel = LANGUAGES.find((l) => l.code === entry.target_language)?.label || entry.target_language;
                      return (
                        <TableRow key={entry.id}>
                          <TableCell>{entry.source_term}</TableCell>
                          <TableCell>{entry.target_term}</TableCell>
                          <TableCell>{langLabel}</TableCell>
                          <TableCell>
                            <Button
                              isIconOnly
                              size="sm"
                              variant="light"
                              color="danger"
                              onPress={() => handleDeleteEntry(entry.id)}
                              isDisabled={deletingId === entry.id}
                              aria-label={`Delete entry for "${entry.source_term}"`}
                            >
                              <Trash2 size={14} />
                            </Button>
                          </TableCell>
                        </TableRow>
                      );
                    })}
                  </TableBody>
                </Table>
              )}
            </CardBody>
          </Card>
        )}
      </div>
    </div>
  );
}

export default TranslationConfig;
