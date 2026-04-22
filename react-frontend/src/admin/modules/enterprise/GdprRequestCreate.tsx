// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * GDPR Request Create
 * Creation form for new admin-initiated GDPR requests.
 * Route: /admin/enterprise/gdpr/requests/create
 */

import { useState, useRef } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  Card, CardBody, Input, Textarea, Select, SelectItem, Button, Chip, Spinner,
} from '@heroui/react';
import {
  ArrowLeft, Save, Eye, Trash2, Download, Edit, Lock, AlertTriangle, Search,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { adminEnterprise, adminUsers } from '../../api/adminApi';
import { PageHeader } from '../../components';
import type { AdminUser } from '../../api/types';

import { useTranslation } from 'react-i18next';

interface RequestTypeOption {
  key: string;
  labelKey: string;
  descriptionKey: string;
  icon: LucideIcon;
}

const REQUEST_TYPES: RequestTypeOption[] = [
  { key: 'access', labelKey: 'enterprise.gdpr_type_access', descriptionKey: 'enterprise.gdpr_type_access_desc', icon: Eye },
  { key: 'erasure', labelKey: 'enterprise.gdpr_type_erasure', descriptionKey: 'enterprise.gdpr_type_erasure_desc', icon: Trash2 },
  { key: 'portability', labelKey: 'enterprise.gdpr_type_portability', descriptionKey: 'enterprise.gdpr_type_portability_desc', icon: Download },
  { key: 'rectification', labelKey: 'enterprise.gdpr_type_rectification', descriptionKey: 'enterprise.gdpr_type_rectification_desc', icon: Edit },
  { key: 'restriction', labelKey: 'enterprise.gdpr_type_restriction', descriptionKey: 'enterprise.gdpr_type_restriction_desc', icon: Lock },
  { key: 'objection', labelKey: 'enterprise.gdpr_type_objection', descriptionKey: 'enterprise.gdpr_type_objection_desc', icon: AlertTriangle },
];

const PRIORITY_KEYS = ['low', 'normal', 'high', 'urgent'] as const;

export function GdprRequestCreate() {
  const { t } = useTranslation('admin');
  usePageTitle("GDPR Create Request Page");
  const { tenantPath } = useTenant();
  const toast = useToast();
  const navigate = useNavigate();

  const [userId, setUserId] = useState('');
  const [selectedType, setSelectedType] = useState('');
  const [priority, setPriority] = useState('normal');
  const [notes, setNotes] = useState('');
  const [saving, setSaving] = useState(false);

  // User search autocomplete state
  const [userSearch, setUserSearch] = useState('');
  const [userResults, setUserResults] = useState<AdminUser[]>([]);
  const [searchLoading, setSearchLoading] = useState(false);
  const [selectedUser, setSelectedUser] = useState<AdminUser | null>(null);
  const [showDropdown, setShowDropdown] = useState(false);
  const [searchDone, setSearchDone] = useState(false);
  const searchTimeoutRef = useRef<NodeJS.Timeout | null>(null);

  const debouncedSearch = (query: string) => {
    if (searchTimeoutRef.current) clearTimeout(searchTimeoutRef.current);
    if (query.length < 2) {
      setUserResults([]);
      setShowDropdown(false);
      setSearchDone(false);
      return;
    }
    setSearchDone(false);
    searchTimeoutRef.current = setTimeout(async () => {
      setSearchLoading(true);
      try {
        const res = await adminUsers.list({ search: query, page: 1, limit: 10 });
        if (res.success && res.data) {
          const data = res.data as unknown as { data?: AdminUser[] } | AdminUser[];
          const users = Array.isArray(data) ? data : (data as { data?: AdminUser[] }).data || [];
          setUserResults(users);
          setShowDropdown(users.length > 0);
          setSearchDone(true);
        }
      } catch {
        setUserResults([]);
        setSearchDone(true);
      } finally {
        setSearchLoading(false);
      }
    }, 300);
  };

  const handleSelectUser = (user: AdminUser) => {
    setSelectedUser(user);
    setUserId(String(user.id));
    setUserSearch('');
    setUserResults([]);
    setShowDropdown(false);
  };

  const handleDeselectUser = () => {
    setSelectedUser(null);
    setUserId('');
    setUserSearch('');
  };

  const handleSubmit = async () => {
    const parsedUserId = parseInt(userId, 10);
    if (!parsedUserId || isNaN(parsedUserId)) {
      toast.error("GDPR Enter Valid User ID");
      return;
    }
    if (!selectedType) {
      toast.error("GDPR Select Request");
      return;
    }

    setSaving(true);
    try {
      const res = await adminEnterprise.createGdprRequest({
        user_id: parsedUserId,
        type: selectedType,
        priority,
        notes: notes.trim() || undefined,
      });
      if (res.success) {
        toast.success("GDPR Request created");
        navigate(tenantPath('/admin/enterprise/gdpr/requests'));
      } else {
        const error = (res as { error?: string }).error || "GDPR Failed Create Request";
        toast.error(error);
      }
    } catch (err) {
      toast.error("GDPR Failed Create Request");
    } finally {
      setSaving(false);
    }
  };

  return (
    <div>
      <PageHeader
        title={"GDPR Create Request"}
        description={"GDPR Create Request."}
        actions={
          <Button
            variant="flat"
            startContent={<ArrowLeft size={16} />}
            onPress={() => navigate(tenantPath('/admin/enterprise/gdpr/requests'))}
            size="sm"
          >
            {"GDPR Back to Requests"}
          </Button>
        }
      />

      <Card shadow="sm">
        <CardBody className="p-6 space-y-6">
          {/* User Search */}
          <div>
            {selectedUser ? (
              <div className="space-y-2">
                <p className="text-sm font-medium text-default-700">
                  {"GDPR User"} <span className="text-danger">*</span>
                </p>
                <Chip
                  onClose={handleDeselectUser}
                  variant="flat"
                  color="primary"
                  size="lg"
                >
                  {selectedUser.name} ({selectedUser.email}) — ID #{selectedUser.id}
                </Chip>
              </div>
            ) : (
              <div className="relative">
                <Input
                  label={"GDPR Search User"}
                  placeholder={"Search user..."}
                  value={userSearch}
                  onValueChange={(val) => {
                    setUserSearch(val);
                    debouncedSearch(val);
                  }}
                  onFocus={() => {
                    if (userResults.length > 0) setShowDropdown(true);
                  }}
                  onBlur={() => {
                    // Delay to allow click on dropdown items
                    setTimeout(() => setShowDropdown(false), 200);
                  }}
                  variant="bordered"
                  isRequired
                  startContent={<Search size={16} />}
                  endContent={searchLoading ? <Spinner size="sm" /> : undefined}
                />
                {showDropdown && userResults.length > 0 && (
                  <div className="absolute z-50 w-full mt-1 bg-content1 border border-divider rounded-xl shadow-lg max-h-60 overflow-y-auto">
                    {userResults.map((user) => (
                      <Button
                        key={user.id}
                        variant="light"
                        className="w-full text-left px-4 py-2.5 hover:bg-default-100 transition-colors first:rounded-t-xl last:rounded-b-xl h-auto min-w-0 justify-start"
                        onMouseDown={(e) => e.preventDefault()}
                        onPress={() => handleSelectUser(user)}
                      >
                        <span className="font-medium text-sm">{user.name}</span>
                        <span className="text-default-500 text-sm"> ({user.email})</span>
                        <span className="text-default-400 text-xs ml-1">— ID #{user.id}</span>
                      </Button>
                    ))}
                  </div>
                )}
                {searchDone && !searchLoading && userResults.length === 0 && userSearch.length >= 2 && (
                  <p className="text-xs text-default-400 mt-1">{`No users found for this search`}</p>
                )}
              </div>
            )}
          </div>

          {/* Request Type - Card Selector */}
          <div>
            <p className="text-sm font-medium text-default-700 mb-3">
              {"GDPR Request"} <span className="text-danger">*</span>
            </p>
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
              {REQUEST_TYPES.map((type) => {
                const isSelected = selectedType === type.key;
                const Icon = type.icon;
                return (
                  <Button
                    key={type.key}
                    variant="flat"
                    onPress={() => setSelectedType(type.key)}
                    className={`
                      flex flex-col items-center gap-2 p-4 rounded-xl border-2 transition-all
                      cursor-pointer text-center h-auto min-w-0
                      ${isSelected
                        ? 'border-primary bg-primary-50 shadow-sm'
                        : 'border-default-200 bg-default-50 hover:border-default-300'
                      }
                    `}
                  >
                    <div className={`
                      flex h-10 w-10 items-center justify-center rounded-lg
                      ${isSelected ? 'bg-primary/20 text-primary' : 'bg-default-200 text-default-600'}
                    `}>
                      <Icon size={20} />
                    </div>
                    <div>
                      <p className={`font-semibold text-sm ${isSelected ? 'text-primary' : 'text-foreground'}`}>
                        {t(type.labelKey)}
                      </p>
                      <p className="text-xs text-default-500 mt-0.5">{t(type.descriptionKey)}</p>
                    </div>
                  </Button>
                );
              })}
            </div>
          </div>

          {/* Priority */}
          <Select
            label={"GDPR Priority"}
            selectedKeys={[priority]}
            onSelectionChange={(keys) => {
              const val = Array.from(keys)[0] as string;
              if (val) setPriority(val);
            }}
            variant="bordered"
            className="max-w-xs"
          >
            {PRIORITY_KEYS.map((key) => (
              <SelectItem key={key} className="capitalize">
                {key.charAt(0).toUpperCase() + key.slice(1)}
              </SelectItem>
            ))}
          </Select>

          {/* Notes */}
          <Textarea
            label={"GDPR Notes"}
            placeholder={"Enter notes..."}
            value={notes}
            onValueChange={setNotes}
            variant="bordered"
            minRows={3}
          />

          {/* Actions */}
          <div className="flex justify-end gap-3 pt-2">
            <Button
              variant="flat"
              onPress={() => navigate(tenantPath('/admin/enterprise/gdpr/requests'))}
            >
              {"GDPR Cancel"}
            </Button>
            <Button
              color="primary"
              startContent={<Save size={16} />}
              onPress={handleSubmit}
              isLoading={saving}
            >
              {"GDPR Create Request"}
            </Button>
          </div>
        </CardBody>
      </Card>
    </div>
  );
}

export default GdprRequestCreate;
