from __future__ import annotations

import hashlib
import json
from datetime import UTC, datetime
from pathlib import Path
from uuid import uuid4

import openpyxl

from safeai_worker.config import Config
from safeai_worker.main import run_once
from safeai_worker.queue import FileQueue


def test_pending_job_becomes_normalized_result(tmp_path: Path) -> None:
    queue_root = tmp_path / "queue"
    storage = tmp_path / "storage"
    storage.mkdir()
    storage_id = f"{'c' * 48}.xlsx"
    document = storage / storage_id

    workbook = openpyxl.Workbook()
    workbook.active.append(["Компания: ООО Тест"])
    workbook.active.append(["ИНН 7707083893"])
    workbook.active.append(["Кабель", 3, "шт.", 120])
    workbook.save(document)

    request_id = uuid4()
    job_id = uuid4()
    pending = queue_root / "pending"
    pending.mkdir(parents=True)
    payload = {
        "requestId": str(request_id),
        "jobId": str(job_id),
        "createdAt": datetime.now(UTC).isoformat(),
        "schemaVersion": 1,
        "document": {
            "storageId": storage_id,
            "sha256": hashlib.sha256(document.read_bytes()).hexdigest(),
            "mediaType": "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
            "size": document.stat().st_size,
        },
    }
    (pending / f"{job_id}.json").write_text(json.dumps(payload), encoding="utf-8")

    config = Config(queue_root, storage, 1, 10, 100_000)
    assert run_once(config, FileQueue(queue_root)) is True

    result_path = queue_root / "completed" / f"{job_id}.result.json"
    result = json.loads(result_path.read_text(encoding="utf-8"))
    assert result["job_id"] == str(job_id)
    assert result["request_id"] == str(request_id)
    assert result["normalized"]["inn"] == "7707083893"
    assert not (queue_root / "processing" / f"{job_id}.json").exists()

