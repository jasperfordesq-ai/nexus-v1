/**
 * Create Deliverable
 * Form for creating a new project deliverable.
 * Wired to adminDeliverability.create() API.
 */

import { useState } from 'react';
import { Card, CardBody, CardHeader, Input, Textarea, Select, SelectItem, Button } from '@heroui/react';
import { Target, ArrowLeft, Save } from 'lucide-react';
import { useNavigate } from 'react-router-dom';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { adminDeliverability } from '../../api/adminApi';
import { PageHeader } from '../../components';

interface DeliverableFormData {
  title: string;
  description: string;
  priority: string;
  status: string;
  due_date: string;
  assigned_to: string;
}

export function CreateDeliverable() {
  usePageTitle('Admin - Create Deliverable');
  const navigate = useNavigate();
  const { tenantPath } = useTenant();
  const toast = useToast();

  const [formData, setFormData] = useState<DeliverableFormData>({
    title: '',
    description: '',
    priority: 'medium',
    status: 'planned',
    due_date: '',
    assigned_to: '',
  });
  const [saving, setSaving] = useState(false);

  const handleChange = (field: keyof DeliverableFormData, value: string) => {
    setFormData((prev) => ({ ...prev, [field]: value }));
  };

  const handleSave = async () => {
    if (!formData.title.trim()) {
      toast.warning('Title is required');
      return;
    }
    setSaving(true);
    try {
      const res = await adminDeliverability.create({
        title: formData.title,
        description: formData.description || undefined,
        priority: formData.priority || undefined,
        status: formData.status || undefined,
        due_date: formData.due_date || undefined,
      });
      if (res?.success) {
        toast.success('Deliverable created successfully');
        navigate(tenantPath('/admin/deliverability/list'));
      } else {
        toast.error('Failed to create deliverable');
      }
    } catch {
      toast.error('An unexpected error occurred');
    } finally {
      setSaving(false);
    }
  };

  return (
    <div>
      <PageHeader
        title="Create Deliverable"
        description="Add a new project deliverable or milestone"
        actions={<Button variant="flat" startContent={<ArrowLeft size={16} />} onPress={() => navigate(tenantPath('/admin/deliverability/list'))}>Back</Button>}
      />

      <Card shadow="sm">
        <CardHeader><h3 className="text-lg font-semibold flex items-center gap-2"><Target size={20} /> Deliverable Details</h3></CardHeader>
        <CardBody className="gap-4">
          <Input
            label="Title"
            placeholder="e.g., Launch User Onboarding Flow"
            isRequired
            variant="bordered"
            value={formData.title}
            onValueChange={(v) => handleChange('title', v)}
          />
          <Textarea
            label="Description"
            placeholder="Describe the deliverable..."
            variant="bordered"
            minRows={3}
            value={formData.description}
            onValueChange={(v) => handleChange('description', v)}
          />
          <Select
            label="Priority"
            variant="bordered"
            selectedKeys={[formData.priority]}
            onSelectionChange={(keys) => {
              const selected = Array.from(keys)[0] as string;
              if (selected) handleChange('priority', selected);
            }}
          >
            <SelectItem key="low">Low</SelectItem>
            <SelectItem key="medium">Medium</SelectItem>
            <SelectItem key="high">High</SelectItem>
            <SelectItem key="critical">Critical</SelectItem>
          </Select>
          <Select
            label="Status"
            variant="bordered"
            selectedKeys={[formData.status]}
            onSelectionChange={(keys) => {
              const selected = Array.from(keys)[0] as string;
              if (selected) handleChange('status', selected);
            }}
          >
            <SelectItem key="planned">Planned</SelectItem>
            <SelectItem key="in_progress">In Progress</SelectItem>
            <SelectItem key="review">In Review</SelectItem>
            <SelectItem key="completed">Completed</SelectItem>
          </Select>
          <Input
            label="Due Date"
            type="date"
            variant="bordered"
            value={formData.due_date}
            onValueChange={(v) => handleChange('due_date', v)}
          />
          <Input
            label="Assigned To"
            placeholder="Team member name"
            variant="bordered"
            value={formData.assigned_to}
            onValueChange={(v) => handleChange('assigned_to', v)}
          />
          <div className="flex justify-end gap-2 pt-2">
            <Button variant="flat" onPress={() => navigate(tenantPath('/admin/deliverability/list'))}>Cancel</Button>
            <Button
              color="primary"
              startContent={<Save size={16} />}
              onPress={handleSave}
              isLoading={saving}
            >
              Create Deliverable
            </Button>
          </div>
        </CardBody>
      </Card>
    </div>
  );
}

export default CreateDeliverable;
