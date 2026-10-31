<?php

namespace Masstech\Opotopo\Catalog;

final class Scope
{
    /**
     * @param  list<int>  $attributeIds
     * @param  list<int>  $offerTreeIds
     * @param  array<int, array{string, int}>  $sections
     */
    public function __construct(
        public readonly int $iblockId,
        public readonly int $offersIblockId,
        public readonly int $priceType,
        public readonly ?int $skuId,
        public readonly ?int $brandId,
        public readonly ?int $galleryId,
        public readonly array $attributeIds,
        public readonly array $offerTreeIds,
        private readonly array $sections,
    ) {}

    /**
     * @return list<int>
     */
    public function propertyIds(): array
    {
        return array_values(array_unique(array_filter([$this->skuId, $this->brandId, $this->galleryId, ...$this->attributeIds])));
    }

    /**
     * @return list<string>
     */
    public function sectionPath(int $sectionId): array
    {
        $path = [];
        while (isset($this->sections[$sectionId]) && ! isset($path[$sectionId])) {
            [$name, $parent] = $this->sections[$sectionId];
            $path[$sectionId] = $name;
            $sectionId = $parent;
        }

        return array_reverse(array_values($path));
    }
}
