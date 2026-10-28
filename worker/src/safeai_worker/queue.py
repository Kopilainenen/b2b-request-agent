from __future__ import annotations

import json
import os
from pathlib import Path
from uuid import UUID, uuid4

from .models import ExtractedDocument, QueuedJob

MAX_JOB_BYTES = 128 * 1024


class FileQueue:
    def __init__(self, root: Path) -> None:
        self.root = root.resolve()
        self.pending = self.root / "pending"
        self.processing = self.root / "processing"
        self.completed = self.root / "completed"
        self.failed = self.root / "failed"
        for directory in (self.pending, self.processing, self.completed, self.failed):
            directory.mkdir(parents=True, exist_ok=True, mode=0o700)

    def claim_next(self) -> tuple[Path, QueuedJob] | None:
        for source in sorted(self.pending.glob("*.json")):
            try:
                UUID(source.stem)
            except ValueError:
                continue
            target = self.processing / source.name
            try:
                os.replace(source, target)
            except (FileNotFoundError, PermissionError):
                continue
            try:
                if target.stat().st_size > MAX_JOB_BYTES or target.is_symlink():
                    raise ValueError("invalid queue file")
                return target, QueuedJob.model_validate_json(target.read_text(encoding="utf-8"))
            except Exception:
                os.replace(target, self.failed / target.name)
                continue
        return None

    def complete(self, claimed_path: Path, result: ExtractedDocument) -> None:
        result_path = self.completed / f"{result.job_id}.result.json"
        temporary = self.completed / f".{result.job_id}.{uuid4()}.tmp"
        payload = result.model_dump_json(indent=2)
        with temporary.open("x", encoding="utf-8") as stream:
            stream.write(payload)
            stream.flush()
            os.fsync(stream.fileno())
        os.replace(temporary, result_path)
        claimed_path.unlink(missing_ok=False)

    def fail(self, claimed_path: Path, code: str) -> None:
        job_name = claimed_path.stem
        error_path = self.failed / f"{job_name}.error.json"
        error_path.write_text(json.dumps({"jobId": job_name, "error": code}), encoding="utf-8")
        claimed_path.unlink(missing_ok=True)

