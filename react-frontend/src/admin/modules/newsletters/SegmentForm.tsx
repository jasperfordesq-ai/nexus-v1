// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Newsletter Segment Form
 * Create and edit audience segments with a dynamic rule builder.
 */

import { useState, useCallback, useEffect } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import {
  Button,
  Card,
  CardHeader,
  CardBody,
  Input,
  Select,
  SelectItem,
  Switch,
  Chip,
  Spinner,
  Divider,
  Tooltip,
} from '@heroui/react';
import {
  Save,
  ArrowLeft,
  Plus,
  Trash2,
  Eye,
  Users,
  Sparkles,
  Lightbulb,
} from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { adminNewsletters } from '../../api/adminApi';
import { PageHeader } from '../../components';

// ─────────────────────────────────────────────────────────────────────────────
// Types
// ─────────────────────────────────────────────────────────────────────────────

interface SegmentRule {
  id: string;
  field: string;
  operator: string;
  value: string;
}

interface SegmentSuggestion {
  name: string;
  description: string;
  match_type: string;
  rules: Array<{ field: string; operator: string; value: string }>;
  estimated_count: number;
}

// ─────────────────────────────────────────────────────────────────────────────
// Rule Field Configuration
// ─────────────────────────────────────────────────────────────────────────────

interface RuleFieldConfig {
  key: string;
  label: string;
  type: 'number' | 'text' | 'select' | 'date' | 'boolean';
  operators: string[];
  options?: string[];
}

const RULE_FIELDS: RuleFieldConfig[] = [
  { key: 'activity_score', label: 'Activity Score', type: 'number', operators: ['equals', 'greater_than', 'less_than'] },
  { key: 'community_rank', label: 'CommunityRank', type: 'number', operators: ['equals', 'greater_than', 'less_than'] },
  { key: 'login_recency', label: 'Login Recency (days)', type: 'number', operators: ['less_than', 'greater_than'] },
  { key: 'transaction_count', label: 'Transaction Count', type: 'number', operators: ['equals', 'greater_than', 'less_than'] },
  { key: 'email_open_rate', label: 'Email Open Rate (%)', type: 'number', operators: ['greater_than', 'less_than'] },
  { key: 'email_click_rate', label: 'Email Click Rate (%)', type: 'number', operators: ['greater_than', 'less_than'] },
  { key: 'county', label: 'County', type: 'text', operators: ['equals', 'contains', 'in'] },
  { key: 'town', label: 'Town/City', type: 'text', operators: ['equals', 'contains', 'in'] },
  { key: 'group_membership', label: 'Group Membership', type: 'select', operators: ['in', 'not_in'] },
  { key: 'profile_type', label: 'Profile Type', type: 'select', operators: ['equals', 'not_equals'], options: ['individual', 'organisation'] },
  { key: 'user_role', label: 'User Role', type: 'select', operators: ['equals', 'not_equals'], options: ['member', 'admin', 'broker'] },
  { key: 'member_since', label: 'Member Since', type: 'date', operators: ['before', 'after'] },
  { key: 'has_listings', label: 'Has Listings', type: 'boolean', operators: ['equals'] },
  { key: 'listing_count', label: 'Listing Count', type: 'number', operators: ['equals', 'greater_than', 'less_than'] },
];

const OPERATOR_LABELS: Record<string, string> = {
  equals: 'Equals',
  not_equals: 'Not Equals',
  greater_than: 'Greater Than',
  less_than: 'Less Than',
  contains: 'Contains',
  in: 'In',
  not_in: 'Not In',
  within_km: 'Within (km)',
  before: 'Before',
  after: 'After',
};

function generateId(): string {
  return Math.random().toString(36).substring(2, 9);
}

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

