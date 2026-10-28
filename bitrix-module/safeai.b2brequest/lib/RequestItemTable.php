<?php

declare(strict_types=1);

namespace SafeAi\B2BRequest;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields;

final class RequestItemTable extends DataManager
{
    public static function getTableName(): string { return 'safeai_b2b_request_item'; }

    public static function getMap(): array
    {
        return [
            (new Fields\IntegerField('ID'))->configurePrimary()->configureAutocomplete(),
            (new Fields\IntegerField('REQUEST_ID'))->configureRequired(),
            (new Fields\IntegerField('SORT'))->configureRequired(),
            (new Fields\StringField('NAME'))->configureRequired()->configureSize(500),
            (new Fields\StringField('QUANTITY'))->configureSize(50),
            (new Fields\StringField('UNIT'))->configureSize(30),
            (new Fields\StringField('PRICE'))->configureSize(50),
        ];
    }
}

