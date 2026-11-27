<?php

namespace Masstech\Opotopo\Sync;

use Bitrix\Main\Type\DateTime;
use DateTimeImmutable;
use Masstech\Opotopo\Tables\ItemTable;
use Masstech\Opotopo\Tables\Upsert;
use Masstech\Opotopo\Vendor\Opotopo\SellerApi\Sync\ItemState;
use Masstech\Opotopo\Vendor\Opotopo\SellerApi\Sync\ItemStore;

final class TableItemStore implements ItemStore
{
    private const PAGE = 1000;

    public function load(string $catalog): array
    {
        $states = [];
        $after = 0;
        do {
            $rows = ItemTable::getList([
                'filter' => ['=CATALOG' => $catalog, '>ID' => $after],
                'order' => ['ID' => 'ASC'],
                'limit' => self::PAGE,
            ])->fetchAll();
            foreach ($rows as $row) {
                $after = (int) $row['ID'];
                $states[$row['MODEL_KEY']] = new ItemState(
                    $row['MODEL_KEY'],
                    $row['SKU'],
                    $row['ADVERTISEMENT_ID'],
                    $row['FINGERPRINT'],
                    $row['ERROR'],
                    $row['ERROR_DETAILS'] === null ? null : json_decode($row['ERROR_DETAILS'], true, flags: JSON_THROW_ON_ERROR),
                    $row['SYNCED_AT'] instanceof DateTime ? new DateTimeImmutable('@'.$row['SYNCED_AT']->getTimestamp()) : null,
                );
            }
        } while (count($rows) === self::PAGE);

        return $states;
    }

    public function save(string $catalog, ItemState $state): void
    {
        Upsert::row(ItemTable::class, ['CATALOG' => $catalog, 'MODEL_KEY' => $state->key], [
            'SKU' => $state->sku,
            'ADVERTISEMENT_ID' => $state->advertisementId,
            'FINGERPRINT' => $state->fingerprint,
            'ERROR' => $state->error,
            'ERROR_DETAILS' => $state->errorDetails === null ? null : json_encode($state->errorDetails, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'SYNCED_AT' => $state->syncedAt === null ? null : DateTime::createFromTimestamp($state->syncedAt->getTimestamp()),
        ]);
    }

    public function delete(string $catalog, array $keys): void
    {
        foreach (array_chunk($keys, self::PAGE) as $chunk) {
            ItemTable::deleteByFilter(['=CATALOG' => $catalog, '@MODEL_KEY' => $chunk]);
        }
    }

    public function keyOf(string $catalog, string $advertisementId): ?string
    {
        return ItemTable::getRow([
            'select' => ['MODEL_KEY'],
            'filter' => ['=ADVERTISEMENT_ID' => $advertisementId, '=CATALOG' => $catalog],
        ])['MODEL_KEY'] ?? null;
    }

    /**
     * @return list<array{MODEL_KEY: string, SKU: string, ERROR: string, ERROR_DETAILS: ?string}>
     */
    public function failures(string $catalog): array
    {
        return ItemTable::getList([
            'select' => ['MODEL_KEY', 'SKU', 'ERROR', 'ERROR_DETAILS'],
            'filter' => ['=CATALOG' => $catalog, '!==ERROR' => null],
            'order' => ['ID' => 'ASC'],
            'limit' => self::LISTED_FAILURES,
        ])->fetchAll();
    }
}
