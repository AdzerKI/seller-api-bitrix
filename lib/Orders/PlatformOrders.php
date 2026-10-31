<?php

namespace Masstech\Opotopo\Orders;

use Bitrix\Catalog\Product\CatalogProvider;
use Bitrix\Main\Application;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Result;
use Bitrix\Sale\Basket;
use Bitrix\Sale\BasketItem;
use Bitrix\Sale\Delivery\Services\EmptyDeliveryService;
use Bitrix\Sale\Delivery\Services\Manager as DeliveryManager;
use Bitrix\Sale\Fuser;
use Bitrix\Sale\Order;
use Bitrix\Sale\OrderStatus;
use Bitrix\Sale\Payment;
use Bitrix\Sale\PaySystem\Manager as PaySystemManager;
use Bitrix\Sale\Shipment;
use Masstech\Opotopo\Catalog\ProductCatalog;
use Masstech\Opotopo\Catalog\ProductRecord;
use Masstech\Opotopo\Catalog\VariantRecord;
use Masstech\Opotopo\Settings;
use Masstech\Opotopo\Sync;
use Masstech\Opotopo\Sync\TableItemStore;
use Masstech\Opotopo\Tables\OrderTable;
use Masstech\Opotopo\Tables\PurchaseTable;
use Masstech\Opotopo\Vendor\Opotopo\SellerApi\SellerApiClient;
use Masstech\Opotopo\Vendor\Opotopo\SellerApi\Webhooks\FulfillmentStatusChanged;
use Masstech\Opotopo\Vendor\Opotopo\SellerApi\Webhooks\PurchaseCreated;
use Masstech\Opotopo\Vendor\Opotopo\SellerApi\Webhooks\PurchaseRevoked;
use Psr\Http\Message\StreamInterface;
use RuntimeException;
use Throwable;

final class PlatformOrders
{
    private const LOCK = Settings::PREFIX.'_fulfillment_';

    private const LOCK_WAIT = 10;

    private const OWN_DELIVERY = 'COURIER';

    private const RECEIVED = 'RECEIVED';

    private const CANCELLED = 'CANCELLED';

    private const PROVIDER_MODULE = 'catalog';

    private const YES = 'Y';

    private const NO = 'N';

    private const LINE_SEPARATOR = "\n";

    private const NAME_SEPARATOR = ' — ';

    public function __construct(
        private readonly SellerApiClient $client,
        private readonly TableItemStore $items,
        private readonly ProductCatalog $catalog,
    ) {}

    public function __invoke(object $event): void
    {
        match (true) {
            $event instanceof PurchaseCreated => $this->purchased($event),
            $event instanceof PurchaseRevoked => $this->revoked($event),
            $event instanceof FulfillmentStatusChanged => $this->changed($event),
        };
    }

    public static function orderId(string $fulfillmentId): ?int
    {
        $row = OrderTable::getRow(['select' => ['ORDER_ID'], 'filter' => ['=FULFILLMENT_ID' => $fulfillmentId]]);

        return $row === null ? null : (int) $row['ORDER_ID'];
    }

    public static function status(int $orderId): ?string
    {
        return OrderTable::getByPrimary($orderId, ['select' => ['STATUS']])->fetch()['STATUS'] ?? null;
    }

    public static function fulfillmentId(int $orderId): ?string
    {
        return OrderTable::getByPrimary($orderId, ['select' => ['FULFILLMENT_ID']])->fetch()['FULFILLMENT_ID'] ?? null;
    }

    public function fulfillment(int $orderId): array
    {
        return $this->client->fulfillment(self::platformId($orderId));
    }

    public function dropOffPoints(int $orderId): array
    {
        return $this->client->dropOffPoints(self::platformId($orderId));
    }

    public function dispatch(int $orderId, array $request): string
    {
        return $this->apply($orderId, $this->client->dispatch(self::platformId($orderId), $request)['status']);
    }

    public function cancel(int $orderId, ?string $reason): string
    {
        return $this->apply($orderId, $this->client->cancel(self::platformId($orderId), $reason)['status']);
    }

    public function document(string $shipmentId, string $type): StreamInterface
    {
        return $this->client->shipmentDocument($shipmentId, $type);
    }

    private static function platformId(int $orderId): string
    {
        return self::fulfillmentId($orderId) ?? throw new RuntimeException('Order '.$orderId.' has no OPOTOPO fulfillment');
    }

