import { z } from 'zod';

const EnvSchema = z.object({
  BIND_HOST: z.union([z.ipv4(), z.ipv6()]).default('127.0.0.1'),
  PORT: z.coerce.number().int().min(1024).max(65535).default(8780),
  HMAC_SECRET: z.string().min(32),
  MAX_CLOCK_SKEW_SECONDS: z.coerce.number().int().min(30).max(900).default(300),
  QUEUE_ROOT: z.string().min(1),
  NODE_ENV: z.enum(['development', 'test', 'production']).default('production')
});

export type Config = z.infer<typeof EnvSchema>;

export function loadConfig(env: NodeJS.ProcessEnv = process.env): Config {
  return EnvSchema.parse(env);
}

