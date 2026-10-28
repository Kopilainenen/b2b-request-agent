from __future__ import annotations

from datetime import datetime
from typing import Literal
from uuid import UUID

from pydantic import BaseModel, ConfigDict, Field


class DocumentRef(BaseModel):
    model_config = ConfigDict(extra="forbid", strict=True)

    storageId: str = Field(pattern=r"^[a-f0-9]{48}\.(pdf|xlsx)$")
    sha256: str = Field(pattern=r"^[a-f0-9]{64}$")
    mediaType: Literal[
        "application/pdf",
        "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
        "application/zip",
        "application/x-zip",
        "application/x-zip-compressed",
    ]
    size: int = Field(gt=0, le=12 * 1024 * 1024)


class QueuedJob(BaseModel):
    model_config = ConfigDict(extra="forbid", strict=True)

    requestId: UUID
    document: DocumentRef
    jobId: UUID
    createdAt: datetime
    schemaVersion: Literal[1]


class NormalizedItem(BaseModel):
    model_config = ConfigDict(extra="forbid")

    name: str = Field(max_length=500)
    quantity: str | None = Field(default=None, max_length=50)
    unit: str | None = Field(default=None, max_length=30)
    price: str | None = Field(default=None, max_length=50)


class NormalizedCard(BaseModel):
    model_config = ConfigDict(extra="forbid")

    company: str | None = Field(default=None, max_length=255)
    inn: str | None = Field(default=None, pattern=r"^(\d{10}|\d{12})$")
    email: str | None = Field(default=None, max_length=254)
    phone: str | None = Field(default=None, max_length=32)
    summary: str = Field(max_length=4_000)
    items: list[NormalizedItem] = Field(default_factory=list, max_length=200)
    confidence: float = Field(ge=0, le=1)
    warnings: list[str] = Field(default_factory=list, max_length=50)


class ExtractedDocument(BaseModel):
    model_config = ConfigDict(extra="forbid")

    schema_version: Literal[1] = 1
    job_id: UUID
    request_id: UUID
    kind: Literal["pdf", "xlsx"]
    text: str
    sheets: list[str] = Field(default_factory=list)
    warnings: list[str] = Field(default_factory=list)
    normalized: NormalizedCard | None = None

