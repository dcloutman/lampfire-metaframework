# `Records`
Records are used as wrappers around data entity records. They provide a common interface for querying and paginating through a data entity's records. They provide a better way for gateways to return results rather than arrays.
- Every record should descend from `AbstrctRecord` which can be extended to work with databases or external APIs
- Every record from the application database should extend `AbstractDatabaseRecord`. These records should be used in the application instead of raw PDO calls

## Objectives
- Keep PDO internals inside the framework.
- Let records own navigation semantics: previous, next, rewind, count.
- Avoid eager materialization to keep memory use controlled.
- Require explicit query execution lifecycle, then page over an active result set.
- Hydrate typed record objects from rows so consumers work with domain records, not database primitives.
