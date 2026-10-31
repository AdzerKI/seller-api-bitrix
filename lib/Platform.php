<?php

namespace Masstech\Opotopo;

use Bitrix\Main\DI\ServiceLocator;
use Bitrix\Main\Web\HttpClient;
use Masstech\Opotopo\Catalog\ProductCatalog;
use Masstech\Opotopo\Orders\PlatformOrders;
use Masstech\Opotopo\Sync\TableItemStore;
use Masstech\Opotopo\Vendor\Nyholm\Psr7\Factory\Psr17Factory;
use Masstech\Opotopo\Vendor\Opotopo\SellerApi\SellerApiClient;

final class Platform
{
    public const TRANSPORT = Settings::MODULE.'.transport';

    private const TIMEOUT = 60;

    public static function client(): SellerApiClient
    {
        $factory = new Psr17Factory;

        return new SellerApiClient(
            ServiceLocator::getInstance()->get(self::TRANSPORT),
            $factory,
            $factory,
            Settings::config(),
        );
    }

    public static function orders(): PlatformOrders
    {
        return new PlatformOrders(self::client(), new TableItemStore, new ProductCatalog);
    }

    public static function transport(): HttpClient
    {
        return new HttpClient([
            'socketTimeout' => self::TIMEOUT,
            'streamTimeout' => self::TIMEOUT,
            'redirect' => false,
        ]);
    }
}
