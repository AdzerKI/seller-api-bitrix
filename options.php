<?php

use Bitrix\Catalog\CatalogIblockTable;
use Bitrix\Catalog\GroupTable;
use Bitrix\Iblock\ElementTable;
use Bitrix\Iblock\PropertyTable;
use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\SiteTable;
use Bitrix\Main\Type\DateTime;
use Bitrix\Sale\Internals\PersonTypeTable;
use Bitrix\Sale\PaySystem\Manager as PaySystemManager;
use Masstech\Opotopo\Controller\Webhook;
use Masstech\Opotopo\Settings;
use Masstech\Opotopo\Sync;
use Masstech\Opotopo\Sync\TableItemStore;
use Masstech\Opotopo\Vendor\Opotopo\SellerApi\Problem;

defined('B_PROLOG_INCLUDED') || exit;

global $APPLICATION;

if ($APPLICATION->GetGroupRight(Settings::MODULE) < 'W') {
    $APPLICATION->AuthForm(Loc::getMessage('ACCESS_DENIED'));
}

Loader::requireModule(Settings::MODULE);
Loader::requireModule('iblock');
Loader::requireModule('catalog');
Loader::requireModule('sale');
Loc::loadMessages(__FILE__);

$iblockId = Settings::id(Settings::IBLOCK);
$choices = static fn (iterable $rows, string $id, string $name): array => array_column(
    array_map(static fn (array $row): array => [$row[$id], '['.$row[$id].'] '.$row[$name]], [...$rows]),
    1,
    0,
);
$properties = $iblockId > 0
    ? $choices(PropertyTable::getList([
        'select' => ['CODE', 'NAME'],
        'filter' => ['=IBLOCK_ID' => $iblockId, '=ACTIVE' => 'Y', '!=CODE' => ''],
        'order' => ['SORT' => 'ASC', 'ID' => 'ASC'],
    ]), 'CODE', 'NAME')
    : [];

$tabs = [
    'platform' => [
        Settings::URL => ['text', Settings::DEFAULT_URL, []],
        Settings::KEY => ['password', '', []],
        Settings::ORGANIZATION => ['text', '', []],
        Settings::PUBLISH => ['checkbox', 'Y', []],
        Settings::WEBHOOK_SECRET => ['password', '', []],
    ],
    'catalog' => [
        Settings::IBLOCK => ['select', '', $choices(CatalogIblockTable::getList([
            'select' => ['IBLOCK_ID', 'NAME' => 'IBLOCK.NAME'],
            'filter' => ['=PRODUCT_IBLOCK_ID' => 0],
        ]), 'IBLOCK_ID', 'NAME')],
        Settings::SKU_PROPERTY => ['select', '', $properties],
        Settings::BRAND_PROPERTY => ['select', '', $properties],
        Settings::GALLERY_PROPERTY => ['select', '', $properties],
        Settings::PRICE_TYPE => ['select', '', $choices(GroupTable::getList([
            'select' => ['ID', 'NAME'],
            'order' => ['SORT' => 'ASC'],
        ]), 'ID', 'NAME')],
    ],
    'orders' => [
        Settings::SITE => ['select', '', $choices(SiteTable::getList([
            'select' => ['LID', 'NAME'],
            'filter' => ['=ACTIVE' => 'Y'],
        ]), 'LID', 'NAME')],
        Settings::PERSON_TYPE => ['select', '', $choices(PersonTypeTable::getList([
            'select' => ['ID', 'NAME'],
            'filter' => ['=ACTIVE' => 'Y', '=ENTITY_REGISTRY_TYPE' => 'ORDER'],
        ]), 'ID', 'NAME')],
        Settings::BUYER => ['text', '', []],
        Settings::PAY_SYSTEM => ['select', '', $choices(PaySystemManager::getList([
            'select' => ['ID', 'NAME'],
            'filter' => [
                '=ACTIVE' => 'Y',
                '=CAN_PRINT_CHECK' => 'N',
                '=ENTITY_REGISTRY_TYPE' => 'ORDER',
                '!=ID' => PaySystemManager::getInnerPaySystemId(),
            ],
        ]), 'ID', 'NAME')],
    ],
];

$request = Application::getInstance()->getContext()->getRequest();
if ($request->isPost() && $request->getPost('save') !== null && check_bitrix_sessid()) {
    foreach ($tabs as $fields) {
        foreach ($fields as $name => [$type]) {
            if ($type === 'password' && (string) $request->getPost($name) === '') {
                continue;
            }
            Settings::set($name, $type === 'checkbox'
                ? ($request->getPost($name) === 'Y' ? 'Y' : 'N')
                : (string) $request->getPost($name));
        }
    }
    LocalRedirect($APPLICATION->GetCurPageParam());
}
if ($request->isPost() && $request->getPost('sync') !== null && check_bitrix_sessid()) {
    Sync::queue();
    LocalRedirect($APPLICATION->GetCurPageParam());
}

$report = Settings::report();
$failures = (new TableItemStore)->failures(Sync::CATALOG);
$names = $failures === [] ? [] : array_column(ElementTable::getList([
    'select' => ['ID', 'NAME'],
    'filter' => ['@ID' => array_map('intval', array_column($failures, 'MODEL_KEY'))],
])->fetchAll(), 'NAME', 'ID');

