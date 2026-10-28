import Fastify, { type FastifyInstance } from 'fastify';
import helmet from '@fastify/helmet';
import rateLimit from '@fastify/rate-limit';
import { loadConfig, type Config } from './config.js';
import { verifySignature } from './security/hmac.js';
import { MemoryNonceStore, type NonceStore } from './security/nonce-store.js';
import { CreateJobSchema } from './schema/job.js';
import { FileJobQueue, type JobQueue } from './queue/file-queue.js';

declare module 'fastify' {
  interface FastifyRequest { rawBody?: Buffer }
}

export async function buildApp(
  config: Config = loadConfig(),
  nonceStore: NonceStore = new MemoryNonceStore(),
  jobQueue: JobQueue = new FileJobQueue(config['QUEUE_ROOT'])
): Promise<FastifyInstance> {
  const app = Fastify({
    logger: { redact: ['req.headers.authorization', 'req.headers.x-signature', 'body'] },
    bodyLimit: 128 * 1024,
    trustProxy: false,
    requestIdHeader: false,
    genReqId: () => crypto.randomUUID()
  });

  await app.register(helmet, { contentSecurityPolicy: false });
  await app.register(rateLimit, { max: 60, timeWindow: '1 minute' });

  app.addContentTypeParser('application/json', { parseAs: 'buffer' }, (request, body, done) => {
    const rawBody = Buffer.isBuffer(body) ? body : Buffer.from(body);
    request.rawBody = rawBody;
    try {
      done(null, JSON.parse(rawBody.toString('utf8')) as unknown);
    } catch (error) {
      done(error as Error, undefined);
    }
  });

  app.get('/health', { config: { rateLimit: { max: 10, timeWindow: '1 minute' } } }, () => ({ status: 'ok' }));

  app.post('/v1/jobs', async (request, reply) => {
    const timestamp = request.headers['x-timestamp'];
    const nonce = request.headers['x-nonce'];
    const signature = request.headers['x-signature'];
    if (typeof timestamp !== 'string' || typeof nonce !== 'string' || typeof signature !== 'string' || !request.rawBody) {
      return reply.code(401).send({ error: 'unauthorized' });
    }
    if (!verifySignature(config.HMAC_SECRET, { timestamp, nonce, signature, rawBody: request.rawBody }, config.MAX_CLOCK_SKEW_SECONDS)) {
      return reply.code(401).send({ error: 'unauthorized' });
    }
    if (!await nonceStore.consume(nonce, Date.now() + config.MAX_CLOCK_SKEW_SECONDS * 1000)) {
      return reply.code(409).send({ error: 'replayed_request' });
    }

    const parsed = CreateJobSchema.safeParse(request.body);
    if (!parsed.success) return reply.code(400).send({ error: 'invalid_request' });

    const queued = await jobQueue.enqueue(parsed.data);
    return reply.code(202).send({ jobId: queued.jobId, status: 'accepted' });
  });

  app.setErrorHandler((error, request, reply) => {
    request.log.error({ err: error, requestId: request.id }, 'request failed');
    void reply.code(500).send({ error: 'internal_error', requestId: request.id });
  });

  return app;
}

