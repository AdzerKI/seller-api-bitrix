<?php

namespace Masstech\Opotopo;

use Bitrix\Main\Application;
use Bitrix\Main\DB\Connection;
use Bitrix\Main\ORM\Data\DataManager;
use Masstech\Opotopo\Tables\DeliveryTable;
use Masstech\Opotopo\Tables\FileTable;
use Masstech\Opotopo\Tables\ItemTable;
use Masstech\Opotopo\Tables\OrderTable;
use Masstech\Opotopo\Tables\PurchaseTable;

final class Schema
{
    /** @var array<class-string<DataManager>, list<array{string, list<string>, ?string}>> */
    private const INDEXES = [
        ItemTable::class => [
            ['catalog_model_key', ['CATALOG', 'MODEL_KEY'], Connection::INDEX_UNIQUE],
            ['advertisement_id', ['ADVERTISEMENT_ID'], null],
        ],
        FileTable::class => [
            ['target', ['SOURCE_HASH', 'GALLERY', 'CATEGORY_ID'], Connection::INDEX_UNIQUE],
            ['file_id', ['FILE_ID'], Connection::INDEX_UNIQUE],
        ],
        DeliveryTable::class => [
            ['processed_at', ['PROCESSED_AT'], null],
        ],
        OrderTable::class => [
            ['fulfillment_id', ['FULFILLMENT_ID'], Connection::INDEX_UNIQUE],
        ],
        PurchaseTable::class => [
            ['basket_id', ['BASKET_ID'], Connection::INDEX_UNIQUE],
        ],
    ];

    public static function install(): void
    {
        $connection = Application::getConnection();
        foreach (self::INDEXES as $table => $indexes) {
            $name = $table::getTableName();
            if ($connection->isTableExists($name)) {
                continue;
            }
            $table::getEntity()->createDbTable();
            foreach ($indexes as [$index, $columns, $type]) {
                $connection->createIndex($name, $name.'_'.$index, $columns, null, $type);
            }
        }
    }

    public static function drop(): void
    {
        $connection = Application::getConnection();
        foreach (array_keys(self::INDEXES) as $table) {
            $connection->dropTable($table::getTableName());
        }
    }
}
