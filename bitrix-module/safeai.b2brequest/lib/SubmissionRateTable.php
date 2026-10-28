<?php

declare(strict_types=1);

namespace SafeAi\B2BRequest;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields;
use Bitrix\Main\Type\DateTime;

final class SubmissionRateTable extends DataManager
{
    public static function getTableName(): string
    {
        return 'safeai_b2b_submission_rate';
    }

    public static function getMap(): array
    {
        return [
            (new Fields\IntegerField('ID'))->configurePrimary()->configureAutocomplete(),
            (new Fields\StringField('KEY_HASH'))->configureRequired()->configureUnique()->configureSize(64),
            (new Fields\IntegerField('ATTEMPTS'))->configureRequired(),
            (new Fields\DatetimeField('WINDOW_STARTED_AT'))->configureRequired(),
            (new Fields\DatetimeField('UPDATED_AT'))->configureRequired()->configureDefaultValue(static fn() => new DateTime()),
        ];
    }
}

