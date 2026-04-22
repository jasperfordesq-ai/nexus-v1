// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Blog Restore
 * Tool for restoring deleted or corrupted blog posts from backups.
 * Loads real backup entries from the API and uses a ConfirmModal before restoring.
 */

import { useState, useEffect, useCallback } from 'react';
import { Card, CardBody, CardHeader, Button, Spinner } from '@heroui/react';
import RotateCcw from 'lucide-react/icons/rotate-ccw';
import AlertTriangle from 'lucide-react/icons/triangle-alert';
import FileArchive from 'lucide-react/icons/file-archive';
import Download from 'lucide-react/icons/download';
import { usePageTitle } from '@/hooks';
import { useToast } from '@/contexts';
import { PageHeader, EmptyState, ConfirmModal } from '../../components';
import { adminTools } from '../../api/adminApi';

interface BlogBackup {
  id: number;
  filename: string;
  created_at: string;
  size: string;
}

export function BlogRestore() {
  usePageTitle("System");
  const toast = useToast();

  const [backups, setBackups] = useState<BlogBackup[]>([]);
  const [loading, setLoading] = useState(true);
  const [restoringId, setRestoringId] = useState<number | null>(null);
  const [confirmBackup, setConfirmBackup] = useState<BlogBackup | null>(null);

  const fetchBackups = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminTools.getBlogBackups();
      if (res.success && res.data) {
        // Handle both direct array and wrapped { data: [...] } responses
        const payload = res.data as unknown;
        if (Array.isArray(payload)) {
          setBackups(payload);
        } else if (payload && typeof payload === 'object' && 'data' in payload) {
          setBackups((payload as { data: BlogBackup[] }).data);
        } else {
          setBackups([]);
        }
      } else {
        setBackups([]);
      }
    } catch {
      toast.error("Failed to load blog backups");
    } finally {
      setLoading(false);
    }
  }, [toast])


  useEffect(() => {
    fetchBackups();
  }, [fetchBackups]);

  const handleRestoreConfirm = useCallback(async () => {
    if (!confirmBackup) return;
    setRestoringId(confirmBackup.id);
    setConfirmBackup(null);
    try {
      const res = await adminTools.restoreBlogBackup(confirmBackup.id);
      if (res.success) {
        const restored = (res.data as { restored_count?: number })?.restored_count;
        const message = restored !== undefined
          ? `${restored} blog post${restored !== 1 ? 's' : ''} restored from ${confirmBackup.filename}`
          : `Blog data restored from ${confirmBackup.filename}`;
        toast.success("Restore Complete", message);
        // Refresh the backup list in case status changed
        await fetchBackups();
      } else {
        toast.error("Restore failed", "Restore Server error");
      }
    } catch {
      toast.error("Restore failed", "Restore Error Occurred");
    } finally {
      setRestoringId(null);
    }
  }, [confirmBackup, toast, fetchBackups])


  if (loading) {
    return (
      <div>
        <PageHeader title={"Blog Restore"} description={"Restore blog posts from automatic backups"} />
        <div className="flex justify-center py-16">
          <Spinner size="lg" />
        </div>
      </div>
    );
  }

  return (
    <div>
      <PageHeader title={"Blog Restore"} description={"Restore blog posts from automatic backups"} />

      <div className="rounded-lg border border-warning-200 bg-warning-50 p-4 mb-4 flex items-start gap-3">
        <AlertTriangle size={20} className="text-warning shrink-0 mt-0.5" />
        <div>
          <p className="font-medium text-warning-700">{"Use With Caution"}</p>
          <p className="text-sm text-warning-600">{"Blog Restore Warning"}</p>
        </div>
      </div>

      {backups.length === 0 ? (
        <EmptyState
          icon={RotateCcw}
          title={"No backups available found"}
          description={"Blog post backups will appear here when auto-backup is enabled"}
        />
      ) : (
        <Card shadow="sm">
          <CardHeader>
            <h3 className="text-lg font-semibold flex items-center gap-2">
              <FileArchive size={20} /> Available Backups ({backups.length})
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
                    onPress={() => setConfirmBackup(backup)}
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

      <ConfirmModal
        isOpen={confirmBackup !== null}
        onClose={() => setConfirmBackup(null)}
        onConfirm={handleRestoreConfirm}
        title={"Restore Blog Backup"}
        message={
          confirmBackup
            ? `Restore Blog Confirm`
            : ''
        }
        confirmLabel={"Restore Backup"}
        confirmColor="warning"
        isLoading={restoringId !== null}
      />
    </div>
  );
}

export default BlogRestore;