    private function purchased(PurchaseCreated $event): void
    {
        if ($event->fulfillmentId === null) {
            return;
        }
        $connection = Application::getConnection();
        if (! $connection->lock(self::LOCK.$event->fulfillmentId, self::LOCK_WAIT)) {
            throw new RuntimeException('Fulfillment '.$event->fulfillmentId.' is locked');
        }
        try {
            if (self::orderId($event->fulfillmentId) === null) {
                $this->create($this->client->fulfillment($event->fulfillmentId));
            }
        } finally {
            $connection->unlock(self::LOCK.$event->fulfillmentId);
        }
    }

    private function create(array $fulfillment): void
    {
        $siteId = Settings::get(Settings::SITE);
        $buyerId = Settings::id(Settings::BUYER);
        $order = Order::create($siteId, $buyerId, $fulfillment['currency']);
        self::check($order->setPersonTypeId(Settings::id(Settings::PERSON_TYPE)));
        self::check($order->setField('EXTERNAL_ORDER', self::YES));
        $basket = Basket::create($siteId);
        $basket->setFUserId(Fuser::getIdByUserId($buyerId));
        $purchases = [];
        foreach ($fulfillment['items'] as $line) {
            $purchases[$line['id']] = $this->line($basket, $line, $fulfillment['currency']);
        }
        self::check($order->setBasket($basket));
        $this->receiver($order, $fulfillment);
        $shipments = [];
        foreach ($basket as $item) {
            $shipment = $shipments[(string) $item->getProvider()] ??= $order->getShipmentCollection()->createItem(
                DeliveryManager::getObjectById(EmptyDeliveryService::getEmptyDeliveryServiceId()),
            );
            self::check($shipment->getShipmentItemCollection()->createItem($item)->setQuantity($item->getQuantity()));
        }
        $deliveryPrice = $fulfillment['method'] === self::OWN_DELIVERY ? (float) ($fulfillment['deliveryPrice'] ?? 0) : 0.0;
        foreach ($shipments as $shipment) {
            self::check($shipment->setField('DELIVERY_NAME', $fulfillment['tariffCarrierName'] ?? self::method($fulfillment['method'])));
        }
        self::check(reset($shipments)->setFields([
            'CUSTOM_PRICE_DELIVERY' => self::YES,
            'BASE_PRICE_DELIVERY' => $deliveryPrice,
            'PRICE_DELIVERY' => $deliveryPrice,
        ]));
        $payment = $order->getPaymentCollection()->createItem(PaySystemManager::getObjectById(Settings::id(Settings::PAY_SYSTEM)));
        self::check($payment->setField('SUM', $order->getPrice()));
        self::check($payment->setPaid(self::YES));
        foreach ($shipments as $shipment) {
            $shipment->setField('DEDUCTED', self::YES);
        }
        $connection = Application::getConnection();
        $connection->startTransaction();
        try {
            self::check($order->save());
            self::check(OrderTable::add(['ORDER_ID' => $order->getId(), 'FULFILLMENT_ID' => $fulfillment['id'], 'STATUS' => $fulfillment['status']]));
            foreach ($purchases as $purchaseId => $item) {
                self::check(PurchaseTable::add(['PURCHASE_ID' => $purchaseId, 'ORDER_ID' => $order->getId(), 'BASKET_ID' => $item->getId()]));
            }
            $connection->commitTransaction();
        } catch (Throwable $failure) {
            $connection->rollbackTransaction();

            throw $failure;
        }
    }

    private function line(Basket $basket, array $line, string $currency): BasketItem
    {
        $record = $this->record($line['advertisementId']);
        $productId = $record === null ? 0 : $this->productId($record, $line['variantName'] ?? null);
        $item = $basket->createItem($productId > 0 ? self::PROVIDER_MODULE : '', $productId);
        $price = $line['amount'] / $line['quantity'];
        self::check($item->setFields([
            'NAME' => implode(self::NAME_SEPARATOR, array_filter([$line['title'] ?? $record?->value(ProductRecord::TITLE), $line['variantName'] ?? null])),
            'QUANTITY' => $line['quantity'],
            'CURRENCY' => $currency,
            'PRICE' => $price,
            'BASE_PRICE' => $price,
            'CUSTOM_PRICE' => self::YES,
        ]));
        if ($productId > 0) {
            self::check($item->setField('PRODUCT_PROVIDER_CLASS', CatalogProvider::class));
        }

        return $item;
    }

    private function record(string $advertisementId): ?ProductRecord
    {
        $key = $this->items->keyOf(Sync::CATALOG, $advertisementId);

        return $key === null ? null : $this->catalog->record((int) $key);
    }

    private function productId(ProductRecord $record, ?string $variant): int
    {
        if ($variant !== null) {
            foreach ($record->value(ProductRecord::VARIANTS) as $offer) {
                if ($offer->value(VariantRecord::NAME) === $variant) {
                    return $offer->id();
                }
            }
        }

        return (int) $record->key();
    }

