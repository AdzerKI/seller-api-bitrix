<?php

namespace Masstech\Opotopo\Tables;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;

final class PurchaseTable extends DataManager
{
    public static function getTableName(): string
    {
        return 'masstech_opotopo_purchases';
    }

    public static function getMap(): array
    {
        return [
            (new StringField('PURCHASE_ID'))->configurePrimary()->configureSize(36),
            (new IntegerField('ORDER_ID'))->configureRequired(),
            (new IntegerField('BASKET_ID'))->configureRequired(),
        ];
    }
}
