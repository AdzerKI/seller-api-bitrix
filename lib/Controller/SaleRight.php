<?php

namespace Masstech\Opotopo\Controller;

use Bitrix\Main\Engine\ActionFilter\Base;
use Bitrix\Main\Error;
use Bitrix\Main\Event;
use Bitrix\Main\EventResult;
use Masstech\Opotopo\Admin\OrderBlock;

final class SaleRight extends Base
{
    public function onBeforeAction(Event $event): ?EventResult
    {
        if (OrderBlock::allowed()) {
            return null;
        }
        $this->addError(new Error(OrderBlock::text('access_denied')));

        return new EventResult(EventResult::ERROR, null, null, $this);
    }
}
