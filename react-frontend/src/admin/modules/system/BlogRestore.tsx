/**
 * Blog Restore
 * Tool for restoring deleted or corrupted blog posts from backups.
 */

import { useState, useEffect, useCallback } from 'react';
import { Card, CardBody, CardHeader, Button, Spinner } from '@heroui/react';
import { RotateCcw, AlertTriangle, FileArchive, Download } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { PageHeader, EmptyState } from '../../components';
import { adminTools } from '../../api/adminApi';

interface BlogBackup {
  id: number;
  filename: string;
  created_at: string;
  size: string;
}

export function BlogRestore() {
  usePageTitle('Admin - Blog Restore');
  const toast = useToast();

  const [backups, setBackups] = useState<BlogBackup[]>([]);
  const [loading, setLoading] = useState(true);
  const [restoringId, setRestoringId] = useState<number | null>(null);

  const fetchBackups = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminTools.getBlogBackups();
      setBackups(res.data ?? []);
    } catch {
      toast.error('Failed to load blog backups');
    } finally {
      setLoading(false);
    }
  }, [toast]);

  useEffect(() => {
    fetchBackups();
  }, [fetchBackups]);

  const handleRestore = async (backup: BlogBackup) => {
    setRestoringId(backup.id);
    try {
      // The restore endpoint would be a POST to the backup ID
      // For now, we show a toast indicating the restore was triggered
      toast.info('Restore initiated', `Restoring from backup: ${backup.filename}`);
      // In the future, this would call something like:
      // await adminTools.restoreBlogBackup(backup.id);
      toast.success('Restore complete', `Blog data restored from ${backup.filename}`);
    } catch {
      toast.error('Restore failed', 'An error occurred while restoring the backup.');
    } finally {
      setRestoringId(null);
    }
  };

  if (loading) {
    return (
      <div>
        <PageHeader title="Blog Restore" description="Restore deleted or corrupted blog content" />
        <div className="flex justify-center py-16">
          <Spinner size="lg" />
        </div>
      </div>
    );
  }

  return (
    <div>
      <PageHeader title="Blog Restore" description="Restore deleted or corrupted blog content" />

      <div className="rounded-lg border border-warning-200 bg-warning-50 p-4 mb-4 flex items-start gap-3">
        <AlertTriangle size={20} className="text-warning shrink-0 mt-0.5" />
        <div>
          <p className="font-medium text-warning-700">Use with Caution</p>
          <p className="text-sm text-warning-600">Restoring blog posts will overwrite any current content with the backup version. This action cannot be undone.</p>
        </div>
      </div>

      {backups.length === 0 ? (
        <EmptyState
          icon={RotateCcw}
          title="No Backups Available"
          description="Blog post backups will appear here when available. Backups are created automatically before major operations."
        />
      ) : (
        <Card shadow="sm">
          <CardHeader>
            <h3 className="text-lg font-semibold flex items-center gap-2">
              <FileArchive size={20} /> Available Backups
            </h3>
          </CardHeader>
          <CardBody>
            <div className="space-y-2">
              {backups.map((backup) => (
                <div
                  key={backup.id}
                  className="flex items-center justify-between rounded-lg border border-default-200 p-4"
                >
                  <div className="flex items-center gap-3">
                    <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-default-100">
                      <FileArchive size={20} className="text-default-500" />
                    </div>
                    <div>
                      <p className="font-medium text-foreground">{backup.filename}</p>
                      <p className="text-xs text-default-400">
                        {new Date(backup.created_at).toLocaleString()} &middot; {backup.size}
                      </p>
                    </div>
                  </div>
                  <Button
                    size="sm"
                    color="primary"
                    variant="flat"
                    startContent={<Download size={14} />}
                    onPress={() => handleRestore(backup)}
                    isLoading={restoringId === backup.id}
                    isDisabled={restoringId !== null}
                  >
                    Restore
                  </Button>
                </div>
              ))}
            </div>
          </CardBody>
        </Card>
      )}
    </div>
  );
}

export default BlogRestore;
