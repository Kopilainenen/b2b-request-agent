<?php

declare(strict_types=1);

namespace SafeAi\B2BRequest;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields;
use Bitrix\Main\Type\DateTime;

final class ExportOutboxTable extends DataManager
{
    public static function getTableName(): string
    {
        return 'safeai_b2b_export_outbox';
    }

    public static function getMap(): array
    {
        return [
            (new Fields\IntegerField('ID'))->configurePrimary()->configureAutocomplete(),
            (new Fields\IntegerField('REQUEST_ID'))->configureRequired()->configureUnique(),
            (new Fields\StringField('STATUS'))->configureRequired()->configureSize(24),
            (new Fields\IntegerField('ATTEMPTS'))->configureRequired()->configureDefaultValue(0),
            (new Fields\DatetimeField('NEXT_ATTEMPT_AT'))->configureRequired()->configureDefaultValue(static fn() => new DateTime()),
            (new Fields\StringField('LAST_ERROR_CODE'))->configureSize(64),
            (new Fields\StringField('EXTERNAL_ID'))->configureSize(64),
            (new Fields\DatetimeField('CREATED_AT'))->configureRequired()->configureDefaultValue(static fn() => new DateTime()),
            (new Fields\DatetimeField('UPDATED_AT'))->configureRequired()->configureDefaultValue(static fn() => new DateTime()),
        ];
    }
}

