<?php

namespace Masstech\Opotopo\Controller;

use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Engine\UrlManager;
use Bitrix\Main\Error;
use Bitrix\Main\HttpResponse;
use Masstech\Opotopo\Admin\OrderBlock;
use Masstech\Opotopo\Platform;
use Masstech\Opotopo\Vendor\Opotopo\SellerApi\Orders\DispatchForm;
use Masstech\Opotopo\Vendor\Opotopo\SellerApi\Orders\InvalidPlace;
use Masstech\Opotopo\Vendor\Opotopo\SellerApi\Problem;
use Throwable;

final class Fulfillment extends Controller
{
    private const DOCUMENT = 'document';

    public static function documentUrl(int $orderId, string $type): string
    {
        return (string) UrlManager::getInstance()->createByController(new self, self::DOCUMENT, [
            'orderId' => $orderId,
            'type' => $type,
            'sessid' => bitrix_sessid(),
        ]);
    }

    protected function getDefaultPreFilters(): array
    {
        return [...parent::getDefaultPreFilters(), new SaleRight];
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    public function dispatchAction(int $orderId, array $fields = []): ?array
    {
        return $this->act(static function (int $orderId) use ($fields): string {
            $orders = Platform::orders();

            return $orders->dispatch($orderId, OrderBlock::request($orders->fulfillment($orderId), $fields));
        }, $orderId);
    }

    public function cancelAction(int $orderId, string $reason = ''): ?array
    {
        return $this->act(static fn (int $orderId) => Platform::orders()->cancel($orderId, trim($reason) ?: null), $orderId);
    }

    public function documentAction(int $orderId, string $type): ?HttpResponse
    {
        try {
            $orders = Platform::orders();
            $shipmentId = in_array($type, DispatchForm::DOCUMENTS, true) ? $orders->fulfillment($orderId)['shipmentId'] ?? null : null;
            if ($shipmentId === null) {
                $this->addError(new Error(OrderBlock::text('document_missing')));

                return null;
            }

            return (new HttpResponse)
                ->addHeader('Content-Type', 'application/pdf')
                ->addHeader('Content-Disposition', 'attachment; filename="'.strtolower($type).'-'.$orderId.'.pdf"')
                ->setContent($orders->document($shipmentId, $type)->getContents());
        } catch (Throwable $failure) {
            $this->addError(new Error(Problem::message($failure)));

            return null;
        }
    }

    /**
     * @param  callable(int): string  $action
     */
    private function act(callable $action, int $orderId): ?array
    {
        try {
            return ['status' => $action($orderId)];
        } catch (InvalidPlace $place) {
            $this->addError(new Error(OrderBlock::text('place_incomplete', ['#NUMBER#' => $place->number])));

            return null;
        } catch (Throwable $failure) {
            $this->addError(new Error(OrderBlock::text('refused', ['#ERROR#' => Problem::message($failure)])));

            return null;
        }
    }
}