export function SegmentForm() {
  const { id } = useParams<{ id: string }>();
  const isEdit = Boolean(id);
  const navigate = useNavigate();
  usePageTitle(isEdit ? 'Admin - Edit Segment' : 'Admin - Create Segment');

  // Form state
  const [name, setName] = useState('');
  const [description, setDescription] = useState('');
  const [isActive, setIsActive] = useState(true);
  const [matchType, setMatchType] = useState<'all' | 'any'>('all');
  const [rules, setRules] = useState<SegmentRule[]>([
    { id: generateId(), field: '', operator: '', value: '' },
  ]);

  // UI state
  const [loading, setLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [previewCount, setPreviewCount] = useState<number | null>(null);
  const [previewing, setPreviewing] = useState(false);
  const [suggestions, setSuggestions] = useState<SegmentSuggestion[]>([]);
  const [loadingSuggestions, setLoadingSuggestions] = useState(false);
  const [errors, setErrors] = useState<Record<string, string>>({});

  // Load existing segment for edit mode
  useEffect(() => {
    if (!isEdit || !id) return;
    setLoading(true);
    adminNewsletters.getSegment(Number(id)).then(res => {
      if (res.success && res.data) {
        const seg = res.data as Record<string, unknown>;
        setName((seg.name as string) || '');
        setDescription((seg.description as string) || '');
        setIsActive(Boolean(seg.is_active));
        setMatchType((seg.match_type as 'all' | 'any') || 'all');

        let parsedRules: Array<{ field: string; operator: string; value: string }> = [];
        if (typeof seg.rules === 'string') {
          try { parsedRules = JSON.parse(seg.rules); } catch { /* empty */ }
        } else if (Array.isArray(seg.rules)) {
          parsedRules = seg.rules as Array<{ field: string; operator: string; value: string }>;
        }

        if (parsedRules.length > 0) {
          setRules(parsedRules.map(r => ({
            id: generateId(),
            field: r.field || '',
            operator: r.operator || '',
            value: String(r.value ?? ''),
          })));
        }
      }
    }).catch(() => {
      // Navigate back if segment not found
      navigate('/admin/newsletters/segments');
    }).finally(() => setLoading(false));
  }, [isEdit, id, navigate]);

  // Load suggestions in create mode
  useEffect(() => {
    if (isEdit) return;
    setLoadingSuggestions(true);
    adminNewsletters.getSegmentSuggestions().then(res => {
      if (res.success && Array.isArray(res.data)) {
        setSuggestions(res.data as SegmentSuggestion[]);
      }
    }).catch(() => {
      // Suggestions are optional
    }).finally(() => setLoadingSuggestions(false));
  }, [isEdit]);

  // ── Rule Handlers ──

  const addRule = useCallback(() => {
    setRules(prev => [...prev, { id: generateId(), field: '', operator: '', value: '' }]);
  }, []);

  const removeRule = useCallback((ruleId: string) => {
    setRules(prev => prev.length > 1 ? prev.filter(r => r.id !== ruleId) : prev);
  }, []);

  const updateRule = useCallback((ruleId: string, key: keyof SegmentRule, value: string) => {
    setRules(prev => prev.map(r => {
      if (r.id !== ruleId) return r;
      const updated = { ...r, [key]: value };
      // Reset operator and value when field changes
      if (key === 'field') {
        const fieldConfig = RULE_FIELDS.find(f => f.key === value);
        updated.operator = fieldConfig?.operators[0] || '';
        updated.value = fieldConfig?.type === 'boolean' ? '1' : '';
      }
      return updated;
    }));
  }, []);

  // ── Preview ──

  const handlePreview = useCallback(async () => {
    setPreviewing(true);
    setPreviewCount(null);
    try {
      const validRules = rules
        .filter(r => r.field && r.operator)
        .map(({ field, operator, value }) => ({ field, operator, value }));

      const res = await adminNewsletters.previewSegment({
        match_type: matchType,
        rules: validRules,
      });

      if (res.success && res.data) {
        const data = res.data as { matching_count: number };
        setPreviewCount(data.matching_count ?? 0);
      }
    } catch {
      setPreviewCount(0);
    }
    setPreviewing(false);
  }, [rules, matchType]);

  // ── Save ──

  const handleSave = useCallback(async () => {
    // Validate
    const newErrors: Record<string, string> = {};
    if (!name.trim()) newErrors.name = 'Name is required';
    if (Object.keys(newErrors).length > 0) {
      setErrors(newErrors);
      return;
    }
    setErrors({});

    setSaving(true);
    try {
      const validRules = rules
        .filter(r => r.field && r.operator)
        .map(({ field, operator, value }) => ({ field, operator, value }));

      const payload = {
        name: name.trim(),
        description: description.trim(),
        is_active: isActive,
        match_type: matchType,
        rules: validRules,
      };

      if (isEdit && id) {
        await adminNewsletters.updateSegment(Number(id), payload);
      } else {
        await adminNewsletters.createSegment(payload);
      }
      navigate('/admin/newsletters/segments');
    } catch {
      setErrors({ form: 'Failed to save segment. Please try again.' });
    }
    setSaving(false);
  }, [name, description, isActive, matchType, rules, isEdit, id, navigate]);

  // ── Apply Suggestion ──

  const applySuggestion = useCallback((suggestion: SegmentSuggestion) => {
    setName(suggestion.name);
    setDescription(suggestion.description);
    setMatchType(suggestion.match_type as 'all' | 'any');
    setRules(
      suggestion.rules.map(r => ({
        id: generateId(),
        field: r.field,
        operator: r.operator,
        value: String(r.value ?? ''),
      }))
    );
    setPreviewCount(suggestion.estimated_count);
  }, []);

  // ── Render helpers ──

  const getFieldConfig = (fieldKey: string): RuleFieldConfig | undefined =>
    RULE_FIELDS.find(f => f.key === fieldKey);

  const renderValueInput = (rule: SegmentRule) => {
    const fieldConfig = getFieldConfig(rule.field);
    if (!fieldConfig) return null;

    if (fieldConfig.type === 'boolean') {
      return (
        <Select
          size="sm"
          label="Value"
          selectedKeys={rule.value ? [rule.value] : []}
          onSelectionChange={(keys) => {
            const val = Array.from(keys)[0] as string;
            if (val) updateRule(rule.id, 'value', val);
          }}
          className="min-w-[120px]"
        >
          <SelectItem key="1">Yes</SelectItem>
          <SelectItem key="0">No</SelectItem>
        </Select>
      );
    }

    if (fieldConfig.type === 'select' && fieldConfig.options) {
      return (
        <Select
          size="sm"
          label="Value"
          selectedKeys={rule.value ? [rule.value] : []}
          onSelectionChange={(keys) => {
            const val = Array.from(keys)[0] as string;
            if (val) updateRule(rule.id, 'value', val);
          }}
          className="min-w-[160px]"
        >
          {fieldConfig.options.map(opt => (
            <SelectItem key={opt}>{opt.charAt(0).toUpperCase() + opt.slice(1)}</SelectItem>
          ))}
        </Select>
      );
    }

    if (fieldConfig.type === 'date') {
      return (
        <Input
          size="sm"
          type="date"
          label="Value"
          value={rule.value}
          onValueChange={(val) => updateRule(rule.id, 'value', val)}
          className="min-w-[160px]"
        />
      );
    }

    return (
      <Input
        size="sm"
        type={fieldConfig.type === 'number' ? 'number' : 'text'}
        label="Value"
        placeholder={fieldConfig.type === 'number' ? '0' : 'Enter value...'}
        value={rule.value}
        onValueChange={(val) => updateRule(rule.id, 'value', val)}
        className="min-w-[160px]"
      />
    );
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center py-20">
        <Spinner size="lg" label="Loading segment..." />
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <PageHeader
        title={isEdit ? 'Edit Segment' : 'Create Segment'}
        description={isEdit ? 'Update this audience segment and its targeting rules' : 'Define a new audience segment with targeting rules'}
        actions={
          <Button
            variant="flat"
            startContent={<ArrowLeft size={16} />}
            onPress={() => navigate('/admin/newsletters/segments')}
          >
            Back to Segments
          </Button>
        }
      />

      {errors.form && (
        <Card shadow="sm">
          <CardBody>
            <p className="text-danger text-sm">{errors.form}</p>
          </CardBody>
        </Card>
      )}

      {/* Segment Details */}
      <Card shadow="sm">
        <CardHeader className="pb-0">
          <h3 className="text-lg font-semibold">Segment Details</h3>
        </CardHeader>
        <CardBody className="space-y-4">
          <Input
            label="Segment Name"
            placeholder="e.g., Active Members, New Joiners"
            value={name}
            onValueChange={setName}
            isRequired
            isInvalid={Boolean(errors.name)}
            errorMessage={errors.name}
          />
          <Input
            label="Description"
            placeholder="Describe what this segment targets..."
            value={description}
            onValueChange={setDescription}
          />
          <div className="flex items-center gap-3">
            <Switch
              isSelected={isActive}
              onValueChange={setIsActive}
              size="sm"
            />
            <span className="text-sm text-foreground">
              {isActive ? 'Active' : 'Inactive'} -- {isActive ? 'This segment is available for targeting' : 'This segment will not appear in targeting options'}
            </span>
          </div>
        </CardBody>
      </Card>

      {/* Rule Builder */}
      <Card shadow="sm">
        <CardHeader className="flex justify-between items-center pb-0">
          <h3 className="text-lg font-semibold">Targeting Rules</h3>
          <Select
            size="sm"
            label="Match Logic"
            selectedKeys={[matchType]}
            onSelectionChange={(keys) => {
              const val = Array.from(keys)[0] as string;
              if (val === 'all' || val === 'any') setMatchType(val);
            }}
            className="max-w-[200px]"
          >
            <SelectItem key="all">ALL rules (AND)</SelectItem>
            <SelectItem key="any">ANY rule (OR)</SelectItem>
          </Select>
        </CardHeader>
        <CardBody className="space-y-3">
          <p className="text-sm text-default-500">
            {matchType === 'all'
              ? 'Members must match ALL of the following rules to be included.'
              : 'Members matching ANY of the following rules will be included.'}
          </p>

          <Divider />

          {rules.map((rule, index) => (
            <div key={rule.id} className="flex flex-wrap items-end gap-2">
              {index > 0 && (
                <Chip size="sm" variant="flat" color="primary" className="mb-1">
                  {matchType === 'all' ? 'AND' : 'OR'}
                </Chip>
              )}

              <Select
                size="sm"
                label="Field"
                selectedKeys={rule.field ? [rule.field] : []}
                onSelectionChange={(keys) => {
                  const val = Array.from(keys)[0] as string;
                  if (val) updateRule(rule.id, 'field', val);
                }}
                className="min-w-[180px] flex-1"
              >
                {RULE_FIELDS.map(f => (
                  <SelectItem key={f.key}>{f.label}</SelectItem>
                ))}
              </Select>

              {rule.field && (
                <Select
                  size="sm"
                  label="Operator"
                  selectedKeys={rule.operator ? [rule.operator] : []}
                  onSelectionChange={(keys) => {
                    const val = Array.from(keys)[0] as string;
                    if (val) updateRule(rule.id, 'operator', val);
                  }}
                  className="min-w-[150px]"
                >
                  {(getFieldConfig(rule.field)?.operators || []).map(op => (
                    <SelectItem key={op}>{OPERATOR_LABELS[op] || op}</SelectItem>
                  ))}
                </Select>
              )}

              {rule.field && rule.operator && renderValueInput(rule)}

              <Tooltip content="Remove rule">
                <Button
                  isIconOnly
                  size="sm"
                  variant="flat"
                  color="danger"
                  onPress={() => removeRule(rule.id)}
                  isDisabled={rules.length === 1}
                  aria-label="Remove rule"
                >
                  <Trash2 size={14} />
                </Button>
              </Tooltip>
            </div>
          ))}

          <div className="pt-2">
            <Button
              size="sm"
              variant="flat"
              startContent={<Plus size={14} />}
              onPress={addRule}
            >
              Add Rule
            </Button>
          </div>
        </CardBody>
      </Card>

      {/* Preview */}
      <Card shadow="sm">
        <CardHeader className="pb-0">
          <h3 className="text-lg font-semibold">Preview</h3>
        </CardHeader>
        <CardBody>
          <div className="flex items-center gap-4">
            <Button
              color="secondary"
              variant="flat"
              startContent={previewing ? undefined : <Eye size={16} />}
              onPress={handlePreview}
              isLoading={previewing}
            >
              Preview Matching Members
            </Button>

            {previewCount !== null && (
              <div className="flex items-center gap-2 px-4 py-2 rounded-lg bg-default-100">
                <Users size={18} className="text-primary" />
                <span className="text-lg font-semibold text-foreground">
                  {previewCount.toLocaleString()}
                </span>
                <span className="text-sm text-default-500">
                  {previewCount === 1 ? 'member matches' : 'members match'}
                </span>
              </div>
            )}
          </div>
        </CardBody>
      </Card>

      {/* Smart Suggestions (create mode only) */}
      {!isEdit && (
        <Card shadow="sm">
          <CardHeader className="flex items-center gap-2 pb-0">
            <Sparkles size={18} className="text-warning" />
            <h3 className="text-lg font-semibold">Smart Suggestions</h3>
          </CardHeader>
          <CardBody>
            {loadingSuggestions ? (
              <div className="flex items-center gap-2 py-4">
                <Spinner size="sm" />
                <span className="text-sm text-default-500">Analyzing your member data...</span>
              </div>
            ) : suggestions.length === 0 ? (
              <p className="text-sm text-default-400 py-2">
                No suggestions available. Add more members to get AI-powered segment suggestions.
              </p>
            ) : (
              <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                {suggestions.map((suggestion, idx) => (
                  <Button
                    key={idx}
                    onPress={() => applySuggestion(suggestion)}
                    variant="bordered"
                    className="text-left p-4 rounded-lg border-default-200 hover:border-primary hover:bg-primary-50/50 h-auto justify-start"
                  >
                    <div className="flex items-start gap-2 text-left w-full">
                      <Lightbulb size={16} className="text-warning mt-0.5 flex-shrink-0" />
                      <div>
                        <p className="font-medium text-foreground text-sm">{suggestion.name}</p>
                        <p className="text-xs text-default-500 mt-1 line-clamp-2">{suggestion.description}</p>
                        <Chip size="sm" variant="flat" color="primary" className="mt-2">
                          ~{suggestion.estimated_count.toLocaleString()} members
                        </Chip>
                      </div>
                    </div>
                  </Button>
                ))}
              </div>
            )}
          </CardBody>
        </Card>
      )}

      {/* Actions */}
      <div className="flex justify-end gap-3 pb-8">
        <Button
          variant="flat"
          onPress={() => navigate('/admin/newsletters/segments')}
        >
          Cancel
        </Button>
        <Button
          color="primary"
          startContent={saving ? undefined : <Save size={16} />}
          onPress={handleSave}
          isLoading={saving}
        >
          {isEdit ? 'Update Segment' : 'Create Segment'}
        </Button>
      </div>
    </div>
  );
}

export default SegmentForm;
