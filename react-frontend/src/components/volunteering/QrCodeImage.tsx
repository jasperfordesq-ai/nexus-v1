// Copyright © 2024–2026 Jasper Ford
// SPDX-License-Identifier: AGPL-3.0-or-later
// Author: Jasper Ford
// See NOTICE file for attribution and acknowledgements.

/**
 * Renders a QR code entirely on the client. The generator (`qrcode`) is loaded
 * with a dynamic import so it lands in its own async chunk and never inflates the
 * initial bundle. This replaces the previous `<img src="api.qrserver.com/...">`
 * that leaked the check-in token URL to a third-party service.
 */

import { useEffect, useState } from 'react';

interface QrCodeImageProps {
  value: string;
  alt: string;
  size?: number;
  className?: string;
}

export function QrCodeImage({ value, alt, size = 200, className }: QrCodeImageProps) {
  const [dataUrl, setDataUrl] = useState<string>('');
  const [failed, setFailed] = useState(false);

  useEffect(() => {
    let cancelled = false;
    setDataUrl('');
    setFailed(false);
    if (!value) return;
    import('qrcode')
      .then((mod) => mod.toDataURL(value, { width: size, margin: 1, errorCorrectionLevel: 'M' }))
      .then((url) => { if (!cancelled) setDataUrl(url); })
      .catch(() => { if (!cancelled) setFailed(true); });
    return () => { cancelled = true; };
  }, [value, size]);

  if (failed) {
    // The qrcode chunk failed to load (stale deploy / offline). Fall back to a
    // usable link so check-in can still proceed, instead of an aria-busy
    // placeholder that appears to load forever.
    return (
      <a
        href={value}
        className={`inline-block max-w-full break-all ${className ?? ''}`}
        style={{ width: size }}
      >
        {alt}
      </a>
    );
  }

  if (!dataUrl) {
    // Same footprint as the rendered code so layout doesn't shift on load.
    return (
      <div
        className={className}
        style={{ width: size, height: size }}
        role="img"
        aria-label={alt}
        aria-busy="true"
      />
    );
  }

  return <img src={dataUrl} alt={alt} className={className} width={size} height={size} />;
}

export default QrCodeImage;
