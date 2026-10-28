<?php

declare(strict_types=1);

use Bitrix\Main\Loader;

Loader::registerAutoLoadClasses('safeai.b2brequest', [
    SafeAi\B2BRequest\RequestTable::class => 'lib/RequestTable.php',
    SafeAi\B2BRequest\AuditLogTable::class => 'lib/AuditLogTable.php',
    SafeAi\B2BRequest\ProcessingJobTable::class => 'lib/ProcessingJobTable.php',
    SafeAi\B2BRequest\ResultTable::class => 'lib/ResultTable.php',
    SafeAi\B2BRequest\RequestItemTable::class => 'lib/RequestItemTable.php',
    SafeAi\B2BRequest\ExportOutboxTable::class => 'lib/ExportOutboxTable.php',
    SafeAi\B2BRequest\SubmissionRateTable::class => 'lib/SubmissionRateTable.php',
    SafeAi\B2BRequest\Enum\RequestStatus::class => 'lib/Enum/RequestStatus.php',
    SafeAi\B2BRequest\Security\FileValidator::class => 'lib/Security/FileValidator.php',
    SafeAi\B2BRequest\Service\PrivateStorage::class => 'lib/Service/PrivateStorage.php',
    SafeAi\B2BRequest\Service\RequestService::class => 'lib/Service/RequestService.php',
    SafeAi\B2BRequest\Service\GatewayClient::class => 'lib/Service/GatewayClient.php',
    SafeAi\B2BRequest\Service\ResultImportService::class => 'lib/Service/ResultImportService.php',
    SafeAi\B2BRequest\Service\ApprovalService::class => 'lib/Service/ApprovalService.php',
    SafeAi\B2BRequest\Service\Bitrix24Client::class => 'lib/Service/Bitrix24Client.php',
    SafeAi\B2BRequest\Service\DealExportService::class => 'lib/Service/DealExportService.php',
    SafeAi\B2BRequest\Security\SubmissionRateLimiter::class => 'lib/Security/SubmissionRateLimiter.php',
]);

