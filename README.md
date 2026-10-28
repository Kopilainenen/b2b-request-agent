# B2B Request Agent

Безопасный агент для автоматизации B2B-заявок на сайтах 1С-Битрикс.

## Для чего проект

Проект сокращает ручной перенос заявок из PDF и Excel в Битрикс24:

1. клиент отправляет форму и прикладывает PDF/XLSX;
2. агент извлекает компанию, ИНН, контакты и товарные позиции;
3. менеджер проверяет и исправляет карточку;
4. только после подтверждения создаётся сделка в Битрикс24.

AI не получает права самостоятельно менять CRM. Между распознаванием документа
и созданием сделки всегда находится проверка человеком.

## Состав

- `bitrix-module/` — форма, карточка менеджера и интеграция с Битрикс24;
- `gateway/` — TypeScript/Fastify Gateway с HMAC-защитой;
- `worker/` — изолированная обработка PDF/XLSX на Python;
- `docs/` — архитектура, безопасность и развёртывание.

```text
1С-Битрикс → Gateway → Worker → проверка менеджером → Битрикс24
```

## Основные меры безопасности

- приватное хранение документов вне `document_root`;
- проверка MIME, сигнатуры и структуры файлов;
- защита от zip-bomb, path traversal, replay и SSRF;
- HMAC, CSRF, server-side rate limit и журнал аудита;
- Transactional Outbox и защита от дублирования сделок;
- секреты передаются только через окружение/secret storage.

## Проверка компонентов

Gateway:

```powershell
cd gateway
npm ci
npm run check
npm test
npm run build
```

Worker:

```powershell
cd worker
python -m venv .venv
.\.venv\Scripts\Activate.ps1
pip install -e ".[dev]" pip-audit
python -m ruff check .
python -m mypy src
python -m pytest -q
```

## Документация

- [Архитектура](docs/ARCHITECTURE.md)
- [Развёртывание](docs/DEPLOYMENT.md)
- [Интеграция с Битрикс24](docs/BITRIX24.md)
- [Политика безопасности](SECURITY.md)

Это MVP/портфолио-проект. Перед production требуется интеграционная проверка на
актуальном тестовом стенде 1С-Битрикс и тестовом портале Битрикс24.

