/**
 * Tenant Hierarchy
 * Visual tree view of parent-child tenant relationships.
 */

import { useEffect, useState, useCallback } from 'react';
import { useNavigate } from 'react-router-dom';
import { Card, CardBody, Button, Chip, Spinner } from '@heroui/react';
import {
  ChevronRight,
  ChevronDown,
  Building2,
  Users,
  RefreshCw,
  Network,
} from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { adminSuper } from '../../api/adminApi';
import { PageHeader } from '../../components';
import type { TenantHierarchyNode } from '../../api/types';

interface TreeNodeProps {
  node: TenantHierarchyNode;
  depth: number;
  onNavigate: (id: number) => void;
}

function TreeNode({ node, depth, onNavigate }: TreeNodeProps) {
  const [expanded, setExpanded] = useState(depth < 2);
  const hasChildren = node.children && node.children.length > 0;

  return (
    <div>
      <div
        className="flex items-center gap-2 py-2 px-3 rounded-lg hover:bg-default-100 cursor-pointer transition-colors"
        style={{ paddingLeft: `${depth * 24 + 12}px` }}
      >
        {hasChildren ? (
          <Button
            isIconOnly
            size="sm"
            variant="light"
            onPress={() => setExpanded(!expanded)}
            className="shrink-0"
          >
            {expanded ? <ChevronDown size={16} /> : <ChevronRight size={16} />}
          </Button>
        ) : (
          <div className="w-8 shrink-0" />
        )}

        <div
          className="flex items-center gap-3 flex-1 min-w-0"
          onClick={() => onNavigate(node.id)}
          role="button"
          tabIndex={0}
          onKeyDown={(e) => { if (e.key === 'Enter') onNavigate(node.id); }}
        >
          <Building2 size={18} className="text-primary shrink-0" />
          <div className="min-w-0 flex-1">
            <span className="font-medium text-foreground">{node.name}</span>
            <span className="text-xs text-default-400 ml-2">({node.slug})</span>
          </div>
          <div className="flex items-center gap-2 shrink-0">
            <span className="flex items-center gap-1 text-xs text-default-500">
              <Users size={12} />
              {node.user_count}
            </span>
            <Chip
              size="sm"
              variant="flat"
              color={node.is_active ? 'success' : 'default'}
            >
              {node.is_active ? 'Active' : 'Inactive'}
            </Chip>
            {node.allows_subtenants && (
              <Chip size="sm" variant="flat" color="secondary">Hub</Chip>
            )}
          </div>
        </div>
      </div>

      {expanded && hasChildren && (
        <div>
          {node.children.map((child) => (
            <TreeNode
              key={child.id}
              node={child}
              depth={depth + 1}
              onNavigate={onNavigate}
            />
          ))}
        </div>
      )}
    </div>
  );
}

export function TenantHierarchy() {
  usePageTitle('Super Admin - Tenant Hierarchy');
  const { tenantPath } = useTenant();
  const toast = useToast();
  const navigate = useNavigate();

  const [hierarchy, setHierarchy] = useState<TenantHierarchyNode[]>([]);
  const [loading, setLoading] = useState(true);

  const loadHierarchy = useCallback(async () => {
    setLoading(true);
    try {
      const res = await adminSuper.getHierarchy();
      if (res.success && res.data) {
        const d = res.data as unknown;
        if (Array.isArray(d)) {
          setHierarchy(d);
        } else if (d && typeof d === 'object' && 'data' in d) {
          setHierarchy((d as { data: TenantHierarchyNode[] }).data);
        }
      }
    } catch {
      toast.error('Failed to load hierarchy');
    }
    setLoading(false);
  }, [toast]);

  useEffect(() => {
    loadHierarchy();
  }, [loadHierarchy]);

  const handleNavigate = (tenantId: number) => {
    navigate(tenantPath(`/admin/super/tenants/${tenantId}/edit`));
  };

  return (
    <div>
      <PageHeader
        title="Tenant Hierarchy"
        description="Visual tree of parent-child tenant relationships"
        actions={
          <Button
            variant="flat"
            startContent={<RefreshCw size={16} />}
            onPress={loadHierarchy}
            isLoading={loading}
            size="sm"
          >
            Refresh
          </Button>
        }
      />

      {loading ? (
        <div className="flex justify-center py-12">
          <Spinner size="lg" />
        </div>
      ) : hierarchy.length === 0 ? (
        <Card shadow="sm">
          <CardBody className="flex flex-col items-center py-12 text-default-400">
            <Network size={40} className="mb-2" />
            <p>No tenant hierarchy data available.</p>
            <p className="text-xs">Create tenants with parent relationships to see the tree.</p>
          </CardBody>
        </Card>
      ) : (
        <Card shadow="sm">
          <CardBody className="p-4">
            {hierarchy.map((node) => (
              <TreeNode
                key={node.id}
                node={node}
                depth={0}
                onNavigate={handleNavigate}
              />
            ))}
          </CardBody>
        </Card>
      )}
    </div>
  );
}

export default TenantHierarchy;
