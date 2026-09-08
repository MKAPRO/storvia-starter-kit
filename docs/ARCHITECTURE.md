# Architecture Overview

STORVIA Starter Kit is a two-application monorepo:

- `apps/api`: Laravel REST API and MariaDB persistence
- `apps/web`: Next.js first-party web client

## File spaces and nodes

`file_spaces` is the namespace boundary. Starter supports personal FileSpaces and department FileSpaces. `nodes` is the single folder/file hierarchy inside a FileSpace; public API identity uses UUIDs while internal numeric identifiers remain persistence details.

A node is always resolved inside its route FileSpace. Cross-FileSpace moves are not allowed.

## Storage

Laravel Filesystem is the storage-driver abstraction. File object storage identity stays backend-only. The default supported object store is the private local disk rooted under Laravel storage; client-provided paths are never authoritative.

## Authorization

Backend authorization is authoritative. Roles and permissions are combined with organization/FileSpace scope. Each user has exactly one role. UI visibility or cached client state never grants API authority.

## Quotas and upload policy

Quotas are charged to the FileSpace, not the uploader. `null` means unlimited and `0` means zero bytes. Upload validation uses server-side content detection plus the file-type registry and department policies.

## Privacy

Direct node privacy supports inherited, private, and restricted access-policy states. Password unlock is an additional gate and does not create authorization by itself.

## Audit recording

Core mutations write to an append-only internal audit ledger with sensitive metadata filtering. The Starter Kit does not expose an administration audit-log browser.

## Public architecture contracts

Selected detailed contracts used by automated regression tests live in `apps/api/docs/architecture/`.
