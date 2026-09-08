# Storage Abstraction Contract

Status: **CONTRACT LOCKED**

Laravel Filesystem is the storage-driver abstraction. STORVIA domain services may wrap it to enforce application invariants but do not create a parallel filesystem-driver framework.

File objects persist backend-only `storage_disk` and opaque `storage_key` metadata plus MIME type, extension, size, and checksum. The browser never supplies an authoritative storage path.

The default supported installation disk is the private local disk rooted under Laravel storage. Object writes, validation, database commit, and failure compensation must not leave a committed node pointing to a missing or inconsistent object.
