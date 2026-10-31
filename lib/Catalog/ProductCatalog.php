<?php

namespace Masstech\Opotopo\Catalog;

use Bitrix\Catalog\Product\PropertyCatalogFeature;
use Bitrix\Iblock\ElementTable;
use Bitrix\Iblock\Model\PropertyFeature;
use Bitrix\Iblock\PropertyTable;
use Bitrix\Iblock\SectionTable;
use Masstech\Opotopo\Settings;
use Masstech\Opotopo\Vendor\Opotopo\SellerApi\Catalog\CatalogSource;

final class ProductCatalog implements CatalogSource
{
    private const PAGE = 100;

    private const ACTIVE = 'Y';

    public function mapping(): array
    {
        return [
            'shop_category' => ProductRecord::SHOP_CATEGORY,
            'fields' => [
                'sku' => ProductRecord::SKU,
                'title' => ProductRecord::TITLE,
                'description' => ProductRecord::DESCRIPTION,
                'price' => ProductRecord::PRICE,
                'quantity' => ProductRecord::QUANTITY,
                'barcode' => ProductRecord::BARCODE,
                'brand' => ProductRecord::BRAND,
            ],
            'attributes' => ProductRecord::ATTRIBUTES,
            'images' => ['cover' => ProductRecord::COVER, 'gallery' => ProductRecord::GALLERY],
            'variants' => [
                'each' => ProductRecord::VARIANTS,
                'name' => VariantRecord::NAME,
                'price' => VariantRecord::PRICE,
                'image' => VariantRecord::IMAGE,
            ],
            'parcel' => [
                'weight' => ProductRecord::WEIGHT,
                'length' => ProductRecord::LENGTH,
                'width' => ProductRecord::WIDTH,
                'height' => ProductRecord::HEIGHT,
            ],
        ];
    }

    public function records(): iterable
    {
        $iblockId = Settings::id(Settings::IBLOCK);
        if ($iblockId <= 0) {
            return;
        }
        $scope = $this->scope($iblockId);
        $after = 0;
        do {
            $elements = $this->elements(['=IBLOCK_ID' => $iblockId, '=ACTIVE' => self::ACTIVE, '>ID' => $after], self::PAGE);
            $after = (int) array_key_last($elements);
            $page = new Page($scope, $elements);
            foreach ($page->products() as $id) {
                yield new ProductRecord($page, $id);
            }
        } while (count($elements) === self::PAGE);
    }

    public function record(int $id): ?ProductRecord
    {
        $iblockId = Settings::id(Settings::IBLOCK);
        if ($iblockId <= 0) {
            return null;
        }
        $page = new Page($this->scope($iblockId), $this->elements(['=IBLOCK_ID' => $iblockId, '=ID' => $id], 1));

        return $page->hasProduct($id) ? new ProductRecord($page, $id) : null;
    }

    /**
     * @param  array<string, mixed>  $filter
     * @return array<int, array<string, mixed>>
     */
    private function elements(array $filter, int $limit): array
    {
        $elements = [];
        $rows = ElementTable::getList([
            'select' => [
                'ID', 'NAME', 'IBLOCK_ID', 'IBLOCK_SECTION_ID', 'DETAIL_TEXT', 'DETAIL_TEXT_TYPE',
                'PREVIEW_TEXT', 'PREVIEW_TEXT_TYPE', 'DETAIL_PICTURE', 'PREVIEW_PICTURE',
            ],
            'filter' => $filter,
            'order' => ['ID' => 'ASC'],
            'limit' => $limit,
        ]);
        while ($row = $rows->fetch()) {
            $elements[(int) $row['ID']] = $row;
        }

        return $elements;
    }

    private function scope(int $iblockId): Scope
    {
        $offersIblockId = Settings::offersIblock();
        $own = $this->propertyIds($iblockId, [
            Settings::SKU_PROPERTY => Settings::get(Settings::SKU_PROPERTY),
            Settings::BRAND_PROPERTY => Settings::get(Settings::BRAND_PROPERTY),
            Settings::GALLERY_PROPERTY => Settings::get(Settings::GALLERY_PROPERTY),
        ]);
        $sections = [];
        $rows = SectionTable::getList([
            'select' => ['ID', 'NAME', 'IBLOCK_SECTION_ID'],
            'filter' => ['=IBLOCK_ID' => $iblockId],
        ]);
        while ($row = $rows->fetch()) {
            $sections[(int) $row['ID']] = [$row['NAME'], (int) $row['IBLOCK_SECTION_ID']];
        }

        return new Scope(
            $iblockId,
            $offersIblockId,
            (int) Settings::get(Settings::PRICE_TYPE),
            $own[Settings::SKU_PROPERTY] ?? null,
            $own[Settings::BRAND_PROPERTY] ?? null,
            $own[Settings::GALLERY_PROPERTY] ?? null,
            array_values(array_diff(
                array_map('intval', PropertyFeature::getDetailPageShowPropertyCodes($iblockId) ?? []),
                array_values($own),
            )),
            $offersIblockId > 0 ? array_map('intval', PropertyCatalogFeature::getOfferTreePropertyCodes($offersIblockId) ?? []) : [],
            $sections,
        );
    }

    /**
     * @param  array<string, string>  $codes
     * @return array<string, int>
     */
    private function propertyIds(int $iblockId, array $codes): array
    {
        $codes = array_filter($codes);
        if ($codes === []) {
            return [];
        }
        $ids = [];
        $rows = PropertyTable::getList([
            'select' => ['ID', 'CODE'],
            'filter' => ['=IBLOCK_ID' => $iblockId, '@CODE' => array_values($codes)],
        ]);
        while ($row = $rows->fetch()) {
            $ids[$row['CODE']] = (int) $row['ID'];
        }

        return array_filter(array_map(static fn (string $code) => $ids[$code] ?? null, $codes));
    }
}
