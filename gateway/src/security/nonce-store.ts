export interface NonceStore {
  consume(nonce: string, expiresAtMs: number): Promise<boolean>;
}

export class MemoryNonceStore implements NonceStore {
  private readonly values = new Map<string, number>();

  consume(nonce: string, expiresAtMs: number): Promise<boolean> {
    const now = Date.now();
    for (const [key, expiry] of this.values) {
      if (expiry <= now) this.values.delete(key);
    }
    if (this.values.has(nonce)) return Promise.resolve(false);
    this.values.set(nonce, expiresAtMs);
    return Promise.resolve(true);
  }
}

