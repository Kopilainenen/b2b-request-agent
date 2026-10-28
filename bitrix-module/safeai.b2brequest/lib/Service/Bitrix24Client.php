<?php

declare(strict_types=1);

namespace SafeAi\B2BRequest\Service;

use RuntimeException;

final class Bitrix24Client
{
    private const METHODS = [
        'crm.item.list',
        'crm.item.add',
        'crm.item.productrow.set',
    ];

    private readonly string $baseUrl;
    private readonly string $host;
    private readonly string $pinnedIp;

    public function __construct()
    {
        $url = rtrim(trim((string)getenv('SAFEAI_BITRIX24_WEBHOOK_URL')), '/');
        $allowedHost = strtolower(trim((string)getenv('SAFEAI_BITRIX24_ALLOWED_HOST')));
        $parts = parse_url($url);
        $host = strtolower((string)($parts['host'] ?? ''));
        $path = (string)($parts['path'] ?? '');

        if (($parts['scheme'] ?? '') !== 'https' || $host === '' || $host !== $allowedHost) {
            throw new RuntimeException('bitrix24_configuration_invalid');
        }
        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])
            || (isset($parts['port']) && (int)$parts['port'] !== 443)
            || preg_match('#\A/rest/\d+/[A-Za-z0-9_-]{8,}/?\z#D', ltrim($path, '/')) !== 1) {
            throw new RuntimeException('bitrix24_webhook_invalid');
        }

        $addresses = gethostbynamel($host) ?: [];
        $public = array_values(array_filter($addresses, static fn(string $ip): bool =>
            filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false
        ));
        if ($public === []) {
            throw new RuntimeException('bitrix24_dns_invalid');
        }

        $this->baseUrl = $url;
        $this->host = $host;
        $this->pinnedIp = $public[0];
    }

    public function findDealByOrigin(string $originId): ?int
    {
        $response = $this->call('crm.item.list', [
            'entityTypeId' => 2,
            'filter' => [
                '=originatorId' => 'SAFEAI_B2B_REQUEST',
                '=originId' => $originId,
            ],
            'select' => ['id'],
            'start' => 0,
        ]);
        $items = $response['result']['items'] ?? null;
        if (!is_array($items) || $items === []) {
            return null;
        }
        $id = filter_var($items[0]['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return $id === false ? null : (int)$id;
    }

    public function createDeal(array $fields): int
    {
        $response = $this->call('crm.item.add', ['entityTypeId' => 2, 'fields' => $fields]);
        $id = filter_var($response['result']['item']['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            throw new RuntimeException('bitrix24_invalid_deal_response');
        }
        return (int)$id;
    }

    public function setProductRows(int $dealId, array $rows): void
    {
        if ($rows === []) {
            return;
        }
        $response = $this->call('crm.item.productrow.set', [
            'ownerType' => 'D',
            'ownerId' => $dealId,
            'productRows' => $rows,
        ]);
        if (!is_array($response['result']['productRows'] ?? null)) {
            throw new RuntimeException('bitrix24_invalid_product_response');
        }
    }

    private function call(string $method, array $parameters): array
    {
        if (!in_array($method, self::METHODS, true)) {
            throw new RuntimeException('bitrix24_method_denied');
        }
        $body = json_encode($parameters, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (strlen($body) > 512 * 1024) {
            throw new RuntimeException('bitrix24_request_too_large');
        }

        $curl = curl_init($this->baseUrl . '/' . $method . '.json');
        if ($curl === false) {
            throw new RuntimeException('bitrix24_transport_init_failed');
        }
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_MAXREDIRS => 0,
            CURLOPT_RESOLVE => [$this->host . ':443:' . $this->pinnedIp],
        ]);
        $raw = curl_exec($curl);
        $status = (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $curlError = curl_errno($curl);
        curl_close($curl);

        if (!is_string($raw) || strlen($raw) > 1024 * 1024 || $curlError !== 0) {
            throw new RuntimeException('bitrix24_transport_failed');
        }
        $decoded = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($decoded) || $status !== 200 || isset($decoded['error'])) {
            throw new RuntimeException('bitrix24_api_rejected');
        }
        return $decoded;
    }
}

