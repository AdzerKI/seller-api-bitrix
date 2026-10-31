<?php

namespace Masstech\Opotopo;

use Bitrix\Catalog\PriceTable;
use Bitrix\Main\Event;
use Bitrix\Main\EventManager;
use CIBlockElement;
use Masstech\Opotopo\Admin\OrderBlock;

final class Events
{
    private const COMPATIBLE = [
        ['iblock', 'OnAfterIBlockElementAdd', 'element'],
        ['iblock', 'OnAfterIBlockElementUpdate', 'element'],
        ['iblock', 'OnAfterIBlockElementDelete', 'element'],
        ['iblock', 'OnAfterIBlockElementSetPropertyValues', 'properties'],
        ['iblock', 'OnAfterIBlockElementSetPropertyValuesEx', 'properties'],
        ['main', 'OnAdminSaleOrderViewDraggable', 'orderBlock'],
    ];

    private const MODEL = [
        ['catalog', 'Bitrix\Catalog\Model\Product::OnAfterAdd', 'product'],
        ['catalog', 'Bitrix\Catalog\Model\Product::OnAfterUpdate', 'product'],
        ['catalog', 'Bitrix\Catalog\Model\Price::OnAfterAdd', 'price'],
        ['catalog', 'Bitrix\Catalog\Model\Price::OnAfterUpdate', 'price'],
        ['catalog', 'Bitrix\Catalog\Model\Price::OnDelete', 'price'],
    ];

    public static function register(): void
    {
        $events = EventManager::getInstance();
        foreach (self::COMPATIBLE as [$module, $event, $method]) {
            $events->registerEventHandlerCompatible($module, $event, Settings::MODULE, self::class, $method);
        }
        foreach (self::MODEL as [$module, $event, $method]) {
            $events->registerEventHandler($module, $event, Settings::MODULE, self::class, $method);
        }
    }

    public static function unregister(): void
    {
        $events = EventManager::getInstance();
        foreach ([...self::COMPATIBLE, ...self::MODEL] as [$module, $event, $method]) {
            $events->unRegisterEventHandler($module, $event, Settings::MODULE, self::class, $method);
        }
    }

    public static function element(array $fields): void
    {
        self::queueFor((int) ($fields['IBLOCK_ID'] ?? 0));
    }

    public static function properties(mixed $elementId, mixed $iblockId): void
    {
        self::queueFor((int) $iblockId);
    }

    public static function product(Event $event): void
    {
        self::queueFor((int) CIBlockElement::GetIBlockByID((int) $event->getParameter('id')));
    }

    public static function price(Event $event): void
    {
        $price = PriceTable::getRow([
            'select' => ['PRODUCT_ID', 'CATALOG_GROUP_ID'],
            'filter' => ['=ID' => (int) $event->getParameter('id')],
        ]);
        if ($price !== null && (int) $price['CATALOG_GROUP_ID'] === Settings::id(Settings::PRICE_TYPE)) {
            self::queueFor((int) CIBlockElement::GetIBlockByID((int) $price['PRODUCT_ID']));
        }
    }

    public static function orderBlock(array $args): ?array
    {
        return OrderBlock::blockset($args);
    }

    private static function queueFor(int $iblockId): void
    {
        if ($iblockId > 0 && Settings::configured() && in_array($iblockId, [Settings::id(Settings::IBLOCK), Settings::offersIblock()], true)) {
            Sync::queue();
        }
    }
}
