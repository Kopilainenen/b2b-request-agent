<?php

defined('B_PROLOG_INCLUDED') && B_PROLOG_INCLUDED === true || die();

$componentName = 'safeai:b2b.request.form';
?>
<form class="safeai-b2b-form" enctype="multipart/form-data" method="post" data-component="<?= htmlspecialcharsbx($componentName) ?>">
    <?= bitrix_sessid_post() ?>
    <label>Компания <input name="company" maxlength="255" required autocomplete="organization"></label>
    <label>Контактное лицо <input name="contactName" maxlength="255" required autocomplete="name"></label>
    <label>Email <input name="email" type="email" maxlength="254" required autocomplete="email"></label>
    <label>Телефон <input name="phone" type="tel" maxlength="32" autocomplete="tel"></label>
    <label>Комментарий <textarea name="comment" maxlength="4000"></textarea></label>
    <label>Заявка в PDF или XLSX, до 12 MiB
        <input name="document" type="file" accept=".pdf,.xlsx,application/pdf,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>
    </label>
    <label><input name="consent" type="checkbox" value="Y" required> Согласен на обработку данных</label>
    <div aria-hidden="true" style="position:absolute;left:-10000px;width:1px;height:1px;overflow:hidden">
        <label>Не заполняйте это поле <input name="website" tabindex="-1" autocomplete="off"></label>
    </div>
    <button type="submit">Отправить заявку</button>
    <p role="status" aria-live="polite"></p>
</form>


