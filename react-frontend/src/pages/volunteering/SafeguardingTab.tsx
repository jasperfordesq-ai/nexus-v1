// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { useState, useEffect, useCallback, useRef } from 'react';
import { motion } from 'framer-motion';
import { Button, Chip, Input, Select, SelectItem, Modal, ModalContent, ModalHeader, ModalBody, ModalFooter, Textarea, useDisclosure } from '@heroui/react';
import ShieldCheck from 'lucide-react/icons/shield-check';
import GraduationCap from 'lucide-react/icons/graduation-cap';
import AlertTriangle from 'lucide-react/icons/triangle-alert';
import Plus from 'lucide-react/icons/plus';
import RefreshCw from 'lucide-react/icons/refresh-cw';
import FileWarning from 'lucide-react/icons/file-warning';
import Calendar from 'lucide-react/icons/calendar';
import { useTranslation } from 'react-i18next';
import { GlassCard } from '@/components/ui';
import { EmptyState } from '@/components/feedback';
import { useToast } from '@/contexts';
import { api } from '@/lib/api';
import { logError } from '@/lib/logger';

interface Training { id: number; training_type: 'children_first' | 'vulnerable_adults' | 'first_aid' | 'manual_handling' | 'other'; training_name: string; provider: string | null; completed_at: string; expires_at: string | null; status: 'pending' | 'verified' | 'expired' | 'rejected'; created_at: string; }
interface Incident { id: number; title: string; description: string; severity: 'low' | 'medium' | 'high' | 'critical'; category: string; status: 'open' | 'investigating' | 'resolved' | 'escalated' | 'closed'; created_at: string; }
type SubView = 'training' | 'incidents';

const TRAINING_TYPE_KEYS = ['children_first', 'vulnerable_adults', 'first_aid', 'manual_handling', 'other'] as const;
const SEVERITY_KEYS = ['low', 'medium', 'high', 'critical'] as const;

function trainingStatusColor(s: Training['status']): 'warning'|'success'|'danger' { if (s==='pending') return 'warning'; if (s==='verified') return 'success'; return 'danger'; }
function severityColor(s: Incident['severity']): 'default'|'warning'|'danger' { if (s==='low') return 'default'; if (s==='medium') return 'warning'; return 'danger'; }
function incidentStatusColor(s: Incident['status']): 'warning'|'success'|'danger'|'primary'|'default' { if (s==='open') return 'warning'; if (s==='investigating') return 'primary'; if (s==='resolved') return 'success'; if (s==='escalated') return 'danger'; return 'default'; }

