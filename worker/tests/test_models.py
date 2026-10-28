from __future__ import annotations

import pytest
from pydantic import ValidationError

from safeai_worker.models import QueuedJob


def test_rejects_extra_fields_and_path_traversal() -> None:
    with pytest.raises(ValidationError):
        QueuedJob.model_validate(
            {
                "requestId": "2cddc0ae-1aac-4cc7-aa6a-3c27112d8435",
                "jobId": "2cddc0ae-1aac-4cc7-aa6a-3c27112d8436",
                "createdAt": "2026-10-27T00:00:00Z",
                "schemaVersion": 1,
                "document": {
                    "storageId": "../../secret.pdf",
                    "sha256": "a" * 64,
                    "mediaType": "application/pdf",
                    "size": 10,
                },
                "unexpected": "command",
            }
        )

