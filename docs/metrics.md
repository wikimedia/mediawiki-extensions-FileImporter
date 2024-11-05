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

* **{PREFIX}.specialPage.execute.total.{AGGREGATION}** - Number of special page loads.
  * This does not include special page loads where the user was not allowed to view the page due to permissions.
* **{PREFIX}.specialPage.execute.fromFileExporter.{AGGREGATION}** - Number of special page loads that appear to come from a FileExporter extension.
  * This should be taken with a pinch of salt as people can refresh the page or send their link to another user and multiple hits will occur here.
* **{PREFIX}.specialPage.execute.noClientUrl.{AGGREGATION}** - Number of special page loads where no client url has been provided.

##### Special Page Interaction

* **{PREFIX}.specialPage.action.edittitle.{AGGREGATION}** - Incremented on successful import, if the edit title screen was used at least once.
* **{PREFIX}.specialPage.action.editinfo.{AGGREGATION}** - Incremented on successful import, if the edit file info screen was used at least once.
* **{PREFIX}.specialPage.action.offeredSourceDelete.{AGGREGATION}** - Number of successful imports where the remote delete feature was offered.
* **{PREFIX}.specialPage.action.offeredSourceEdit.{AGGREGATION}** - Number of successful imports where the remote edit feature was offered.

##### Importing

* **{PREFIX}.import.result.success.{AGGREGATION}** - Imports that resulted in success
* **{PREFIX}.import.result.exception.{AGGREGATION}** - Imports that resulted in an exception

* **{PREFIX}.import.timing.wholeImport.{AGGREGATION}** - Time (ms) spent processing the whole import (includes all sub times below).
* **{PREFIX}.import.timing.buildOperations.{AGGREGATION}** - Time (ms) spent building the operations needed for import.
* **{PREFIX}.import.timing.prepareOperations.{AGGREGATION}** - Time (ms) spent preparing the content that should be imported.
* **{PREFIX}.import.timing.validateOperations.{AGGREGATION}** - Time (ms) spent validating the content that should be imported.
* **{PREFIX}.import.timing.commitOperations.{AGGREGATION}** - Time (ms) spent committing the operations needed for import.
* **{PREFIX}.import.timing.miscActions.{AGGREGATION}** - Time (ms) spent on other miscellaneous write actions, such as creation of further edits / revisions.

* **{PREFIX}.import.details.textRevisions.{AGGREGATION}** - Number of text revisions imported.
* **{PREFIX}.import.details.fileRevisions.{AGGREGATION}** - Number of file revisions imported.
* **{PREFIX}.import.details.totalFileSizes.{AGGREGATION}** - Total size of all revisions in a single import (bytes).

* **{PREFIX}.import.postImport.delete.failed.{AGGREGATION}** - Post-imports where we failed to delete the remote source file.
* **{PREFIX}.import.postImport.delete.successful.{AGGREGATION}** - Post-imports where we were able to delete the remote source file.
* **{PREFIX}.import.postImport.edit.failed.{AGGREGATION}** - Post-imports where we failed to edit the remote source file.
* **{PREFIX}.import.postImport.edit.successful.{AGGREGATION}** - Post-imports where we were able to edit the remote source file.

##### Errors

All errors are logged this group of metrics.  A few errors are "recoverable",
meaning that the user can complete the import by making trivial changes to the
file title, for example.  Unrecoverable errors cannot be resolved without going
outside of the workflow, for example being granted upload permissions.  Errors
are broken down by type, and are catalogued on a [wiki page](https://www.mediawiki.org/wiki/Extension:FileImporter/Errors).

* **{PREFIX}.error.byRecoverable.{true/false}.byType.{error-key}'


### Prometheus

#### Info

##### General

##### FileImporter specific

* **{PREFIX}** is "MediaWiki_FileImporter"

#### Metrics

##### Special Page Loading

* **{PREFIX}_specialPage_executions_total** - Number of special page loads.
  * This does not include special page loads where the user was not allowed to view the page due to permissions.
  * **parameter=fromFileExporter** - Number of special page loads that appear to come from a FileExporter extension.
  * This should be taken with a pinch of salt as people can refresh the page or send their link to another user and multiple hits will occur here.
  * **parameter=noClientUrl** - Number of special page loads where no client url has been provided.

##### Special Page Interaction

* **{PREFIX}_specialPage_actions_total**
  * **action=edittitle** - Incremented on successful import, if the edit title screen was used at least once.
  * **action=editinfo** - Incremented on successful import, if the edit file info screen was used at least once.
  * **action=offeredSourceDelete** - Number of successful imports where the remote delete feature was offered.
  * **action=offeredSourceEdit** - Number of successful imports where the remote edit feature was offered.

##### Importing

* **{PREFIX}_imports_total
  * **result=success** - Imports that resulted in success

* **{PREFIX}_import_duration_seconds** - Time spent processing the whole import (includes all sub times below).
* **{PREFIX}_import_operation_duration_seconds**
  * **operation=build** - Time spent building the operations needed for import.
  * **operation=prepare** - Time spent preparing the content that should be imported.
  * **operation=validate** - Time spent validating the content that should be imported.
  * **operation=commit** - Time spent committing the operations needed for import.
  * **operation=misc** - Time spent on other miscellaneous write actions, such as creation of further edits / revisions.

* **{PREFIX}_import_details_textRevisions** - Number of text revisions imported.
* **{PREFIX}_import_details_fileRevisions** - Number of file revisions imported.
* **{PREFIX}_import_details_totalFileSizes_bytes** - Total size of all revisions in a single import.

* **{PREFIX}_postImport_results_total**
  * **action=delete,result=failed** - Post-imports where we failed to delete the remote source file.
  * **action=delete,result=successful** - Post-imports where we were able to delete the remote source file.
  * **action=edit,result=failed** - Post-imports where we failed to edit the remote source file.
  * **action=edit,result=successful** - Post-imports where we were able to edit the remote source file.

##### Errors

All errors are logged this group of metrics.  A few errors are "recoverable",
meaning that the user can complete the import by making trivial changes to the
file title, for example.  Unrecoverable errors cannot be resolved without going
outside of the workflow, for example being granted upload permissions.  Errors
are broken down by type, and are catalogued on a [wiki page](https://www.mediawiki.org/wiki/Extension:FileImporter/Errors).

* **{PREFIX}_errors_total**
  * **recoverable={true/false}**
  * **type={error-key}**
