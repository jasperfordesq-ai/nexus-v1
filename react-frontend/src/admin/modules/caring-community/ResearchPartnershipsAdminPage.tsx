// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useCallback, useEffect, useMemo, useState } from 'react';
import {
  Button,
  Card,
  CardBody,
  CardHeader,
  Chip,
  Input,
  Modal,
  ModalBody,
  ModalContent,
  ModalFooter,
  ModalHeader,
  Select,
  SelectItem,
  Spinner,
  Table,
  TableBody,
  TableCell,
  TableColumn,
  TableHeader,
  TableRow,
  Textarea,
  Tooltip,
} from '@heroui/react';
import Database from 'lucide-react/icons/database';
import FileCheck2 from 'lucide-react/icons/file-check-2';
import FileText from 'lucide-react/icons/file-text';
import FlaskConical from 'lucide-react/icons/flask-conical';
import Plus from 'lucide-react/icons/plus';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import ShieldOff from 'lucide-react/icons/shield-off';
import { useToast } from '@/contexts';
import { usePageTitle } from '@/hooks';
import { api } from '@/lib/api';
import { PageHeader } from '../../components';

type PartnerStatus = 'draft' | 'active' | 'paused' | 'ended';

interface ResearchPartner {
  id: number;
  name: string;
  institution: string;
  contact_email: string | null;
  agreement_reference: string | null;
  methodology_url: string | null;
  status: PartnerStatus;
  starts_at: string | null;
  ends_at: string | null;
  created_at: string;
}

interface AgreementTemplateSummary {
  key: string;
  title: string;
  summary: string;
  suitable_for: string[];
  placeholders: string[];
}

interface AgreementTemplateRender {
  key: string;
  title: string;
  markdown: string;
  placeholders_used: string[];
  placeholders_missing: string[];
}

interface ResearchExport {
  id: number;
  partner_id: number;
  partner_name: string | null;
  partner_institution: string | null;
  dataset_key: string;
  period_start: string;
  period_end: string;
  status: 'generated' | 'superseded' | 'revoked';
  row_count: number;
  anonymization_version: string;
  data_hash: string;
  generated_at: string;
}

const statusColors: Record<string, 'default' | 'success' | 'warning' | 'danger' | 'secondary'> = {
  active: 'success',
  draft: 'default',
  paused: 'warning',
  ended: 'secondary',
  generated: 'success',
  revoked: 'danger',
  superseded: 'warning',
};

function formatDate(value: string | null): string {
  if (!value) return '-';
  return new Date(value).toLocaleDateString();
}

