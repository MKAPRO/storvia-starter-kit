# Resource Privacy / Access Policy Contract

Status: **CONTRACT LOCKED**

## Visibility states

- `inherit` — no local visibility restriction is added.
- `private` — the local policy gate admits only the owner.
- `restricted` — the local policy gate admits the owner plus explicit direct user grants.
- There is deliberately **no `public` resource state**.

A resource grant never bypasses organizational/FileSpace authorization. All applicable restrictive gates must pass.

## Persistence

The direct-access foundation uses:

- `node_access_policies`
- `node_access_grants`
- `node_access_unlocks`

Grants are direct **user-only** grants in the Starter Kit.

## Password gate

Resource passwords are never stored raw. Password unlock is not authorization: it only satisfies the password gate after the actor has a valid content-access path.

A protected request that still requires a password returns `RESOURCE_PASSWORD_REQUIRED` with HTTP 423.

## Audit

Privacy mutations are recorded as security-relevant node events, including `node.privacy_changed`.
