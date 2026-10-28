<?php

declare(strict_types=1);

namespace SafeAi\B2BRequest;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields;
use Bitrix\Main\Type\DateTime;

final class AuditLogTable extends DataManager
{
    public static function getTableName(): string
    {
        return 'safeai_b2b_audit_log';
    }

    public static function getMap(): array
    {
        return [
            (new Fields\IntegerField('ID'))->configurePrimary()->configureAutocomplete(),
            (new Fields\IntegerField('REQUEST_ID'))->configureRequired(),
            (new Fields\StringField('EVENT'))->configureRequired()->configureSize(64),
            (new Fields\IntegerField('ACTOR_ID')),
            (new Fields\StringField('REQUEST_ID_TRACE'))->configureRequired()->configureSize(36),
            (new Fields\TextField('METADATA_JSON')),
            (new Fields\DatetimeField('CREATED_AT'))->configureRequired()->configureDefaultValue(static fn() => new DateTime()),
        ];
    }
}

