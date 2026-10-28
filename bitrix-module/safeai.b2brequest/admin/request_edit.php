<?php

declare(strict_types=1);

use Bitrix\Main\Loader;
use SafeAi\B2BRequest\RequestItemTable;
use SafeAi\B2BRequest\RequestTable;
use SafeAi\B2BRequest\ResultTable;
use SafeAi\B2BRequest\ExportOutboxTable;

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';

if (!$USER->IsAdmin() || !Loader::includeModule('safeai.b2brequest')) {
    $APPLICATION->AuthForm('Доступ запрещен');
}
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$request = $id ? RequestTable::getByPrimary($id)->fetch() : false;
$result = $request ? ResultTable::getList(['filter' => ['=REQUEST_ID' => $id], 'limit' => 1])->fetch() : false;
if (!$request) {
    require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';
    ShowError('Заявка не найдена.');
    require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
    return;
}
$items = $result ? RequestItemTable::getList(['filter' => ['=REQUEST_ID' => $id], 'order' => ['SORT' => 'ASC']])->fetchAll() : [];
$warnings = $result ? json_decode((string)$result['WARNINGS_JSON'], true) : [];
$outbox = $request ? ExportOutboxTable::getList(['filter' => ['=REQUEST_ID' => $id], 'limit' => 1])->fetch() : false;
$APPLICATION->SetTitle('B2B-заявка #' . (int)$id);
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';
?>
<p><a href="safeai_b2b_requests.php?lang=<?= urlencode(LANGUAGE_ID) ?>">← К списку</a></p>
<table class="adm-detail-content-table edit-table">
    <tr><td class="adm-detail-content-cell-l">Статус</td><td><?= htmlspecialcharsbx((string)$request['STATUS']) ?></td></tr>
    <tr><td class="adm-detail-content-cell-l">Исходная компания</td><td><?= htmlspecialcharsbx((string)$request['COMPANY']) ?></td></tr>
    <tr><td class="adm-detail-content-cell-l">Исходный контакт</td><td><?= htmlspecialcharsbx((string)$request['CONTACT_NAME']) ?>, <?= htmlspecialcharsbx((string)$request['EMAIL']) ?></td></tr>
    <tr><td class="adm-detail-content-cell-l">Документ</td><td><a href="safeai_b2b_download.php?id=<?= (int)$id ?>&amp;<?= bitrix_sessid_get() ?>"><?= htmlspecialcharsbx((string)$request['ORIGINAL_NAME']) ?></a></td></tr>
    <?php if ($outbox): ?>
        <tr><td class="adm-detail-content-cell-l">Экспорт в Битрикс24</td><td>
            <?= htmlspecialcharsbx((string)$outbox['STATUS']) ?>,
            попыток: <?= (int)$outbox['ATTEMPTS'] ?>
            <?php if ((string)$outbox['EXTERNAL_ID'] !== ''): ?>,
                ID сделки: <?= htmlspecialcharsbx((string)$outbox['EXTERNAL_ID']) ?>
            <?php endif; ?>
        </td></tr>
    <?php endif; ?>
</table>

<?php if (!$result): ?>
    <?php ShowNote('Результат обработки ещё не получен.'); ?>
<?php else: ?>
    <?php if (is_array($warnings) && $warnings): ?>
        <div class="adm-info-message-wrap adm-info-message-yellow"><div class="adm-info-message">
            <strong>Требуется проверка:</strong>
            <ul><?php foreach ($warnings as $warning): ?><li><?= htmlspecialcharsbx((string)$warning) ?></li><?php endforeach; ?></ul>
        </div></div>
    <?php endif; ?>
    <p>Уверенность нормализации: <strong><?= number_format((float)$result['CONFIDENCE'] * 100, 0) ?>%</strong>. Это подсказка, а не гарантия.</p>
    <form method="post" action="safeai_b2b_request_action.php">
        <?= bitrix_sessid_post() ?>
        <input type="hidden" name="id" value="<?= (int)$id ?>">
        <input type="hidden" name="lang" value="<?= htmlspecialcharsbx(LANGUAGE_ID) ?>">
        <table class="adm-detail-content-table edit-table">
            <tr><td class="adm-detail-content-cell-l"><label for="company">Компания</label></td><td><input id="company" name="company" maxlength="255" size="60" required value="<?= htmlspecialcharsbx((string)$result['COMPANY']) ?>"></td></tr>
            <tr><td class="adm-detail-content-cell-l"><label for="inn">ИНН</label></td><td><input id="inn" name="inn" pattern="(?:\d{10}|\d{12})" maxlength="12" required value="<?= htmlspecialcharsbx((string)$result['INN']) ?>"></td></tr>
            <tr><td class="adm-detail-content-cell-l"><label for="email">Email</label></td><td><input id="email" type="email" name="email" maxlength="254" value="<?= htmlspecialcharsbx((string)$result['EMAIL']) ?>"></td></tr>
            <tr><td class="adm-detail-content-cell-l"><label for="phone">Телефон</label></td><td><input id="phone" name="phone" maxlength="32" value="<?= htmlspecialcharsbx((string)$result['PHONE']) ?>"></td></tr>
            <tr><td class="adm-detail-content-cell-l"><label for="summary">Содержание</label></td><td><textarea id="summary" name="summary" maxlength="4000" rows="10" cols="80" required><?= htmlspecialcharsbx((string)$result['SUMMARY']) ?></textarea></td></tr>
        </table>
        <h3>Распознанные позиции</h3>
        <table class="adm-list-table"><thead><tr><td>Наименование</td><td>Количество</td><td>Ед.</td><td>Цена</td></tr></thead><tbody>
        <?php foreach ($items as $index => $item): ?><tr>
            <td><input name="items[<?= (int)$index ?>][name]" maxlength="500" size="45" value="<?= htmlspecialcharsbx((string)$item['NAME']) ?>"></td>
            <td><input name="items[<?= (int)$index ?>][quantity]" inputmode="decimal" maxlength="50" size="10" value="<?= htmlspecialcharsbx((string)$item['QUANTITY']) ?>"></td>
            <td><input name="items[<?= (int)$index ?>][unit]" maxlength="30" size="8" value="<?= htmlspecialcharsbx((string)$item['UNIT']) ?>"></td>
            <td><input name="items[<?= (int)$index ?>][price]" inputmode="decimal" maxlength="50" size="12" value="<?= htmlspecialcharsbx((string)$item['PRICE']) ?>"></td>
        </tr><?php endforeach; ?>
        </tbody></table>

        <?php if ($request['STATUS'] === 'review'): ?>
            <p><label><input type="checkbox" name="confirmed" value="Y" required> Я сверил данные с исходным документом</label></p>
            <button class="adm-btn-save" type="submit" name="operation" value="approve">Подтвердить заявку</button>
            <button type="submit" name="operation" value="reject" formnovalidate onclick="return confirm('Отклонить заявку?')">Отклонить</button>
        <?php else: ?>
            <?php ShowNote('Решение по заявке уже принято. Повторное подтверждение запрещено.'); ?>
        <?php endif; ?>
        <?php if ($request['STATUS'] === 'export_failed'): ?>
            <button type="submit" name="operation" value="retry_export" formnovalidate>Повторить экспорт в Битрикс24</button>
        <?php endif; ?>
    </form>
<?php endif; ?>
<?php require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php'; ?>

