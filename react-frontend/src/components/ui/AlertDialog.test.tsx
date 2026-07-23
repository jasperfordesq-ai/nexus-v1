// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect } from 'vitest';
import { render, screen } from '@/test/test-utils';
import { AlertDialog } from './AlertDialog';

describe('AlertDialog', () => {
  it('applies the responsive confirmation-sheet classes to Container and Dialog', () => {
    render(
      <AlertDialog.Backdrop isOpen onOpenChange={() => {}}>
        <AlertDialog.Container>
          <AlertDialog.Dialog>
            <AlertDialog.Header>
              <AlertDialog.Heading>Delete item?</AlertDialog.Heading>
            </AlertDialog.Header>
            <AlertDialog.Body>
              <p>This cannot be undone.</p>
            </AlertDialog.Body>
          </AlertDialog.Dialog>
        </AlertDialog.Container>
      </AlertDialog.Backdrop>,
    );

    expect(screen.getByText('This cannot be undone.')).toBeInTheDocument();
    const container = document.querySelector('.nexus-responsive-alertdialog-container');
    const dialog = document.querySelector('.nexus-responsive-alertdialog-dialog');
    expect(container).not.toBeNull();
    expect(dialog).not.toBeNull();
    expect(container).toContainElement(dialog as HTMLElement);
  });

  it('merges call-site classNames with the sheet classes', () => {
    render(
      <AlertDialog.Backdrop isOpen onOpenChange={() => {}}>
        <AlertDialog.Container>
          <AlertDialog.Dialog className="sm:max-w-[420px]">
            <AlertDialog.Header>
              <AlertDialog.Heading>Sized dialog</AlertDialog.Heading>
            </AlertDialog.Header>
          </AlertDialog.Dialog>
        </AlertDialog.Container>
      </AlertDialog.Backdrop>,
    );

    const dialog = document.querySelector('.nexus-responsive-alertdialog-dialog');
    expect(dialog).not.toBeNull();
    expect((dialog as HTMLElement).className).toContain('sm:max-w-[420px]');
  });
});
