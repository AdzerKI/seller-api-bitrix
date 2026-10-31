<?php

namespace Masstech\Opotopo\Catalog;

use Bitrix\Iblock\PropertyTable;
use CIBlockFormatProperties;
use Masstech\Opotopo\Vendor\Opotopo\SellerApi\Catalog\Text;

final class Values
{
    private const HTML = 'html';

    /**
     * @return list<string>
     */
    public static function texts(array $element, ?array $property): array
    {
        $value = $property['VALUE'] ?? null;
        if ($property === null || $property['PROPERTY_TYPE'] === PropertyTable::TYPE_FILE || $value === null || $value === false || $value === '' || $value === []) {
            return [];
        }
        $display = CIBlockFormatProperties::GetDisplayValue($element, $property)['DISPLAY_VALUE'] ?? [];

        return array_values(array_filter(
            array_map(static fn ($entry) => Text::plain((string) $entry), is_array($display) ? $display : [$display]),
            static fn (string $text) => $text !== '',
        ));
    }

    public static function text(?string $value, ?string $type): string
    {
        return $type === self::HTML ? Text::plain((string) $value) : trim((string) $value);
    }
}
