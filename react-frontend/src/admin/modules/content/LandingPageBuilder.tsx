// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Landing Page Builder
 * Admin UI for customizing the tenant's public landing page sections.
 * Supports enable/disable, reorder, and content editing per section.
 */

import { useState, useEffect, useCallback, useRef } from 'react';
import { useTranslation } from 'react-i18next';
import {
  Card,
  CardBody,
  CardHeader,
  Input,
  Switch,
  Button,
  Textarea,
  Spinner,
  Select,
  SelectItem,
  Divider,
} from '@heroui/react';
import ChevronUp from 'lucide-react/icons/chevron-up';
import ChevronDown from 'lucide-react/icons/chevron-down';
import Save from 'lucide-react/icons/save';
import RotateCcw from 'lucide-react/icons/rotate-ccw';
import Plus from 'lucide-react/icons/plus';
import Trash2 from 'lucide-react/icons/trash-2';
import ChevronRight from 'lucide-react/icons/chevron-right';
import Layers from 'lucide-react/icons/layers';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { adminLandingPage } from '../../api/adminApi';
import { PageHeader } from '../../components';
import type {
  LandingPageConfig,
  LandingSection,
  LandingSectionType,
  LandingIconId,
  HeroContent,
  FeaturePillsContent,
  FeaturePillItem,
  StatsContent,
  HowItWorksContent,
  HowItWorksStep,
  CoreValuesContent,
  CoreValue,
  CtaContent,
} from '@/types/landing-page';
import { DEFAULT_LANDING_PAGE_CONFIG } from '@/types/landing-page';

// ─────────────────────────────────────────────────────────────────────────────
// Constants
// ─────────────────────────────────────────────────────────────────────────────

const SECTION_LABEL_KEYS: Record<LandingSectionType, string> = {
  hero: 'content.landing_section_hero',
  feature_pills: 'content.landing_section_feature_pills',
  stats: 'content.landing_section_stats',
  how_it_works: 'content.landing_section_how_it_works',
  core_values: 'content.landing_section_core_values',
  cta: 'content.landing_section_cta',
};

const ICON_OPTIONS: LandingIconId[] = [
  'clock',
  'users',
  'zap',
  'user-plus',
  'search',
  'handshake',
  'coins',
  'heart',
  'shield',
  'star',
  'globe',
  'book-open',
  'message-circle',
  'award',
  'target',
  'thumbs-up',
];

const HELPER_TEXT_KEY = 'content.landing_helper_text';

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

/** Deep-clone a config to avoid mutation */
function cloneConfig(config: LandingPageConfig): LandingPageConfig {
  return JSON.parse(JSON.stringify(config));
}

/** Sort sections by order */
function sortedSections(sections: LandingSection[]): LandingSection[] {
  return [...sections].sort((a, b) => a.order - b.order);
}

/**
 * Strip empty content fields so the backend only gets explicitly set values.
 * If all fields in a section's content are empty, remove the content key entirely.
 */
function cleanConfig(config: LandingPageConfig): LandingPageConfig {
  const cleaned = cloneConfig(config);
  for (const section of cleaned.sections) {
    if (!section.content) continue;
    const content = section.content as Record<string, unknown>;
    // Remove empty string values at the top level
    for (const key of Object.keys(content)) {
      const val = content[key];
      if (val === '' || val === undefined || val === null) {
        delete content[key];
      }
      // Clean arrays: remove items with all-empty fields
      if (Array.isArray(val)) {
        const filtered = val.filter((item: Record<string, unknown>) => {
          return Object.values(item).some(
            (v) => v !== '' && v !== undefined && v !== null,
          );
        });
        if (filtered.length === 0) {
          delete content[key];
        } else {
          content[key] = filtered;
        }
      }
    }
    // If content is now empty, remove it
    if (Object.keys(content).length === 0) {
      delete section.content;
    }
  }
  return cleaned;
}

// ─────────────────────────────────────────────────────────────────────────────
// Icon Select Component
// ─────────────────────────────────────────────────────────────────────────────

