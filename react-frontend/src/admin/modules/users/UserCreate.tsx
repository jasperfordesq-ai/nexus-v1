// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Admin User Create
 * Form to create a new user with role assignment and optional welcome email.
 * Parity: PHP Admin\UserController::create()
 */

import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import {
  Card,
  CardBody,
  Input,
  Button,
  Select,
  SelectItem,
  Switch,
} from '@heroui/react';
import { ArrowLeft, Save } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { adminUsers } from '../../api/adminApi';
import { PageHeader } from '../../components';
import type { CreateUserPayload } from '../../api/types';

export function UserCreate() {
  const { t } = useTranslation('admin');
  usePageTitle(t('user_create.page_title'));
  const { tenantPath } = useTenant();
  const toast = useToast();
  const navigate = useNavigate();

  // Form state
  const [firstName, setFirstName] = useState('');
  const [lastName, setLastName] = useState('');
  const [email, setEmail] = useState('');
  const [phone, setPhone] = useState('');
  const [role, setRole] = useState('member');
  const [status, setStatus] = useState('active');
  const [password, setPassword] = useState('');
  const [sendWelcomeEmail, setSendWelcomeEmail] = useState(true);
  const [submitting, setSubmitting] = useState(false);

  // Validation state
  const [errors, setErrors] = useState<Record<string, string>>({});

  function validate(): boolean {
    const newErrors: Record<string, string> = {};

    if (!firstName.trim()) {
      newErrors.first_name = t('user_create.first_name_required');
    }
    if (!lastName.trim()) {
      newErrors.last_name = t('user_create.last_name_required');
    }
    if (!email.trim()) {
      newErrors.email = t('user_create.email_required');
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      newErrors.email = t('user_create.email_invalid');
    }
    if (!role) {
      newErrors.role = t('user_create.role_required');
    }
    if (password.trim() && password.trim().length < 8) {
      newErrors.password = t('user_create.password_min_length');
    }

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();

    if (!validate()) return;

    setSubmitting(true);

    try {
      const payload: CreateUserPayload = {
        first_name: firstName.trim(),
        last_name: lastName.trim(),
        email: email.trim(),
        role,
        status,
        send_welcome_email: sendWelcomeEmail,
      };

      // Only include password if provided (backend auto-generates if empty)
      if (password.trim()) {
        payload.password = password.trim();
      }
      if (phone.trim()) {
        payload.phone = phone.trim();
      }

      const res = await adminUsers.create(payload);

      if (res.success) {
        toast.success(t('user_create.create_success'));
        navigate(tenantPath('/admin/users'));
      } else {
        toast.error(res.error || t('user_create.create_failed'));
      }
    } catch {
      toast.error(t('error_occurred'));
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <div>
      <PageHeader
        title={t('user_create.title')}
        actions={
          <Button
            variant="flat"
            startContent={<ArrowLeft size={16} />}
            onPress={() => navigate(tenantPath('/admin/users'))}
          >
            {t('user_create.back_to_users')}
          </Button>
        }
      />

      <form onSubmit={handleSubmit}>
        <Card className="max-w-2xl">
          <CardBody className="gap-5 p-6">
            {/* Name Fields */}
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <Input
                label={t('user_create.first_name')}
                placeholder={t('user_create.first_name_placeholder')}
                value={firstName}
                onValueChange={setFirstName}
                isRequired
                isInvalid={!!errors.first_name}
                errorMessage={errors.first_name}
                isDisabled={submitting}
              />
              <Input
                label={t('user_create.last_name')}
                placeholder={t('user_create.last_name_placeholder')}
                value={lastName}
                onValueChange={setLastName}
                isRequired
                isInvalid={!!errors.last_name}
                errorMessage={errors.last_name}
                isDisabled={submitting}
              />
            </div>

            {/* Email */}
            <Input
              label={t('user_create.email')}
              type="email"
              placeholder={t('user_create.email_placeholder')}
              value={email}
              onValueChange={setEmail}
              isRequired
              isInvalid={!!errors.email}
              errorMessage={errors.email}
              isDisabled={submitting}
            />

            {/* Phone */}
            <Input
              label={t('user_create.phone')}
              type="tel"
              placeholder={t('user_create.phone_placeholder')}
              value={phone}
              onValueChange={setPhone}
              isDisabled={submitting}
            />

            {/* Role & Status */}
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <Select
                label={t('user_create.role')}
                placeholder={t('user_create.role_placeholder')}
                selectedKeys={role ? [role] : []}
                onSelectionChange={(keys) => setRole(Array.from(keys)[0] as string)}
                isRequired
                isInvalid={!!errors.role}
                errorMessage={errors.role}
                isDisabled={submitting}
              >
                <SelectItem key="member">{t('user_create.role_member')}</SelectItem>
                <SelectItem key="broker">{t('user_create.role_broker')}</SelectItem>
                <SelectItem key="moderator">{t('user_create.role_moderator')}</SelectItem>
                <SelectItem key="newsletter_admin">{t('user_create.role_newsletter_admin')}</SelectItem>
                <SelectItem key="tenant_admin">{t('user_create.role_tenant_admin')}</SelectItem>
                <SelectItem key="admin">{t('user_create.role_admin')}</SelectItem>
              </Select>

              <Select
                label={t('user_create.status')}
                placeholder={t('user_create.status_placeholder')}
                selectedKeys={[status]}
                onSelectionChange={(keys) => setStatus(Array.from(keys)[0] as string)}
                isDisabled={submitting}
              >
                <SelectItem key="active">{t('user_create.status_active')}</SelectItem>
                <SelectItem key="pending">{t('user_create.status_pending')}</SelectItem>
              </Select>
            </div>

            {/* Password */}
            <Input
              label={t('user_create.password')}
              type="password"
              placeholder={t('user_create.password_placeholder')}
              description={t('user_create.password_description')}
              value={password}
              onValueChange={setPassword}
              isDisabled={submitting}
              isInvalid={!!errors.password}
              errorMessage={errors.password}
            />

            {/* Send Welcome Email */}
            <div className="flex items-center justify-between rounded-lg border border-default-200 p-4">
              <div>
                <p className="text-sm font-medium text-foreground">{t('user_create.send_welcome_email')}</p>
                <p className="text-xs text-default-500">
                  {t('user_create.send_welcome_email_description')}
                </p>
              </div>
              <Switch
                isSelected={sendWelcomeEmail}
                onValueChange={setSendWelcomeEmail}
                isDisabled={submitting}
                aria-label={t('user_create.send_welcome_email')}
              />
            </div>

            {/* Submit */}
            <div className="flex justify-end gap-3 pt-2">
              <Button
                variant="flat"
                onPress={() => navigate(tenantPath('/admin/users'))}
                isDisabled={submitting}
              >
                {t('cancel')}
              </Button>
              <Button
                type="submit"
                color="primary"
                startContent={!submitting ? <Save size={16} /> : undefined}
                isLoading={submitting}
              >
                {t('user_create.create_button')}
              </Button>
            </div>
          </CardBody>
        </Card>
      </form>
    </div>
  );
}

export default UserCreate;
