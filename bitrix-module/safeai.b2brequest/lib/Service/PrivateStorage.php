<?php

declare(strict_types=1);

namespace SafeAi\B2BRequest\Service;

use Bitrix\Main\Config\Option;
use RuntimeException;

final class PrivateStorage
{
    public function __construct(private readonly string $root)
    {
        if ($root === '' || !$this->isAbsolutePath($root)) {
            throw new RuntimeException('Путь приватного хранилища должен быть абсолютным.');
        }
    }

    public static function fromOptions(): self
    {
        $default = dirname((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), 1) . DIRECTORY_SEPARATOR . 'b2b-request-private';
        $environmentPath = trim((string)getenv('SAFEAI_STORAGE_ROOT'));
        $configured = $environmentPath !== ''
            ? $environmentPath
            : (string)Option::get('safeai.b2brequest', 'private_storage_path', $default);
        return new self($configured);
    }

    /** @return array{storageName:string,path:string} */
    public function storeUploaded(string $temporaryPath, string $extension): array
    {
        $this->ensureRoot();
        $storageName = bin2hex(random_bytes(24)) . '.' . $extension;
        $target = $this->root . DIRECTORY_SEPARATOR . $storageName;

        if (!move_uploaded_file($temporaryPath, $target)) {
            throw new RuntimeException('Не удалось сохранить документ.');
        }
        @chmod($target, 0600);

        return ['storageName' => $storageName, 'path' => $target];
    }

    public function resolve(string $storageName): string
    {
        if (!preg_match('/\A[a-f0-9]{48}\.(pdf|xlsx)\z/D', $storageName)) {
            throw new RuntimeException('Некорректный идентификатор файла.');
        }
        $path = $this->root . DIRECTORY_SEPARATOR . $storageName;
        if (!is_file($path)) {
            throw new RuntimeException('Файл не найден.');
        }
        return $path;
    }

    private function ensureRoot(): void
    {
        if (!is_dir($this->root) && !mkdir($this->root, 0700, true) && !is_dir($this->root)) {
            throw new RuntimeException('Не удалось создать приватное хранилище.');
        }
        if (!is_writable($this->root)) {
            throw new RuntimeException('Приватное хранилище недоступно для записи.');
        }
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/') || preg_match('/\A[A-Za-z]:[\\\\\/]/', $path) === 1;
    }
}

