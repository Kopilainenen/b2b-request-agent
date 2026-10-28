<?php

declare(strict_types=1);

namespace SafeAi\B2BRequest\Service;

use Bitrix\Main\Application;
use Bitrix\Main\Type\DateTime;
use SafeAi\B2BRequest\AuditLogTable;
use SafeAi\B2BRequest\ExportOutboxTable;
use SafeAi\B2BRequest\RequestItemTable;
use SafeAi\B2BRequest\RequestTable;
use SafeAi\B2BRequest\ResultTable;
use RuntimeException;

final class DealExportService
{
    private const MAX_ATTEMPTS = 8;

    public static function runAgent(): string
    {
        try {
            (new self())->exportNext();
        } catch (\Throwable) {
            // The agent never exposes webhook details or document content.
        }
        return '\\SafeAi\\B2BRequest\\Service\\DealExportService::runAgent();';
    }

    public function exportNext(): void
    {
        $connection = Application::getConnection();
        $lockName = 'safeai_b2b_deal_export';
        $lock = $connection->queryScalar("SELECT GET_LOCK('" . $connection->getSqlHelper()->forSql($lockName) . "', 0)");
        if ((string)$lock !== '1') {
            return;
        }
        try {
            $this->recoverStaleJobs();
            $row = ExportOutboxTable::getList([
                'filter' => ['@STATUS' => ['pending', 'retry'], '<=NEXT_ATTEMPT_AT' => new DateTime()],
                'order' => ['ID' => 'ASC'],
                'limit' => 1,
            ])->fetch();
            if (!$row) {
                return;
            }
            $this->export($row);
        } finally {
            $connection->queryExecute("SELECT RELEASE_LOCK('" . $connection->getSqlHelper()->forSql($lockName) . "')");
        }
    }

    public function retryFailed(int $requestId, int $actorId): void
    {
        $outbox = ExportOutboxTable::getList(['filter' => ['=REQUEST_ID' => $requestId], 'limit' => 1])->fetch();
        $request = RequestTable::getByPrimary($requestId)->fetch();
        if (!$outbox || !$request || $outbox['STATUS'] !== 'failed' || $request['STATUS'] !== 'export_failed') {
            throw new RuntimeException('export_retry_not_allowed');
        }
        ExportOutboxTable::update((int)$outbox['ID'], [
            'STATUS' => 'retry',
            'ATTEMPTS' => 0,
            'NEXT_ATTEMPT_AT' => new DateTime(),
            'LAST_ERROR_CODE' => '',
            'UPDATED_AT' => new DateTime(),
        ]);
        RequestTable::update($requestId, ['STATUS' => 'approved', 'UPDATED_AT' => new DateTime()]);
        AuditLogTable::add([
            'REQUEST_ID' => $requestId,
            'EVENT' => 'bitrix24.export_retried_by_manager',
            'ACTOR_ID' => $actorId,
            'REQUEST_ID_TRACE' => bin2hex(random_bytes(16)),
            'METADATA_JSON' => '{}',
        ]);
    }

