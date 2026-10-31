<?php

namespace Masstech\Opotopo\Tables;

use Bitrix\Main\ORM\Data\DataManager;

final class Upsert
{
    /**
     * @param  class-string<DataManager>  $table
     * @param  array<string, mixed>  $key
     * @param  array<string, mixed>  $values
     */
    public static function row(string $table, array $key, array $values): void
    {
        $connection = $table::getEntity()->getConnection();
        [$sql] = $connection->getSqlHelper()->prepareMerge($table::getTableName(), array_keys($key), $key + $values, $values);
        $connection->queryExecute($sql);
    }
}
