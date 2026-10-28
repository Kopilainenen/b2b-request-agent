<?php

declare(strict_types=1);

use Bitrix\Main\Application;
use Bitrix\Main\Engine\Contract\Controllerable;
use Bitrix\Main\Engine\ActionFilter;
use Bitrix\Main\Loader;
use Bitrix\Main\Web\Cookie;
use SafeAi\B2BRequest\Security\FileValidator;
use SafeAi\B2BRequest\Service\PrivateStorage;
use SafeAi\B2BRequest\Service\RequestService;
use SafeAi\B2BRequest\Security\SubmissionRateLimiter;

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

final class SafeAiB2BRequestFormComponent extends CBitrixComponent implements Controllerable
{
    public function configureActions(): array
    {
        return [
            'submit' => [
                'prefilters' => [
                    new ActionFilter\HttpMethod([ActionFilter\HttpMethod::METHOD_POST]),
                    new ActionFilter\Csrf(),
                ],
            ],
        ];
    }

    public function executeComponent(): void
    {
        $this->includeComponentTemplate();
    }

    public function submitAction(array $fields, array $document): array
    {
        if (!Loader::includeModule('safeai.b2brequest')) {
            throw new RuntimeException('Модуль недоступен.');
        }

        $this->enforceRateLimit();
        $service = new RequestService(new FileValidator(), PrivateStorage::fromOptions());
        $created = $service->create($fields, $document);

        return ['requestId' => $created['publicId'], 'status' => 'received'];
    }

    private function enforceRateLimit(): void
    {
        $request = Application::getInstance()->getContext()->getRequest();
        (new SubmissionRateLimiter())->consume((string)($_SERVER['REMOTE_ADDR'] ?? ''));
        $cookieName = 'safeai_b2b_submit';
        if ($request->getCookie($cookieName) === '1') {
            throw new RuntimeException('Повторите отправку позже.');
        }

        $cookie = new Cookie($cookieName, '1', time() + 60);
        $cookie->setHttpOnly(true);
        $cookie->setSecure($request->isHttps());
        $cookie->setSameSite('Lax');
        Application::getInstance()->getContext()->getResponse()->addCookie($cookie);
    }
}

