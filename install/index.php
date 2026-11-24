<?php

use Bitrix\Main\Context;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Loader;
use Bitrix\Main\ModuleManager;
use Masstech\Opotopo\Events;
use Masstech\Opotopo\Schema;
use Masstech\Opotopo\Settings;
use Masstech\Opotopo\Sync;

Loc::loadMessages(__FILE__);

class masstech_opotopo extends CModule
{
    private const REQUIRED = [
        'main' => '24.0.0',
        'iblock' => '23.0.0',
        'catalog' => '23.0.0',
        'sale' => '23.0.0',
    ];

    private const STEP = 'step';

    private const CONFIRMED = 2;

    private const SAVE_DATA = 'savedata';

    private const YES = 'Y';

    public $MODULE_ID = 'masstech.opotopo';

    public $MODULE_VERSION;

    public $MODULE_VERSION_DATE;

    public $MODULE_NAME;

    public $MODULE_DESCRIPTION;

    public $PARTNER_NAME;

    public $PARTNER_URI;

    public function __construct()
    {
        $arModuleVersion = [];
        include __DIR__.'/version.php';
        $this->MODULE_VERSION = $arModuleVersion['VERSION'];
        $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];
        $this->MODULE_NAME = Loc::getMessage('MASSTECH_OPOTOPO_MODULE_NAME');
        $this->MODULE_DESCRIPTION = Loc::getMessage('MASSTECH_OPOTOPO_MODULE_DESCRIPTION');
        $this->PARTNER_NAME = Loc::getMessage('MASSTECH_OPOTOPO_PARTNER_NAME');
        $this->PARTNER_URI = 'https://opotopo.com';
    }

    public function DoInstall(): bool
    {
        global $APPLICATION;
        foreach (self::REQUIRED as $module => $version) {
            $installed = ModuleManager::getVersion($module);
            if ($installed === false || version_compare($installed, $version, '<')) {
                $APPLICATION->ThrowException(
                    Loc::getMessage('MASSTECH_OPOTOPO_REQUIRES', ['#MODULE#' => $module, '#VERSION#' => $version]),
                );

                return false;
            }
        }
        ModuleManager::registerModule($this->MODULE_ID);
        Loader::requireModule($this->MODULE_ID);
        Schema::install();
        CopyDirFiles(__DIR__.'/js', $_SERVER['DOCUMENT_ROOT'].'/bitrix/js', true, true);
        Events::register();
        Sync::schedule();

        return true;
    }

    public function DoUninstall(): bool
    {
        global $APPLICATION;
        Loader::requireModule($this->MODULE_ID);
        $request = Context::getCurrent()->getRequest();
        if ((int) $request->get(self::STEP) < self::CONFIRMED) {
            $APPLICATION->IncludeAdminFile($this->MODULE_NAME, __DIR__.'/unstep1.php');
        }
        CAgent::RemoveModuleAgents($this->MODULE_ID);
        Events::unregister();
        DeleteDirFilesEx('/bitrix/js/'.$this->MODULE_ID);
        if ($request->get(self::SAVE_DATA) !== self::YES) {
            Schema::drop();
            Settings::clear();
        }
        ModuleManager::unRegisterModule($this->MODULE_ID);

        return true;
    }
}
