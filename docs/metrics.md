### EventLogging

* No schemas

### Graphite

#### Info

##### General

* **{PREFIX}** is a metric prefix defined by MediaWiki ([docs](https://www.mediawiki.org/wiki/Manual:$wgStatsdMetricPrefix)).
* **{AGGREGATION}** is a suffix added by statsd / graphite per aggregation type. ([docs](https://wikitech.wikimedia.org/wiki/Graphite#Extended_properties))
* You can find more docs @ https://wikitech.wikimedia.org/wiki/Graphite

##### FileImporter specific

* **{PREFIX}** is "MediaWiki.FileImporter"

#### Metrics

##### Special Page Loading

* **{PREFIX}.specialPage.execute.total** - Number of special page loads.
  * This does not include special page loads where the user was not allowed to view the page due to permissions.
* **{PREFIX}.specialPage.execute.fromFileExporter** - Number of special page loads that appear to come from a FileExporter extension.
  * This should be taken with a pinch of salt as people can refresh the page or send their link to another user and multiple hits will occur here.
* **{PREFIX}.specialPage.execute.noClientUrl** - Number of special page loads where no client url has been provided.

##### Importing

* **{PREFIX}.import.result.success.{AGGREGATION}** - Imports that resulted in success
* **{PREFIX}.import.result.exception.{AGGREGATION}** - Imports that resulted in an exception

* **{PREFIX}.import.timing.wholeImport.{AGGREGATION}** - Time (ms) spent processing the whole import (includes all sub times below).
* **{PREFIX}.import.timing.buildOperations.{AGGREGATION}** - Time (ms) spent building the operations needed for import.
* **{PREFIX}.import.timing.prepareOperations.{AGGREGATION}** - Time (ms) spent preparing and checking the operations needed for import.
* **{PREFIX}.import.timing.commitOperations.{AGGREGATION}** - Time (ms) spent committing the operations needed for import.
* **{PREFIX}.import.timing.miscActions.{AGGREGATION}** - Time (ms) spent on other miscellaneous write actions, such as creation of further edits / revisions.

* **{PREFIX}.import.details.textRevisions.{AGGREGATION}** - Number of text revisions imported.
* **{PREFIX}.import.details.fileRevisions.{AGGREGATION}** - Number of file revisions imported.
* **{PREFIX}.import.details.individualFileSizes.{AGGREGATION}** - Individual file revision sizes (bytes).
* **{PREFIX}.import.details.totalFileSizes.{AGGREGATION}** - Total size of all revisions in a single import (bytes).