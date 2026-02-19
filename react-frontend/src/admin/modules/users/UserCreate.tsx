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
import { usePageTitle } from '@/hooks';
import { useTenant, useToast } from '@/contexts';
import { adminUsers } from '../../api/adminApi';
import { PageHeader } from '../../components';
import type { CreateUserPayload } from '../../api/types';

export function UserCreate() {
  usePageTitle('Admin - Create User');
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
      newErrors.first_name = 'First name is required';
    }
    if (!lastName.trim()) {
      newErrors.last_name = 'Last name is required';
    }
    if (!email.trim()) {
      newErrors.email = 'Email is required';
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      newErrors.email = 'Please enter a valid email address';
    }
    if (!role) {
      newErrors.role = 'Role is required';
    }
    if (password.trim() && password.trim().length < 8) {
      newErrors.password = 'Password must be at least 8 characters';
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
        toast.success('User created successfully');
        navigate(tenantPath('/admin/users'));
      } else {
        toast.error(res.error || 'Failed to create user');
      }
    } catch {
      toast.error('An unexpected error occurred');
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <div>
      <PageHeader
        title="Create User"
        actions={
          <Button
            variant="flat"
            startContent={<ArrowLeft size={16} />}
            onPress={() => navigate(tenantPath('/admin/users'))}
          >
            Back to Users
          </Button>
        }
      />

      <form onSubmit={handleSubmit}>
        <Card className="max-w-2xl">
          <CardBody className="gap-5 p-6">
            {/* Name Fields */}
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <Input
                label="First Name"
                placeholder="Enter first name"
                value={firstName}
                onValueChange={setFirstName}
                isRequired
                isInvalid={!!errors.first_name}
                errorMessage={errors.first_name}
                isDisabled={submitting}
              />
              <Input
                label="Last Name"
                placeholder="Enter last name"
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
              label="Email"
              type="email"
              placeholder="user@example.com"
              value={email}
              onValueChange={setEmail}
              isRequired
              isInvalid={!!errors.email}
              errorMessage={errors.email}
              isDisabled={submitting}
            />

            {/* Phone */}
            <Input
              label="Phone"
              type="tel"
              placeholder="Enter phone number (optional)"
              value={phone}
              onValueChange={setPhone}
              isDisabled={submitting}
            />

            {/* Role & Status */}
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <Select
                label="Role"
                placeholder="Select a role"
                selectedKeys={role ? [role] : []}
                onSelectionChange={(keys) => setRole(Array.from(keys)[0] as string)}
                isRequired
                isInvalid={!!errors.role}
                errorMessage={errors.role}
                isDisabled={submitting}
              >
                <SelectItem key="member">Member</SelectItem>
                <SelectItem key="broker">Broker</SelectItem>
                <SelectItem key="moderator">Moderator</SelectItem>
                <SelectItem key="newsletter_admin">Newsletter Admin</SelectItem>
                <SelectItem key="tenant_admin">Tenant Admin</SelectItem>
                <SelectItem key="admin">Admin</SelectItem>
              </Select>

              <Select
                label="Status"
                placeholder="Select status"
                selectedKeys={[status]}
                onSelectionChange={(keys) => setStatus(Array.from(keys)[0] as string)}
                isDisabled={submitting}
              >
                <SelectItem key="active">Active</SelectItem>
                <SelectItem key="pending">Pending</SelectItem>
              </Select>
            </div>

            {/* Password */}
            <Input
              label="Password"
              type="password"
              placeholder="Leave blank to auto-generate"
              description="Minimum 8 characters. If left blank, a random password will be generated automatically."
              value={password}
              onValueChange={setPassword}
              isDisabled={submitting}
              isInvalid={!!errors.password}
              errorMessage={errors.password}
            />

            {/* Send Welcome Email */}
            <div className="flex items-center justify-between rounded-lg border border-default-200 p-4">
              <div>
                <p className="text-sm font-medium text-foreground">Send Welcome Email</p>
                <p className="text-xs text-default-500">
                  Send the new user an email with their login details and a link to get started.
                </p>
              </div>
              <Switch
                isSelected={sendWelcomeEmail}
                onValueChange={setSendWelcomeEmail}
                isDisabled={submitting}
                aria-label="Send welcome email"
              />
            </div>

            {/* Submit */}
            <div className="flex justify-end gap-3 pt-2">
              <Button
                variant="flat"
                onPress={() => navigate(tenantPath('/admin/users'))}
                isDisabled={submitting}
              >
                Cancel
              </Button>
              <Button
                type="submit"
                color="primary"
                startContent={!submitting ? <Save size={16} /> : undefined}
                isLoading={submitting}
              >
                Create User
              </Button>
            </div>
          </CardBody>
        </Card>
      </form>
    </div>
  );
}

export default UserCreate;
