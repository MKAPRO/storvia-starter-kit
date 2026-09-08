# Repository Hygiene

The public source history must contain source code and safe documentation only.

Do not commit:

- real `.env` files, tokens, credentials, or private keys;
- database dumps, SQLite state, or operational records;
- uploaded user content or physical storage objects;
- logs, sessions, caches, build outputs, or test caches;
- patch archives, local backups, or temporary release artifacts;
- machine-local editor or AI-assistant configuration;
- absolute paths that identify a developer workstation.

Migrations plus `DatabaseSeeder` create the safe baseline catalog. The first Super Admin is created during installation with a password supplied securely; no permanent credential is committed.

Before publishing a release, perform a secret/path scan and verify the release again from a clean clone.
