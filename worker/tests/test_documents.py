from __future__ import annotations

import hashlib
from datetime import UTC, datetime
from pathlib import Path
from uuid import uuid4

import openpyxl

from safeai_worker.config import Config
from safeai_worker.documents import extract_document, resolve_document
from safeai_worker.models import DocumentRef, QueuedJob


def test_extracts_xlsx_and_neutralises_formula(tmp_path: Path) -> None:
    storage = tmp_path / "storage"
    storage.mkdir()
    name = f"{'a' * 48}.xlsx"
    path = storage / name
    workbook = openpyxl.Workbook()
    sheet = workbook.active
    sheet.append(["Товар", "Количество"])
    sheet.append(["@DANGEROUS", 5])
    workbook.save(path)

    job = _job(path, name)
    config = Config(tmp_path / "queue", storage, 1, 10, 100_000)
    resolved = resolve_document(storage, job)
    result = extract_document(resolved, job, config)

    assert result.kind == "xlsx"
    assert "'@DANGEROUS" in result.text
    assert "Лист" in result.text


def _job(path: Path, storage_id: str) -> QueuedJob:
    return QueuedJob(
        requestId=uuid4(),
        jobId=uuid4(),
        createdAt=datetime.now(UTC),
        schemaVersion=1,
        document=DocumentRef(
            storageId=storage_id,
            sha256=hashlib.sha256(path.read_bytes()).hexdigest(),
            mediaType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
            size=path.stat().st_size,
        ),
    )

