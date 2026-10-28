import { mkdir, open, rename, rm } from 'node:fs/promises';
import { dirname, join, resolve } from 'node:path';
import type { CreateJob } from '../schema/job.js';

export interface QueuedJob extends CreateJob {
  jobId: string;
  createdAt: string;
  schemaVersion: 1;
}

export interface JobQueue {
  enqueue(job: CreateJob): Promise<QueuedJob>;
}

export class FileJobQueue implements JobQueue {
  private readonly pendingDirectory: string;

  constructor(queueRoot: string) {
    const root = resolve(queueRoot);
    if (root === resolve('.')) throw new Error('QUEUE_ROOT must not be the process working directory');
    this.pendingDirectory = join(root, 'pending');
  }

  async enqueue(job: CreateJob): Promise<QueuedJob> {
    await mkdir(this.pendingDirectory, { recursive: true, mode: 0o700 });
    const jobId = crypto.randomUUID();
    const queued: QueuedJob = {
      ...job,
      jobId,
      createdAt: new Date().toISOString(),
      schemaVersion: 1
    };
    const finalPath = this.safeJobPath(jobId, '.json');
    const temporaryPath = this.safeJobPath(jobId, `.tmp-${crypto.randomUUID()}`);
    const handle = await open(temporaryPath, 'wx', 0o600);
    try {
      await handle.writeFile(`${JSON.stringify(queued)}\n`, { encoding: 'utf8' });
      await handle.sync();
    } catch (error) {
      await rm(temporaryPath, { force: true });
      throw error;
    } finally {
      await handle.close();
    }
    await rename(temporaryPath, finalPath);
    return queued;
  }

  private safeJobPath(jobId: string, suffix: string): string {
    if (!/^[0-9a-f-]{36}$/.test(jobId) || !/^\.(?:json|tmp-[0-9a-f-]{36})$/.test(suffix)) {
      throw new Error('invalid queue filename');
    }
    const path = resolve(this.pendingDirectory, `${jobId}${suffix}`);
    if (dirname(path) !== resolve(this.pendingDirectory)) throw new Error('unsafe queue path');
    return path;
  }
}

