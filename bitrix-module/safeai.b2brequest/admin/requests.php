<?php

declare(strict_types=1);

use Bitrix\Main\Loader;
use SafeAi\B2BRequest\RequestTable;

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';

if (!$USER->IsAdmin() || !Loader::includeModule('safeai.b2brequest')) {
    $APPLICATION->AuthForm('Доступ запрещен');
}

$APPLICATION->SetTitle('B2B-заявки');
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';

$rows = RequestTable::getList([
    'select' => ['ID', 'PUBLIC_ID', 'STATUS', 'COMPANY', 'CONTACT_NAME', 'EMAIL', 'ORIGINAL_NAME', 'FILE_SIZE', 'CREATED_AT'],
    'order' => ['CREATED_AT' => 'DESC'],
    'limit' => 200,
]);
?>
<table class="adm-list-table">
    <thead><tr class="adm-list-table-header"><td>ID</td><td>Статус</td><td>Компания</td><td>Контакт</td><td>Файл</td><td>Создана</td></tr></thead>
    <tbody>
    <?php while ($row = $rows->fetch()): ?>
        <tr class="adm-list-table-row">
            <td><a href="safeai_b2b_request_edit.php?id=<?= (int)$row['ID'] ?>&amp;lang=<?= urlencode(LANGUAGE_ID) ?>"><?= (int)$row['ID'] ?></a></td>
            <td><?= htmlspecialcharsbx((string)$row['STATUS']) ?></td>
            <td><?= htmlspecialcharsbx((string)$row['COMPANY']) ?></td>
            <td><?= htmlspecialcharsbx((string)$row['CONTACT_NAME']) ?><br><?= htmlspecialcharsbx((string)$row['EMAIL']) ?></td>
            <td><a href="safeai_b2b_download.php?id=<?= (int)$row['ID'] ?>&amp;<?= bitrix_sessid_get() ?>"><?= htmlspecialcharsbx((string)$row['ORIGINAL_NAME']) ?></a> (<?= number_format((int)$row['FILE_SIZE'] / 1024, 1, '.', ' ') ?> KiB)</td>
            <td><?= htmlspecialcharsbx((string)$row['CREATED_AT']) ?></td>
        </tr>
    <?php endwhile; ?>
    </tbody>
</table>
<?php
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';

