# Throttling

In case the extension gets used to a degree that puts too much load on the involved servers, the
plan involves the following steps:

## Short-term workarounds

- Lower FileImporterMaxAggregatedBytes significantly, e.g. to 10 MB only (see
  [README.md](../README.md)). This number is essentially the maximum number of bytes allowed per
  user action.
- If this is not enough, FileImporterMaxRevisions can be set to a very low value like 1 or 2 (see
  [README.md](../README.md)).

## Mid-term workarounds

- The extension makes HTTP requests to the configured `FileImporterCommonsHelperServer` (see
  [README.md](../README.md#Configuration)). Additional caching can reduce the load.
- Binary files are transferred in chunks. It might be worth looking into the individual
  configurations the different MWHttpRequest implementations (typically CURL) allow in terms of
  buffer sizes as well as timings.

## Long-term solutions

- Make use of asynchronous jobs for high-load file transfers. Jobs can be balanced, prioritized, and
  throttled e.g. per user, and possibly allow uploading bigger files.
- Replace the HTTP-based file transfer service with an other one, e.g. Swift (see
  [file-backends.md](file-backends.md)).
