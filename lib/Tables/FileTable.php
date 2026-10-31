<?php

namespace Masstech\Opotopo\Tables;

use Bitrix\Main\ORM\Data\DataManager;
use Bitrix\Main\ORM\Data\Internal\DeleteByFilterTrait;
use Bitrix\Main\ORM\Fields\IntegerField;
use Bitrix\Main\ORM\Fields\StringField;
use Bitrix\Main\ORM\Fields\TextField;

final class FileTable extends DataManager
{
    use DeleteByFilterTrait;

    public static function getTableName(): string
    {
        return 'masstech_opotopo_files';
    }

    public static function getMap(): array
    {
        return [
            (new IntegerField('ID'))->configurePrimary()->configureAutocomplete(),
            (new TextField('SOURCE'))->configureRequired(),
            (new StringField('SOURCE_HASH'))->configureRequired()->configureSize(64),
            (new StringField('GALLERY'))->configureRequired()->configureSize(32),
            (new StringField('CATEGORY_ID'))->configureRequired()->configureSize(36),
            (new IntegerField('SIZE'))->configureRequired()->configureSize(8),
            (new IntegerField('MODIFIED_AT'))->configureRequired()->configureSize(8),
            (new StringField('CHECKSUM'))->configureRequired()->configureSize(64),
            (new StringField('FILE_ID'))->configureRequired()->configureSize(36),
        ];
    }
}
