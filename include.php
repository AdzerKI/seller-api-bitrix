<?php

use Bitrix\Main\DI\ServiceLocator;
use Masstech\Opotopo\Platform;

defined('B_PROLOG_INCLUDED') || exit;

require_once __DIR__.'/vendor-prefixed/vendor/autoload.php';
require_once __DIR__.'/vendor/autoload.php';

ServiceLocator::getInstance()->addInstanceLazy(Platform::TRANSPORT, ['constructor' => static fn () => Platform::transport()]);
