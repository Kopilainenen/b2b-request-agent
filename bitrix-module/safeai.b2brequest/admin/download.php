<?php

declare(strict_types=1);

use Bitrix\Main\Loader;
use SafeAi\B2BRequest\AuditLogTable;
use SafeAi\B2BRequest\RequestTable;
use SafeAi\B2BRequest\Service\PrivateStorage;

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';

if (!$USER->IsAdmin() || !check_bitrix_sessid() || !Loader::includeModule('safeai.b2brequest')) {
    http_response_code(403);
    die('Forbidden');
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$row = $id ? RequestTable::getByPrimary($id)->fetch() : false;
if (!$row) {
    http_response_code(404);
    die('Not found');
}

$path = PrivateStorage::fromOptions()->resolve((string)$row['STORAGE_NAME']);
AuditLogTable::add([
    'REQUEST_ID' => (int)$row['ID'],
    'EVENT' => 'document.downloaded',
    'ACTOR_ID' => (int)$USER->GetID(),
    'REQUEST_ID_TRACE' => bin2hex(random_bytes(16)),
    'METADATA_JSON' => '{}',
]);

while (ob_get_level() > 0) {
    ob_end_clean();
}
header('Content-Type: ' . (string)$row['MEDIA_TYPE']);
header('Content-Length: ' . (string)filesize($path));
header("Content-Disposition: attachment; filename*=UTF-8''" . rawurlencode((string)$row['ORIGINAL_NAME']));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, private');
readfile($path);
exit;