$control = new CAdminTabControl(Settings::PREFIX, array_map(
    static fn (string $tab): array => ['DIV' => $tab, 'TAB' => Settings::message('TAB_'.$tab)],
    array_keys($tabs),
));
?>
<form method="post" action="<?= htmlspecialcharsbx($APPLICATION->GetCurPageParam()) ?>">
    <?= bitrix_sessid_post() ?>
    <?php $control->Begin(); ?>
    <?php foreach ($tabs as $fields) { ?>
        <?php $control->BeginNextTab(); ?>
        <?php foreach ($fields as $name => [$type, $default, $options]) {
            $value = $type === 'password' ? '' : Settings::get($name, $default);
            $id = Settings::PREFIX.'_'.$name;
            ?>
            <tr>
                <td width="40%"><label for="<?= $id ?>"><?= Settings::message($name) ?></label></td>
                <td width="60%">
                    <?php if ($type === 'select') { ?>
                        <select id="<?= $id ?>" name="<?= $name ?>">
                            <option value=""></option>
                            <?php foreach ($options as $option => $label) { ?>
                                <option value="<?= htmlspecialcharsbx($option) ?>"<?= (string) $option === $value ? ' selected' : '' ?>><?= htmlspecialcharsbx($label) ?></option>
                            <?php } ?>
                        </select>
                    <?php } elseif ($type === 'checkbox') { ?>
                        <input type="checkbox" id="<?= $id ?>" name="<?= $name ?>" value="Y"<?= $value === 'Y' ? ' checked' : '' ?>>
                    <?php } else { ?>
                        <input type="<?= $type ?>" id="<?= $id ?>" name="<?= $name ?>" size="60" value="<?= htmlspecialcharsbx($value) ?>" autocomplete="off">
                    <?php } ?>
                    <?php if ($name === Settings::WEBHOOK_SECRET) { ?>
                        <br><?= Settings::message('webhook_url') ?>: <code><?= htmlspecialcharsbx(Webhook::url()) ?></code>
                    <?php } ?>
                </td>
            </tr>
        <?php } ?>
    <?php } ?>
    <?php $control->Buttons(); ?>
    <input type="submit" name="save" value="<?= Settings::message('save') ?>" class="adm-btn-save">
    <?php $control->End(); ?>
</form>
<h2><?= Settings::message('sync_title') ?></h2>
<?php
if ($report === null) {
    echo '<p>'.Settings::message('sync_never').'</p>';
} elseif (isset($report['error'])) {
    CAdminMessage::ShowMessage(Settings::message('sync_failed', [
        '#DATE#' => DateTime::createFromTimestamp($report['finishedAt'])->toString(),
        '#ERROR#' => htmlspecialcharsbx($report['error']),
    ]));
} else {
    echo '<p>'.Settings::message('sync_report', [
        '#DATE#' => DateTime::createFromTimestamp($report['finishedAt'])->toString(),
        '#CREATED#' => $report['created'],
        '#UPDATED#' => $report['updated'],
        '#SKIPPED#' => $report['skipped'],
        '#ARCHIVED#' => $report['archived'],
        '#FAILED#' => $report['failed'],
    ]).'</p>';
}
?>
<?php if (Settings::configured()) { ?>
    <form method="post" action="<?= htmlspecialcharsbx($APPLICATION->GetCurPageParam()) ?>">
        <?= bitrix_sessid_post() ?>
        <input type="submit" name="sync" value="<?= Settings::message('sync_now') ?>">
    </form>
<?php } ?>
<?php if ($failures !== []) { ?>
    <table class="adm-list-table">
        <tr class="adm-list-table-header">
            <td class="adm-list-table-cell"><?= Settings::message('failure_product') ?></td>
            <td class="adm-list-table-cell"><?= Settings::message('failure_sku') ?></td>
            <td class="adm-list-table-cell"><?= Settings::message('failure_error') ?></td>
            <td class="adm-list-table-cell"><?= Settings::message('failure_details') ?></td>
        </tr>
        <?php foreach ($failures as $failure) {
            $id = (int) $failure['MODEL_KEY'];
            $details = $failure['ERROR_DETAILS'] === null ? null : json_decode($failure['ERROR_DETAILS'], true);
            ?>
            <tr class="adm-list-table-row">
                <td class="adm-list-table-cell">
                    <?php if (isset($names[$id])) { ?>
                        <a href="<?= htmlspecialcharsbx(CIBlock::GetAdminElementEditLink($iblockId, $id)) ?>"><?= htmlspecialcharsbx($names[$id]) ?></a>
                    <?php } else { ?>
                        <?= htmlspecialcharsbx($failure['MODEL_KEY']) ?>
                    <?php } ?>
                </td>
                <td class="adm-list-table-cell"><?= htmlspecialcharsbx($failure['SKU']) ?></td>
                <td class="adm-list-table-cell"><code><?= htmlspecialcharsbx($failure['ERROR']) ?></code></td>
                <td class="adm-list-table-cell"><?= htmlspecialcharsbx(is_array($details) ? Problem::details($details) : '') ?></td>
            </tr>
        <?php } ?>
    </table>
<?php } ?>
