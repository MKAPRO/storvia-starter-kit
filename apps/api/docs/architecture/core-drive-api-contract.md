# Core Drive API Contract

Status: **CONTRACT LOCKED**

The authenticated File Manager API exposes visible FileSpaces, node browsing, folder creation, rename/move, upload/download, Favorites, Trash, restore, and upload-policy discovery.

`file_spaces` and `nodes` remain the authoritative domain model. The API does not introduce parallel drive/folder/file tables. Public identities are UUIDs.

Every node route resolves the node inside the route FileSpace. Backend policies and FileSpace access services are authoritative; the client must not infer authority from role labels or UI state.
