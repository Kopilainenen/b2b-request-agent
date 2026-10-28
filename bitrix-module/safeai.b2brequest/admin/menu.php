<?php

declare(strict_types=1);

use Bitrix\Main\Loader;

if (!Loader::includeModule('safeai.b2brequest') || !$USER->IsAdmin()) {
    return false;
}

return [
    'parent_menu' => 'global_menu_services',
    'section' => 'safeai_b2brequest',
    'sort' => 500,
    'text' => 'B2B-заявки',
    'title' => 'Безопасная обработка B2B-заявок',
    'url' => 'safeai_b2b_requests.php?lang=' . LANGUAGE_ID,
    'icon' => 'fileman_menu_icon',
    'page_icon' => 'fileman_menu_icon',
    'items_id' => 'menu_safeai_b2brequest',
];

