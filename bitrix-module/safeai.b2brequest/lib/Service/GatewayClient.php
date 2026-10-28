<?php

declare(strict_types=1);

namespace SafeAi\B2BRequest\Service;

use RuntimeException;

final class GatewayClient
{
    public function enqueue(string $requestUuid, array $document): ?string
    {
        $url = trim((string)getenv('SAFEAI_GATEWAY_URL'));
        $secret = (string)getenv('SAFEAI_GATEWAY_HMAC_SECRET');
        if ($url === '' && $secret === '') {
            return null;
        }
        if (strlen($secret) < 32) {
            throw new RuntimeException('Gateway secret is missing or too short.');
        }
        $parts = parse_url($url);
        $host = strtolower((string)($parts['host'] ?? ''));
        if (($parts['scheme'] ?? '') !== 'http' || !in_array($host, ['127.0.0.1', '::1'], true)
            || (int)($parts['port'] ?? 0) !== 8780 || !in_array((string)($parts['path'] ?? ''), ['', '/'], true)
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            throw new RuntimeException('MVP Gateway must use loopback HTTP only.');
        }

        $body = json_encode([
            'requestId' => $requestUuid,
            'document' => $document,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $timestamp = (string)time();
        $nonce = rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
        $bodyHash = hash_hmac('sha256', $body, $secret);
        $signature = hash_hmac('sha256', $timestamp . "\n" . $nonce . "\n" . $bodyHash, $secret);

        $curl = curl_init(rtrim($url, '/') . '/v1/jobs');
        if ($curl === false) {
            throw new RuntimeException('Unable to initialise Gateway client.');
        }
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'X-Timestamp: ' . $timestamp,
                'X-Nonce: ' . $nonce,
                'X-Signature: ' . $signature,
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP,
            CURLOPT_MAXREDIRS => 0,
        ]);
        $response = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);
        if (!is_string($response) || strlen($response) > 16_384 || $status !== 202) {
            throw new RuntimeException('Gateway rejected the processing job.');
        }
        $decoded = json_decode($response, true, 16, JSON_THROW_ON_ERROR);
        $jobId = is_array($decoded) ? (string)($decoded['jobId'] ?? '') : '';
        if (preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/D', $jobId) !== 1) {
            throw new RuntimeException('Gateway returned an invalid job identifier.');
        }
        return $jobId;
    }
}

