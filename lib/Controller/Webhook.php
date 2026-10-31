<?php

namespace Masstech\Opotopo\Controller;

use Bitrix\Main\Engine\ActionFilter\Authentication;
use Bitrix\Main\Engine\ActionFilter\Csrf;
use Bitrix\Main\Engine\ActionFilter\HttpMethod;
use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Engine\UrlManager;
use Bitrix\Main\HttpRequest;
use Bitrix\Main\HttpResponse;
use Masstech\Opotopo\Vendor\Opotopo\SellerApi\Webhooks\WebhookReceiver;
use Masstech\Opotopo\Webhooks\Endpoint;

final class Webhook extends Controller
{
    private const ACTION = 'receive';

    public static function url(): string
    {
        return (string) UrlManager::getInstance()->createByController(new self, self::ACTION, [], true);
    }

    public function configureActions(): array
    {
        return [
            self::ACTION => [
                'prefilters' => [new HttpMethod([HttpMethod::METHOD_POST])],
                '-prefilters' => [Authentication::class, Csrf::class],
            ],
        ];
    }

    public function receiveAction(): HttpResponse
    {
        $headers = [];
        foreach (WebhookReceiver::HEADERS as $header) {
            $headers[$header] = $this->request->getHeader($header);
        }

        return (new HttpResponse)->setStatus((string) Endpoint::receive(HttpRequest::getInput(), $headers));
    }
}
