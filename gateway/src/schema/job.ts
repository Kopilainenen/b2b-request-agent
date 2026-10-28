import { z } from 'zod';

export const CreateJobSchema = z.object({
  requestId: z.uuid(),
  document: z.object({
    storageId: z.string().regex(/^[a-f0-9]{48}\.(pdf|xlsx)$/),
    sha256: z.string().regex(/^[a-f0-9]{64}$/),
    mediaType: z.enum([
      'application/pdf',
      'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
      'application/zip',
      'application/x-zip',
      'application/x-zip-compressed'
    ]),
    size: z.number().int().positive().max(12 * 1024 * 1024)
  })
}).strict();

export type CreateJob = z.infer<typeof CreateJobSchema>;