    private function export(array $outbox): void
    {
        $outboxId = (int)$outbox['ID'];
        $requestId = (int)$outbox['REQUEST_ID'];
        $attempt = (int)$outbox['ATTEMPTS'] + 1;
        ExportOutboxTable::update($outboxId, ['STATUS' => 'processing', 'ATTEMPTS' => $attempt, 'UPDATED_AT' => new DateTime()]);

        try {
            $request = RequestTable::getByPrimary($requestId)->fetch();
            $result = ResultTable::getList(['filter' => ['=REQUEST_ID' => $requestId], 'limit' => 1])->fetch();
            if (!$request || !$result || !in_array($request['STATUS'], ['approved', 'exporting'], true)) {
                throw new RuntimeException('export_state_invalid');
            }
            RequestTable::update($requestId, ['STATUS' => 'exporting', 'UPDATED_AT' => new DateTime()]);
            $items = RequestItemTable::getList(['filter' => ['=REQUEST_ID' => $requestId], 'order' => ['SORT' => 'ASC']])->fetchAll();

            $client = new Bitrix24Client();
            $originId = (string)$request['PUBLIC_ID'];
            $dealId = $client->findDealByOrigin($originId);
            if ($dealId === null) {
                $dealId = $client->createDeal($this->dealFields($request, $result, $items));
            }
            $rows = $this->productRows($items);
            if ($rows !== []) {
                $client->setProductRows($dealId, $rows);
            }

            $now = new DateTime();
            ExportOutboxTable::update($outboxId, [
                'STATUS' => 'completed',
                'EXTERNAL_ID' => (string)$dealId,
                'LAST_ERROR_CODE' => '',
                'UPDATED_AT' => $now,
            ]);
            RequestTable::update($requestId, ['STATUS' => 'exported', 'UPDATED_AT' => $now]);
            AuditLogTable::add([
                'REQUEST_ID' => $requestId,
                'EVENT' => 'bitrix24.deal_exported',
                'REQUEST_ID_TRACE' => bin2hex(random_bytes(16)),
                'METADATA_JSON' => json_encode(['dealId' => $dealId], JSON_THROW_ON_ERROR),
            ]);
        } catch (\Throwable $error) {
            $final = $attempt >= self::MAX_ATTEMPTS;
            $delayMinutes = min(60, 2 ** min(6, $attempt));
            ExportOutboxTable::update($outboxId, [
                'STATUS' => $final ? 'failed' : 'retry',
                'NEXT_ATTEMPT_AT' => DateTime::createFromTimestamp(time() + $delayMinutes * 60),
                'LAST_ERROR_CODE' => $this->safeErrorCode($error),
                'UPDATED_AT' => new DateTime(),
            ]);
            RequestTable::update($requestId, ['STATUS' => $final ? 'export_failed' : 'approved', 'UPDATED_AT' => new DateTime()]);
        }
    }

    private function dealFields(array $request, array $result, array $items): array
    {
        $opportunity = 0.0;
        foreach ($items as $item) {
            if (is_numeric($item['PRICE']) && is_numeric($item['QUANTITY'])) {
                $opportunity += (float)$item['PRICE'] * (float)$item['QUANTITY'];
            }
        }
        $fields = [
            'title' => $this->plainForCrm('B2B-заявка: ' . (string)$result['COMPANY'], 255),
            'comments' => $this->plainForCrm((string)$result['SUMMARY'], 4_000),
            'currencyId' => 'RUB',
            'sourceId' => 'WEB',
            'sourceDescription' => 'B2B Request Agent',
            'originatorId' => 'SAFEAI_B2B_REQUEST',
            'originId' => (string)$request['PUBLIC_ID'],
            'opened' => false,
        ];
        if ($opportunity > 0) {
            $fields['opportunity'] = round($opportunity, 2);
            $fields['isManualOpportunity'] = false;
        }
        return $fields;
    }

    private function productRows(array $items): array
    {
        $rows = [];
        foreach (array_slice($items, 0, 200) as $index => $item) {
            $quantity = is_numeric($item['QUANTITY']) ? (float)$item['QUANTITY'] : 1.0;
            $price = is_numeric($item['PRICE']) ? (float)$item['PRICE'] : 0.0;
            if ($quantity <= 0 || $price < 0) {
                continue;
            }
            $rows[] = [
                'productName' => $this->plainForCrm((string)$item['NAME'], 500),
                'quantity' => $quantity,
                'price' => $price,
                'measureName' => $this->plainForCrm((string)$item['UNIT'], 30),
                'sort' => ($index + 1) * 10,
            ];
        }
        return $rows;
    }

    private function safeErrorCode(\Throwable $error): string
    {
        $message = $error->getMessage();
        return preg_match('/\A[a-z0-9_]{3,64}\z/D', $message) === 1 ? $message : 'export_failed';
    }

    private function plainForCrm(string $value, int $maxLength): string
    {
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', strip_tags($value)) ?? '';
        $value = str_replace(['[', ']'], ['(', ')'], $value);
        return mb_substr(trim($value), 0, $maxLength);
    }

    private function recoverStaleJobs(): void
    {
        $staleBefore = DateTime::createFromTimestamp(time() - 15 * 60);
        $rows = ExportOutboxTable::getList([
            'select' => ['ID'],
            'filter' => ['=STATUS' => 'processing', '<UPDATED_AT' => $staleBefore],
            'limit' => 20,
        ])->fetchAll();
        foreach ($rows as $row) {
            ExportOutboxTable::update((int)$row['ID'], [
                'STATUS' => 'retry',
                'NEXT_ATTEMPT_AT' => new DateTime(),
                'LAST_ERROR_CODE' => 'stale_job_recovered',
                'UPDATED_AT' => new DateTime(),
            ]);
        }
    }
}

