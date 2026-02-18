/**
 * Newsletter Resend Modal
 * Resend workflow - modal component for re-sending newsletters to targeted recipients
 */

import { useState, useEffect } from 'react';
import {
  Modal, ModalContent, ModalHeader, ModalBody, ModalFooter,
  Button, RadioGroup, Radio, Input, Card, CardBody,
} from '@heroui/react';
import { Mail, Users, AlertCircle } from 'lucide-react';
import { useToast } from '@/contexts';
import { adminNewsletters } from '../../api/adminApi';
import type { ResendInfo } from '../../api/types';

interface NewsletterResendProps {
  isOpen: boolean;
  onClose: () => void;
  newsletterId: number;
  onSuccess?: () => void;
}

export function NewsletterResend({ isOpen, onClose, newsletterId, onSuccess }: NewsletterResendProps) {
  const toast = useToast();
  const [loading, setLoading] = useState(true);
  const [sending, setSending] = useState(false);
  const [info, setInfo] = useState<ResendInfo | null>(null);
  const [target, setTarget] = useState<'non_openers' | 'non_clickers' | 'segment'>('non_openers');
  const [subjectOverride, setSubjectOverride] = useState('');

  useEffect(() => {
    if (!isOpen) return;

    const loadInfo = async () => {
      setLoading(true);
      try {
        const res = await adminNewsletters.getResendInfo(newsletterId);
        if (res.success && res.data) {
          setInfo(res.data as ResendInfo);
        }
      } catch {
        toast.error('Failed to load resend information');
      }
      setLoading(false);
    };

    loadInfo();
  }, [isOpen, newsletterId, toast]);

  const handleResend = async () => {
    if (!info) return;

    setSending(true);
    try {
      const res = await adminNewsletters.resend(newsletterId, {
        target,
        subject_override: subjectOverride || undefined,
      });

      if (res.success) {
        const data = res.data as { queued_count?: number };
        toast.success(`Resend queued for ${data.queued_count || 0} recipients`);
        onSuccess?.();
        onClose();
      } else {
        toast.error('Failed to queue resend');
      }
    } catch {
      toast.error('Failed to queue resend');
    }
    setSending(false);
  };

  const getRecipientCount = () => {
    if (!info) return 0;
    switch (target) {
      case 'non_openers': return info.non_openers_count;
      case 'non_clickers': return info.non_clickers_count;
      default: return 0;
    }
  };

  const recipientCount = getRecipientCount();

  return (
    <Modal
      isOpen={isOpen}
      onClose={onClose}
      size="2xl"
      scrollBehavior="inside"
    >
      <ModalContent>
        {(onModalClose) => (
          <>
            <ModalHeader className="flex flex-col gap-1">
              <div className="flex items-center gap-2">
                <Mail size={20} />
                <span>Resend Newsletter</span>
              </div>
              <p className="text-sm font-normal text-default-500">
                Send this newsletter to a targeted subset of recipients
              </p>
            </ModalHeader>
            <ModalBody>
              {loading ? (
                <div className="flex items-center justify-center py-8">
                  <div className="text-default-400">Loading resend info...</div>
                </div>
              ) : info ? (
                <div className="space-y-4">
                  <Card>
                    <CardBody className="gap-2">
                      <div className="grid grid-cols-3 gap-4 text-center">
                        <div>
                          <p className="text-sm text-default-500">Total Sent</p>
                          <p className="text-2xl font-bold">{info.total_sent.toLocaleString()}</p>
                        </div>
                        <div>
                          <p className="text-sm text-default-500">Opened</p>
                          <p className="text-2xl font-bold text-success">{info.total_opened.toLocaleString()}</p>
                        </div>
                        <div>
                          <p className="text-sm text-default-500">Clicked</p>
                          <p className="text-2xl font-bold text-primary">{info.total_clicked.toLocaleString()}</p>
                        </div>
                      </div>
                    </CardBody>
                  </Card>

                  <RadioGroup
                    label="Resend to"
                    value={target}
                    onValueChange={(v) => setTarget(v as typeof target)}
                  >
                    <Radio value="non_openers">
                      <div className="flex flex-col gap-1">
                        <span>Non-openers</span>
                        <span className="text-xs text-default-500">
                          {info.non_openers_count.toLocaleString()} recipients who didn't open
                        </span>
                      </div>
                    </Radio>
                    <Radio value="non_clickers">
                      <div className="flex flex-col gap-1">
                        <span>Non-clickers</span>
                        <span className="text-xs text-default-500">
                          {info.non_clickers_count.toLocaleString()} recipients who opened but didn't click
                        </span>
                      </div>
                    </Radio>
                  </RadioGroup>

                  <Input
                    label="Subject Override (Optional)"
                    placeholder="Leave blank to use original subject"
                    value={subjectOverride}
                    onValueChange={setSubjectOverride}
                    description="Override the original subject line for this resend"
                  />

                  <Card className="bg-default-100">
                    <CardBody className="flex-row items-center gap-3">
                      <Users size={20} className="text-primary" />
                      <div className="flex-1">
                        <p className="text-sm font-medium">Preview Recipient Count</p>
                        <p className="text-xs text-default-500">
                          This resend will be sent to {recipientCount.toLocaleString()} recipient{recipientCount !== 1 ? 's' : ''}
                        </p>
                      </div>
                    </CardBody>
                  </Card>

                  {recipientCount === 0 && (
                    <Card className="bg-warning-50 dark:bg-warning-50/10">
                      <CardBody className="flex-row items-center gap-3">
                        <AlertCircle size={20} className="text-warning" />
                        <div className="flex-1">
                          <p className="text-sm font-medium text-warning">No Recipients</p>
                          <p className="text-xs text-warning-600 dark:text-warning-400">
                            There are no recipients matching your selection criteria
                          </p>
                        </div>
                      </CardBody>
                    </Card>
                  )}
                </div>
              ) : (
                <div className="flex items-center justify-center py-8">
                  <div className="text-danger">Failed to load newsletter information</div>
                </div>
              )}
            </ModalBody>
            <ModalFooter>
              <Button variant="light" onPress={onModalClose}>
                Cancel
              </Button>
              <Button
                color="primary"
                onPress={handleResend}
                isLoading={sending}
                isDisabled={!info || recipientCount === 0 || loading}
              >
                Queue Resend
              </Button>
            </ModalFooter>
          </>
        )}
      </ModalContent>
    </Modal>
  );
}

export default NewsletterResend;
