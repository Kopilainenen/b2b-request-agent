from __future__ import annotations

import logging
import time

from .config import Config
from .documents import extract_document, resolve_document
from .queue import FileQueue

LOGGER = logging.getLogger("safeai.worker")


def run_once(config: Config, queue: FileQueue) -> bool:
    claimed = queue.claim_next()
    if claimed is None:
        return False
    claimed_path, job = claimed
    try:
        document = resolve_document(config.storage_root, job)
        result = extract_document(document, job, config)
        queue.complete(claimed_path, result)
        LOGGER.info("job completed", extra={"job_id": str(job.jobId)})
    except Exception:
        LOGGER.exception("job failed", extra={"job_id": str(job.jobId)})
        queue.fail(claimed_path, "document_processing_failed")
    return True


def main() -> None:
    logging.basicConfig(level=logging.INFO, format="%(asctime)s %(levelname)s %(message)s")
    config = Config.from_env()
    queue = FileQueue(config.queue_root)
    while True:
        if not run_once(config, queue):
            time.sleep(config.poll_interval_seconds)


if __name__ == "__main__":
    main()

