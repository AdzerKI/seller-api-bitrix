<?php

namespace Masstech\Opotopo\Admin;

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Type\DateTime;
use Bitrix\Sale\Order;
use Masstech\Opotopo\Controller\Fulfillment;
use Masstech\Opotopo\Orders\PlatformOrders;
use Masstech\Opotopo\Platform;
use Masstech\Opotopo\Settings;
use Masstech\Opotopo\Vendor\Opotopo\SellerApi\Orders\DispatchForm;
use Masstech\Opotopo\Vendor\Opotopo\SellerApi\Problem;
use Throwable;

final class OrderBlock
{
    public const RIGHT = 'U';

    public const SCRIPT = '/bitrix/js/'.Settings::MODULE.'/order.js';

    private const BLOCK = Settings::PREFIX;

    private const DISPATCH = 'DISPATCH';

    private const CANCEL = 'CANCEL';

    private const PLACES = 'places';

    private const MILLIMETERS_IN_CENTIMETER = 10;

    private const INDEX = '__index__';

    private const NUMBER = '__number__';

    private const NAME_SEPARATOR = ' — ';

    public static function blockset(array $args): ?array
    {
        $order = $args['ORDER'] ?? null;
        if (! $order instanceof Order || ! self::allowed() || PlatformOrders::fulfillmentId((int) $order->getId()) === null) {
            return null;
        }

        return [
            'BLOCKSET' => self::BLOCK,
            'getBlocksBrief' => static fn () => [self::BLOCK => ['TITLE' => self::text('title')]],
            'getBlockContent' => static fn (string $code) => $code === self::BLOCK ? self::render((int) $order->getId()) : '',
            'getScripts' => static fn () => '<script src="'.self::SCRIPT.'"></script>',
        ];
    }

