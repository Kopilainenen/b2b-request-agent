<?php

declare(strict_types=1);

namespace SafeAi\B2BRequest;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields;
use Bitrix\Main\Type\DateTime;

final class ProcessingJobTable extends DataManager
{
    public static function getTableName(): string { return 'safeai_b2b_job'; }

    public static function getMap(): array
    {
        return [
            (new Fields\IntegerField('ID'))->configurePrimary()->configureAutocomplete(),
            (new Fields\IntegerField('REQUEST_ID'))->configureRequired(),
            (new Fields\StringField('JOB_UUID'))->configureRequired()->configureUnique()->configureSize(36),
            (new Fields\StringField('STATUS'))->configureRequired()->configureSize(24),
            (new Fields\DatetimeField('CREATED_AT'))->configureRequired()->configureDefaultValue(static fn() => new DateTime()),
            (new Fields\DatetimeField('COMPLETED_AT')),
        ];
    }
}

