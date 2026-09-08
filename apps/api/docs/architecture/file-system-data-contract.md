# File System Data Contract

Status: **CONTRACT LOCKED**

`file_spaces` is the namespace boundary. Supported types are `personal` and `department`. A personal FileSpace targets one owner user; a department FileSpace targets one department.

`nodes` is the single logical hierarchy for both folders and files. Public identity is the UUID. Internal numeric IDs are persistence details and must not become API identifiers.

Node resolution is always constrained by the active FileSpace. Create, rename, move, Trash, restore, Favorites, and browse operations must preserve that namespace boundary. Cross-FileSpace moves are not supported.

Storage identity (`storage_disk`, `storage_key`) is backend-only and immutable through ordinary logical rename/move operations.