    public static function allowed(): bool
    {
        global $APPLICATION;

        return $APPLICATION->GetGroupRight('sale') >= self::RIGHT;
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    public static function request(array $fulfillment, array $fields): array
    {
        return DispatchForm::request(
            $fulfillment,
            $fields,
            array_values((array) ($fields[self::PLACES] ?? [])),
            static fn (float $grams) => $grams,
            static fn (float $millimeters) => $millimeters / self::MILLIMETERS_IN_CENTIMETER,
        );
    }

    private static function render(int $orderId): string
    {
        $orders = Platform::orders();
        try {
            $fulfillment = $orders->fulfillment($orderId);
        } catch (Throwable $failure) {
            return '<p class="adm-info-message-red">'.htmlspecialcharsbx(self::text('read_failed', ['#ERROR#' => Problem::message($failure)])).'</p>';
        }
        $available = $fulfillment['availableActions'];
        $method = $fulfillment['method'];
        $html = '<div data-opotopo-order="'.$orderId.'">'
            .'<p><b>'.htmlspecialcharsbx(PlatformOrders::statusLabel($fulfillment['status'])).'</b> · '.htmlspecialcharsbx(PlatformOrders::method($method)).'</p>';
        if (isset($fulfillment['trackingNumber'])) {
            $html .= '<p>'.htmlspecialcharsbx(implode(' ', array_filter([$fulfillment['carrierName'] ?? null, $fulfillment['trackingNumber']]))).'</p>';
        }
        if (in_array(self::DISPATCH, $available, true) && isset($fulfillment['dispatchDueAt'])) {
            $html .= '<p>'.htmlspecialcharsbx(self::text('dispatch_due', [
                '#DATE#' => DateTime::createFromTimestamp(strtotime($fulfillment['dispatchDueAt']))->toString(),
            ])).'</p>';
        }
        if (isset($fulfillment['shipmentId'])) {
            $html .= '<p>'.implode(' ', array_map(
                static fn (string $type) => '<a class="adm-btn" href="'.htmlspecialcharsbx(Fulfillment::documentUrl($orderId, $type)).'">'
                    .htmlspecialcharsbx(self::text('document_'.$type)).'</a>',
                DispatchForm::DOCUMENTS,
            )).'</p>';
        }
        if (in_array(self::DISPATCH, $available, true)) {
            $html .= match (true) {
                $method === DispatchForm::OWN_DELIVERY => self::tracking($fulfillment),
                in_array($method, DispatchForm::CARRIER, true) => self::carrier($orders, $orderId, $fulfillment),
                default => '',
            };
            $html .= '<p><button type="button" class="adm-btn adm-btn-save" data-opotopo-action="dispatch">'
                .htmlspecialcharsbx(self::text(match (true) {
                    $method === DispatchForm::OWN_DELIVERY => 'dispatch_own',
                    $method === DispatchForm::PICKUP => 'dispatch_pickup',
                    default => 'dispatch_carrier',
                })).'</button></p>';
        }
        if (in_array(self::CANCEL, $available, true)) {
            $html .= '<p><label>'.htmlspecialcharsbx(self::text('cancel_reason')).'<br>'
                .'<textarea name="reason" rows="2" cols="60"></textarea></label></p>'
                .'<p><button type="button" class="adm-btn" data-opotopo-action="cancel">'.htmlspecialcharsbx(self::text('cancel')).'</button></p>';
        }

        return $html.'<p class="adm-info-message-red" data-opotopo-error hidden></p></div>';
    }

    private static function tracking(array $fulfillment): string
    {
        $html = '';
        foreach (array_combine(DispatchForm::TRACKING, [
            ['carrier_name', 'text', false],
            ['tracking_number', 'text', $fulfillment['trackingRequired']],
            ['tracking_url', 'url', false],
        ]) as $name => [$label, $type, $required]) {
            $html .= '<p><label>'.htmlspecialcharsbx(self::text($label)).'<br>'
                .'<input type="'.$type.'" size="40" name="fields['.$name.']"'.($required ? ' required' : '').'></label></p>';
        }

        return $html;
    }

    private static function carrier(PlatformOrders $orders, int $orderId, array $fulfillment): string
    {
        $html = '';
        try {
            $points = $orders->dropOffPoints($orderId);
        } catch (Throwable $failure) {
            $points = [];
            $html .= '<p class="adm-info-message-red">'.htmlspecialcharsbx(self::text('points_failed', ['#ERROR#' => Problem::message($failure)])).'</p>';
        }
        $html .= '<p><label>'.htmlspecialcharsbx(self::text('drop_off_point')).'<br><select name="fields['.DispatchForm::DROP_OFF_POINT.']">'
            .'<option value="">'.htmlspecialcharsbx(self::text('courier_pickup')).'</option>';
        foreach ($points as $point) {
            $html .= '<option value="'.htmlspecialcharsbx($point['externalId']).'">'
                .htmlspecialcharsbx(implode(self::NAME_SEPARATOR, array_filter([$point['name'] ?? null, $point['addressLine'] ?? null]))).'</option>';
        }
        $html .= '</select></label></p>';
        $place = '<fieldset data-opotopo-place><legend>'.htmlspecialcharsbx(self::text('place', ['#NUMBER#' => self::NUMBER])).'</legend><p>';
        foreach (DispatchForm::SIZES as $size) {
            $place .= '<label>'.htmlspecialcharsbx(self::text('size_'.$size)).' <input type="number" min="0" step="any" size="6" name="fields['.self::PLACES.']['
                .self::INDEX.']['.$size.']"></label> ';
        }
        $place .= '</p>';
        foreach ($fulfillment['items'] as $line) {
            $place .= '<p><label>'.htmlspecialcharsbx(implode(self::NAME_SEPARATOR, array_filter([$line['title'] ?? null, $line['variantName'] ?? null])))
                .' <input type="number" min="0" max="'.(int) $line['quantity'].'" size="4" name="fields['.self::PLACES.']['.self::INDEX.']['.DispatchForm::LINES.']['
                .htmlspecialcharsbx($line['id']).']"></label></p>';
        }
        $place .= '</fieldset>';

        return $html.'<div data-opotopo-places><p>'.htmlspecialcharsbx(self::text('places_hint')).'</p>'
            .str_replace([self::INDEX, self::NUMBER], ['0', '1'], $place)
            .'<template>'.$place.'</template></div>'
            .'<p><button type="button" class="adm-btn" data-opotopo-add-place>'.htmlspecialcharsbx(self::text('add_place')).'</button></p>';
    }

    public static function text(string $name, ?array $replace = null): string
    {
        Loc::loadMessages(__FILE__);

        return Settings::message('block_'.$name, $replace);
    }
}
