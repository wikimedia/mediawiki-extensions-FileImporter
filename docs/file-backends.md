# What about Swift?

FileImporter currently does not use MediaWiki's FileBackend infrastructure (e.g. SwiftFileBackend)
to transfer binary files, but requests them via HTTP.

Reasoning:
- FileImporter is not meant to create new demand, but to replace existing community processes that
  already cause the same HTTP requests.
- The feature is expected to be used by the same small group of expert users (essentially Commons
  users that know what it means to move a file to Commons).
- Throttling can easily be done in case of unexpected overuse (see [throttling.md](throttling.md)).
- The code architecture allows to introduce support for other backends, if the need arises.
- The team estimated the development time for full Swift support much higher than what it seemed
  worth it at the time. For example, the shard configuration for all source sites must be made
  available to the FileImporter extension. Currently this isn't easily available, and possibly
  requires major refactoring.

This estimation might change later when actual usage data is available.

Previous discussions:
- https://phabricator.wikimedia.org/T190716#4224000
