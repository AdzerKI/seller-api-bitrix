<?php

namespace Masstech\Opotopo;

use Bitrix\Main\Config\Option;
use Bitrix\Main\Localization\Loc;
use CCatalogSku;
use Masstech\Opotopo\Vendor\Opotopo\SellerApi\ClientConfig;

final class Settings
{
    public const MODULE = 'masstech.opotopo';

    public const PREFIX = 'masstech_opotopo';

    public const URL = 'url';

    public const KEY = 'key';

    public const ORGANIZATION = 'organization';

    public const PUBLISH = 'publish';

    public const WEBHOOK_SECRET = 'webhook_secret';

    public const IBLOCK = 'iblock_id';

    public const SKU_PROPERTY = 'sku_property';

    public const BRAND_PROPERTY = 'brand_property';

    public const GALLERY_PROPERTY = 'gallery_property';

    public const PRICE_TYPE = 'price_type';

    public const SITE = 'site_id';

    public const PERSON_TYPE = 'person_type_id';

    public const BUYER = 'buyer_id';

    public const PAY_SYSTEM = 'pay_system_id';

    public const REPORT = 'report';

    public const DEFAULT_URL = 'https://api.opotopo.com';

    private const YES = 'Y';

    public static function configured(): bool
    {
        return self::get(self::KEY) !== '';
    }

    public static function config(): ClientConfig
    {
        $organization = self::get(self::ORGANIZATION);

        return new ClientConfig(
            rtrim(self::get(self::URL, self::DEFAULT_URL), '/') ?: self::DEFAULT_URL,
            self::get(self::KEY),
            $organization === '' ? null : $organization,
            self::get(self::PUBLISH, self::YES) === self::YES,
        );
    }

    public static function get(string $name, string $default = ''): string
    {
        return trim(Option::get(self::MODULE, $name, $default));
    }

    public static function id(string $name): int
    {
        return (int) self::get($name);
    }

    public static function set(string $name, string $value): void
    {
        Option::set(self::MODULE, $name, trim($value));
    }

    public static function report(): ?array
    {
        $report = json_decode(self::get(self::REPORT), true);

        return is_array($report) ? $report : null;
    }

    public static function saveReport(array $report): void
    {
        Option::set(self::MODULE, self::REPORT, json_encode($report, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    public static function offersIblock(): int
    {
        $iblockId = self::id(self::IBLOCK);

        $offers = $iblockId > 0 ? CCatalogSku::GetInfoByProductIBlock($iblockId) : false;

        return is_array($offers) ? (int) $offers['IBLOCK_ID'] : 0;
    }

    public static function message(string $name, ?array $replace = null): string
    {
        return (string) Loc::getMessage(strtoupper(self::PREFIX.'_'.$name), $replace);
    }

    public static function clear(): void
    {
        Option::delete(self::MODULE);
    }
}