export default function ResearchPartnershipsAdminPage() {
  usePageTitle('Research Partnerships');
  const { showToast } = useToast();

  const [partners, setPartners] = useState<ResearchPartner[]>([]);
  const [exports, setExports] = useState<ResearchExport[]>([]);
  const [loading, setLoading] = useState(true);
  const [partnerModalOpen, setPartnerModalOpen] = useState(false);
  const [exportModalOpen, setExportModalOpen] = useState(false);
  const [selectedPartnerId, setSelectedPartnerId] = useState<number | null>(null);
  const [workingId, setWorkingId] = useState<number | null>(null);

  const [name, setName] = useState('');
  const [institution, setInstitution] = useState('');
  const [contactEmail, setContactEmail] = useState('');
  const [agreementReference, setAgreementReference] = useState('');
  const [methodologyUrl, setMethodologyUrl] = useState('');
  const [status, setStatus] = useState<PartnerStatus>('draft');
  const [scope, setScope] = useState('caring_community_aggregate_v1');
  const [periodStart, setPeriodStart] = useState('2026-01-01');
  const [periodEnd, setPeriodEnd] = useState('2026-12-31');

  // AG65 — agreement templates
  const [templates, setTemplates] = useState<AgreementTemplateSummary[]>([]);
  const [templateModalOpen, setTemplateModalOpen] = useState(false);
  const [activeTemplate, setActiveTemplate] = useState<AgreementTemplateSummary | null>(null);
  const [templateValues, setTemplateValues] = useState<Record<string, string>>({});
  const [renderedTemplate, setRenderedTemplate] = useState<AgreementTemplateRender | null>(null);
  const [renderingTemplate, setRenderingTemplate] = useState(false);

  const activePartners = useMemo(
    () => partners.filter((partner) => partner.status === 'active'),
    [partners],
  );

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const [partnerResponse, exportResponse, templatesResponse] = await Promise.all([
        api.get<{ partners: ResearchPartner[] }>('/v2/admin/caring-community/research/partners'),
        api.get<{ exports: ResearchExport[] }>('/v2/admin/caring-community/research/dataset-exports'),
        api.get<{ templates: AgreementTemplateSummary[] }>('/v2/admin/caring-community/research/agreement-templates'),
      ]);

      if (partnerResponse.success) {
        setPartners(partnerResponse.data?.partners ?? []);
      }
      if (exportResponse.success) {
        setExports(exportResponse.data?.exports ?? []);
      }
      if (templatesResponse.success) {
        setTemplates(templatesResponse.data?.templates ?? []);
      }
    } catch {
      showToast('Failed to load research partnerships', 'error');
    } finally {
      setLoading(false);
    }
  }, [showToast]);

  const openTemplate = useCallback((template: AgreementTemplateSummary) => {
    setActiveTemplate(template);
    const initialValues: Record<string, string> = {};
    template.placeholders.forEach((p) => {
      initialValues[p] = '';
    });
    setTemplateValues(initialValues);
    setRenderedTemplate(null);
    setTemplateModalOpen(true);
  }, []);

  const renderTemplate = useCallback(async () => {
    if (!activeTemplate) return;
    setRenderingTemplate(true);
    try {
      const response = await api.post<AgreementTemplateRender>(
        `/v2/admin/caring-community/research/agreement-templates/${encodeURIComponent(activeTemplate.key)}/render`,
        { values: templateValues },
      );
      if (response.success && response.data) {
        setRenderedTemplate(response.data);
      } else {
        showToast(response.error || 'Failed to render template', 'error');
      }
    } catch {
      showToast('Failed to render template', 'error');
    } finally {
      setRenderingTemplate(false);
    }
  }, [activeTemplate, templateValues, showToast]);

  const downloadRendered = useCallback(() => {
    if (!renderedTemplate) return;
    const blob = new Blob([renderedTemplate.markdown], { type: 'text/markdown;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `${renderedTemplate.key}-${new Date().toISOString().slice(0, 10)}.md`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
  }, [renderedTemplate]);

  useEffect(() => {
    void load();
  }, [load]);

  const resetPartnerForm = () => {
    setName('');
    setInstitution('');
    setContactEmail('');
    setAgreementReference('');
    setMethodologyUrl('');
    setStatus('draft');
    setScope('caring_community_aggregate_v1');
  };

  const createPartner = useCallback(async () => {
    const response = await api.post<ResearchPartner>('/v2/admin/caring-community/research/partners', {
      name,
      institution,
      contact_email: contactEmail || null,
      agreement_reference: agreementReference || null,
      methodology_url: methodologyUrl || null,
      status,
      data_scope: { datasets: scope.split(',').map((item) => item.trim()).filter(Boolean) },
    });

    if (!response.success) {
      showToast(response.error || 'Failed to create research partner', 'error');
      return;
    }

    showToast('Research partner created', 'success');
    setPartnerModalOpen(false);
    resetPartnerForm();
    await load();
  }, [agreementReference, contactEmail, institution, load, methodologyUrl, name, scope, showToast, status]);

  const generateExport = useCallback(async () => {
    if (!selectedPartnerId) return;

    setWorkingId(selectedPartnerId);
    const response = await api.post(
      `/v2/admin/caring-community/research/partners/${selectedPartnerId}/dataset-exports`,
      {
        period_start: periodStart,
        period_end: periodEnd,
      },
    );
    setWorkingId(null);

    if (!response.success) {
      showToast(response.error || 'Failed to generate dataset export', 'error');
      return;
    }

    showToast('Aggregate dataset export generated', 'success');
    setExportModalOpen(false);
    await load();
  }, [load, periodEnd, periodStart, selectedPartnerId, showToast]);

  const revokeExport = useCallback(async (exportId: number) => {
    setWorkingId(exportId);
    const response = await api.post(`/v2/admin/caring-community/research/dataset-exports/${exportId}/revoke`);
    setWorkingId(null);

    if (!response.success) {
      showToast(response.error || 'Failed to revoke dataset export', 'error');
      return;
    }

    showToast('Dataset export revoked', 'success');
    await load();
  }, [load, showToast]);

  return (
    <div className="space-y-6">
      <PageHeader
        title="Research Partnerships"
        subtitle="Manage privacy-safe research partners, aggregate dataset exports, and revocation history"
        icon={<FlaskConical size={20} />}
        actions={
          <div className="flex items-center gap-2">
            <Tooltip content="Refresh">
              <Button isIconOnly size="sm" variant="flat" onPress={() => void load()} isLoading={loading}>
                <RefreshCw size={15} />
              </Button>
            </Tooltip>
            <Button size="sm" variant="flat" startContent={<Database size={15} />} onPress={() => setExportModalOpen(true)}>
              Generate Export
            </Button>
            <Button size="sm" color="primary" startContent={<Plus size={15} />} onPress={() => setPartnerModalOpen(true)}>
              Add Partner
            </Button>
          </div>
        }
      />

      <Card>
        <CardHeader className="flex items-center gap-2">
          <FlaskConical size={18} className="text-primary" />
          <span className="font-semibold">Research Partners</span>
        </CardHeader>
        <CardBody className="p-0">
          {loading ? (
            <div className="flex justify-center py-10"><Spinner /></div>
          ) : (
            <Table aria-label="Research partners" removeWrapper>
              <TableHeader>
                <TableColumn>Partner</TableColumn>
                <TableColumn>Status</TableColumn>
                <TableColumn>Agreement</TableColumn>
                <TableColumn>Methodology</TableColumn>
                <TableColumn>Created</TableColumn>
              </TableHeader>
              <TableBody emptyContent="No research partners yet.">
                {partners.map((partner) => (
                  <TableRow key={partner.id}>
                    <TableCell>
                      <div className="font-medium">{partner.name}</div>
                      <div className="text-xs text-default-500">{partner.institution}</div>
                      {partner.contact_email && <div className="text-xs text-default-500">{partner.contact_email}</div>}
                    </TableCell>
                    <TableCell>
                      <Chip size="sm" color={statusColors[partner.status] ?? 'default'} variant="flat">
                        {partner.status}
                      </Chip>
                    </TableCell>
                    <TableCell>{partner.agreement_reference || '-'}</TableCell>
                    <TableCell>
                      {partner.methodology_url ? (
                        <a className="text-primary text-sm" href={partner.methodology_url} target="_blank" rel="noreferrer">
                          View
                        </a>
                      ) : '-'}
                    </TableCell>
                    <TableCell>{formatDate(partner.created_at)}</TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          )}
        </CardBody>
      </Card>

      <Card>
        <CardHeader className="flex items-center gap-2">
          <FileText size={18} className="text-primary" />
          <span className="font-semibold">Agreement Templates</span>
          <span className="text-xs text-default-500 font-normal ml-2">
            FADP/nDSG-aligned drafts for legal review
          </span>
        </CardHeader>
        <CardBody>
          {templates.length === 0 ? (
            <p className="text-sm text-default-500">No agreement templates available.</p>
          ) : (
            <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
              {templates.map((template) => (
                <div
                  key={template.key}
                  className="rounded-lg border border-default-200 bg-content1 p-3"
                >
                  <div className="flex items-start justify-between gap-2">
                    <div className="flex-1">
                      <p className="font-medium text-sm">{template.title}</p>
                      <p className="text-xs text-default-500 mt-1">{template.summary}</p>
                    </div>
                  </div>
                  {template.suitable_for.length > 0 && (
                    <div className="flex flex-wrap gap-1 mt-2">
                      {template.suitable_for.map((s) => (
                        <Chip key={s} size="sm" variant="flat" color="default">
                          {s}
                        </Chip>
                      ))}
                    </div>
                  )}
                  <div className="flex items-center justify-between mt-2.5">
                    <span className="text-xs text-default-400">
                      {template.placeholders.length} placeholder
                      {template.placeholders.length === 1 ? '' : 's'}
                    </span>
                    <Button
                      size="sm"
                      variant="flat"
                      color="primary"
                      onPress={() => openTemplate(template)}
                    >
                      Open
                    </Button>
                  </div>
                </div>
              ))}
            </div>
          )}
        </CardBody>
      </Card>

      <Card>
        <CardHeader className="flex items-center gap-2">
          <FileCheck2 size={18} className="text-primary" />
          <span className="font-semibold">Dataset Export History</span>
        </CardHeader>
        <CardBody className="p-0">
          <Table aria-label="Research dataset exports" removeWrapper>
            <TableHeader>
              <TableColumn>Partner</TableColumn>
              <TableColumn>Period</TableColumn>
              <TableColumn>Status</TableColumn>
              <TableColumn>Rows</TableColumn>
              <TableColumn>Hash</TableColumn>
              <TableColumn>Actions</TableColumn>
            </TableHeader>
            <TableBody emptyContent="No dataset exports yet.">
              {exports.map((item) => (
                <TableRow key={item.id}>
                  <TableCell>
                    <div className="font-medium">{item.partner_name || `Partner #${item.partner_id}`}</div>
                    <div className="text-xs text-default-500">{item.partner_institution || item.dataset_key}</div>
                  </TableCell>
                  <TableCell>{formatDate(item.period_start)} - {formatDate(item.period_end)}</TableCell>
                  <TableCell>
                    <Chip size="sm" color={statusColors[item.status] ?? 'default'} variant="flat">
                      {item.status}
                    </Chip>
                  </TableCell>
                  <TableCell>{item.row_count}</TableCell>
                  <TableCell>
                    <code className="text-xs">{item.data_hash.slice(0, 12)}...</code>
                  </TableCell>
                  <TableCell>
                    <Button
                      size="sm"
                      variant="flat"
                      color="danger"
                      startContent={<ShieldOff size={13} />}
                      isDisabled={item.status === 'revoked'}
                      isLoading={workingId === item.id}
                      onPress={() => void revokeExport(item.id)}
                    >
                      Revoke
                    </Button>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </CardBody>
      </Card>

      <Modal isOpen={partnerModalOpen} onOpenChange={setPartnerModalOpen} size="2xl">
        <ModalContent>
          {(close) => (
            <>
              <ModalHeader>Add Research Partner</ModalHeader>
              <ModalBody>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                  <Input label="Name" value={name} onValueChange={setName} isRequired />
                  <Input label="Institution" value={institution} onValueChange={setInstitution} isRequired />
                  <Input label="Contact email" value={contactEmail} onValueChange={setContactEmail} type="email" />
                  <Input label="Agreement reference" value={agreementReference} onValueChange={setAgreementReference} />
                  <Input label="Methodology URL" value={methodologyUrl} onValueChange={setMethodologyUrl} className="md:col-span-2" />
                  <Select
                    label="Status"
                    selectedKeys={[status]}
                    onChange={(event) => setStatus(event.target.value as PartnerStatus)}
                  >
                    {['draft', 'active', 'paused', 'ended'].map((item) => (
                      <SelectItem key={item}>{item}</SelectItem>
                    ))}
                  </Select>
                  <Textarea
                    label="Dataset scope"
                    value={scope}
                    onValueChange={setScope}
                    className="md:col-span-2"
                    description="Comma-separated dataset keys."
                  />
                </div>
              </ModalBody>
              <ModalFooter>
                <Button variant="flat" onPress={close}>Cancel</Button>
                <Button color="primary" onPress={() => void createPartner()} isDisabled={!name.trim() || !institution.trim()}>
                  Create
                </Button>
              </ModalFooter>
            </>
          )}
        </ModalContent>
      </Modal>

      <Modal isOpen={templateModalOpen} onOpenChange={setTemplateModalOpen} size="3xl" scrollBehavior="inside">
        <ModalContent>
          {(close) => (
            <>
              <ModalHeader>
                {activeTemplate?.title ?? 'Agreement Template'}
              </ModalHeader>
              <ModalBody className="gap-4">
                {activeTemplate && (
                  <>
                    <p className="text-sm text-default-500">{activeTemplate.summary}</p>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                      {activeTemplate.placeholders.map((p) => (
                        <Input
                          key={p}
                          label={p.replace(/_/g, ' ')}
                          size="sm"
                          value={templateValues[p] ?? ''}
                          onValueChange={(v) =>
                            setTemplateValues((prev) => ({ ...prev, [p]: v }))
                          }
                        />
                      ))}
                    </div>
                    {renderedTemplate && (
                      <div className="rounded-lg border border-default-200 bg-default-50 dark:bg-default-100/30 p-3">
                        <div className="flex items-center justify-between mb-2">
                          <span className="text-xs font-semibold">
                            Rendered Markdown ({renderedTemplate.markdown.length} chars)
                          </span>
                          {renderedTemplate.placeholders_missing.length > 0 && (
                            <Chip size="sm" color="warning" variant="flat">
                              {renderedTemplate.placeholders_missing.length} placeholder
                              {renderedTemplate.placeholders_missing.length === 1 ? '' : 's'} missing
                            </Chip>
                          )}
                        </div>
                        <pre className="text-xs whitespace-pre-wrap font-mono max-h-80 overflow-y-auto">
                          {renderedTemplate.markdown}
                        </pre>
                      </div>
                    )}
                  </>
                )}
              </ModalBody>
              <ModalFooter>
                <Button variant="flat" onPress={close}>Close</Button>
                {renderedTemplate && (
                  <Button variant="flat" color="primary" onPress={downloadRendered}>
                    Download Markdown
                  </Button>
                )}
                <Button color="primary" onPress={() => void renderTemplate()} isLoading={renderingTemplate}>
                  {renderedTemplate ? 'Re-render' : 'Render'}
                </Button>
              </ModalFooter>
            </>
          )}
        </ModalContent>
      </Modal>

      <Modal isOpen={exportModalOpen} onOpenChange={setExportModalOpen} size="md">
        <ModalContent>
          {(close) => (
            <>
              <ModalHeader>Generate Aggregate Dataset</ModalHeader>
              <ModalBody>
                <Select
                  label="Active research partner"
                  selectedKeys={selectedPartnerId ? [String(selectedPartnerId)] : []}
                  onChange={(event) => setSelectedPartnerId(Number(event.target.value) || null)}
                >
                  {activePartners.map((partner) => (
                    <SelectItem key={String(partner.id)}>{partner.name}</SelectItem>
                  ))}
                </Select>
                <div className="grid grid-cols-2 gap-3">
                  <Input label="Period start" type="date" value={periodStart} onValueChange={setPeriodStart} />
                  <Input label="Period end" type="date" value={periodEnd} onValueChange={setPeriodEnd} />
                </div>
                <p className="text-xs text-default-500">
                  Exports are aggregate only and use suppression thresholds. Direct member identifiers are not included.
                </p>
              </ModalBody>
              <ModalFooter>
                <Button variant="flat" onPress={close}>Cancel</Button>
                <Button color="primary" onPress={() => void generateExport()} isDisabled={!selectedPartnerId} isLoading={workingId === selectedPartnerId}>
                  Generate
                </Button>
              </ModalFooter>
            </>
          )}
        </ModalContent>
      </Modal>
    </div>
  );
}
