<?php

namespace Masstech\Opotopo\Catalog;

use Bitrix\Main\Application;
use Bitrix\Main\Config\Option;
use Bitrix\Main\FileTable;
use CFile;
use Bitrix\Main\Web\MimeType;
use Masstech\Opotopo\Vendor\Opotopo\SellerApi\Files\LocalFile;

final class Media
{
    private const UPLOAD_DIR = 'upload';

    private const SCHEME = 'https:';

    /**
     * @param  list<int>  $ids
     * @return array<int, string|LocalFile>
     */
    public static function load(array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids)));
        if ($ids === []) {
            return [];
        }
        $root = Application::getDocumentRoot().'/'.Option::get('main', 'upload_dir', self::UPLOAD_DIR);
        $entries = [];
        $files = FileTable::getList([
            'select' => ['ID', 'SUBDIR', 'FILE_NAME', 'ORIGINAL_NAME', 'CONTENT_TYPE', 'HANDLER_ID'],
            'filter' => ['@ID' => $ids],
        ]);
        while ($file = $files->fetch()) {
            $entries[(int) $file['ID']] = (string) $file['HANDLER_ID'] !== ''
                ? self::url(CFile::GetFileSRC($file))
                : new LocalFile(
                    $root.'/'.$file['SUBDIR'].'/'.$file['FILE_NAME'],
                    $file['ORIGINAL_NAME'] ?: $file['FILE_NAME'],
                    $file['CONTENT_TYPE'],
                );
        }

        return $entries;
    }

    public static function file(string $path): LocalFile
    {
        return new LocalFile($path, basename($path), MimeType::getByFileExtension(pathinfo($path, PATHINFO_EXTENSION)));
    }

    private static function url(string $src): string
    {
        return str_starts_with($src, '//') ? self::SCHEME.$src : $src;
    }
}
