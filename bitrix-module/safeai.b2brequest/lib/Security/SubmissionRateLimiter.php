<?php

declare(strict_types=1);

namespace SafeAi\B2BRequest\Security;

use Bitrix\Main\Application;
use Bitrix\Main\Type\DateTime;
use SafeAi\B2BRequest\SubmissionRateTable;
use RuntimeException;

final class SubmissionRateLimiter
{
    private const WINDOW_SECONDS = 600;
    private const MAX_ATTEMPTS = 5;

    public function consume(string $remoteAddress): void
    {
        if (filter_var($remoteAddress, FILTER_VALIDATE_IP) === false) {
            throw new RuntimeException('Не удалось проверить источник запроса.');
        }
        $secret = (string)getenv('SAFEAI_RATE_LIMIT_SECRET');
        if (strlen($secret) < 32) {
            throw new RuntimeException('Форма временно недоступна.');
        }
        $key = hash_hmac('sha256', $remoteAddress, $secret);
        $connection = Application::getConnection();
        $lockName = 'safeai_rate_' . substr($key, 0, 32);
        $escaped = $connection->getSqlHelper()->forSql($lockName);
        if ((string)$connection->queryScalar("SELECT GET_LOCK('{$escaped}', 1)") !== '1') {
            throw new RuntimeException('Повторите отправку позже.');
        }

        try {
            $now = time();
            $row = SubmissionRateTable::getList(['filter' => ['=KEY_HASH' => $key], 'limit' => 1])->fetch();
            if (!$row) {
                SubmissionRateTable::add([
                    'KEY_HASH' => $key,
                    'ATTEMPTS' => 1,
                    'WINDOW_STARTED_AT' => DateTime::createFromTimestamp($now),
                    'UPDATED_AT' => DateTime::createFromTimestamp($now),
                ]);
                return;
            }

            $windowStart = $row['WINDOW_STARTED_AT'] instanceof DateTime
                ? $row['WINDOW_STARTED_AT']->getTimestamp()
                : 0;
            if ($windowStart + self::WINDOW_SECONDS <= $now) {
                SubmissionRateTable::update((int)$row['ID'], [
                    'ATTEMPTS' => 1,
                    'WINDOW_STARTED_AT' => DateTime::createFromTimestamp($now),
                    'UPDATED_AT' => DateTime::createFromTimestamp($now),
                ]);
                return;
            }
            if ((int)$row['ATTEMPTS'] >= self::MAX_ATTEMPTS) {
                throw new RuntimeException('Слишком много отправок. Повторите позже.');
            }
            SubmissionRateTable::update((int)$row['ID'], [
                'ATTEMPTS' => (int)$row['ATTEMPTS'] + 1,
                'UPDATED_AT' => DateTime::createFromTimestamp($now),
            ]);
        } finally {
            $connection->queryScalar("SELECT RELEASE_LOCK('{$escaped}')");
        }
    }
}

