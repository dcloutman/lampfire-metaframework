# `Records`
Records are used as wrappers around data entity records. They provide a common interface for querying and paginating through a data entity's records. They provide a better way for gateways to return results rather than arrays.
- Every record should descend from `AbstrctRecord` which can be extended to work with databases or external APIs
- Every record from the application database should extend `AbstractDatabaseRecord`. These records should be used in the application instead of raw PDO calls
