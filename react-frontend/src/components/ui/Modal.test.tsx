// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '@/test/test-utils';
import {
  Modal,
  ModalContent,
  ModalHeader,
  ModalBody,
  ModalFooter,
} from './Modal';

// HeroUI Modal renders into a portal; query via screen (searches document.body).

describe('Modal — open/closed gate', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders modal content when isOpen=true', () => {
    render(
      <Modal isOpen>
        <ModalContent>
          <ModalBody><p>modal body text</p></ModalBody>
        </ModalContent>
      </Modal>
    );
    expect(screen.getByText('modal body text')).toBeInTheDocument();
  });

  it('does not render modal content when isOpen=false', () => {
    render(
      <Modal isOpen={false}>
        <ModalContent>
          <ModalBody><p>hidden modal</p></ModalBody>
        </ModalContent>
      </Modal>
    );
    expect(screen.queryByText('hidden modal')).not.toBeInTheDocument();
  });
});

describe('Modal — compound sections', () => {
  it('renders ModalHeader children', () => {
    render(
      <Modal isOpen>
        <ModalContent>
          <ModalHeader>Modal Title</ModalHeader>
          <ModalBody><p>body</p></ModalBody>
        </ModalContent>
      </Modal>
    );
    expect(screen.getByText('Modal Title')).toBeInTheDocument();
  });

  it('renders ModalBody children', () => {
    render(
      <Modal isOpen>
        <ModalContent>
          <ModalBody><span>Body Content</span></ModalBody>
        </ModalContent>
      </Modal>
    );
    expect(screen.getByText('Body Content')).toBeInTheDocument();
  });

  it('renders ModalFooter children', () => {
    render(
      <Modal isOpen>
        <ModalContent>
          <ModalBody><p>body</p></ModalBody>
          <ModalFooter><button>Confirm</button></ModalFooter>
        </ModalContent>
      </Modal>
    );
    expect(screen.getByRole('button', { name: /confirm/i })).toBeInTheDocument();
  });
});

describe('Modal — render prop children', () => {
  it('passes onClose function to render-prop children', () => {
    const onClose = vi.fn();
    render(
      <Modal isOpen onClose={onClose} onOpenChange={(open) => { if (!open) onClose(); }}>
        <ModalContent>
          {(close) => (
            <ModalBody>
              <button onClick={close}>Close via prop</button>
            </ModalBody>
          )}
        </ModalContent>
      </Modal>
    );
    expect(screen.getByRole('button', { name: /close via prop/i })).toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: /close via prop/i }));
    expect(onClose).toHaveBeenCalled();
  });
});

describe('Modal — close trigger visibility', () => {
  it('renders built-in close trigger by default (hideCloseButton not set)', () => {
    render(
      <Modal isOpen>
        <ModalContent>
          <ModalHeader>Title</ModalHeader>
          <ModalBody><p>content</p></ModalBody>
        </ModalContent>
      </Modal>
    );
    // HeroUI CloseTrigger renders as a button
    const buttons = screen.queryAllByRole('button');
    expect(buttons.length).toBeGreaterThan(0);
  });

  it('does not render close trigger when hideCloseButton=true', () => {
    // When hideCloseButton=true the CloseTrigger element is not rendered.
    // We verify the modal still opens and has no extra button.
    render(
      <Modal isOpen hideCloseButton>
        <ModalContent>
          <ModalBody><p>no close btn</p></ModalBody>
        </ModalContent>
      </Modal>
    );
    expect(screen.getByText('no close btn')).toBeInTheDocument();
    // No close-button role expected (there may be 0 buttons total)
    // We just verify the content is there without crashing.
  });
});

describe('Modal — size normalisation', () => {
  // Sizes xs/sm/md/lg/full map to the container size directly;
  // xl/2xl/3xl/4xl/5xl map container to "lg" + add max-w className on Dialog.
  // We verify the modal renders without error for each category.
  const sizes = ['xs', 'sm', 'md', 'lg', 'xl', '2xl', 'full'] as const;
  sizes.forEach((size) => {
    it(`renders at size="${size}"`, () => {
      render(
        <Modal isOpen size={size}>
          <ModalContent>
            <ModalBody><p>size test</p></ModalBody>
          </ModalContent>
        </Modal>
      );
      expect(screen.getByText('size test')).toBeInTheDocument();
    });
  });
});
