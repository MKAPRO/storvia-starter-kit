# Department FileSpace Contract

Status: **CONTRACT LOCKED**

A department FileSpace has `type = department`, no personal owner, and one authoritative department. Nodes do not duplicate department identity; organization context resolves through `node -> file_space -> department`.

Every request is scoped by the active FileSpace UUID. A node UUID alone is never sufficient for content access, and move destinations must remain inside the same FileSpace.

The frontend selects an authorized Department FileSpace and uses the same core browser/API contract as personal storage. Backend scope and permissions remain authoritative.
