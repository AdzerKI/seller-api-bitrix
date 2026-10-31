<?php

namespace Masstech\Opotopo\Tables;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Data\Internal\DeleteByFilterTrait;
use Bitrix\Main\ORM\Fields\DatetimeField;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\ORM\Fields\TextField;

final class ItemTable extends DataManager
{
    use DeleteByFilterTrait;

    public static function getTableName(): string
    {
        return 'masstech_opotopo_items';
    }

    public static function getMap(): array
    {
        return [
            (new IntegerField('ID'))->configurePrimary()->configureAutocomplete(),
            (new StringField('CATALOG'))->configureRequired()->configureSize(64),
            (new StringField('MODEL_KEY'))->configureRequired()->configureSize(191),
            (new StringField('SKU'))->configureRequired()->configureSize(64),
            (new StringField('ADVERTISEMENT_ID'))->configureNullable()->configureSize(36),
            (new StringField('FINGERPRINT'))->configureNullable()->configureSize(64),
            (new StringField('ERROR'))->configureNullable()->configureSize(64),
            (new TextField('ERROR_DETAILS'))->configureNullable(),
            (new DatetimeField('SYNCED_AT'))->configureNullable(),
        ];
    }
}