export function SafeguardingTab() {
  const { t } = useTranslation('volunteering');
  const toast = useToast();
  const [subView, setSubView] = useState<SubView>('training');
  const [trainings, setTrainings] = useState<Training[]>([]);
  const [incidents, setIncidents] = useState<Incident[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const trainingModal = useDisclosure();
  const [trainingForm, setTrainingForm] = useState({ training_type: 'children_first', training_name: '', provider: '', completed_at: '', expires_at: '' });
  const [isSubmittingTraining, setIsSubmittingTraining] = useState(false);
  const incidentModal = useDisclosure();
  const [incidentForm, setIncidentForm] = useState({ title: '', description: '', severity: 'low', category: '' });
  const [isSubmittingIncident, setIsSubmittingIncident] = useState(false);

  // AbortController ref to cancel stale requests
  const abortRef = useRef<AbortController | null>(null);

  // Stable refs for t/toast — avoids re-creating callbacks when i18n namespace loads
  const tRef = useRef(t);
  tRef.current = t;
  const toastRef = useRef(toast);
  toastRef.current = toast;

  const load = useCallback(async () => {
    abortRef.current?.abort();
    const controller = new AbortController();
    abortRef.current = controller;

    try {
      setIsLoading(true); setError(null);
      const [tRes, iRes] = await Promise.all([api.get<{ items: Training[]; cursor?: string | null; has_more?: boolean }>('/v2/volunteering/training'), api.get<{ items: Incident[]; total?: number; page?: number; per_page?: number }>('/v2/volunteering/incidents')]);
      if (controller.signal.aborted) return;
      if (tRes.success && tRes.data) {
        const tPayload = tRes.data as Record<string, unknown>;
        setTrainings(Array.isArray(tPayload.items) ? tPayload.items as Training[] : Array.isArray(tRes.data) ? tRes.data as unknown as Training[] : []);
      }
      if (iRes.success && iRes.data) {
        const iPayload = iRes.data as Record<string, unknown>;
        setIncidents(Array.isArray(iPayload.items) ? iPayload.items as Incident[] : Array.isArray(iRes.data) ? iRes.data as unknown as Incident[] : []);
      }
    } catch (err) { if (controller.signal.aborted) return; logError('Failed to load safeguarding data', err); setError(tRef.current('safeguarding.load_error', 'Unable to load safeguarding data.')); }
    finally { setIsLoading(false); }
  }, []);
  useEffect(() => { load(); }, [load]);

  const handleSubmitTraining = async () => {
    if (!trainingForm.training_name.trim() || !trainingForm.completed_at) { toastRef.current.error(tRef.current('safeguarding.fill_required', 'Please fill in all required fields.')); return; }
    try {
      setIsSubmittingTraining(true);
      const res = await api.post('/v2/volunteering/training', { training_type: trainingForm.training_type, training_name: trainingForm.training_name.trim(), provider: trainingForm.provider.trim() || null, completed_at: trainingForm.completed_at, expires_at: trainingForm.expires_at || null });
      if (res.success) { trainingModal.onClose(); setTrainingForm({ training_type: 'children_first', training_name: '', provider: '', completed_at: '', expires_at: '' }); toastRef.current.success(tRef.current('safeguarding.training_added', 'Training record submitted.')); load(); }
      else { toastRef.current.error(tRef.current('safeguarding.training_failed', 'Failed to submit training record.')); }
    } catch (err) { logError('Failed to submit training record', err); toastRef.current.error(tRef.current('safeguarding.training_failed', 'Failed to submit training record.')); }
    finally { setIsSubmittingTraining(false); }
  };

  const handleSubmitIncident = async () => {
    if (!incidentForm.title.trim() || !incidentForm.description.trim()) { toastRef.current.error(tRef.current('safeguarding.fill_required', 'Please fill in all required fields.')); return; }
    try {
      setIsSubmittingIncident(true);
      const res = await api.post('/v2/volunteering/incidents', { title: incidentForm.title.trim(), description: incidentForm.description.trim(), severity: incidentForm.severity, category: incidentForm.category.trim() || undefined });
      if (res.success) { incidentModal.onClose(); setIncidentForm({ title: '', description: '', severity: 'low', category: '' }); toastRef.current.success(tRef.current('safeguarding.incident_reported', 'Incident reported.')); load(); }
      else { toastRef.current.error(tRef.current('safeguarding.incident_failed', 'Failed to report incident.')); }
    } catch (err) { logError('Failed to report incident', err); toastRef.current.error(tRef.current('safeguarding.incident_failed', 'Failed to report incident.')); }
    finally { setIsSubmittingIncident(false); }
  };

  const cV = { hidden: { opacity: 0 }, visible: { opacity: 1, transition: { staggerChildren: 0.05 } } };
  const iV = { hidden: { opacity: 0, y: 20 }, visible: { opacity: 1, y: 0 } };

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-2">
          <ShieldCheck className="w-5 h-5 text-rose-400" aria-hidden="true" />
          <h2 className="text-lg font-semibold text-theme-primary">{t('safeguarding.heading', 'Safeguarding')}</h2>
        </div>
        <Button size="sm" className="bg-gradient-to-r from-rose-500 to-pink-600 text-white" startContent={<Plus className="w-4 h-4" aria-hidden="true" />} onPress={subView === 'training' ? trainingModal.onOpen : incidentModal.onOpen}>
          {subView === 'training' ? t('safeguarding.add_training', 'Add Training') : t('safeguarding.report_incident', 'Report Incident')}
        </Button>
      </div>
      <div className="flex gap-2">
        <Button size="sm" variant={subView === 'training' ? 'solid' : 'flat'} color={subView === 'training' ? 'primary' : 'default'} startContent={<GraduationCap className="w-4 h-4" aria-hidden="true" />} onPress={() => setSubView('training')}>{t('safeguarding.training_records', 'Training Records')}</Button>
        <Button size="sm" variant={subView === 'incidents' ? 'solid' : 'flat'} color={subView === 'incidents' ? 'primary' : 'default'} startContent={<FileWarning className="w-4 h-4" aria-hidden="true" />} onPress={() => setSubView('incidents')}>{t('safeguarding.incident_reports', 'Incident Reports')}</Button>
      </div>
      {error && !isLoading && (<GlassCard className="p-8 text-center"><AlertTriangle className="w-12 h-12 text-[var(--color-warning)] mx-auto mb-4" aria-hidden="true" /><p className="text-theme-muted mb-4">{error}</p><Button className="bg-gradient-to-r from-rose-500 to-pink-600 text-white" startContent={<RefreshCw className="w-4 h-4" aria-hidden="true" />} onPress={load}>{t('safeguarding.try_again', 'Try Again')}</Button></GlassCard>)}
      {!error && isLoading && (<div className="space-y-3">{[1, 2, 3].map((i) => (<GlassCard key={i} className="p-5 animate-pulse"><div className="h-4 bg-theme-hover rounded w-1/3 mb-2" /><div className="h-3 bg-theme-hover rounded w-2/3" /></GlassCard>))}</div>)}
      {!error && !isLoading && subView === 'training' && (trainings.length === 0 ? (<EmptyState icon={<GraduationCap className="w-12 h-12" aria-hidden="true" />} title={t('safeguarding.no_training_title', 'No training records')} description={t('safeguarding.no_training_desc', 'Add your safeguarding training records to get started.')} action={<Button className="bg-gradient-to-r from-rose-500 to-pink-600 text-white" onPress={trainingModal.onOpen}>{t('safeguarding.add_training', 'Add Training')}</Button>} />) : (
        <motion.div variants={cV} initial="hidden" animate="visible" className="space-y-3">
          {trainings.map((tr) => (<motion.div key={tr.id} variants={iV}><GlassCard className="p-4"><div className="flex items-start justify-between gap-3"><div className="flex-1 min-w-0"><div className="flex items-center gap-2 mb-1"><span className="text-sm font-semibold text-theme-primary">{tr.training_name}</span><Chip size="sm" color={trainingStatusColor(tr.status)} variant="flat">{t(`safeguarding.status_${tr.status}`, tr.status)}</Chip></div><p className="text-xs text-theme-muted">{t(`safeguarding.training_types.${tr.training_type}`, tr.training_type)}{tr.provider && ` — ${tr.provider}`}</p><div className="flex items-center gap-3 mt-1 text-xs text-theme-subtle"><span className="flex items-center gap-1"><Calendar className="w-3 h-3" aria-hidden="true" />{t('safeguarding.completed', 'Completed')}: {new Date(tr.completed_at).toLocaleDateString()}</span>{tr.expires_at && (<span>{t('safeguarding.expires', 'Expires')}: {new Date(tr.expires_at).toLocaleDateString()}</span>)}</div></div></div></GlassCard></motion.div>))}
        </motion.div>))}
      {!error && !isLoading && subView === 'incidents' && (incidents.length === 0 ? (<EmptyState icon={<FileWarning className="w-12 h-12" aria-hidden="true" />} title={t('safeguarding.no_incidents_title', 'No incidents reported')} description={t('safeguarding.no_incidents_desc', 'No safeguarding incidents have been reported.')} />) : (
        <motion.div variants={cV} initial="hidden" animate="visible" className="space-y-3">
          {incidents.map((inc) => (<motion.div key={inc.id} variants={iV}><GlassCard className="p-4"><div className="flex items-start justify-between gap-3"><div className="flex-1 min-w-0"><div className="flex items-center gap-2 mb-1"><span className="text-sm font-semibold text-theme-primary">{inc.title}</span><Chip size="sm" color={severityColor(inc.severity)} variant="flat" className={inc.severity === 'critical' ? 'font-bold' : ''}>{t(`safeguarding.severity_options.${inc.severity}`, inc.severity)}</Chip><Chip size="sm" color={incidentStatusColor(inc.status)} variant="flat">{t(`safeguarding.incident_status.${inc.status}`, inc.status)}</Chip></div><p className="text-xs text-theme-muted line-clamp-2">{inc.description}</p><div className="flex items-center gap-3 mt-1 text-xs text-theme-subtle">{inc.category && <span>{inc.category}</span>}<span>{new Date(inc.created_at).toLocaleDateString()}</span></div></div></div></GlassCard></motion.div>))}
        </motion.div>))}
      <Modal isOpen={trainingModal.isOpen} onClose={() => { setTrainingForm({ training_type: 'children_first', training_name: '', provider: '', completed_at: '', expires_at: '' }); trainingModal.onClose(); }} size="lg" classNames={{ base: 'bg-content1 border border-theme-default' }}><ModalContent><ModalHeader className="text-theme-primary"><div className="flex items-center gap-2"><GraduationCap className="w-5 h-5 text-rose-400" aria-hidden="true" />{t('safeguarding.add_training_title', 'Add Training Record')}</div></ModalHeader><ModalBody className="space-y-4"><Select label={t('safeguarding.training_type', 'Training Type')} selectedKeys={new Set([trainingForm.training_type])} onSelectionChange={(keys) => { const val = Array.from(keys)[0] as string; if (val) setTrainingForm((f) => ({ ...f, training_type: val })); }} classNames={{ trigger: 'bg-theme-elevated border-theme-default' }}>{TRAINING_TYPE_KEYS.map((key) => (<SelectItem key={key}>{t(`safeguarding.training_types.${key}`, key)}</SelectItem>))}</Select><Input label={t('safeguarding.training_name', 'Training Name')} isRequired value={trainingForm.training_name} onChange={(e) => setTrainingForm((f) => ({ ...f, training_name: e.target.value }))} classNames={{ inputWrapper: 'bg-theme-elevated border-theme-default' }} /><Input label={t('safeguarding.provider', 'Provider')} value={trainingForm.provider} onChange={(e) => setTrainingForm((f) => ({ ...f, provider: e.target.value }))} classNames={{ inputWrapper: 'bg-theme-elevated border-theme-default' }} /><Input label={t('safeguarding.completed_at', 'Date Completed')} type="date" isRequired value={trainingForm.completed_at} onChange={(e) => setTrainingForm((f) => ({ ...f, completed_at: e.target.value }))} classNames={{ inputWrapper: 'bg-theme-elevated border-theme-default' }} /><Input label={t('safeguarding.expires_at', 'Expiry Date')} type="date" value={trainingForm.expires_at} onChange={(e) => setTrainingForm((f) => ({ ...f, expires_at: e.target.value }))} classNames={{ inputWrapper: 'bg-theme-elevated border-theme-default' }} /></ModalBody><ModalFooter><Button variant="flat" onPress={() => { setTrainingForm({ training_type: 'children_first', training_name: '', provider: '', completed_at: '', expires_at: '' }); trainingModal.onClose(); }} className="text-theme-muted">{t('safeguarding.cancel', 'Cancel')}</Button><Button className="bg-gradient-to-r from-rose-500 to-pink-600 text-white" onPress={handleSubmitTraining} isLoading={isSubmittingTraining} startContent={!isSubmittingTraining ? <Plus className="w-4 h-4" aria-hidden="true" /> : undefined}>{t('safeguarding.submit_training', 'Submit')}</Button></ModalFooter></ModalContent></Modal>
      <Modal isOpen={incidentModal.isOpen} onClose={() => { setIncidentForm({ title: '', description: '', severity: 'low', category: '' }); incidentModal.onClose(); }} size="lg" classNames={{ base: 'bg-content1 border border-theme-default' }}><ModalContent><ModalHeader className="text-theme-primary"><div className="flex items-center gap-2"><FileWarning className="w-5 h-5 text-amber-400" aria-hidden="true" />{t('safeguarding.report_incident_title', 'Report Incident')}</div></ModalHeader><ModalBody className="space-y-4"><Input label={t('safeguarding.incident_title', 'Title')} isRequired value={incidentForm.title} onChange={(e) => setIncidentForm((f) => ({ ...f, title: e.target.value }))} classNames={{ inputWrapper: 'bg-theme-elevated border-theme-default' }} /><Textarea label={t('safeguarding.incident_description', 'Description')} isRequired value={incidentForm.description} onChange={(e) => setIncidentForm((f) => ({ ...f, description: e.target.value }))} maxLength={2000} classNames={{ input: 'bg-transparent text-theme-primary', inputWrapper: 'bg-theme-elevated border-theme-default' }} /><Select label={t('safeguarding.severity', 'Severity')} selectedKeys={new Set([incidentForm.severity])} onSelectionChange={(keys) => { const val = Array.from(keys)[0] as string; if (val) setIncidentForm((f) => ({ ...f, severity: val })); }} classNames={{ trigger: 'bg-theme-elevated border-theme-default' }}>{SEVERITY_KEYS.map((key) => (<SelectItem key={key}>{t(`safeguarding.severity_options.${key}`, key)}</SelectItem>))}</Select><Input label={t('safeguarding.category', 'Category')} value={incidentForm.category} onChange={(e) => setIncidentForm((f) => ({ ...f, category: e.target.value }))} classNames={{ inputWrapper: 'bg-theme-elevated border-theme-default' }} /></ModalBody><ModalFooter><Button variant="flat" onPress={() => { setIncidentForm({ title: '', description: '', severity: 'low', category: '' }); incidentModal.onClose(); }} className="text-theme-muted">{t('safeguarding.cancel', 'Cancel')}</Button><Button className="bg-gradient-to-r from-amber-500 to-orange-600 text-white" onPress={handleSubmitIncident} isLoading={isSubmittingIncident} startContent={!isSubmittingIncident ? <AlertTriangle className="w-4 h-4" aria-hidden="true" /> : undefined}>{t('safeguarding.submit_incident', 'Report')}</Button></ModalFooter></ModalContent></Modal>
    </div>
  );
}

export default SafeguardingTab;
