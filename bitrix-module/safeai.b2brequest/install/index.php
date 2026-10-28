<?php

declare(strict_types=1);

use Bitrix\Main\Application;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;
use Bitrix\Main\ModuleManager;
use SafeAi\B2BRequest\AuditLogTable;
use SafeAi\B2BRequest\RequestTable;
use SafeAi\B2BRequest\ProcessingJobTable;
use SafeAi\B2BRequest\ResultTable;
use SafeAi\B2BRequest\RequestItemTable;
use SafeAi\B2BRequest\ExportOutboxTable;
use SafeAi\B2BRequest\SubmissionRateTable;

final class safeai_b2brequest extends CModule
{
    public $MODULE_ID = 'safeai.b2brequest';
    public $MODULE_VERSION;
    public $MODULE_VERSION_DATE;
    public $MODULE_NAME = 'B2B Request Agent';
    public $MODULE_DESCRIPTION = 'Безопасный прием и ручная проверка B2B-заявок.';
    public $PARTNER_NAME = 'Safe AI';

    public function __construct()
    {
        $version = [];
        include __DIR__ . '/version.php';
        $this->MODULE_VERSION = (string)($arModuleVersion['VERSION'] ?? '0.0.0');
        $this->MODULE_VERSION_DATE = (string)($arModuleVersion['VERSION_DATE'] ?? '');
    }

    public function DoInstall(): void
    {
        global $APPLICATION;

        if (version_compare(PHP_VERSION, '8.4.27', '<')) {
            $APPLICATION->ThrowException('Установка запрещена: требуется PHP 8.4.27 или новее.');
            return;
        }
        if (!extension_loaded('fileinfo') || !extension_loaded('zip') || !extension_loaded('curl')) {
            $APPLICATION->ThrowException('Требуются PHP-расширения fileinfo, zip и curl.');
            return;
        }
        if ((string)ini_get('session.upload_progress.enabled') !== ''
            && filter_var(ini_get('session.upload_progress.enabled'), FILTER_VALIDATE_BOOL)) {
            $APPLICATION->ThrowException('Перед установкой отключите session.upload_progress.enabled.');
            return;
        }

        ModuleManager::registerModule($this->MODULE_ID);
        Loader::includeModule($this->MODULE_ID);
        $this->installDatabase();
        $this->InstallFiles();
        CAgent::AddAgent(
            '\\SafeAi\\B2BRequest\\Service\\ResultImportService::runAgent();',
            $this->MODULE_ID,
            'N',
            60,
            '',
            'Y'
        );
        CAgent::AddAgent(
            '\\SafeAi\\B2BRequest\\Service\\DealExportService::runAgent();',
            $this->MODULE_ID,
            'N',
            60,
            '',
            'Y'
        );

        $documentRoot = rtrim((string)Application::getDocumentRoot(), '/\\');
        Option::set($this->MODULE_ID, 'private_storage_path', dirname($documentRoot) . DIRECTORY_SEPARATOR . 'b2b-request-private');
    }

    public function DoUninstall(): void
    {
        global $APPLICATION, $step;
        $step = (int)$step;

        if ($step < 2) {
            $APPLICATION->IncludeAdminFile('Удаление B2B Request Agent', __DIR__ . '/unstep1.php');
            return;
        }

        if (($_REQUEST['save_data'] ?? 'Y') !== 'Y') {
            $connection = Application::getConnection();
            foreach ([AuditLogTable::class, SubmissionRateTable::class, ExportOutboxTable::class, RequestItemTable::class, ResultTable::class, ProcessingJobTable::class, RequestTable::class] as $tableClass) {
                if ($connection->isTableExists($tableClass::getTableName())) {
                    $connection->dropTable($tableClass::getTableName());
                }
            }
        }
        CAgent::RemoveAgent('\\SafeAi\\B2BRequest\\Service\\ResultImportService::runAgent();', $this->MODULE_ID);
        CAgent::RemoveAgent('\\SafeAi\\B2BRequest\\Service\\DealExportService::runAgent();', $this->MODULE_ID);
        $this->UnInstallFiles();
        Option::delete($this->MODULE_ID);
        ModuleManager::unRegisterModule($this->MODULE_ID);
    }

    public function InstallFiles($arParams = []): bool
    {
        CopyDirFiles(__DIR__ . '/admin', Application::getDocumentRoot() . '/bitrix/admin', true, true);
        CopyDirFiles(__DIR__ . '/components', Application::getDocumentRoot() . '/local/components', true, true);
        return true;
    }

    public function UnInstallFiles(): bool
    {
        DeleteDirFiles(__DIR__ . '/admin', Application::getDocumentRoot() . '/bitrix/admin');
        DeleteDirFilesEx('/local/components/safeai/b2b.request.form');
        return true;
    }

    private function installDatabase(): void
    {
        $connection = Application::getConnection();
        foreach ([RequestTable::class, ProcessingJobTable::class, ResultTable::class, RequestItemTable::class, ExportOutboxTable::class, SubmissionRateTable::class, AuditLogTable::class] as $tableClass) {
            $entity = $tableClass::getEntity();
            if (!$connection->isTableExists($entity->getDBTableName())) {
                $entity->createDbTable();
            }
        }
        $connection->createIndex(RequestTable::getTableName(), 'UX_SAFEAI_B2B_PUBLIC_ID', ['PUBLIC_ID'], true);
        $connection->createIndex(RequestTable::getTableName(), 'IX_SAFEAI_B2B_STATUS_CREATED', ['STATUS', 'CREATED_AT']);
        $connection->createIndex(AuditLogTable::getTableName(), 'IX_SAFEAI_B2B_AUDIT_REQUEST', ['REQUEST_ID', 'CREATED_AT']);
        $connection->createIndex(ProcessingJobTable::getTableName(), 'UX_SAFEAI_B2B_JOB_UUID', ['JOB_UUID'], true);
        $connection->createIndex(ProcessingJobTable::getTableName(), 'IX_SAFEAI_B2B_JOB_REQUEST', ['REQUEST_ID']);
        $connection->createIndex(ResultTable::getTableName(), 'UX_SAFEAI_B2B_RESULT_REQUEST', ['REQUEST_ID'], true);
        $connection->createIndex(RequestItemTable::getTableName(), 'IX_SAFEAI_B2B_ITEM_REQUEST', ['REQUEST_ID', 'SORT']);
        $connection->createIndex(ExportOutboxTable::getTableName(), 'UX_SAFEAI_B2B_OUTBOX_REQUEST', ['REQUEST_ID'], true);
        $connection->createIndex(ExportOutboxTable::getTableName(), 'IX_SAFEAI_B2B_OUTBOX_DUE', ['STATUS', 'NEXT_ATTEMPT_AT']);
        $connection->createIndex(SubmissionRateTable::getTableName(), 'UX_SAFEAI_B2B_RATE_KEY', ['KEY_HASH'], true);
    }
}

