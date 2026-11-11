import { createHmac } from 'node:crypto';

const BASE32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
const STEP_SECONDS = 30;

/** RFC 6238 code (SHA-1, 6 digits, 30 s), as an authenticator app shows it for `secret`. */
export function totpCode(secret: string, at: Date = new Date()): string {
  const counter = Buffer.alloc(8);
  counter.writeBigUInt64BE(BigInt(Math.floor(at.getTime() / 1000 / STEP_SECONDS)));
  const hmac = createHmac('sha1', base32Decode(secret)).update(counter).digest();
  const offset = (hmac.at(-1) ?? 0) & 0x0f;
  const binary = hmac.readUInt32BE(offset) & 0x7fffffff;
  return String(binary % 1_000_000).padStart(6, '0');
}

/** Milliseconds until the next code: the API accepts each code once (no replay). */
export function msUntilNextCode(now: Date = new Date()): number {
  return STEP_SECONDS * 1000 - (now.getTime() % (STEP_SECONDS * 1000));
}

function base32Decode(secret: string): Buffer {
  let bits = '';
  for (const char of secret.replace(/=+$/, '').toUpperCase()) {
    const value = BASE32.indexOf(char);
    if (value === -1) {
      throw new Error('A TOTP secret is base32.');
    }
    bits += value.toString(2).padStart(5, '0');
  }
  const bytes: number[] = [];
  for (let i = 0; i + 8 <= bits.length; i += 8) {
    bytes.push(parseInt(bits.slice(i, i + 8), 2));
  }
  return Buffer.from(bytes);
}

/**
 * A code the API will accept after one used at `lastUsedAt`: codes are single-use per 30-second
 * step, so it waits for the next step when needed.
 */
export async function nextUnusedCode(secret: string, lastUsedAt: Date): Promise<string> {
  const step = (date: Date) => Math.floor(date.getTime() / 1000 / STEP_SECONDS);
  while (step(new Date()) <= step(lastUsedAt)) {
    await new Promise((resolve) => setTimeout(resolve, msUntilNextCode() + 250));
  }
  return totpCode(secret);
}
