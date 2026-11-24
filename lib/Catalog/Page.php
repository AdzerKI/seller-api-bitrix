<?php

namespace Masstech\Opotopo\Catalog;

use Bitrix\Catalog\PriceTable;
use Bitrix\Catalog\ProductTable;
use Bitrix\Catalog\StoreBarcodeTable;
use CCatalogSku;
use CCurrencyRates;
use CIBlockElement;
use Masstech\Opotopo\Vendor\Opotopo\SellerApi\Currency;

final class Page
{
    private const TYPES = [ProductTable::TYPE_PRODUCT, ProductTable::TYPE_SKU];

    private const ACTIVE = 'Y';

    private const PROPERTY_OPTIONS = ['USE_PROPERTY_ID' => 'Y'];

    /** @var array<int, array<string, mixed>> */
    private array $products = [];

    /** @var array<int, array<int, array<string, mixed>>> */
    private array $offers = [];

    /** @var array<int, float> */
    private array $prices = [];

    /** @var array<int, array<int, array<string, mixed>>> */
    private array $properties = [];

    /** @var array<int, string> */
    private array $barcodes = [];

    /** @var array<int, mixed> */
    private array $media = [];

    /**
     * @param  array<int, array<string, mixed>>  $elements
     */
    public function __construct(public readonly Scope $scope, private readonly array $elements)
    {
        if ($elements === []) {
            return;
        }
        $ids = array_keys($elements);
        $this->loadProducts($ids, self::TYPES);
        $skus = array_keys(array_filter(
            $this->products,
            static fn (array $product) => (int) $product['TYPE'] === ProductTable::TYPE_SKU,
        ));
        if ($skus !== [] && $scope->offersIblockId > 0) {
            $this->offers = CCatalogSku::getOffersList(
                $skus,
                $scope->iblockId,
                ['ACTIVE' => self::ACTIVE],
                ['ID', 'NAME', 'DETAIL_PICTURE'],
                ['ID' => $scope->offerTreeIds],
                self::PROPERTY_OPTIONS,
            ) ?: [];
        }
        $offerIds = array_merge([], ...array_map('array_keys', array_values($this->offers)));
        $this->loadProducts($offerIds, [ProductTable::TYPE_OFFER]);
        $this->loadPrices(array_keys($this->products));
        $this->properties = array_fill_keys($ids, []);
        if ($scope->propertyIds() !== []) {
            CIBlockElement::GetPropertyValuesArray(
                $this->properties,
                $scope->iblockId,
                ['ID' => $ids, 'IBLOCK_ID' => $scope->iblockId],
                ['ID' => $scope->propertyIds()],
                self::PROPERTY_OPTIONS,
            );
        }
        $this->loadBarcodes($ids);
        $files = [];
        foreach ($elements as $id => $element) {
            $files[] = (int) $element['DETAIL_PICTURE'];
            $files[] = (int) $element['PREVIEW_PICTURE'];
            foreach ($this->values($id, $scope->galleryId) as $file) {
                $files[] = (int) $file;
            }
        }
        foreach ($this->offers as $offers) {
            foreach ($offers as $offer) {
                $files[] = (int) $offer['DETAIL_PICTURE'];
            }
        }
        $this->media = Media::load($files);
    }

    /**
     * @return list<int>
     */
    public function products(): array
    {
        return array_values(array_filter(array_keys($this->elements), fn (int $id) => isset($this->products[$id])));
    }

    public function element(int $id): array
    {
        return $this->elements[$id];
    }

    public function product(int $id): array
    {
        return $this->products[$id];
    }

    public function hasProduct(int $id): bool
    {
        return isset($this->products[$id]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function offers(int $productId): array
    {
        return $this->offers[$productId] ?? [];
    }

    public function price(int $id): ?float
    {
        return $this->prices[$id] ?? null;
    }

    public function barcode(int $id): ?string
    {
        return $this->barcodes[$id] ?? null;
    }

    public function property(int $id, ?int $propertyId): ?array
    {
        return $propertyId === null ? null : ($this->properties[$id][$propertyId] ?? null);
    }

    /**
     * @return list<mixed>
     */
    public function values(int $id, ?int $propertyId): array
    {
        $value = $this->property($id, $propertyId)['VALUE'] ?? null;

        return array_values(array_filter(is_array($value) ? $value : [$value], static fn ($entry) => $entry !== null && $entry !== false && $entry !== ''));
    }

    public function media(mixed $fileId): mixed
    {
        return $this->media[(int) $fileId] ?? null;
    }

    /**
     * @param  list<int>  $ids
     * @param  list<int>  $types
     */
    private function loadProducts(array $ids, array $types): void
    {
        if ($ids === []) {
            return;
        }
        $rows = ProductTable::getList([
            'select' => ['ID', 'TYPE', 'QUANTITY', 'QUANTITY_TRACE', 'WEIGHT', 'LENGTH', 'WIDTH', 'HEIGHT'],
            'filter' => ['@ID' => $ids, '@TYPE' => $types],
        ]);
        while ($row = $rows->fetch()) {
            $this->products[(int) $row['ID']] = $row;
        }
    }

    /**
     * @param  list<int>  $ids
     */
    private function loadPrices(array $ids): void
    {
        if ($ids === [] || $this->scope->priceType <= 0) {
            return;
        }
        $from = [];
        $rows = PriceTable::getList([
            'select' => ['PRODUCT_ID', 'PRICE', 'CURRENCY', 'QUANTITY_FROM'],
            'filter' => ['@PRODUCT_ID' => $ids, '=CATALOG_GROUP_ID' => $this->scope->priceType],
        ]);
        while ($row = $rows->fetch()) {
            $id = (int) $row['PRODUCT_ID'];
            $quantity = (int) $row['QUANTITY_FROM'];
            if (isset($from[$id]) && $from[$id] <= $quantity) {
                continue;
            }
            $from[$id] = $quantity;
            $this->prices[$id] = $row['CURRENCY'] === Currency::PLATFORM
                ? (float) $row['PRICE']
                : (float) CCurrencyRates::ConvertCurrency($row['PRICE'], $row['CURRENCY'], Currency::PLATFORM);
        }
    }

    /**
     * @param  list<int>  $ids
     */
    private function loadBarcodes(array $ids): void
    {
        $rows = StoreBarcodeTable::getList([
            'select' => ['PRODUCT_ID', 'BARCODE'],
            'filter' => ['@PRODUCT_ID' => $ids],
            'order' => ['ID' => 'ASC'],
        ]);
        while ($row = $rows->fetch()) {
            $this->barcodes[(int) $row['PRODUCT_ID']] ??= $row['BARCODE'];
        }
    }
}
