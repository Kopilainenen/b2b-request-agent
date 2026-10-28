<?php

declare(strict_types=1);

namespace SafeAi\B2BRequest;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields;
use Bitrix\Main\Type\DateTime;

final class ResultTable extends DataManager
{
    public static function getTableName(): string { return 'safeai_b2b_result'; }

    public static function getMap(): array
    {
        return [
            (new Fields\IntegerField('ID'))->configurePrimary()->configureAutocomplete(),
            (new Fields\IntegerField('REQUEST_ID'))->configureRequired()->configureUnique(),
            (new Fields\StringField('COMPANY'))->configureSize(255),
            (new Fields\StringField('INN'))->configureSize(12),
            (new Fields\StringField('EMAIL'))->configureSize(254),
            (new Fields\StringField('PHONE'))->configureSize(32),
            (new Fields\TextField('SUMMARY'))->configureRequired(),
            (new Fields\FloatField('CONFIDENCE'))->configureRequired(),
            (new Fields\TextField('WARNINGS_JSON'))->configureRequired(),
            (new Fields\DatetimeField('CREATED_AT'))->configureRequired()->configureDefaultValue(static fn() => new DateTime()),
            (new Fields\DatetimeField('UPDATED_AT'))->configureRequired()->configureDefaultValue(static fn() => new DateTime()),
        ];
    }
}

