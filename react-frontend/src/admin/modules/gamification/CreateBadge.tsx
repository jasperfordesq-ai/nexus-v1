/**
 * Admin Create Badge
 * Form to create a new custom badge.
 * Parity: PHP Admin\CustomBadgeController@store
 */

import { useState } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import {
  Card,
  CardBody,
  CardHeader,
  Button,
  Input,
  Textarea,
  Select,
  SelectItem,
} from '@heroui/react';
import { ArrowLeft, Save, Award } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { adminGamification } from '../../api/adminApi';
import { PageHeader } from '../../components';

// ─────────────────────────────────────────────────────────────────────────────
// Constants
// ─────────────────────────────────────────────────────────────────────────────

const ICON_OPTIONS = [
  { key: 'award', label: 'Award' },
  { key: 'star', label: 'Star' },
  { key: 'trophy', label: 'Trophy' },
  { key: 'medal', label: 'Medal' },
  { key: 'shield', label: 'Shield' },
  { key: 'heart', label: 'Heart' },
  { key: 'zap', label: 'Lightning' },
  { key: 'flame', label: 'Flame' },
  { key: 'crown', label: 'Crown' },
  { key: 'gem', label: 'Gem' },
  { key: 'target', label: 'Target' },
  { key: 'rocket', label: 'Rocket' },
] as const;

// ─────────────────────────────────────────────────────────────────────────────
// Component
// ─────────────────────────────────────────────────────────────────────────────

interface FormData {
  name: string;
  slug: string;
  description: string;
  icon: string;
}

export function CreateBadge() {
  usePageTitle('Admin - Create Badge');
  const toast = useToast();
  const navigate = useNavigate();

  const [formData, setFormData] = useState<FormData>({
    name: '',
    slug: '',
    description: '',
    icon: 'award',
  });
  const [saving, setSaving] = useState(false);

  const updateField = <K extends keyof FormData>(key: K, value: FormData[K]) => {
    setFormData((prev) => {
      const updated = { ...prev, [key]: value };
      // Auto-generate slug from name if slug hasn't been manually edited
      if (key === 'name') {
        const autoSlug = value
          .toLowerCase()
          .replace(/[^a-z0-9]+/g, '_')
          .replace(/^_+|_+$/g, '');
        updated.slug = autoSlug;
      }
      return updated;
    });
  };

  const handleSave = async () => {
    if (!formData.name.trim()) {
      toast.error('Badge name is required');
      return;
    }

    setSaving(true);

    const res = await adminGamification.createBadge({
      name: formData.name.trim(),
      slug: formData.slug.trim() || undefined,
      description: formData.description.trim(),
      icon: formData.icon,
    });

    if (res.success) {
      toast.success(`Badge "${formData.name.trim()}" created`);
      navigate('../custom-badges');
    } else {
      const errorMsg = (res as { error?: string }).error
        || (res as { errors?: Array<{ message: string }> }).errors?.[0]?.message
        || 'Failed to create badge';
      toast.error(errorMsg);
    }

    setSaving(false);
  };

  return (
    <div>
      <PageHeader
        title="Create Badge"
        description="Create a new custom badge for your community"
        actions={
          <Link to="../custom-badges">
            <Button variant="flat" startContent={<ArrowLeft size={16} />}>
              Back to Badges
            </Button>
          </Link>
        }
      />

      <Card shadow="sm" className="max-w-2xl">
        <CardHeader className="flex items-center gap-2 pb-0">
          <Award size={20} className="text-success" />
          <h3 className="text-lg font-semibold text-foreground">Badge Details</h3>
        </CardHeader>
        <CardBody className="gap-4">
          <Input
            label="Name"
            placeholder="e.g. Community Champion"
            value={formData.name}
            onValueChange={(v) => updateField('name', v)}
            isRequired
            variant="bordered"
            autoFocus
          />

          <Input
            label="Slug"
            placeholder="Auto-generated from name"
            value={formData.slug}
            onValueChange={(v) => setFormData((prev) => ({ ...prev, slug: v }))}
            variant="bordered"
            description="URL-safe identifier. Auto-generated from the name."
            classNames={{ input: 'font-mono text-sm' }}
          />

          <Textarea
            label="Description"
            placeholder="Describe what this badge is awarded for..."
            value={formData.description}
            onValueChange={(v) => updateField('description', v)}
            variant="bordered"
            minRows={3}
          />

          <Select
            label="Icon"
            selectedKeys={new Set([formData.icon])}
            onSelectionChange={(keys) => {
              const selected = Array.from(keys)[0] as string;
              if (selected) updateField('icon', selected);
            }}
            variant="bordered"
          >
            {ICON_OPTIONS.map((opt) => (
              <SelectItem key={opt.key}>{opt.label}</SelectItem>
            ))}
          </Select>

          {/* Preview */}
          <div className="rounded-lg border border-default-200 p-4">
            <p className="text-xs text-default-500 mb-2 uppercase tracking-wider font-semibold">Preview</p>
            <div className="flex items-center gap-3">
              <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-success/10 text-success">
                <Award size={24} />
              </div>
              <div>
                <p className="font-semibold text-foreground">{formData.name || 'Badge Name'}</p>
                <p className="text-sm text-default-500">{formData.description || 'Badge description will appear here'}</p>
              </div>
            </div>
          </div>

          <div className="flex justify-end gap-2 pt-2">
            <Link to="../custom-badges">
              <Button variant="flat" isDisabled={saving}>Cancel</Button>
            </Link>
            <Button
              color="primary"
              startContent={<Save size={16} />}
              onPress={handleSave}
              isLoading={saving}
            >
              Create Badge
            </Button>
          </div>
        </CardBody>
      </Card>
    </div>
  );
}

export default CreateBadge;
