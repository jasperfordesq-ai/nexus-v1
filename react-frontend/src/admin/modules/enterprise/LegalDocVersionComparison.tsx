import { useState, useEffect } from 'react';
import {
  ModalHeader,
  ModalBody,
  ModalFooter,
  Button,
  Spinner,
  Chip,
} from '@heroui/react';
import { GitCompare, FileText, AlertCircle } from 'lucide-react';
import { useToast } from '@/contexts/ToastContext';
import { adminLegalDocs } from '@/admin/api/adminApi';
import type { VersionComparison } from '@/admin/api/types';

interface LegalDocVersionComparisonProps {
  documentId: number;
  version1Id: number;
  version2Id: number;
  onClose: () => void;
}

export default function LegalDocVersionComparison({
  documentId,
  version1Id,
  version2Id,
  onClose,
}: LegalDocVersionComparisonProps) {
  const { error } = useToast();

  const [comparison, setComparison] = useState<VersionComparison | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    loadComparison();
  }, [documentId, version1Id, version2Id]);

  const loadComparison = async () => {
    try {
      setLoading(true);
      const response = await adminLegalDocs.compareVersions(documentId, version1Id, version2Id);

      if (response.success && response.data) {
        setComparison(response.data);
      } else {
        error(response.error || 'Failed to load comparison');
      }
    } catch (err) {
      error('Failed to load comparison');
    } finally {
      setLoading(false);
    }
  };

  return (
    <>
      <ModalHeader className="flex items-center gap-2">
        <GitCompare size={20} />
        <span>Version Comparison</span>
      </ModalHeader>

      <ModalBody>
        {loading ? (
          <div className="flex justify-center items-center min-h-[300px]">
            <Spinner size="lg" />
          </div>
        ) : comparison ? (
          <div className="space-y-6">
            {/* Version Headers */}
            <div className="grid grid-cols-2 gap-4">
              <div className="p-4 border rounded-lg">
                <div className="flex items-center gap-2 mb-2">
                  <FileText size={16} />
                  <span className="font-semibold">
                    Version {comparison.version1.version_number}
                  </span>
                  {comparison.version1.is_current && (
                    <Chip size="sm" color="success">Current</Chip>
                  )}
                </div>
                <p className="text-sm text-[var(--color-text-secondary)]">
                  Created: {new Date(comparison.version1.created_at).toLocaleDateString()}
                </p>
                {comparison.version1.effective_date && (
                  <p className="text-sm text-[var(--color-text-secondary)]">
                    Effective: {new Date(comparison.version1.effective_date).toLocaleDateString()}
                  </p>
                )}
              </div>

              <div className="p-4 border rounded-lg">
                <div className="flex items-center gap-2 mb-2">
                  <FileText size={16} />
                  <span className="font-semibold">
                    Version {comparison.version2.version_number}
                  </span>
                  {comparison.version2.is_current && (
                    <Chip size="sm" color="success">Current</Chip>
                  )}
                </div>
                <p className="text-sm text-[var(--color-text-secondary)]">
                  Created: {new Date(comparison.version2.created_at).toLocaleDateString()}
                </p>
                {comparison.version2.effective_date && (
                  <p className="text-sm text-[var(--color-text-secondary)]">
                    Effective: {new Date(comparison.version2.effective_date).toLocaleDateString()}
                  </p>
                )}
              </div>
            </div>

            {/* Changes Count */}
            <div className="flex items-center gap-2 p-3 bg-primary-50 dark:bg-primary-900/20 rounded-lg">
              <AlertCircle size={18} className="text-primary" />
              <span className="text-sm">
                {comparison.changes_count > 0
                  ? `${comparison.changes_count} changes detected`
                  : 'No significant changes detected'}
              </span>
            </div>

            {/* Diff Display */}
            <div>
              <h3 className="font-semibold mb-3">Content Comparison</h3>
              <div
                className="prose dark:prose-invert max-w-none p-4 border rounded-lg bg-[var(--color-surface)]"
                dangerouslySetInnerHTML={{ __html: comparison.diff_html }}
              />
            </div>

            {/* Summary of Changes */}
            {comparison.version1.summary_of_changes && (
              <div>
                <h3 className="font-semibold mb-2">
                  Summary (v{comparison.version1.version_number})
                </h3>
                <p className="text-sm text-[var(--color-text-secondary)] p-3 bg-[var(--color-surface)] rounded-lg">
                  {comparison.version1.summary_of_changes}
                </p>
              </div>
            )}

            {comparison.version2.summary_of_changes && (
              <div>
                <h3 className="font-semibold mb-2">
                  Summary (v{comparison.version2.version_number})
                </h3>
                <p className="text-sm text-[var(--color-text-secondary)] p-3 bg-[var(--color-surface)] rounded-lg">
                  {comparison.version2.summary_of_changes}
                </p>
              </div>
            )}
          </div>
        ) : (
          <div className="text-center py-12">
            <AlertCircle size={48} className="mx-auto text-[var(--color-text-tertiary)] mb-4" />
            <p className="text-[var(--color-text-secondary)]">Failed to load comparison</p>
          </div>
        )}
      </ModalBody>

      <ModalFooter>
        <Button color="primary" onPress={onClose}>
          Close
        </Button>
      </ModalFooter>
    </>
  );
}
