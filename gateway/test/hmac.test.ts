import { describe, expect, it } from 'vitest';
import { calculateSignature, verifySignature } from '../src/security/hmac.js';

describe('HMAC verification', () => {
  const secret = '0123456789abcdef0123456789abcdef';
  const body = Buffer.from('{"requestId":"test"}');
  const now = 1_800_000_000_000;
  const timestamp = String(Math.floor(now / 1000));
  const nonce = 'nonce_0123456789abcdef';

  it('accepts a valid signature', () => {
    const signature = calculateSignature(secret, timestamp, nonce, body);
    expect(verifySignature(secret, { timestamp, nonce, signature, rawBody: body }, 300, now)).toBe(true);
  });

  it('rejects tampering and stale timestamps', () => {
    const signature = calculateSignature(secret, timestamp, nonce, body);
    expect(verifySignature(secret, { timestamp, nonce, signature, rawBody: Buffer.from('tampered') }, 300, now)).toBe(false);
    expect(verifySignature(secret, { timestamp, nonce, signature, rawBody: body }, 300, now + 301_000)).toBe(false);
  });
});

