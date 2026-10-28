<?php

defined('B_PROLOG_INCLUDED') && B_PROLOG_INCLUDED === true || die();

?>
<form action="<?= htmlspecialcharsbx($APPLICATION->GetCurPage()) ?>" method="post">
    <?= bitrix_sessid_post() ?>
    <input type="hidden" name="lang" value="<?= htmlspecialcharsbx(LANGUAGE_ID) ?>">
    <input type="hidden" name="id" value="safeai.b2brequest">
    <input type="hidden" name="uninstall" value="Y">
    <input type="hidden" name="step" value="2">
    <label>
        <input type="checkbox" name="save_data" value="Y" checked>
        Сохранить заявки и журнал аудита
    </label>
    <p><strong>Документы из приватного хранилища автоматически не удаляются.</strong></p>
    <input type="submit" value="Продолжить">
</form>

