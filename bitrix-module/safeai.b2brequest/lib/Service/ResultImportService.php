<?php

declare(strict_types=1);

namespace SafeAi\B2BRequest\Service;

use Bitrix\Main\Application;
use Bitrix\Main\Type\DateTime;
use SafeAi\B2BRequest\AuditLogTable;
use SafeAi\B2BRequest\ProcessingJobTable;
use SafeAi\B2BRequest\RequestItemTable;
use SafeAi\B2BRequest\RequestTable;
use SafeAi\B2BRequest\ResultTable;
use RuntimeException;

final class ResultImportService
{
    private const MAX_RESULT_BYTES = 2 * 1024 * 1024;

    public static function runAgent(): string
    {
        try {
            (new self())->importAvailable();
        } catch (\Throwable) {
            // Agent must not disclose document data or paths into the public response.
        }
        return '\\SafeAi\\B2BRequest\\Service\\ResultImportService::runAgent();';
    }

    public function importAvailable(): void
    {
        $root = trim((string)getenv('SAFEAI_QUEUE_ROOT'));
        if ($root === '' || !$this->isAbsolutePath($root)) {
            return;
        }
        $completed = rtrim($root, '/\\') . DIRECTORY_SEPARATOR . 'completed';
        $imported = rtrim($root, '/\\') . DIRECTORY_SEPARATOR . 'imported';
        if (!is_dir($completed)) {
            return;
        }
        if (!is_dir($imported) && !mkdir($imported, 0700, true) && !is_dir($imported)) {
            throw new RuntimeException('Unable to create imported result directory.');
        }

        $files = glob($completed . DIRECTORY_SEPARATOR . '*.result.json') ?: [];
        foreach (array_slice($files, 0, 20) as $path) {
            $this->importOne($path, $imported);
        }
    }

    private function importOne(string $path, string $imported): void
    {
        $name = basename($path);
        if (preg_match('/\A[0-9a-f-]{36}\.result\.json\z/D', $name) !== 1
            || is_link($path) || !is_file($path)) {
            return;
        }
        $size = filesize($path);
        if ($size === false || $size < 2 || $size > self::MAX_RESULT_BYTES) {
            return;
        }
        $raw = file_get_contents($path);
        $data = is_string($raw) ? json_decode($raw, true, 32, JSON_THROW_ON_ERROR) : null;
        if (!is_array($data) || ($data['schema_version'] ?? null) !== 1) {
            return;
        }
        $jobUuid = $this->uuid($data['job_id'] ?? null);
        $requestUuid = $this->uuid($data['request_id'] ?? null);
        $normalized = $data['normalized'] ?? null;
        if (!is_array($normalized)) {
            return;
        }

        $job = ProcessingJobTable::getList(['filter' => ['=JOB_UUID' => $jobUuid], 'limit' => 1])->fetch();
        if (!$job || $job['STATUS'] === 'completed') {
            return;
        }
        $request = RequestTable::getByPrimary((int)$job['REQUEST_ID'])->fetch();
        if (!$request || !hash_equals((string)$request['PUBLIC_ID'], $requestUuid)) {
            throw new RuntimeException('Result does not belong to the queued request.');
        }

        $card = $this->validateCard($normalized);
        $connection = Application::getConnection();
        $connection->startTransaction();
        try {
            $result = ResultTable::add([
                'REQUEST_ID' => (int)$request['ID'],
                'COMPANY' => $card['company'],
                'INN' => $card['inn'],
                'EMAIL' => $card['email'],
                'PHONE' => $card['phone'],
                'SUMMARY' => $card['summary'],
                'CONFIDENCE' => $card['confidence'],
                'WARNINGS_JSON' => json_encode($card['warnings'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            ]);
            if (!$result->isSuccess()) {
                throw new RuntimeException(implode('; ', $result->getErrorMessages()));
            }
            foreach ($card['items'] as $index => $item) {
                RequestItemTable::add([
                    'REQUEST_ID' => (int)$request['ID'],
                    'SORT' => ($index + 1) * 10,
                    'NAME' => $item['name'],
                    'QUANTITY' => $item['quantity'],
                    'UNIT' => $item['unit'],
                    'PRICE' => $item['price'],
                ]);
            }
            $now = new DateTime();
            ProcessingJobTable::update((int)$job['ID'], ['STATUS' => 'completed', 'COMPLETED_AT' => $now]);
            RequestTable::update((int)$request['ID'], ['STATUS' => 'review', 'UPDATED_AT' => $now]);
            AuditLogTable::add([
                'REQUEST_ID' => (int)$request['ID'],
                'EVENT' => 'processing.result_imported',
                'REQUEST_ID_TRACE' => bin2hex(random_bytes(16)),
                'METADATA_JSON' => json_encode(['confidence' => $card['confidence']], JSON_THROW_ON_ERROR),
            ]);
            $connection->commitTransaction();
        } catch (\Throwable $error) {
            $connection->rollbackTransaction();
            throw $error;
        }

        if (!rename($path, $imported . DIRECTORY_SEPARATOR . $name)) {
            // Database idempotency prevents a duplicate import on the next run.
        }
    }

    private function validateCard(array $value): array
    {
        $items = [];
        foreach (array_slice(is_array($value['items'] ?? null) ? $value['items'] : [], 0, 200) as $item) {
            if (!is_array($item)) continue;
            $name = $this->text($item['name'] ?? '', 500);
            if ($name === '') continue;
            $items[] = [
                'name' => $name,
                'quantity' => $this->decimal($item['quantity'] ?? null),
                'unit' => $this->text($item['unit'] ?? '', 30),
                'price' => $this->decimal($item['price'] ?? null),
            ];
        }
        $confidence = is_numeric($value['confidence'] ?? null) ? (float)$value['confidence'] : 0.0;
        $warnings = [];
        foreach (array_slice(is_array($value['warnings'] ?? null) ? $value['warnings'] : [], 0, 50) as $warning) {
            $warnings[] = $this->text($warning, 500);
        }
        return [
            'company' => $this->text($value['company'] ?? '', 255),
            'inn' => preg_match('/\A(?:\d{10}|\d{12})\z/D', (string)($value['inn'] ?? '')) ? (string)$value['inn'] : '',
            'email' => filter_var($value['email'] ?? '', FILTER_VALIDATE_EMAIL) ? (string)$value['email'] : '',
            'phone' => preg_match('/\A\+?\d{7,15}\z/D', (string)($value['phone'] ?? '')) ? (string)$value['phone'] : '',
            'summary' => $this->text($value['summary'] ?? '', 4_000),
            'confidence' => max(0.0, min(1.0, $confidence)),
            'warnings' => array_values(array_filter($warnings)),
            'items' => $items,
        ];
    }

    private function text(mixed $value, int $max): string
    {
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', strip_tags((string)$value)) ?? '';
        return mb_substr(trim($text), 0, $max);
    }

    private function decimal(mixed $value): string
    {
        $value = str_replace(',', '.', trim((string)$value));
        return preg_match('/\A\d{1,12}(?:\.\d{1,4})?\z/D', $value) ? $value : '';
    }

    private function uuid(mixed $value): string
    {
        $value = strtolower((string)$value);
        if (preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/D', $value) !== 1) {
            throw new RuntimeException('Invalid result identifier.');
        }
        return $value;
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/') || preg_match('/\A[A-Za-z]:[\\\\\/]/', $path) === 1;
    }
}

