from __future__ import annotations

import re

from .models import NormalizedCard, NormalizedItem

INN = re.compile(r"(?i)\b(?:инн\s*[:№]?\s*)?(\d{10}|\d{12})\b")
EMAIL = re.compile(r"(?i)\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,63}\b")
PHONE = re.compile(r"(?<!\d)(?:\+7|8)[\s(\-]*\d{3}[)\s\-]*\d{3}[\s\-]*\d{2}[\s\-]*\d{2}(?!\d)")
COMPANY = re.compile(
    r'(?im)^\s*(?:компания|организация|покупатель|заказчик)\s*[:\-]\s*([^\n]{2,255})$'
)
ITEM_LINE = re.compile(
    r"^\s*(?:\d+[.)]\s*)?([^\t;]{2,500})[\t;]+(\d+(?:[.,]\d+)?)"
    r"(?:[\t;]+([\w.\-]{1,30}))?(?:[\t;]+(\d+(?:[.,]\d+)?))?\s*$"
)


def normalize(text: str) -> NormalizedCard:
    limited = text[:1_000_000]
    inn = _first_group(INN, limited)
    email_match = EMAIL.search(limited)
    phone_match = PHONE.search(limited)
    company = _first_group(COMPANY, limited)
    items = _items(limited)
    found = sum(value is not None for value in (company, inn, email_match, phone_match))
    confidence = min(0.95, 0.25 + found * 0.15 + (0.1 if items else 0.0))
    warnings: list[str] = []
    if company is None:
        warnings.append("Компания не определена автоматически")
    if inn is None:
        warnings.append("ИНН не найден или имеет неверную длину")
    if not items:
        warnings.append("Товарные позиции не распознаны")

    summary_lines = [line.strip() for line in limited.splitlines() if line.strip()][:20]
    return NormalizedCard(
        company=company,
        inn=inn,
        email=email_match.group(0).lower() if email_match else None,
        phone=_normalise_phone(phone_match.group(0)) if phone_match else None,
        summary="\n".join(summary_lines)[:4_000],
        items=items,
        confidence=confidence,
        warnings=warnings,
    )


def _first_group(pattern: re.Pattern[str], text: str) -> str | None:
    match = pattern.search(text)
    return match.group(1).strip(' \t"«»') if match else None


def _normalise_phone(value: str) -> str:
    digits = re.sub(r"\D", "", value)
    if len(digits) == 11 and digits.startswith("8"):
        digits = "7" + digits[1:]
    return "+" + digits


def _items(text: str) -> list[NormalizedItem]:
    result: list[NormalizedItem] = []
    for line in text.splitlines():
        match = ITEM_LINE.match(line)
        if not match:
            continue
        name, quantity, unit, price = match.groups()
        lowered = name.strip().lower()
        if lowered in {"наименование", "товар", "позиция"}:
            continue
        result.append(
            NormalizedItem(
                name=name.strip(),
                quantity=quantity.replace(",", "."),
                unit=unit.strip() if unit else None,
                price=price.replace(",", ".") if price else None,
            )
        )
        if len(result) >= 200:
            break
    return result
