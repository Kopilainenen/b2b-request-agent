from __future__ import annotations

import os
from dataclasses import dataclass
from pathlib import Path


@dataclass(frozen=True, slots=True)
class Config:
    queue_root: Path
    storage_root: Path
    poll_interval_seconds: float
    max_pdf_pages: int
    max_extracted_chars: int

    @classmethod
    def from_env(cls) -> Config:
        queue = _absolute_directory("SAFEAI_QUEUE_ROOT")
        storage = _absolute_directory("SAFEAI_STORAGE_ROOT")
        return cls(
            queue_root=queue,
            storage_root=storage,
            poll_interval_seconds=_bounded_float("SAFEAI_POLL_INTERVAL_SECONDS", 2, 0.2, 60),
            max_pdf_pages=_bounded_int("SAFEAI_MAX_PDF_PAGES", 50, 1, 500),
            max_extracted_chars=_bounded_int(
                "SAFEAI_MAX_EXTRACTED_CHARS", 1_000_000, 1_000, 5_000_000
            ),
        )


def _absolute_directory(name: str) -> Path:
    raw = os.environ.get(name, "").strip()
    if not raw:
        raise RuntimeError(f"{name} is required")
    path = Path(raw)
    if not path.is_absolute():
        raise RuntimeError(f"{name} must be an absolute path")
    return path.resolve()


def _bounded_int(name: str, default: int, minimum: int, maximum: int) -> int:
    value = int(os.environ.get(name, str(default)))
    if not minimum <= value <= maximum:
        raise RuntimeError(f"{name} must be between {minimum} and {maximum}")
    return value


def _bounded_float(name: str, default: float, minimum: float, maximum: float) -> float:
    value = float(os.environ.get(name, str(default)))
    if not minimum <= value <= maximum:
        raise RuntimeError(f"{name} must be between {minimum} and {maximum}")
    return value

