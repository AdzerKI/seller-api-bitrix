<?php

namespace Masstech\Opotopo\Files;

use Masstech\Opotopo\Tables\FileTable;
use Masstech\Opotopo\Tables\Upsert;
use Masstech\Opotopo\Vendor\Opotopo\SellerApi\Files\FileStore;
use Masstech\Opotopo\Vendor\Opotopo\SellerApi\Files\FileTarget;
use Masstech\Opotopo\Vendor\Opotopo\SellerApi\Files\StoredFile;

final class TableFileStore implements FileStore
{
    private const PAGE = 1000;

    public function find(FileTarget $target): ?StoredFile
    {
        $row = FileTable::getRow([
            'select' => ['SOURCE', 'SIZE', 'MODIFIED_AT', 'CHECKSUM', 'FILE_ID'],
            'filter' => ['=SOURCE_HASH' => $target->sourceHash, '=GALLERY' => $target->gallery, '=CATEGORY_ID' => $target->categoryId],
        ]);

        return $row === null
            ? null
            : new StoredFile($row['SOURCE'], (int) $row['SIZE'], (int) $row['MODIFIED_AT'], $row['CHECKSUM'], $row['FILE_ID']);
    }

    public function save(FileTarget $target, StoredFile $file): void
    {
        Upsert::row(FileTable::class, [
            'SOURCE_HASH' => $target->sourceHash,
            'GALLERY' => $target->gallery,
            'CATEGORY_ID' => $target->categoryId,
        ], [
            'SOURCE' => $file->source,
            'SIZE' => $file->size,
            'MODIFIED_AT' => $file->modifiedAt,
            'CHECKSUM' => $file->checksum,
            'FILE_ID' => $file->fileId,
        ]);
    }

    public function forget(array $fileIds): void
    {
        foreach (array_chunk($fileIds, self::PAGE) as $chunk) {
            FileTable::deleteByFilter(['@FILE_ID' => $chunk]);
        }
    }
}
