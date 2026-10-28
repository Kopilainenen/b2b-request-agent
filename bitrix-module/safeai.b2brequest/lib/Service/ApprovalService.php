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

final class ApprovalService
{
    public function approve(int $requestId, array $input, int $actorId): void
    {
        $request = RequestTable::getByPrimary($requestId)->fetch();
        $result = ResultTable::getList(['filter' => ['=REQUEST_ID' => $requestId], 'limit' => 1])->fetch();
        if (!$request || !$result || $request['STATUS'] !== 'review') {
            throw new RuntimeException('Заявка не находится на ручной проверке.');
        }

        $company = $this->text($input['company'] ?? '', 255);
        $summary = $this->text($input['summary'] ?? '', 4_000);
        $inn = trim((string)($input['inn'] ?? ''));
        $email = trim((string)($input['email'] ?? ''));
        $phone = trim((string)($input['phone'] ?? ''));
        if ($company === '' || $summary === '' || preg_match('/\A(?:\d{10}|\d{12})\z/D', $inn) !== 1) {
            throw new RuntimeException('Проверьте компанию, ИНН и описание заявки.');
        }
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('Некорректный email.');
        }
        if ($phone !== '' && preg_match('/\A\+?\d{7,15}\z/D', $phone) !== 1) {
            throw new RuntimeException('Некорректный телефон.');
        }

        $connection = Application::getConnection();
        $connection->startTransaction();
        try {
            ResultTable::update((int)$result['ID'], [
                'COMPANY' => $company,
                'INN' => $inn,
                'EMAIL' => $email,
                'PHONE' => $phone,
                'SUMMARY' => $summary,
                'UPDATED_AT' => new DateTime(),
            ]);
            $this->replaceItems($requestId, $input['items'] ?? []);
            RequestTable::update($requestId, [
                'STATUS' => 'approved',
                'REVIEWED_BY' => $actorId,
                'UPDATED_AT' => new DateTime(),
            ]);
            $queued = ExportOutboxTable::add([
                'REQUEST_ID' => $requestId,
                'STATUS' => 'pending',
                'ATTEMPTS' => 0,
                'NEXT_ATTEMPT_AT' => new DateTime(),
            ]);
            if (!$queued->isSuccess()) {
                throw new RuntimeException(implode('; ', $queued->getErrorMessages()));
            }
            AuditLogTable::add([
                'REQUEST_ID' => $requestId,
                'EVENT' => 'request.approved_by_manager',
                'ACTOR_ID' => $actorId,
                'REQUEST_ID_TRACE' => bin2hex(random_bytes(16)),
                'METADATA_JSON' => '{}',
            ]);
            $connection->commitTransaction();
        } catch (\Throwable $error) {
            $connection->rollbackTransaction();
            throw $error;
        }
    }

    public function reject(int $requestId, int $actorId): void
    {
        $request = RequestTable::getByPrimary($requestId)->fetch();
        if (!$request || $request['STATUS'] !== 'review') {
            throw new RuntimeException('Заявка не находится на ручной проверке.');
        }
        RequestTable::update($requestId, ['STATUS' => 'rejected', 'REVIEWED_BY' => $actorId, 'UPDATED_AT' => new DateTime()]);
        AuditLogTable::add([
            'REQUEST_ID' => $requestId,
            'EVENT' => 'request.rejected_by_manager',
            'ACTOR_ID' => $actorId,
            'REQUEST_ID_TRACE' => bin2hex(random_bytes(16)),
            'METADATA_JSON' => '{}',
        ]);
    }

    private function text(mixed $value, int $max): string
    {
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', strip_tags((string)$value)) ?? '';
        return mb_substr(trim($text), 0, $max);
    }

    private function replaceItems(int $requestId, mixed $input): void
    {
        $validated = [];
        foreach (array_slice(is_array($input) ? $input : [], 0, 200) as $row) {
            if (!is_array($row)) {
                continue;
            }
            $name = $this->text($row['name'] ?? '', 500);
            if ($name === '') {
                continue;
            }
            $quantity = $this->decimal($row['quantity'] ?? '', false);
            $price = $this->decimal($row['price'] ?? '', true);
            $validated[] = [
                'name' => $name,
                'quantity' => $quantity === '' ? '1' : $quantity,
                'unit' => $this->text($row['unit'] ?? '', 30),
                'price' => $price,
            ];
        }

        $existing = RequestItemTable::getList(['select' => ['ID'], 'filter' => ['=REQUEST_ID' => $requestId]])->fetchAll();
        foreach ($existing as $row) {
            RequestItemTable::delete((int)$row['ID']);
        }
        foreach ($validated as $index => $row) {
            $added = RequestItemTable::add([
                'REQUEST_ID' => $requestId,
                'SORT' => ($index + 1) * 10,
                'NAME' => $row['name'],
                'QUANTITY' => $row['quantity'],
                'UNIT' => $row['unit'],
                'PRICE' => $row['price'],
            ]);
            if (!$added->isSuccess()) {
                throw new RuntimeException(implode('; ', $added->getErrorMessages()));
            }
        }
    }

    private function decimal(mixed $value, bool $allowZero): string
    {
        $value = str_replace(',', '.', trim((string)$value));
        if ($value === '') {
            return '';
        }
        if (preg_match('/\A\d{1,12}(?:\.\d{1,4})?\z/D', $value) !== 1) {
            throw new RuntimeException('Количество или цена имеют неверный формат.');
        }
        if (!$allowZero && (float)$value <= 0) {
            throw new RuntimeException('Количество должно быть больше нуля.');
        }
        return $value;
    }
}

