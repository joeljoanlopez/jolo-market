import QRCode from 'qrcode';
import { useMemo } from 'react';

interface QrCodeProps {
  /** The text to encode, e.g. an otpauth:// provisioning URI. */
  value: string;
  /** What the image is, for screen readers. */
  label: string;
}

/** Scanners need a light border of 4 modules around the code (ISO/IEC 18004). */
const QUIET_ZONE = 4;

/**
 * Draws a QR code as SVG, dark modules on a light background, so it stays sharp at any size
 * and needs no canvas. The value never leaves the browser.
 */
export function QrCode({ value, label }: QrCodeProps) {
  const { size, path } = useMemo(() => {
    const { modules } = QRCode.create(value, { errorCorrectionLevel: 'M' });
    let d = '';
    for (let row = 0; row < modules.size; row++) {
      for (let column = 0; column < modules.size; column++) {
        if (modules.get(row, column)) {
          d += `M${String(column + QUIET_ZONE)} ${String(row + QUIET_ZONE)}h1v1h-1z`;
        }
      }
    }
    return { size: modules.size + 2 * QUIET_ZONE, path: d };
  }, [value]);

  return (
    <svg
      role="img"
      aria-label={label}
      viewBox={`0 0 ${String(size)} ${String(size)}`}
      shapeRendering="crispEdges"
      className="size-48 bg-background fill-foreground"
    >
      <path d={path} />
    </svg>
  );
}
