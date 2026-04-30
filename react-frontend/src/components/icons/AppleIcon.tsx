// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

import type { SVGProps } from 'react';

export function AppleIcon(props: SVGProps<SVGSVGElement>) {
  return (
    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" {...props}>
      <path
        fill="currentColor"
        d="M16.365 1.43c0 1.14-.41 2.21-1.23 3.21-.99 1.21-2.18 1.92-3.47 1.81-.16-1.13.4-2.32 1.18-3.21.79-.91 2.13-1.6 3.52-1.81zm4.96 17.36c-.66 1.51-.97 2.18-1.82 3.51-1.18 1.85-2.85 4.16-4.92 4.18-1.84.02-2.31-1.21-4.81-1.2-2.5.02-3.02 1.22-4.86 1.2-2.07-.02-3.65-2.1-4.83-3.95-3.3-5.16-3.65-11.21-1.61-14.43 1.45-2.29 3.74-3.63 5.89-3.63 2.19 0 3.57 1.21 5.38 1.21 1.76 0 2.83-1.22 5.36-1.22 1.91 0 3.94 1.05 5.38 2.86-4.73 2.6-3.96 9.42 0 11.47z"
      />
    </svg>
  );
}

export default AppleIcon;
