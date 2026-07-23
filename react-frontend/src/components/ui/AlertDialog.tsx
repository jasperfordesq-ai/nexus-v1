// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import type { ComponentProps } from 'react';
import { AlertDialog as HeroUIAlertDialog, type AlertDialogProps as HeroUIAlertDialogProps } from '@heroui/react/alert-dialog';
import { cn } from '@/lib/helpers';

type HeroUIContainerProps = ComponentProps<typeof HeroUIAlertDialog.Container>;
type HeroUIDialogProps = ComponentProps<typeof HeroUIAlertDialog.Dialog>;

export type AlertDialogContainerProps = Omit<HeroUIContainerProps, 'className'> & {
  className?: string;
};

export type AlertDialogDialogProps = Omit<HeroUIDialogProps, 'className'> & {
  className?: string;
};

// The container/dialog carry the responsive-sheet classes so every
// confirmation dialog in the app renders as a bottom action sheet on phones
// (see the `nexus-responsive-alertdialog-*` blocks in index.css) without any
// call-site changes. Presentation only — open/close flow is untouched.
function AlertDialogContainer({ className, ...props }: AlertDialogContainerProps) {
  return (
    <HeroUIAlertDialog.Container
      {...props}
      className={cn('nexus-responsive-alertdialog-container', className)}
    />
  );
}

function AlertDialogDialog({ className, ...props }: AlertDialogDialogProps) {
  return (
    <HeroUIAlertDialog.Dialog
      {...props}
      className={cn('nexus-responsive-alertdialog-dialog', className)}
    />
  );
}

export type AlertDialogProps = HeroUIAlertDialogProps;

export const AlertDialog = Object.assign(
  function AlertDialog(props: HeroUIAlertDialogProps) {
    return <HeroUIAlertDialog {...props} />;
  },
  {
    Trigger: HeroUIAlertDialog.Trigger,
    Backdrop: HeroUIAlertDialog.Backdrop,
    Container: AlertDialogContainer,
    Dialog: AlertDialogDialog,
    CloseTrigger: HeroUIAlertDialog.CloseTrigger,
    Header: HeroUIAlertDialog.Header,
    Heading: HeroUIAlertDialog.Heading,
    Icon: HeroUIAlertDialog.Icon,
    Body: HeroUIAlertDialog.Body,
    Footer: HeroUIAlertDialog.Footer,
  },
);
