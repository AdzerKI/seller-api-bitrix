<?php

namespace Masstech\Opotopo\Tables;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Data\Internal\DeleteByFilterTrait;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;

final class DeliveryTable extends DataManager
{
    use DeleteByFilterTrait;

    public static function getTableName(): string
    {
        return 'masstech_opotopo_deliveries';
    }

    public static function getMap(): array
    {
        return [
            (new StringField('WEBHOOK_ID'))->configurePrimary()->configureSize(191),
            (new IntegerField('PROCESSED_AT'))->configureRequired()->configureSize(8),
        ];
    }
}
