<?php

declare(strict_types=1);

namespace SafeAi\B2BRequest\Service;

use Bitrix\Main\ArgumentException;
use Bitrix\Main\Type\DateTime;
use SafeAi\B2BRequest\AuditLogTable;
use SafeAi\B2BRequest\Enum\RequestStatus;
use SafeAi\B2BRequest\RequestTable;
use SafeAi\B2BRequest\ProcessingJobTable;
use SafeAi\B2BRequest\Security\FileValidator;
use RuntimeException;

final class RequestService
{
    public function __construct(
        private readonly FileValidator $validator,
        private readonly PrivateStorage $storage,
    ) {}

    /** @return array{id:int,publicId:string} */
    public function create(array $input, array $file): array
    {
        if (trim((string)($input['website'] ?? '')) !== '') {
            throw new ArgumentException('Не удалось отправить заявку.');
        }
        $company = $this->cleanText($input['company'] ?? '', 255);
        $contactName = $this->cleanText($input['contactName'] ?? '', 255);
        $email = filter_var(trim((string)($input['email'] ?? '')), FILTER_VALIDATE_EMAIL);
        $phone = $this->cleanText($input['phone'] ?? '', 32, false);
        $comment = $this->cleanText($input['comment'] ?? '', 4_000, false);

        if ($company === '' || $contactName === '' || $email === false) {
            throw new ArgumentException('Проверьте компанию, контактное лицо и email.');
        }
        if (($input['consent'] ?? '') !== 'Y') {
            throw new ArgumentException('Необходимо согласие на обработку данных.');
        }

        $metadata = $this->validator->validate($file);
        $stored = $this->storage->storeUploaded((string)$file['tmp_name'], $metadata['extension']);
        $publicId = $this->uuidV4();
        $now = new DateTime();

        $result = RequestTable::add([
            'PUBLIC_ID' => $publicId,
            'STATUS' => RequestStatus::NEW->value,
            'COMPANY' => $company,
            'CONTACT_NAME' => $contactName,
            'EMAIL' => $email,
            'PHONE' => $phone,
            'COMMENT' => $comment,
            'STORAGE_NAME' => $stored['storageName'],
            'ORIGINAL_NAME' => $this->safeOriginalName((string)($file['name'] ?? 'document')),
            'MEDIA_TYPE' => $metadata['mediaType'],
            'FILE_SIZE' => $metadata['size'],
            'SHA256' => $metadata['sha256'],
            'CREATED_AT' => $now,
            'UPDATED_AT' => $now,
        ]);

        if (!$result->isSuccess()) {
            @unlink($stored['path']);
            throw new RuntimeException(implode('; ', $result->getErrorMessages()));
        }

        $id = (int)$result->getId();
        AuditLogTable::add([
            'REQUEST_ID' => $id,
            'EVENT' => 'request.created',
            'REQUEST_ID_TRACE' => $this->uuidV4(),
            'METADATA_JSON' => json_encode(['mediaType' => $metadata['mediaType'], 'size' => $metadata['size']], JSON_THROW_ON_ERROR),
        ]);

        try {
            $jobUuid = (new GatewayClient())->enqueue($publicId, [
                'storageId' => $stored['storageName'],
                'sha256' => $metadata['sha256'],
                'mediaType' => $metadata['mediaType'],
                'size' => $metadata['size'],
            ]);
            if ($jobUuid !== null) {
                ProcessingJobTable::add([
                    'REQUEST_ID' => $id,
                    'JOB_UUID' => $jobUuid,
                    'STATUS' => 'queued',
                ]);
                RequestTable::update($id, ['STATUS' => 'processing', 'UPDATED_AT' => new DateTime()]);
            }
        } catch (\Throwable) {
            AuditLogTable::add([
                'REQUEST_ID' => $id,
                'EVENT' => 'processing.enqueue_failed',
                'REQUEST_ID_TRACE' => $this->uuidV4(),
                'METADATA_JSON' => '{}',
            ]);
        }

        return ['id' => $id, 'publicId' => $publicId];
    }

    private function cleanText(mixed $value, int $maxLength, bool $required = true): string
    {
        $text = trim(strip_tags((string)$value));
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? '';
        $text = mb_substr($text, 0, $maxLength);
        if ($required && $text === '') {
            return '';
        }
        return $text;
    }

    private function safeOriginalName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[^\pL\pN._()\- ]/u', '_', $name) ?? 'document';
        return mb_substr($name, 0, 255);
    }

    private function uuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        return sprintf('%s-%s-%s-%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20));
    }
}

