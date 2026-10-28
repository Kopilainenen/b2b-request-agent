<?php

declare(strict_types=1);

namespace SafeAi\B2BRequest\Security;

use RuntimeException;
use ZipArchive;

final class FileValidator
{
    public const MAX_BYTES = 12 * 1024 * 1024;
    private const MAX_ZIP_ENTRIES = 2_000;
    private const MAX_UNCOMPRESSED_BYTES = 80 * 1024 * 1024;

    /** @return array{extension:string,mediaType:string,size:int,sha256:string} */
    public function validate(array $file): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Файл не был загружен полностью.');
        }

        $path = (string)($file['tmp_name'] ?? '');
        if ($path === '' || !is_uploaded_file($path)) {
            throw new RuntimeException('Недопустимый источник файла.');
        }

        $size = filesize($path);
        if ($size === false || $size < 1 || $size > self::MAX_BYTES) {
            throw new RuntimeException('Размер файла должен быть от 1 байта до 12 MiB.');
        }

        $originalName = (string)($file['name'] ?? '');
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($extension, ['pdf', 'xlsx'], true)) {
            throw new RuntimeException('Разрешены только PDF и XLSX.');
        }

        $mediaType = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        if (!is_string($mediaType)) {
            throw new RuntimeException('Не удалось определить тип файла.');
        }

        if ($extension === 'pdf') {
            $this->validatePdf($path, $mediaType);
        } else {
            $this->validateXlsx($path, $mediaType);
        }

        return [
            'extension' => $extension,
            'mediaType' => $mediaType,
            'size' => $size,
            'sha256' => hash_file('sha256', $path),
        ];
    }

    private function validatePdf(string $path, string $mediaType): void
    {
        if ($mediaType !== 'application/pdf') {
            throw new RuntimeException('Содержимое файла не соответствует PDF.');
        }

        $handle = fopen($path, 'rb');
        $header = $handle === false ? false : fread($handle, 5);
        if (is_resource($handle)) {
            fclose($handle);
        }
        if ($header !== '%PDF-') {
            throw new RuntimeException('Некорректная сигнатура PDF.');
        }
    }

    private function validateXlsx(string $path, string $mediaType): void
    {
        $allowedMime = [
            'application/zip',
            'application/x-zip',
            'application/x-zip-compressed',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];
        if (!in_array($mediaType, $allowedMime, true)) {
            throw new RuntimeException('Содержимое файла не соответствует XLSX.');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('Некорректный XLSX-контейнер.');
        }

        try {
            if ($zip->numFiles > self::MAX_ZIP_ENTRIES) {
                throw new RuntimeException('В XLSX слишком много элементов.');
            }
            if ($zip->locateName('[Content_Types].xml') === false || $zip->locateName('xl/workbook.xml') === false) {
                throw new RuntimeException('Файл не является книгой XLSX.');
            }

            $total = 0;
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $stat = $zip->statIndex($index);
                if (!is_array($stat)) {
                    throw new RuntimeException('Не удалось проверить структуру XLSX.');
                }
                $name = (string)($stat['name'] ?? '');
                if (str_contains($name, '../') || str_starts_with($name, '/') || str_contains($name, "\\")) {
                    throw new RuntimeException('Опасный путь внутри XLSX.');
                }
                $total += (int)($stat['size'] ?? 0);
                if ($total > self::MAX_UNCOMPRESSED_BYTES) {
                    throw new RuntimeException('XLSX превышает лимит распакованного содержимого.');
                }
            }
        } finally {
            $zip->close();
        }
    }
}

