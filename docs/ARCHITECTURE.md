# Архитектура и границы доверия

```text
Интернет
   │
   ▼
Публичная форма 1С-Битрикс
   │  CSRF, rate limit, сигнатура файла
   ▼
Приватное хранилище + MySQL
   │
   │ HMAC + timestamp + nonce
   ▼
TypeScript Gateway (loopback)
   │ атомарный spool
   ▼
Python Worker без сетевых полномочий
   │ PDF/XLSX → строгий JSON
   ▼
Импортирующий агент Битрикс
   │ проверка job UUID ↔ request UUID
   ▼
Карточка менеджера
   │ явное подтверждение POST + CSRF
   ▼
Transactional Outbox
   │ HTTPS, allowlist, DNS pinning
   ▼
Bitrix24 REST
```

## Недоверенные данные

Недоверенными считаются форма, документ, имя файла, XLSX ZIP-структура,
извлеченный текст, результат нормализации, REST-ответ и содержимое заголовков.

Текст документа никогда не интерпретируется как команда. Worker не имеет shell,
токена Битрикс24 или права создавать сделки. REST-токен есть только у PHP-агента
экспорта и передается через окружение/secret storage.

## Состояния заявки

```text
new → processing → review → approved → exporting → exported
                         ↘ rejected
                                      ↘ export_failed → approved (ручной retry)
```

Переход в `approved` возможен только от администратора, из состояния `review`,
через POST с корректной Bitrix session CSRF-защитой.

