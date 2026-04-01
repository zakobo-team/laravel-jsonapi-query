# Changelog

## v0.1.4

### Added
- Added native support for configuring built-in additional filters directly from `JsonApiQueryResource`.
- Added regression coverage for resources that use `WhereIdIn` as an additional filter without app-local wrapper classes.
- Added regression coverage for plain resources that define default sorts together with additional custom sorts.

### Changed
- Moved additional-filter resolution into the package so Laravel apps no longer need local base-resource bridge classes.
- Kept query configuration on the shared package API instead of duplicating resource glue code in individual apps.

### Benefits
- Laravel apps now use the same JSON:API query pattern without repo-specific wrapper resources.
- Shared query behavior is easier to reason about for teams moving between repos.
- Consumers get one canonical package-level way to expose built-in filters such as `filter[id]`.