    private function receiver(Order $order, array $fulfillment): void
    {
        $properties = $order->getPropertyCollection();
        foreach ([
            [$properties->getPayerName(), $fulfillment['recipientName'] ?? null],
            [$properties->getPhone(), $fulfillment['recipientPhone'] ?? null],
            [$properties->getAddress(), $fulfillment['deliveryAddressLine'] ?? $fulfillment['carrierPointAddressLine'] ?? $fulfillment['pickupAddressLine'] ?? null],
            [$properties->getDeliveryLocationZip(), $fulfillment['deliveryPostalCode'] ?? null],
        ] as [$property, $value]) {
            if ($property !== null && $value !== null) {
                self::check($property->setValue($value));
            }
        }
        self::check($order->setField('USER_DESCRIPTION', $fulfillment['deliveryComment'] ?? ''));
        self::check($order->setField('COMMENTS', implode(self::LINE_SEPARATOR, array_filter([
            self::text('order_number', ['#ID#' => $fulfillment['orderId']]),
            self::method($fulfillment['method']),
            $fulfillment['carrierPointName'] ?? $fulfillment['pickupLocationName'] ?? null,
            $fulfillment['carrierPointCity'] ?? null,
        ]))));
    }

    private function revoked(PurchaseRevoked $event): void
    {
        $purchase = PurchaseTable::getByPrimary($event->purchaseId)->fetch();
        $order = $purchase === false ? null : Order::load((int) $purchase['ORDER_ID']);
        $item = $order?->getBasket()->getItemById((int) $purchase['BASKET_ID']);
        if ($item === null || $order->isCanceled()) {
            return;
        }
        $status = $order->getField('STATUS_ID');
        $shipment = $this->shipmentOf($order, $item);
        if ($shipment?->isShipped()) {
            self::check($shipment->setField('DEDUCTED', self::NO));
            self::check($order->save());
        }
        self::check($item->delete());
        $this->repay($order);
        if ($shipment !== null && ! $shipment->getShipmentItemCollection()->isEmpty()) {
            self::check($shipment->setField('DEDUCTED', self::YES));
        }
        self::check($order->setField('STATUS_ID', $status));
        self::check($order->save());
    }

    private function changed(FulfillmentStatusChanged $event): void
    {
        $orderId = self::orderId($event->fulfillmentId);
        if ($orderId !== null) {
            $this->apply($orderId, $event->status);
        }
    }

    private function apply(int $orderId, string $status): string
    {
        if (self::status($orderId) === $status) {
            return $status;
        }
        $order = Order::load($orderId);
        if ($status === self::RECEIVED) {
            self::check($order->setField('STATUS_ID', OrderStatus::getFinalStatus()));
            self::check($order->save());
        } elseif ($status === self::CANCELLED && ! $order->isCanceled()) {
            foreach ($order->getShipmentCollection()->getNotSystemItems() as $shipment) {
                if ($shipment->isShipped()) {
                    self::check($shipment->setField('DEDUCTED', self::NO));
                }
            }
            foreach ($order->getPaymentCollection() as $payment) {
                self::check($payment->setPaid(self::NO));
            }
            self::check($order->save());
            self::check($order->setField('CANCELED', self::YES));
            self::check($order->save());
        }
        self::check(OrderTable::update($orderId, ['STATUS' => $status]));

        return $status;
    }

    private function shipmentOf(Order $order, BasketItem $item): ?Shipment
    {
        foreach ($order->getShipmentCollection()->getNotSystemItems() as $shipment) {
            if ($shipment->getShipmentItemCollection()->getItemByBasketCode($item->getBasketCode()) !== null) {
                return $shipment;
            }
        }

        return null;
    }

    private function repay(Order $order): void
    {
        /** @var Payment $payment */
        foreach ($order->getPaymentCollection() as $payment) {
            if ($payment->isPaid()) {
                self::check($payment->setPaid(self::NO));
            }
            self::check($payment->setField('SUM', $order->getPrice()));
            if ($order->getPrice() > 0) {
                self::check($payment->setPaid(self::YES));
            }
        }
    }

    public static function method(string $method): string
    {
        return self::text('method_'.$method);
    }

    public static function statusLabel(string $status): string
    {
        return self::text('status_'.$status) ?: $status;
    }

    private static function text(string $name, ?array $replace = null): string
    {
        Loc::loadMessages(__FILE__);

        return Settings::message($name, $replace);
    }

    private static function check(Result $result): void
    {
        if (! $result->isSuccess()) {
            throw new RuntimeException(implode('; ', $result->getErrorMessages()));
        }
    }
}
