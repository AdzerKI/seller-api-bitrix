<?php

namespace Masstech\Opotopo\Catalog;

use Masstech\Opotopo\Vendor\Opotopo\SellerApi\Catalog\ItemRecord;
use Masstech\Opotopo\Vendor\Opotopo\SellerApi\Files\FileSource;

final class VariantRecord implements ItemRecord
{
    public const NAME = 'name';

    public const PRICE = 'price';

    public const IMAGE = 'image';

    private const VALUE_SEPARATOR = ', ';

    public function __construct(
        private readonly Page $page,
        private readonly array $offer,
        private readonly float $price,
    ) {}

    public function id(): int
    {
        return (int) $this->offer['ID'];
    }

    public function key(): string
    {
        return (string) $this->id();
    }

    public function value(string $path): mixed
    {
        return match ($path) {
            self::NAME => $this->name(),
            self::PRICE => $this->price,
            self::IMAGE => $this->page->media($this->offer['DETAIL_PICTURE']),
            default => null,
        };
    }

    public function file(mixed $entry): FileSource
    {
        return Media::file((string) $entry);
    }

    private function name(): string
    {
        $values = [];
        foreach ($this->page->scope->offerTreeIds as $propertyId) {
            array_push($values, ...Values::texts($this->offer, $this->offer['PROPERTIES'][$propertyId] ?? null));
        }

        return $values === [] ? $this->offer['NAME'] : implode(self::VALUE_SEPARATOR, $values);
    }
}
