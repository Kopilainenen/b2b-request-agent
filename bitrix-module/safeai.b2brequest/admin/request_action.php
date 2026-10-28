<?php

declare(strict_types=1);

use Bitrix\Main\Loader;
use SafeAi\B2BRequest\Service\ApprovalService;
use SafeAi\B2BRequest\Service\DealExportService;

require_once $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';

if (!$USER->IsAdmin() || !Loader::includeModule('safeai.b2brequest')
    || $_SERVER['REQUEST_METHOD'] !== 'POST' || !check_bitrix_sessid()) {
    http_response_code(403);
    die('Forbidden');
}
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$operation = (string)($_POST['operation'] ?? '');
if (!$id || !in_array($operation, ['approve', 'reject', 'retry_export'], true)) {
    http_response_code(400);
    die('Bad request');
}

try {
    $service = new ApprovalService();
    if ($operation === 'approve') {
        if (($_POST['confirmed'] ?? '') !== 'Y') {
            throw new RuntimeException('Необходимо подтвердить сверку документа.');
        }
        $service->approve($id, $_POST, (int)$USER->GetID());
    } elseif ($operation === 'reject') {
        $service->reject($id, (int)$USER->GetID());
    } else {
        (new DealExportService())->retryFailed($id, (int)$USER->GetID());
    }
    LocalRedirect('safeai_b2b_request_edit.php?id=' . $id . '&lang=' . urlencode(LANGUAGE_ID));
} catch (\Throwable $error) {
    require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php';
    ShowError(htmlspecialcharsbx($error->getMessage()));
    require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php';
}

