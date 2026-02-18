/**
 * Newsletter Bounces
 * Bounce tracking and suppression list management
 */

import { useState, useCallback, useEffect } from 'react';
import {
  Button, Card, CardBody, CardHeader, Tabs, Tab, Chip, Input,
  Select, SelectItem, Table, TableHeader, TableColumn, TableBody, TableRow, TableCell,
} from '@heroui/react';
import { AlertTriangle, RefreshCw, Trash2, Download, Search } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { adminNewsletters } from '../../api/adminApi';
import { PageHeader, ConfirmModal } from '../../components';
import type { NewsletterBounce, SuppressionListEntry } from '../../api/types';

export function NewsletterBounces() {
  usePageTitle('Admin - Newsletter Bounces');
  const toast = useToast();
  const [activeTab, setActiveTab] = useState('bounces');
  const [bounces, setBounces] = useState<NewsletterBounce[]>([]);
  const [suppressionList, setSuppressionList] = useState<SuppressionListEntry[]>([]);
  const [loading, setLoading] = useState(true);
  const [typeFilter, setTypeFilter] = useState<string>('all');
  const [searchQuery, setSearchQuery] = useState('');
  const [unsuppressTarget, setUnsuppressTarget] = useState<string | null>(null);
  const [processing, setProcessing] = useState(false);

  // Stats state
  const [totalBounces7d, setTotalBounces7d] = useState(0);
  const [hardBounces, setHardBounces] = useState(0);
  const [suppressedCount, setSuppressedCount] = useState(0);

  const loadBounces = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminNewsletters.getBounces({ limit: 100, type: typeFilter === 'all' ? undefined : typeFilter });
      if (res.success && res.data) {
        const data = Array.isArray(res.data) ? res.data : [];
        setBounces(data as NewsletterBounce[]);

        // Calculate stats
        const last7Days = Date.now() - 7 * 24 * 60 * 60 * 1000;
        const recent = data.filter((b: NewsletterBounce) => new Date(b.bounced_at).getTime() > last7Days);
        setTotalBounces7d(recent.length);
        setHardBounces(data.filter((b: NewsletterBounce) => b.bounce_type === 'hard').length);
      }
    } catch {
      setBounces([]);
    }
    setLoading(false);
  }, [typeFilter]);

  const loadSuppressionList = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminNewsletters.getSuppressionList();
      if (res.success && res.data) {
        const data = Array.isArray(res.data) ? res.data : [];
        setSuppressionList(data as SuppressionListEntry[]);
        setSuppressedCount(data.length);
      }
    } catch {
      setSuppressionList([]);
    }
    setLoading(false);
  }, []);

  useEffect(() => {
    if (activeTab === 'bounces') loadBounces();
    else loadSuppressionList();
  }, [activeTab, loadBounces, loadSuppressionList]);

  const handleUnsuppress = async () => {
    if (!unsuppressTarget) return;
    setProcessing(true);
    try {
      const res = await adminNewsletters.unsuppress(unsuppressTarget);
      if (res.success) {
        toast.success(`${unsuppressTarget} removed from suppression list`);
        setUnsuppressTarget(null);
        loadSuppressionList();
      } else {
        toast.error('Failed to unsuppress email');
      }
    } catch {
      toast.error('Failed to unsuppress email');
    }
    setProcessing(false);
  };

  const exportBounces = () => {
    const csv = [
      ['Email', 'Bounce Type', 'Reason', 'Campaign', 'Date'].join(','),
      ...bounces.map(b =>
        [b.email, b.bounce_type, b.bounce_reason || '', b.newsletter_subject || '', b.bounced_at].join(',')
      ),
    ].join('\n');
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `newsletter-bounces-${new Date().toISOString().split('T')[0]}.csv`;
    a.click();
    URL.revokeObjectURL(url);
  };

  const filteredBounces = bounces.filter(b =>
    searchQuery === '' || b.email.toLowerCase().includes(searchQuery.toLowerCase())
  );

  const filteredSuppression = suppressionList.filter(s =>
    searchQuery === '' || s.email.toLowerCase().includes(searchQuery.toLowerCase())
  );

  const getBadgeColor = (type: string) => {
    switch (type) {
      case 'hard': return 'danger';
      case 'soft': return 'warning';
      case 'complaint': return 'danger';
      default: return 'default';
    }
  };

  return (
    <div>
      <PageHeader
        title="Newsletter Bounces"
        description="Bounce tracking and suppression list management"
        actions={
          <div className="flex gap-2">
            <Button
              variant="flat"
              startContent={<RefreshCw size={16} />}
              onPress={() => activeTab === 'bounces' ? loadBounces() : loadSuppressionList()}
              isLoading={loading}
            >
              Refresh
            </Button>
            {activeTab === 'bounces' && (
              <Button
                variant="flat"
                startContent={<Download size={16} />}
                onPress={exportBounces}
                isDisabled={bounces.length === 0}
              >
                Export CSV
              </Button>
            )}
          </div>
        }
      />

      <div className="grid gap-6 md:grid-cols-3 mb-6">
        <Card>
          <CardBody className="gap-2">
            <div className="flex items-center justify-between">
              <span className="text-sm text-default-500">Total Bounces (7d)</span>
              <AlertTriangle size={20} className="text-warning" />
            </div>
            <p className="text-2xl font-bold">{totalBounces7d.toLocaleString()}</p>
          </CardBody>
        </Card>

        <Card>
          <CardBody className="gap-2">
            <div className="flex items-center justify-between">
              <span className="text-sm text-default-500">Hard Bounces</span>
              <AlertTriangle size={20} className="text-danger" />
            </div>
            <p className="text-2xl font-bold">{hardBounces.toLocaleString()}</p>
          </CardBody>
        </Card>

        <Card>
          <CardBody className="gap-2">
            <div className="flex items-center justify-between">
              <span className="text-sm text-default-500">Suppressed Emails</span>
              <Trash2 size={20} className="text-default-400" />
            </div>
            <p className="text-2xl font-bold">{suppressedCount.toLocaleString()}</p>
          </CardBody>
        </Card>
      </div>

      <Card>
        <CardHeader className="flex flex-col gap-3">
          <Tabs
            selectedKey={activeTab}
            onSelectionChange={(key) => setActiveTab(key as string)}
            aria-label="Bounce tabs"
          >
            <Tab key="bounces" title="Recent Bounces" />
            <Tab key="suppression" title="Suppression List" />
          </Tabs>

          <div className="flex gap-2 w-full">
            <Input
              placeholder="Search by email..."
              value={searchQuery}
              onValueChange={setSearchQuery}
              startContent={<Search size={16} />}
              className="flex-1"
            />
            {activeTab === 'bounces' && (
              <Select
                label="Bounce Type"
                selectedKeys={[typeFilter]}
                onSelectionChange={(keys) => setTypeFilter(Array.from(keys)[0] as string)}
                className="w-40"
              >
                <SelectItem key="all">All Types</SelectItem>
                <SelectItem key="hard">Hard</SelectItem>
                <SelectItem key="soft">Soft</SelectItem>
                <SelectItem key="complaint">Complaint</SelectItem>
              </Select>
            )}
          </div>
        </CardHeader>
        <CardBody>
          {activeTab === 'bounces' ? (
            <Table aria-label="Bounces table" isStriped>
              <TableHeader>
                <TableColumn>EMAIL</TableColumn>
                <TableColumn>TYPE</TableColumn>
                <TableColumn>REASON</TableColumn>
                <TableColumn>CAMPAIGN</TableColumn>
                <TableColumn>DATE</TableColumn>
              </TableHeader>
              <TableBody
                items={filteredBounces}
                emptyContent={loading ? 'Loading...' : 'No bounces found'}
              >
                {(bounce) => (
                  <TableRow key={bounce.id}>
                    <TableCell>
                      <span className="font-mono text-sm">{bounce.email}</span>
                    </TableCell>
                    <TableCell>
                      <Chip size="sm" color={getBadgeColor(bounce.bounce_type)} variant="flat">
                        {bounce.bounce_type}
                      </Chip>
                    </TableCell>
                    <TableCell>
                      <span className="text-sm text-default-600">{bounce.bounce_reason || '—'}</span>
                    </TableCell>
                    <TableCell>
                      <span className="text-sm">{bounce.newsletter_subject || '—'}</span>
                    </TableCell>
                    <TableCell>
                      <span className="text-sm text-default-500">
                        {new Date(bounce.bounced_at).toLocaleDateString()}
                      </span>
                    </TableCell>
                  </TableRow>
                )}
              </TableBody>
            </Table>
          ) : (
            <Table aria-label="Suppression list table" isStriped>
              <TableHeader>
                <TableColumn>EMAIL</TableColumn>
                <TableColumn>REASON</TableColumn>
                <TableColumn>BOUNCE COUNT</TableColumn>
                <TableColumn>SUPPRESSED AT</TableColumn>
                <TableColumn>ACTIONS</TableColumn>
              </TableHeader>
              <TableBody
                items={filteredSuppression}
                emptyContent={loading ? 'Loading...' : 'No suppressed emails'}
              >
                {(entry) => (
                  <TableRow key={entry.email}>
                    <TableCell>
                      <span className="font-mono text-sm">{entry.email}</span>
                    </TableCell>
                    <TableCell>
                      <Chip size="sm" variant="flat">{entry.reason}</Chip>
                    </TableCell>
                    <TableCell>
                      <span className="text-sm">{entry.bounce_count}</span>
                    </TableCell>
                    <TableCell>
                      <span className="text-sm text-default-500">
                        {new Date(entry.suppressed_at).toLocaleDateString()}
                      </span>
                    </TableCell>
                    <TableCell>
                      <Button
                        size="sm"
                        variant="light"
                        color="primary"
                        onPress={() => setUnsuppressTarget(entry.email)}
                      >
                        Unsuppress
                      </Button>
                    </TableCell>
                  </TableRow>
                )}
              </TableBody>
            </Table>
          )}
        </CardBody>
      </Card>

      {unsuppressTarget && (
        <ConfirmModal
          isOpen={!!unsuppressTarget}
          onClose={() => setUnsuppressTarget(null)}
          onConfirm={handleUnsuppress}
          title="Unsuppress Email"
          message={`Remove "${unsuppressTarget}" from the suppression list? They will be able to receive newsletters again.`}
          confirmLabel="Unsuppress"
          confirmColor="primary"
          isLoading={processing}
        />
      )}
    </div>
  );
}

export default NewsletterBounces;
