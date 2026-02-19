/**
 * Super Admin - Tenant Hierarchy Tree
 * Drag-and-drop tree view with filters, search, and stats
 */

import { useState, useMemo, useEffect } from 'react';
import { Link } from 'react-router-dom';
import {
  Card,
  CardBody,
  CardHeader,
  Divider,
  Input,
  Button,
  Chip,
  Checkbox,
} from '@heroui/react';
import {
  Search,
  ChevronRight,
  ChevronDown,
  Building2,
  Users,
  Network,
  Layers,
  GripVertical,
  RotateCcw,
  Minimize2,
  Maximize2,
  Info,
} from 'lucide-react';
import { usePageTitle } from '@/hooks/usePageTitle';
import { useApi } from '@/hooks/useApi';
import { useToast } from '@/contexts/ToastContext';
import { adminSuper } from '@/admin/api/adminApi';
import { PageHeader } from '@/admin/components/PageHeader';
import { StatusBadge } from '@/admin/components/DataTable';
import type { TenantHierarchyNode } from '@/admin/api/types';

// Local storage key for tree state
const TREE_STATE_KEY = 'superAdminTreeState';

interface TreeState {
  expanded: Set<number>;
}

export function TenantHierarchy() {
  usePageTitle('Tenant Hierarchy - Super Admin');
  const toast = useToast();

  // State
  const [search, setSearch] = useState('');
  const [showInactive, setShowInactive] = useState(true);
  const [showNonHubs, setShowNonHubs] = useState(true);
  const [draggedNode, setDraggedNode] = useState<TenantHierarchyNode | null>(null);

  // Load hierarchy
  const { data: hierarchy, isLoading, error, execute } = useApi<TenantHierarchyNode[]>(
    '/v2/admin/super/tenants/hierarchy',
    { immediate: true, deps: [] }
  );

  // Load/save tree expansion state
  const [treeState, setTreeState] = useState<TreeState>(() => {
    const saved = localStorage.getItem(TREE_STATE_KEY);
    if (saved) {
      try {
        const parsed = JSON.parse(saved);
        return { expanded: new Set(parsed.expanded || []) };
      } catch {
        return { expanded: new Set<number>() };
      }
    }
    return { expanded: new Set<number>() };
  });

  // Save tree state to localStorage
  useEffect(() => {
    localStorage.setItem(
      TREE_STATE_KEY,
      JSON.stringify({ expanded: Array.from(treeState.expanded) })
    );
  }, [treeState]);

  // Move handler
  const handleMove = async (tenantId: number, newParentId: number) => {
    try {
      const response = await adminSuper.moveTenant(tenantId, newParentId);
      if (response.success) {
        toast.success('Tenant moved successfully');
        execute();
      } else {
        toast.error(response.error || 'Failed to move tenant');
      }
    } catch (error) {
      toast.error('An error occurred');
    }
  };

  // Calculate stats
  const stats = useMemo(() => {
    if (!hierarchy) return null;

    const countNodes = (nodes: TenantHierarchyNode[], depth = 0): { total: number; active: number; hubs: number; users: number; maxDepth: number } => {
      let total = 0;
      let active = 0;
      let hubs = 0;
      let users = 0;
      let maxDepth = depth;

      for (const node of nodes) {
        total++;
        if (node.is_active) active++;
        if (node.allows_subtenants) hubs++;
        users += node.user_count || 0;

        if (node.children && node.children.length > 0) {
          const childStats = countNodes(node.children, depth + 1);
          total += childStats.total;
          active += childStats.active;
          hubs += childStats.hubs;
          users += childStats.users;
          maxDepth = Math.max(maxDepth, childStats.maxDepth);
        }
      }

      return { total, active, hubs, users, maxDepth };
    };

    return countNodes(hierarchy || []);
  }, [hierarchy]);

  // Filter nodes
  const filteredHierarchy = useMemo(() => {
    if (!hierarchy) return [];

    const filterNode = (node: TenantHierarchyNode): TenantHierarchyNode | null => {
      // Search filter
      const matchesSearch = !search || node.name.toLowerCase().includes(search.toLowerCase()) || node.slug.toLowerCase().includes(search.toLowerCase());

      // Active filter
      const matchesActive = showInactive || node.is_active;

      // Hub filter
      const matchesHub = showNonHubs || node.allows_subtenants;

      // Filter children recursively
      const filteredChildren = node.children
        ?.map(filterNode)
        .filter((child): child is TenantHierarchyNode => child !== null) || [];

      // Include node if it matches filters OR if any child matches
      if (matchesSearch && matchesActive && matchesHub) {
        return { ...node, children: filteredChildren };
      }

      if (filteredChildren.length > 0) {
        return { ...node, children: filteredChildren };
      }

      return null;
    };

    return (hierarchy || []).map(filterNode).filter((node): node is TenantHierarchyNode => node !== null);
  }, [hierarchy, search, showInactive, showNonHubs]);

  // Toggle node expansion
  const toggleNode = (id: number) => {
    setTreeState((prev) => {
      const newExpanded = new Set(prev.expanded);
      if (newExpanded.has(id)) {
        newExpanded.delete(id);
      } else {
        newExpanded.add(id);
      }
      return { expanded: newExpanded };
    });
  };

  // Expand all nodes
  const expandAll = () => {
    if (!hierarchy) return;
    const allIds = new Set<number>();
    const collectIds = (nodes: TenantHierarchyNode[]) => {
      nodes.forEach((node) => {
        allIds.add(node.id);
        if (node.children) collectIds(node.children);
      });
    };
    collectIds(hierarchy);
    setTreeState({ expanded: allIds });
  };

  // Collapse all nodes
  const collapseAll = () => {
    setTreeState({ expanded: new Set<number>() });
  };

  // Drag handlers
  const handleDragStart = (node: TenantHierarchyNode) => {
    // Only allow dragging non-master tenants
    if (node.id === 1) return;
    setDraggedNode(node);
  };

  const handleDragOver = (e: React.DragEvent) => {
    e.preventDefault();
  };

  const handleDrop = async (targetNode: TenantHierarchyNode) => {
    if (!draggedNode || draggedNode.id === targetNode.id) {
      setDraggedNode(null);
      return;
    }

    // Validate: target must be a hub
    if (!targetNode.allows_subtenants) {
      alert('Target tenant must be a hub (allows sub-tenants)');
      setDraggedNode(null);
      return;
    }

    // Validate: prevent circular references
    const isDescendant = (parent: TenantHierarchyNode, potentialDescendant: number): boolean => {
      if (parent.id === potentialDescendant) return true;
      if (!parent.children) return false;
      return parent.children.some((child) => isDescendant(child, potentialDescendant));
    };

    if (isDescendant(draggedNode, targetNode.id)) {
      alert('Cannot move a tenant to one of its own descendants');
      setDraggedNode(null);
      return;
    }

    // Perform move
    try {
      await handleMove(draggedNode.id, targetNode.id);
      execute();
    } catch (error) {
      console.error('Move error:', error);
    }

    setDraggedNode(null);
  };

  // Render tree node
  const renderNode = (node: TenantHierarchyNode, depth = 0) => {
    const isExpanded = treeState.expanded.has(node.id);
    const hasChildren = node.children && node.children.length > 0;
    const isDragging = draggedNode?.id === node.id;

    return (
      <div key={node.id} className={isDragging ? 'opacity-50' : ''}>
        <div
          className="flex items-center gap-2 py-2 px-3 hover:bg-default-100 dark:hover:bg-default-50 rounded-lg cursor-pointer group"
          style={{ paddingLeft: `${depth * 1.5 + 0.75}rem` }}
          draggable={node.id !== 1}
          onDragStart={() => handleDragStart(node)}
          onDragOver={handleDragOver}
          onDrop={() => handleDrop(node)}
        >
          {/* Drag handle */}
          {node.id !== 1 && (
            <GripVertical size={14} className="text-default-300 opacity-0 group-hover:opacity-100 cursor-grab" />
          )}

          {/* Expand/collapse */}
          <button
            type="button"
            onClick={() => toggleNode(node.id)}
            className="shrink-0"
            disabled={!hasChildren}
          >
            {hasChildren ? (
              isExpanded ? (
                <ChevronDown size={16} className="text-default-400" />
              ) : (
                <ChevronRight size={16} className="text-default-400" />
              )
            ) : (
              <div className="w-4" />
            )}
          </button>

          {/* Icon */}
          <Building2 size={16} className="text-default-400 shrink-0" />

          {/* Name */}
          <Link
            to={(`/admin/super/tenants/${node.id}`)}
            className="font-medium text-foreground hover:text-primary flex-1"
          >
            {node.name}
          </Link>

          {/* Badges */}
          <div className="flex items-center gap-2">
            {node.allows_subtenants && (
              <Chip size="sm" variant="flat" color="primary" className="h-5">
                <Network size={10} className="mr-1" />
                Hub
              </Chip>
            )}
            <StatusBadge status={node.is_active ? 'active' : 'inactive'} />
            <Chip size="sm" variant="flat" className="h-5">
              <Users size={10} className="mr-1" />
              {node.user_count || 0}
            </Chip>
          </div>
        </div>

        {/* Children */}
        {hasChildren && isExpanded && (
          <div>
            {node.children.map((child) => renderNode(child, depth + 1))}
          </div>
        )}
      </div>
    );
  };

  if (isLoading) {
    return (
      <div className="p-6 flex items-center justify-center h-64">
        <div className="text-center">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-primary mx-auto mb-4" />
          <p className="text-default-500">Loading hierarchy...</p>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="p-6">
        <div className="p-4 bg-danger-50 dark:bg-danger-900/20 border border-danger-200 dark:border-danger-800 rounded-lg">
          <p className="text-danger-700 dark:text-danger-400">{error}</p>
        </div>
      </div>
    );
  }

  return (
    <div className="p-6">
      <PageHeader
        title="Tenant Hierarchy"
        description="View and manage the tenant hierarchy tree with drag-and-drop"
      />

      {/* Stats Cards */}
      {stats && (
        <div className="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
          <Card>
            <CardBody className="flex flex-col items-center justify-center py-4">
              <Building2 size={24} className="text-primary mb-2" />
              <p className="text-2xl font-bold">{stats.total}</p>
              <p className="text-xs text-default-500">Total Tenants</p>
            </CardBody>
          </Card>
          <Card>
            <CardBody className="flex flex-col items-center justify-center py-4">
              <div className="w-3 h-3 rounded-full bg-success mb-3" />
              <p className="text-2xl font-bold">{stats.active}</p>
              <p className="text-xs text-default-500">Active</p>
            </CardBody>
          </Card>
          <Card>
            <CardBody className="flex flex-col items-center justify-center py-4">
              <Network size={24} className="text-primary mb-2" />
              <p className="text-2xl font-bold">{stats.hubs}</p>
              <p className="text-xs text-default-500">Hubs</p>
            </CardBody>
          </Card>
          <Card>
            <CardBody className="flex flex-col items-center justify-center py-4">
              <Layers size={24} className="text-primary mb-2" />
              <p className="text-2xl font-bold">{stats.maxDepth}</p>
              <p className="text-xs text-default-500">Max Depth</p>
            </CardBody>
          </Card>
          <Card>
            <CardBody className="flex flex-col items-center justify-center py-4">
              <Users size={24} className="text-primary mb-2" />
              <p className="text-2xl font-bold">{stats.users.toLocaleString()}</p>
              <p className="text-xs text-default-500">Total Users</p>
            </CardBody>
          </Card>
        </div>
      )}

      {/* Toolbar */}
      <Card className="mb-6">
        <CardBody className="gap-4">
          <div className="flex items-center gap-3 flex-wrap">
            {/* Search */}
            <Input
              placeholder="Search by name or slug..."
              value={search}
              onValueChange={setSearch}
              startContent={<Search size={16} className="text-default-400" />}
              size="sm"
              variant="bordered"
              className="max-w-xs"
            />

            {/* Filters */}
            <Checkbox
              isSelected={showInactive}
              onValueChange={setShowInactive}
              size="sm"
            >
              Show inactive
            </Checkbox>

            <Checkbox
              isSelected={showNonHubs}
              onValueChange={setShowNonHubs}
              size="sm"
            >
              Show non-hubs
            </Checkbox>

            <div className="flex-1" />

            {/* Actions */}
            <Button
              size="sm"
              variant="flat"
              onPress={expandAll}
              startContent={<Maximize2 size={14} />}
            >
              Expand All
            </Button>
            <Button
              size="sm"
              variant="flat"
              onPress={collapseAll}
              startContent={<Minimize2 size={14} />}
            >
              Collapse All
            </Button>
            <Button
              size="sm"
              variant="flat"
              onPress={execute}
              startContent={<RotateCcw size={14} />}
            >
              Refresh
            </Button>
          </div>

          {/* Legend */}
          <div className="flex items-center gap-4 text-xs text-default-500">
            <div className="flex items-center gap-2">
              <Info size={14} />
              <span>Legend:</span>
            </div>
            <div className="flex items-center gap-2">
              <Chip size="sm" variant="flat" color="primary" className="h-5">
                <Network size={10} className="mr-1" />
                Hub
              </Chip>
              <span>= Allows sub-tenants</span>
            </div>
            <div className="flex items-center gap-2">
              <GripVertical size={14} className="text-default-300" />
              <span>= Drag to move (hubs only)</span>
            </div>
          </div>
        </CardBody>
      </Card>

      {/* Tree */}
      <Card>
        <CardHeader>
          <span className="font-semibold">Hierarchy Tree</span>
        </CardHeader>
        <Divider />
        <CardBody className="p-0">
          {filteredHierarchy.length === 0 ? (
            <div className="p-8 text-center text-default-500">
              No tenants match the current filters.
            </div>
          ) : (
            <div className="py-2">
              {filteredHierarchy.map((node) => renderNode(node))}
            </div>
          )}
        </CardBody>
      </Card>

      {/* Drag hint */}
      {draggedNode && (
        <div className="fixed bottom-4 right-4 p-4 bg-primary text-white rounded-lg shadow-lg">
          <p className="text-sm">
            Dragging: <strong>{draggedNode.name}</strong>
          </p>
          <p className="text-xs opacity-80">Drop on a hub to move</p>
        </div>
      )}
    </div>
  );
}

export default TenantHierarchy;
