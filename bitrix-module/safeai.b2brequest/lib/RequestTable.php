<?php

declare(strict_types=1);

namespace SafeAi\B2BRequest;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields;
use Bitrix\Main\Type\DateTime;

final class RequestTable extends DataManager
{
    public static function getTableName(): string
    {
        return 'safeai_b2b_request';
    }

    public static function getMap(): array
    {
        return [
            (new Fields\IntegerField('ID'))->configurePrimary()->configureAutocomplete(),
            (new Fields\StringField('PUBLIC_ID'))->configureRequired()->configureUnique()->configureSize(36),
            (new Fields\StringField('STATUS'))->configureRequired()->configureSize(24),
            (new Fields\StringField('COMPANY'))->configureRequired()->configureSize(255),
            (new Fields\StringField('CONTACT_NAME'))->configureRequired()->configureSize(255),
            (new Fields\StringField('EMAIL'))->configureRequired()->configureSize(254),
            (new Fields\StringField('PHONE'))->configureSize(32),
            (new Fields\TextField('COMMENT')),
            (new Fields\StringField('STORAGE_NAME'))->configureRequired()->configureSize(80),
            (new Fields\StringField('ORIGINAL_NAME'))->configureRequired()->configureSize(255),
            (new Fields\StringField('MEDIA_TYPE'))->configureRequired()->configureSize(100),
            (new Fields\IntegerField('FILE_SIZE'))->configureRequired(),
            (new Fields\StringField('SHA256'))->configureRequired()->configureSize(64),
            (new Fields\IntegerField('REVIEWED_BY')),
            (new Fields\DatetimeField('CREATED_AT'))->configureRequired()->configureDefaultValue(static fn() => new DateTime()),
            (new Fields\DatetimeField('UPDATED_AT'))->configureRequired()->configureDefaultValue(static fn() => new DateTime()),
        ];
    }
}

