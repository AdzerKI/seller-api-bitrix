<?php

namespace Masstech\Opotopo\Webhooks;

use Bitrix\Main\Application;
use Masstech\Opotopo\Platform;
use Masstech\Opotopo\Settings;
use Masstech\Opotopo\Vendor\Opotopo\SellerApi\Webhooks\WebhookReceiver;
use Throwable;

final class Endpoint
{
    private const NOT_FOUND = 404;

    private const FAILED = 500;

    /**
     * @param  array<string, ?string>  $headers
     */
    public static function receive(string $body, array $headers): int
    {
        $secret = Settings::get(Settings::WEBHOOK_SECRET);
        if ($secret === '') {
            return self::NOT_FOUND;
        }
        try {
            return (new WebhookReceiver($secret, new TableDeliveryStore))->receive($body, $headers, Platform::orders())->status();
        } catch (Throwable $failure) {
            Application::getInstance()->getExceptionHandler()->writeToLog($failure);

            return self::FAILED;
        }
    }
}
