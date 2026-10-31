<?php

namespace Masstech\Opotopo\Tables;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;

final class OrderTable extends DataManager
{
    public static function getTableName(): string
    {
        return 'masstech_opotopo_orders';
    }

    public static function getMap(): array
    {
        return [
            (new IntegerField('ORDER_ID'))->configurePrimary(),
            (new StringField('FULFILLMENT_ID'))->configureRequired()->configureSize(36),
            (new StringField('STATUS'))->configureRequired()->configureSize(32),
        ];
    }
}
