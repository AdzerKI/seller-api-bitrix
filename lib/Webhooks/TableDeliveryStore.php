<?php

namespace Masstech\Opotopo\Webhooks;

use Bitrix\Main\Application;
use Masstech\Opotopo\Settings;
use Masstech\Opotopo\Tables\DeliveryTable;
use Masstech\Opotopo\Tables\Upsert;
use Masstech\Opotopo\Vendor\Opotopo\SellerApi\Webhooks\DeliveryStore;

final class TableDeliveryStore implements DeliveryStore
{
    private const LOCK = Settings::PREFIX.'_webhook_';

    private const TTL = 2 * 24 * 3600;

    public function acquire(string $webhookId): bool
    {
        return Application::getConnection()->lock(self::LOCK.$webhookId);
    }

    public function release(string $webhookId): void
    {
        Application::getConnection()->unlock(self::LOCK.$webhookId);
    }

    public function processed(string $webhookId): bool
    {
        return DeliveryTable::getByPrimary($webhookId, ['select' => ['WEBHOOK_ID']])->fetch() !== false;
    }

    public function markProcessed(string $webhookId): void
    {
        $now = time();
        DeliveryTable::deleteByFilter(['<PROCESSED_AT' => $now - self::TTL]);
        Upsert::row(DeliveryTable::class, ['WEBHOOK_ID' => $webhookId], ['PROCESSED_AT' => $now]);
    }
}
