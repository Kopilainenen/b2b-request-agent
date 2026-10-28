import { mkdtemp, readFile, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { afterEach, describe, expect, it } from 'vitest';
import { FileJobQueue } from '../src/queue/file-queue.js';

const roots: string[] = [];
afterEach(async () => Promise.all(roots.splice(0).map(async root => rm(root, { recursive: true, force: true }))));

describe('FileJobQueue', () => {
  it('atomically persists a versioned job without document content', async () => {
    const root = await mkdtemp(join(tmpdir(), 'safeai-queue-'));
    roots.push(root);
    const queue = new FileJobQueue(root);
    const queued = await queue.enqueue({
      requestId: '2cddc0ae-1aac-4cc7-aa6a-3c27112d8435',
      document: {
        storageId: `${'a'.repeat(48)}.pdf`,
        sha256: 'b'.repeat(64),
        mediaType: 'application/pdf',
        size: 100
      }
    });
    const persisted = JSON.parse(await readFile(join(root, 'pending', `${queued.jobId}.json`), 'utf8')) as unknown;
    expect(persisted).toEqual(queued);
  });
});