function IconSelect({
  value,
  onChange,
  label,
  placeholder,
}: {
  value?: LandingIconId;
  onChange: (val: LandingIconId | undefined) => void;
  label: string;
  placeholder: string;
}) {
  return (
    <Select
      label={label}
      placeholder={placeholder}
      selectedKeys={value ? [value] : []}
      onSelectionChange={(keys) => {
        const selected = Array.from(keys)[0] as string | undefined;
        onChange(selected as LandingIconId | undefined);
      }}
      variant="bordered"
      size="sm"
    >
      {ICON_OPTIONS.map((icon) => (
        <SelectItem key={icon}>{icon}</SelectItem>
      ))}
    </Select>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Section Content Editors
// ─────────────────────────────────────────────────────────────────────────────

function HeroEditor({
  content,
  onChange,
}: {
  content: HeroContent;
  onChange: (c: HeroContent) => void;
}) {
  const { t } = useTranslation('admin');
  const update = (field: keyof HeroContent, value: string) => {
    onChange({ ...content, [field]: value });
  };
  return (
    <div className="flex flex-col gap-4">
      <Input
        label={"Badge text"}
        placeholder={"e.g. Welcome to our community"}
        value={content.badge_text || ''}
        onValueChange={(v) => update('badge_text', v)}
        variant="bordered"
        size="sm"
      />
      <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
        <Input
          label={"Headline line 1"}
          placeholder={"Main headline"}
          value={content.headline_1 || ''}
          onValueChange={(v) => update('headline_1', v)}
          variant="bordered"
          size="sm"
        />
        <Input
          label={"Headline line 2"}
          placeholder={"Supporting headline"}
          value={content.headline_2 || ''}
          onValueChange={(v) => update('headline_2', v)}
          variant="bordered"
          size="sm"
        />
      </div>
      <Textarea
        label={"Subheadline"}
        placeholder={"A short paragraph introducing your community"}
        value={content.subheadline || ''}
        onValueChange={(v) => update('subheadline', v)}
        variant="bordered"
        size="sm"
        minRows={2}
      />
      <Divider />
      <p className="text-sm font-medium text-default-600">{"Primary call-to-action"}</p>
      <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
        <Input
          label={"Button text"}
          placeholder={"Get started"}
          value={content.cta_primary_text || ''}
          onValueChange={(v) => update('cta_primary_text', v)}
          variant="bordered"
          size="sm"
        />
        <Input
          label={"Button link"}
          placeholder={"/register"}
          value={content.cta_primary_link || ''}
          onValueChange={(v) => update('cta_primary_link', v)}
          variant="bordered"
          size="sm"
        />
      </div>
      <p className="text-sm font-medium text-default-600">{"Secondary call-to-action"}</p>
      <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
        <Input
          label={"Button text"}
          placeholder={"Learn more"}
          value={content.cta_secondary_text || ''}
          onValueChange={(v) => update('cta_secondary_text', v)}
          variant="bordered"
          size="sm"
        />
        <Input
          label={"Button link"}
          placeholder={"/about"}
          value={content.cta_secondary_link || ''}
          onValueChange={(v) => update('cta_secondary_link', v)}
          variant="bordered"
          size="sm"
        />
      </div>
      <p className="text-xs text-default-400">{t(HELPER_TEXT_KEY)}</p>
    </div>
  );
}

function FeaturePillsEditor({
  content,
  onChange,
}: {
  content: FeaturePillsContent;
  onChange: (c: FeaturePillsContent) => void;
}) {
  const { t } = useTranslation('admin');
  const items = content.items || [];

  const updateItem = (index: number, field: keyof FeaturePillItem, value: string) => {
    const updated: FeaturePillItem[] = [...items];
    updated[index] = { ...updated[index], [field]: value } as FeaturePillItem;
    onChange({ ...content, items: updated });
  };

  const updateItemIcon = (index: number, value: LandingIconId | undefined) => {
    const updated: FeaturePillItem[] = [...items];
    updated[index] = { ...updated[index], icon: value } as FeaturePillItem;
    onChange({ ...content, items: updated });
  };

  const addItem = () => {
    if (items.length >= 6) return;
    onChange({
      ...content,
      items: [...items, { title: '', description: '' }],
    });
  };

  const removeItem = (index: number) => {
    const updated = items.filter((_, i) => i !== index);
    onChange({ ...content, items: updated });
  };

  return (
    <div className="flex flex-col gap-4">
      {items.map((item, i) => (
        <Card key={i} shadow="none" className="border border-default-200">
          <CardBody className="flex flex-col gap-3 p-3">
            <div className="flex items-center justify-between">
              <span className="text-sm font-medium text-default-600">
                {`Item ${i + 1}`}
              </span>
              <Button
                size="sm"
                color="danger"
                variant="light"
                startContent={<Trash2 size={14} />}
                onPress={() => removeItem(i)}
              >
                {"Remove"}
              </Button>
            </div>
            <div className="grid grid-cols-1 gap-3 md:grid-cols-3">
              <IconSelect
                value={item.icon}
                onChange={(val) => updateItemIcon(i, val)}
                label={"Icon"}
                placeholder={"Select an icon"}
              />
              <Input
                label={"Title"}
                value={item.title}
                onValueChange={(v) => updateItem(i, 'title', v)}
                variant="bordered"
                size="sm"
              />
              <Input
                label={"Description"}
                value={item.description}
                onValueChange={(v) => updateItem(i, 'description', v)}
                variant="bordered"
                size="sm"
              />
            </div>
          </CardBody>
        </Card>
      ))}
      {items.length < 6 && (
        <Button
          size="sm"
          variant="flat"
          startContent={<Plus size={14} />}
          onPress={addItem}
          className="self-start"
        >
          {"Add item"}
        </Button>
      )}
      <p className="text-xs text-default-400">{t(HELPER_TEXT_KEY)}</p>
    </div>
  );
}

function StatsEditor({
  content,
  onChange,
}: {
  content: StatsContent;
  onChange: (c: StatsContent) => void;
}) {
  const { t } = useTranslation('admin');
  return (
    <div className="flex flex-col gap-4">
      <Switch
        isSelected={content.show_live_stats !== false}
        onValueChange={(val) => onChange({ ...content, show_live_stats: val })}
        size="sm"
      >
        {"Show live community stats"}
      </Switch>
      <p className="text-xs text-default-400">
        {"Display real-time member and exchange counts"}
      </p>
    </div>
  );
}

function HowItWorksEditor({
  content,
  onChange,
}: {
  content: HowItWorksContent;
  onChange: (c: HowItWorksContent) => void;
}) {
  const { t } = useTranslation('admin');
  const steps = content.steps || [];

  const updateStep = (index: number, field: keyof HowItWorksStep, value: string) => {
    const updated: HowItWorksStep[] = [...steps];
    updated[index] = { ...updated[index], [field]: value } as HowItWorksStep;
    onChange({ ...content, steps: updated });
  };

  const updateStepIcon = (index: number, value: LandingIconId | undefined) => {
    const updated: HowItWorksStep[] = [...steps];
    updated[index] = { ...updated[index], icon: value } as HowItWorksStep;
    onChange({ ...content, steps: updated });
  };

  const addStep = () => {
    onChange({
      ...content,
      steps: [...steps, { title: '', description: '' }],
    });
  };

  const removeStep = (index: number) => {
    onChange({ ...content, steps: steps.filter((_, i) => i !== index) });
  };

  return (
    <div className="flex flex-col gap-4">
      <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
        <Input
          label={"Section title"}
          placeholder={"How it works"}
          value={content.title || ''}
          onValueChange={(v) => onChange({ ...content, title: v })}
          variant="bordered"
          size="sm"
        />
        <Input
          label={"Section subtitle"}
          placeholder={"Three simple steps to get started"}
          value={content.subtitle || ''}
          onValueChange={(v) => onChange({ ...content, subtitle: v })}
          variant="bordered"
          size="sm"
        />
      </div>
      <Divider />
      {steps.map((step, i) => (
        <Card key={i} shadow="none" className="border border-default-200">
          <CardBody className="flex flex-col gap-3 p-3">
            <div className="flex items-center justify-between">
              <span className="text-sm font-medium text-default-600">
                {`Step ${i + 1}`}
              </span>
              <Button
                size="sm"
                color="danger"
                variant="light"
                startContent={<Trash2 size={14} />}
                onPress={() => removeStep(i)}
              >
                {"Remove"}
              </Button>
            </div>
            <div className="grid grid-cols-1 gap-3 md:grid-cols-3">
              <IconSelect
                value={step.icon}
                onChange={(val) => updateStepIcon(i, val)}
                label={"Icon"}
                placeholder={"Select an icon"}
              />
              <Input
                label={"Title"}
                value={step.title}
                onValueChange={(v) => updateStep(i, 'title', v)}
                variant="bordered"
                size="sm"
              />
              <Input
                label={"Description"}
                value={step.description}
                onValueChange={(v) => updateStep(i, 'description', v)}
                variant="bordered"
                size="sm"
              />
            </div>
          </CardBody>
        </Card>
      ))}
      <Button
        size="sm"
        variant="flat"
        startContent={<Plus size={14} />}
        onPress={addStep}
        className="self-start"
      >
        {"Add step"}
      </Button>
      <p className="text-xs text-default-400">{t(HELPER_TEXT_KEY)}</p>
    </div>
  );
}

function CoreValuesEditor({
  content,
  onChange,
}: {
  content: CoreValuesContent;
  onChange: (c: CoreValuesContent) => void;
}) {
  const { t } = useTranslation('admin');
  const values = content.values || [];

  const updateValue = (index: number, field: keyof CoreValue, value: string) => {
    const updated: CoreValue[] = [...values];
    updated[index] = { ...updated[index], [field]: value } as CoreValue;
    onChange({ ...content, values: updated });
  };

  const addValue = () => {
    onChange({
      ...content,
      values: [...values, { title: '', description: '' }],
    });
  };

  const removeValue = (index: number) => {
    onChange({ ...content, values: values.filter((_, i) => i !== index) });
  };

  return (
    <div className="flex flex-col gap-4">
      <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
        <Input
          label={"Section title"}
          placeholder={"Our values"}
          value={content.title || ''}
          onValueChange={(v) => onChange({ ...content, title: v })}
          variant="bordered"
          size="sm"
        />
        <Input
          label={"Section subtitle"}
          placeholder={"What we stand for as a community"}
          value={content.subtitle || ''}
          onValueChange={(v) => onChange({ ...content, subtitle: v })}
          variant="bordered"
          size="sm"
        />
      </div>
      <Divider />
      {values.map((val, i) => (
        <Card key={i} shadow="none" className="border border-default-200">
          <CardBody className="flex flex-col gap-3 p-3">
            <div className="flex items-center justify-between">
              <span className="text-sm font-medium text-default-600">
                {`Value ${i + 1}`}
              </span>
              <Button
                size="sm"
                color="danger"
                variant="light"
                startContent={<Trash2 size={14} />}
                onPress={() => removeValue(i)}
              >
                {"Remove"}
              </Button>
            </div>
            <Input
              label={"Title"}
              value={val.title}
              onValueChange={(v) => updateValue(i, 'title', v)}
              variant="bordered"
              size="sm"
            />
            <Textarea
              label={"Description"}
              value={val.description}
              onValueChange={(v) => updateValue(i, 'description', v)}
              variant="bordered"
              size="sm"
              minRows={2}
            />
          </CardBody>
        </Card>
      ))}
      <Button
        size="sm"
        variant="flat"
        startContent={<Plus size={14} />}
        onPress={addValue}
        className="self-start"
      >
        {"Add value"}
      </Button>
      <p className="text-xs text-default-400">{t(HELPER_TEXT_KEY)}</p>
    </div>
  );
}

function CtaEditor({
  content,
  onChange,
}: {
  content: CtaContent;
  onChange: (c: CtaContent) => void;
}) {
  const { t } = useTranslation('admin');
  const update = (field: keyof CtaContent, value: string) => {
    onChange({ ...content, [field]: value });
  };
  return (
    <div className="flex flex-col gap-4">
      <Input
        label={"Title"}
        placeholder={"Ready to join?"}
        value={content.title || ''}
        onValueChange={(v) => update('title', v)}
        variant="bordered"
        size="sm"
      />
      <Textarea
        label={"Description"}
        placeholder={"Become a member and start exchanging today"}
        value={content.description || ''}
        onValueChange={(v) => update('description', v)}
        variant="bordered"
        size="sm"
        minRows={2}
      />
      <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
        <Input
          label={"Button text"}
          placeholder={"Join now"}
          value={content.button_text || ''}
          onValueChange={(v) => update('button_text', v)}
          variant="bordered"
          size="sm"
        />
        <Input
          label={"Button link"}
          placeholder={"/register"}
          value={content.button_link || ''}
          onValueChange={(v) => update('button_link', v)}
          variant="bordered"
          size="sm"
        />
      </div>
      <p className="text-xs text-default-400">{t(HELPER_TEXT_KEY)}</p>
    </div>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Section Card Component
// ─────────────────────────────────────────────────────────────────────────────

function SectionCard({
  section,
  isFirst,
  isLast,
  isExpanded,
  onToggleExpand,
  onToggleEnabled,
  onMoveUp,
  onMoveDown,
  onContentChange,
}: {
  section: LandingSection;
  isFirst: boolean;
  isLast: boolean;
  isExpanded: boolean;
  onToggleExpand: () => void;
  onToggleEnabled: (enabled: boolean) => void;
  onMoveUp: () => void;
  onMoveDown: () => void;
  onContentChange: (content: LandingSection['content']) => void;
}) {
  const { t } = useTranslation('admin');
  const sectionType = section.type as LandingSectionType;
  const labelKey = SECTION_LABEL_KEYS[sectionType];
  const label = labelKey ? t(labelKey) : sectionType;

  const renderEditor = () => {
    switch (sectionType) {
      case 'hero':
        return (
          <HeroEditor
            content={(section.content as HeroContent) || {}}
            onChange={(c) => onContentChange(c)}
          />
        );
      case 'feature_pills':
        return (
          <FeaturePillsEditor
            content={(section.content as FeaturePillsContent) || {}}
            onChange={(c) => onContentChange(c)}
          />
        );
      case 'stats':
        return (
          <StatsEditor
            content={(section.content as StatsContent) || {}}
            onChange={(c) => onContentChange(c)}
          />
        );
      case 'how_it_works':
        return (
          <HowItWorksEditor
            content={(section.content as HowItWorksContent) || {}}
            onChange={(c) => onContentChange(c)}
          />
        );
      case 'core_values':
        return (
          <CoreValuesEditor
            content={(section.content as CoreValuesContent) || {}}
            onChange={(c) => onContentChange(c)}
          />
        );
      case 'cta':
        return (
          <CtaEditor
            content={(section.content as CtaContent) || {}}
            onChange={(c) => onContentChange(c)}
          />
        );
      default:
        return <p className="text-sm text-default-400">{"No editor available for this section"}</p>;
    }
  };

  return (
    <Card
      shadow="sm"
      className={`transition-opacity ${!section.enabled ? 'opacity-60' : ''}`}
    >
      <CardHeader
        className="flex cursor-pointer items-center justify-between gap-3 px-4 py-3"
        onClick={onToggleExpand}
      >
        <div className="flex items-center gap-3">
          <ChevronRight
            size={16}
            className={`text-default-400 transition-transform ${
              isExpanded ? 'rotate-90' : ''
            }`}
          />
          <div>
            <p className="text-sm font-semibold">{label}</p>
            <p className="text-xs text-default-400">
              {section.enabled ? "Visible" : "Hidden"}
            </p>
          </div>
        </div>
        <div role="presentation" className="flex items-center gap-2" onClick={(e) => e.stopPropagation()}>
          <Switch
            size="sm"
            isSelected={section.enabled}
            onValueChange={onToggleEnabled}
            aria-label={`Toggle ${label}`}
          />
          <Button
            isIconOnly
            size="sm"
            variant="light"
            isDisabled={isFirst}
            onPress={onMoveUp}
            aria-label={`Move ${label} up`}
          >
            <ChevronUp size={16} />
          </Button>
          <Button
            isIconOnly
            size="sm"
            variant="light"
            isDisabled={isLast}
            onPress={onMoveDown}
            aria-label={`Move ${label} down`}
          >
            <ChevronDown size={16} />
          </Button>
        </div>
      </CardHeader>
      {isExpanded && (
        <CardBody className="px-4 pb-4 pt-0">
          <Divider className="mb-4" />
          {renderEditor()}
        </CardBody>
      )}
    </Card>
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Main Component
// ─────────────────────────────────────────────────────────────────────────────

export function LandingPageBuilder() {
  const { t } = useTranslation('admin');
  usePageTitle("Landing Page Builder");
  const toast = useToast();

  // State
  const [config, setConfig] = useState<LandingPageConfig>(
    cloneConfig(DEFAULT_LANDING_PAGE_CONFIG),
  );
  const [savedConfig, setSavedConfig] = useState<LandingPageConfig | null>(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [expandedSections, setExpandedSections] = useState<Set<string>>(new Set());
  const [isDirty, setIsDirty] = useState(false);
  const [confirmReset, setConfirmReset] = useState(false);
  const initialLoadDone = useRef(false);

  // ─── Fetch config on mount ──────────────────────────────────────────────
  const fetchConfig = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminLandingPage.get();
      if (res.success && res.data) {
        const result = res.data as unknown as { config: LandingPageConfig | null };
        if (result.config) {
          setConfig(result.config);
          setSavedConfig(cloneConfig(result.config));
        } else {
          // No custom config — use defaults
          const defaults = cloneConfig(DEFAULT_LANDING_PAGE_CONFIG);
          setConfig(defaults);
          setSavedConfig(null);
        }
      }
    } catch {
      toast.error("Failed to load landing page configuration");
    } finally {
      setLoading(false);
      initialLoadDone.current = true;
    }
  }, [toast]);


  useEffect(() => {
    fetchConfig();
  }, [fetchConfig]);

  // ─── Track dirty state ──────────────────────────────────────────────────
  useEffect(() => {
    if (!initialLoadDone.current) return;
    const currentJson = JSON.stringify(cleanConfig(config));
    const savedJson = savedConfig
      ? JSON.stringify(cleanConfig(savedConfig))
      : JSON.stringify(cleanConfig(cloneConfig(DEFAULT_LANDING_PAGE_CONFIG)));
    setIsDirty(currentJson !== savedJson);
  }, [config, savedConfig]);

  // ─── Warn on navigation with unsaved changes ───────────────────────────
  useEffect(() => {
    const handler = (e: BeforeUnloadEvent) => {
      if (isDirty) {
        e.preventDefault();
      }
    };
    window.addEventListener('beforeunload', handler);
    return () => window.removeEventListener('beforeunload', handler);
  }, [isDirty]);

  // ─── Save ───────────────────────────────────────────────────────────────
  const handleSave = async () => {
    setSaving(true);
    try {
      const cleaned = cleanConfig(config);
      const res = await adminLandingPage.update(cleaned);
      if (res.success) {
        toast.success("Landing page saved");
        setSavedConfig(cloneConfig(config));
        setIsDirty(false);
      } else {
        toast.error("Failed to save landing page");
      }
    } catch {
      toast.error("Failed to save landing page");
    } finally {
      setSaving(false);
    }
  };

  // ─── Reset to defaults ─────────────────────────────────────────────────
  const handleReset = async () => {
    setSaving(true);
    try {
      const res = await adminLandingPage.update(null);
      if (res.success) {
        const defaults = cloneConfig(DEFAULT_LANDING_PAGE_CONFIG);
        setConfig(defaults);
        setSavedConfig(null);
        setIsDirty(false);
        setExpandedSections(new Set());
        toast.success("Landing page reset to defaults");
      } else {
        toast.error("Failed to reset landing page");
      }
    } catch {
      toast.error("Failed to reset landing page");
    } finally {
      setSaving(false);
      setConfirmReset(false);
    }
  };

  // ─── Section operations ─────────────────────────────────────────────────
  const sections = sortedSections(config.sections);

  const updateSection = (id: string, updater: (s: LandingSection) => LandingSection) => {
    setConfig((prev) => ({
      ...prev,
      sections: prev.sections.map((s) => (s.id === id ? updater(s) : s)),
    }));
  };

  const toggleEnabled = (id: string, enabled: boolean) => {
    updateSection(id, (s) => ({ ...s, enabled }));
  };

  const moveSection = (id: string, direction: 'up' | 'down') => {
    setConfig((prev) => {
      const sorted = sortedSections(prev.sections);
      const index = sorted.findIndex((s) => s.id === id);
      if (
        (direction === 'up' && index <= 0) ||
        (direction === 'down' && index >= sorted.length - 1)
      ) {
        return prev;
      }
      const swapIndex = direction === 'up' ? index - 1 : index + 1;
      const updated: LandingSection[] = [...sorted];
      const current = updated[index];
      const swap = updated[swapIndex];
      if (!current || !swap) return prev;
      // Swap order values
      const tempOrder = current.order;
      updated[index] = { ...current, order: swap.order };
      updated[swapIndex] = { ...swap, order: tempOrder };
      return { ...prev, sections: updated };
    });
  };

  const updateContent = (id: string, content: LandingSection['content']) => {
    updateSection(id, (s) => ({ ...s, content }));
  };

  const toggleExpanded = (id: string) => {
    setExpandedSections((prev) => {
      const next = new Set(prev);
      if (next.has(id)) {
        next.delete(id);
      } else {
        next.add(id);
      }
      return next;
    });
  };

  // ─── Render ─────────────────────────────────────────────────────────────
  if (loading) {
    return (
      <div className="flex min-h-[400px] items-center justify-center">
        <Spinner size="lg" label={"Loading landing page"} />
      </div>
    );
  }

  return (
    <div className="mx-auto max-w-4xl">
      <PageHeader
        title={"Landing Page Builder"}
        description={"Design the public landing page for your community"}
        actions={
          <div className="flex items-center gap-2">
            {confirmReset ? (
              <>
                <span className="text-sm text-danger">{"Reset all sections to defaults?"}</span>
                <Button
                  size="sm"
                  color="danger"
                  variant="flat"
                  onPress={handleReset}
                  isLoading={saving}
                >
                  {"Yes, reset"}
                </Button>
                <Button
                  size="sm"
                  variant="flat"
                  onPress={() => setConfirmReset(false)}
                  isDisabled={saving}
                >
                  {"Cancel"}
                </Button>
              </>
            ) : (
              <Button
                size="sm"
                variant="flat"
                startContent={<RotateCcw size={14} />}
                onPress={() => setConfirmReset(true)}
                isDisabled={saving}
              >
                {"Reset to defaults"}
              </Button>
            )}
            <Button
              size="sm"
              color="primary"
              startContent={<Save size={14} />}
              onPress={handleSave}
              isDisabled={!isDirty || saving}
              isLoading={saving}
            >
              {"Save changes"}
            </Button>
          </div>
        }
      />

      {/* Section ordering info */}
      <div className="mb-4 flex items-center gap-2 rounded-lg border border-default-200 bg-default-50 px-4 py-3">
        <Layers size={16} className="text-default-400" />
        <p className="text-sm text-default-500">
          {"Drag sections using the arrows to reorder. Toggle to show or hide each section on the public page."}
        </p>
      </div>

      {/* Section list */}
      <div className="flex flex-col gap-3">
        {sections.map((section, index) => (
          <SectionCard
            key={section.id}
            section={section}
            isFirst={index === 0}
            isLast={index === sections.length - 1}
            isExpanded={expandedSections.has(section.id)}
            onToggleExpand={() => toggleExpanded(section.id)}
            onToggleEnabled={(enabled) => toggleEnabled(section.id, enabled)}
            onMoveUp={() => moveSection(section.id, 'up')}
            onMoveDown={() => moveSection(section.id, 'down')}
            onContentChange={(content) => updateContent(section.id, content)}
          />
        ))}
      </div>

      {/* Dirty state indicator */}
      {isDirty && (
        <div className="mt-4 rounded-lg border border-warning-200 bg-warning-50 px-4 py-3">
          <p className="text-sm text-warning-700">
            {"You have unsaved changes"}
          </p>
        </div>
      )}
    </div>
  );
}

export default LandingPageBuilder;
