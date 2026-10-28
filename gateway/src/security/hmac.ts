import { createHmac, timingSafeEqual } from 'node:crypto';

export interface SignedRequest {
  timestamp: string;
  nonce: string;
  signature: string;
  rawBody: Buffer;
}

const NONCE_PATTERN = /^[A-Za-z0-9_-]{16,128}$/;
const SIGNATURE_PATTERN = /^[a-f0-9]{64}$/;

export function calculateSignature(secret: string, timestamp: string, nonce: string, rawBody: Buffer): string {
  const bodyHash = createHmac('sha256', secret).update(rawBody).digest('hex');
  return createHmac('sha256', secret)
    .update(`${timestamp}\n${nonce}\n${bodyHash}`, 'utf8')
    .digest('hex');
}

export function verifySignature(secret: string, request: SignedRequest, maxClockSkewSeconds: number, nowMs = Date.now()): boolean {
  if (!NONCE_PATTERN.test(request.nonce) || !SIGNATURE_PATTERN.test(request.signature)) return false;
  const timestampSeconds = Number(request.timestamp);
  if (!Number.isSafeInteger(timestampSeconds)) return false;
  if (Math.abs(Math.floor(nowMs / 1000) - timestampSeconds) > maxClockSkewSeconds) return false;

  const expected = Buffer.from(calculateSignature(secret, request.timestamp, request.nonce, request.rawBody), 'hex');
  const provided = Buffer.from(request.signature, 'hex');
  return expected.length === provided.length && timingSafeEqual(expected, provided);
}

