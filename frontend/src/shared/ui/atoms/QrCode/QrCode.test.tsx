import { render, screen } from '@testing-library/react';
import QRCode from 'qrcode';
import { describe, expect, it } from 'vitest';

import { QrCode } from './QrCode';

const uri = 'otpauth://totp/jolo-market:maria?secret=JBSWY3DPEHPK3PXP&issuer=jolo-market';

describe('QrCode', () => {
  it('is an image named by its label', () => {
    render(<QrCode value={uri} label="Código QR para tu app de autenticación" />);

    expect(
      screen.getByRole('img', { name: 'Código QR para tu app de autenticación' }),
    ).toBeInTheDocument();
  });

  it('draws one dark square per dark module of the QR code, with a quiet zone', () => {
    render(<QrCode value={uri} label="QR" />);

    const { modules } = QRCode.create(uri, { errorCorrectionLevel: 'M' });
    const darkModules = Array.from(modules.data).filter(Boolean).length;
    const image = screen.getByRole('img', { name: 'QR' });
    expect(image).toHaveAttribute(
      'viewBox',
      `0 0 ${String(modules.size + 8)} ${String(modules.size + 8)}`,
    );
    expect(image.querySelector('path')?.getAttribute('d')?.match(/M/g)).toHaveLength(darkModules);
  });
});
