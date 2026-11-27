<?php

namespace Masstech\Opotopo\Catalog;

use Bitrix\Catalog\ProductTable;
use Masstech\Opotopo\Vendor\Opotopo\SellerApi\Catalog\ItemRecord;
use Masstech\Opotopo\Vendor\Opotopo\SellerApi\Files\FileSource;

final class ProductRecord implements ItemRecord
{
    public const SKU = 'sku';

    public const TITLE = 'title';

    public const DESCRIPTION = 'description';

    public const PRICE = 'price';

    public const QUANTITY = 'quantity';

    public const UNIT = 'unit';

    public const BARCODE = 'barcode';

    public const BRAND = 'brand';

    public const SHOP_CATEGORY = 'shop_category';

    public const ATTRIBUTES = 'attributes';

    public const COVER = 'cover';

    public const GALLERY = 'gallery';

    public const VARIANTS = 'variants';

    public const WEIGHT = 'weight';

    public const LENGTH = 'length';

    public const WIDTH = 'width';

    public const HEIGHT = 'height';

    private const GRAMS = 1;

    private const MILLIMETERS_IN_CENTIMETER = 10;

    private const SKU_PREFIX = 'bx-';

    private const TRACED = 'Y';

    private const VALUE_SEPARATOR = ', ';

    private ?array $variants = null;

    public function __construct(private readonly Page $page, private readonly int $id) {}

    public function key(): string
    {
        return (string) $this->id;
    }

    public function value(string $path): mixed
    {
        $element = $this->page->element($this->id);
        $scope = $this->page->scope;

        return match ($path) {
            self::SKU => $this->texts($scope->skuId)[0] ?? self::SKU_PREFIX.$this->id,
            self::TITLE => $element['NAME'],
            self::DESCRIPTION => Values::text($element['DETAIL_TEXT'], $element['DETAIL_TEXT_TYPE'])
                ?: Values::text($element['PREVIEW_TEXT'], $element['PREVIEW_TEXT_TYPE']),
            self::PRICE => $this->sku() ? $this->lowestPrice() : $this->page->price($this->id),
            self::QUANTITY => $this->quantity(),
            self::UNIT => $this->unit(),
            self::BARCODE => $this->page->barcode($this->id),
            self::BRAND => $this->texts($scope->brandId)[0] ?? null,
            self::SHOP_CATEGORY => $scope->sectionPath((int) $element['IBLOCK_SECTION_ID']),
            self::ATTRIBUTES => $this->attributes(),
            self::COVER => $this->page->media($element['DETAIL_PICTURE']) ?? $this->page->media($element['PREVIEW_PICTURE']),
            self::GALLERY => array_values(array_filter(array_map(
                fn ($file) => $this->page->media($file),
                $this->page->values($this->id, $scope->galleryId),
            ))),
            self::VARIANTS => $this->variants(),
            self::WEIGHT => $this->size('WEIGHT', self::GRAMS),
            self::LENGTH => $this->size('LENGTH', self::MILLIMETERS_IN_CENTIMETER),
            self::WIDTH => $this->size('WIDTH', self::MILLIMETERS_IN_CENTIMETER),
            self::HEIGHT => $this->size('HEIGHT', self::MILLIMETERS_IN_CENTIMETER),
            default => null,
        };
    }

    public function file(mixed $entry): FileSource
    {
        return Media::file((string) $entry);
    }

    private function sku(): bool
    {
        return (int) $this->page->product($this->id)['TYPE'] === ProductTable::TYPE_SKU;
    }

    private function lowestPrice(): ?float
    {
        $prices = array_map(fn (VariantRecord $variant) => $variant->value(VariantRecord::PRICE), $this->variants());

        return $prices === [] ? null : min($prices);
    }

    private function quantity(): ?float
    {
        $products = $this->sku()
            ? array_map(fn (int $offerId) => $this->page->product($offerId), $this->offerIds())
            : [$this->page->product($this->id)];
        $total = 0.0;
        foreach ($products as $product) {
            if ($product['QUANTITY_TRACE'] !== self::TRACED) {
                return null;
            }
            $total += max(0.0, (float) $product['QUANTITY']);
        }

        return $total;
    }

    private function unit(): ?string
    {
        $codes = array_unique(array_map(
            fn (array $product) => $this->page->scope->measureCode($product['MEASURE']),
            $this->priced(),
        ));

        return count($codes) === 1 ? reset($codes) : null;
    }

    /**
     * @return array<string, string>
     */
    private function attributes(): array
    {
        $attributes = [];
        foreach ($this->page->scope->attributeIds as $propertyId) {
            $texts = $this->texts($propertyId);
            if ($texts !== []) {
                $attributes[$this->page->property($this->id, $propertyId)['NAME']] = implode(self::VALUE_SEPARATOR, $texts);
            }
        }

        return $attributes;
    }

    /**
     * @return list<VariantRecord>
     */
    private function variants(): array
    {
        if ($this->variants === null) {
            $this->variants = [];
            if ($this->sku()) {
                foreach ($this->page->offers($this->id) as $offerId => $offer) {
                    $price = $this->page->price($offerId);
                    if ($price !== null) {
                        $this->variants[] = new VariantRecord($this->page, $offer, $price);
                    }
                }
            }
        }

        return $this->variants;
    }

    private function size(string $field, int $divisor): float
    {
        return max(array_map(static fn (array $product) => (float) $product[$field] / $divisor, $this->priced()));
    }

    /**
     * @return non-empty-list<array<string, mixed>>
     */
    private function priced(): array
    {
        return array_map(fn (VariantRecord $variant) => $this->page->product($variant->id()), $this->variants())
            ?: [$this->page->product($this->id)];
    }

    /**
     * @return list<int>
     */
    private function offerIds(): array
    {
        return array_values(array_filter(
            array_keys($this->page->offers($this->id)),
            fn (int $offerId) => $this->page->hasProduct($offerId),
        ));
    }

    /**
     * @return list<string>
     */
    private function texts(?int $propertyId): array
    {
        return Values::texts($this->page->element($this->id), $this->page->property($this->id, $propertyId));
    }
}
