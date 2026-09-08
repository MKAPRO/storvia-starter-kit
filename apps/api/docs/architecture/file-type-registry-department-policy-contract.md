# File Type Registry + Department Policy Contract

Status: **CONTRACT LOCKED**

The upload policy is source-driven and backend-authoritative.

## File detection

- Server content inspection uses `finfo(FILEINFO_MIME_TYPE)`.
- Browser-provided MIME is never authoritative.
- A plain ZIP renamed to an OOXML extension is rejected.
- Rename operations must not change the effective final extension.
- SVG handling stores **only a reconstructed canonical safe SVG**.

## Registry and scope

- Each Department has at most one department FileSpace and department upload policy applies to that FileSpace.
- Department policy does not inherit through the Department tree.
- Personal FileSpaces use the global registry only.
- Administration is protected by `system.manage`.
- There is no destructive file-type DELETE route.

## Upload-policy API

Authenticated clients read the effective upload policy from:

`GET /api/v1/file-manager/spaces/{fileSpace}/upload-policy`
