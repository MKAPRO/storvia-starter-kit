# Storage Quotas Contract

Status: **CONTRACT LOCKED**

STORVIA Starter Kit storage quotas are **FileSpace quotas**.

## Accounting authority

- `nodes.size` is the authoritative persisted byte size for quota accounting.
- Usage includes active and trashed file nodes; Trash does not free storage quota.
- A Department FileSpace charges the shared FileSpace, not the uploader's Personal FileSpace.
- `quota_bytes = null` means unlimited.
- `quota_bytes = 0` means zero bytes.
- The maximum finite quota accepted by the API is `MAX_QUOTA_BYTES = 9,007,199,254,740,991`.

## Upload enforcement

Quota enforcement runs inside the upload transaction. The target FileSpace row is locked with `lockForUpdate()` before the committed usage ledger changes. An upload that exceeds the limit fails with `STORAGE_QUOTA_EXCEEDED` and the stored object is compensated.

## Administration

Quota administration is protected by `system.manage`. New organization FileSpaces default to zero bytes unless explicitly configured.

## Trash and permanent deletion

Moving a file to Trash does not remove its physical object and does not reduce quota usage. Permanent deletion is the operation that can release persisted storage usage.
