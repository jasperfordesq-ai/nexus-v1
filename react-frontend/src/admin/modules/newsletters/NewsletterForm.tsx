/**
 * Newsletter Create/Edit Form
 * Form for creating or editing a newsletter campaign.
 */

import { useState, useEffect } from 'react';
import { Card, CardBody, CardHeader, Input, Button, Textarea, Select, SelectItem } from '@heroui/react';
import { Save, ArrowLeft } from 'lucide-react';
import { useNavigate, useParams } from 'react-router-dom';
import { usePageTitle } from '@/hooks';
import { adminNewsletters } from '../../api/adminApi';
import { PageHeader } from '../../components';

export function NewsletterForm() {
  const { id } = useParams<{ id: string }>();
  const isEdit = Boolean(id);
  usePageTitle(`Admin - ${isEdit ? 'Edit' : 'Create'} Newsletter`);
  const navigate = useNavigate();

  const [name, setName] = useState('');
  const [subject, setSubject] = useState('');
  const [content, setContent] = useState('');
  const [status, setStatus] = useState('draft');
  const [saving, setSaving] = useState(false);
  const [loading, setLoading] = useState(isEdit);

  useEffect(() => {
    if (isEdit && id) {
      (async () => {
        try {
          const res = await adminNewsletters.get(Number(id));
          if (res.success && res.data) {
            const d = res.data as Record<string, unknown>;
            setName((d.name as string) || '');
            setSubject((d.subject as string) || '');
            setContent((d.content as string) || '');
            setStatus((d.status as string) || 'draft');
          }
        } catch { /* empty */ }
        setLoading(false);
      })();
    }
  }, [id, isEdit]);

  const handleSubmit = async () => {
    if (!name.trim()) return;
    setSaving(true);
    try {
      if (isEdit && id) {
        await adminNewsletters.update(Number(id), { name, subject, content, status });
      } else {
        await adminNewsletters.create({ name, subject, content, status });
      }
      navigate('../newsletters');
    } catch { /* empty */ }
    setSaving(false);
  };

  if (loading) {
    return <div className="flex justify-center py-16"><span className="text-default-400">Loading...</span></div>;
  }

  return (
    <div>
      <PageHeader
        title={isEdit ? 'Edit Newsletter' : 'Create Newsletter'}
        description={isEdit ? 'Update newsletter details' : 'Create a new email campaign'}
        actions={
          <Button variant="flat" startContent={<ArrowLeft size={16} />} onPress={() => navigate('../newsletters')}>
            Back
          </Button>
        }
      />
      <Card shadow="sm">
        <CardHeader><h3 className="text-lg font-semibold">Newsletter Details</h3></CardHeader>
        <CardBody className="gap-4">
          <Input label="Campaign Name" placeholder="e.g., Monthly Update" value={name} onValueChange={setName} isRequired variant="bordered" />
          <Input label="Subject Line" placeholder="e.g., Your February Update" value={subject} onValueChange={setSubject} variant="bordered" />
          <Textarea label="Content" placeholder="Newsletter content..." value={content} onValueChange={setContent} variant="bordered" minRows={6} />
          <Select label="Status" selectedKeys={[status]} onSelectionChange={(keys) => { const v = Array.from(keys)[0]; if (v) setStatus(String(v)); }} variant="bordered">
            <SelectItem key="draft">Draft</SelectItem>
            <SelectItem key="scheduled">Scheduled</SelectItem>
            <SelectItem key="sending">Sending</SelectItem>
            <SelectItem key="sent">Sent</SelectItem>
          </Select>
          <div className="flex justify-end gap-2 pt-2">
            <Button variant="flat" onPress={() => navigate('../newsletters')}>Cancel</Button>
            <Button color="primary" startContent={<Save size={16} />} onPress={handleSubmit} isLoading={saving}>
              {isEdit ? 'Update' : 'Create'} Newsletter
            </Button>
          </div>
        </CardBody>
      </Card>
    </div>
  );
}

export default NewsletterForm;
