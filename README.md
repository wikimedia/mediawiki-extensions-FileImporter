# FileImporter extension

This MediaWiki extension allows for the easy importing of a file from one site to another.
The word site is chosen specifically here as there is no reason code can not be written allowing the importing of Files from sites that are not MediaWiki wikis.

This extension has been created as part of the
[2013 German Community Technical Wishlist](https://meta.wikimedia.org/wiki/WMDE_Technical_Wishes/Move_files_to_Commons)
where a wish requested that it be possible to
["Correctly move files from Wikipedia to Commons"](https://phabricator.wikimedia.org/T140462)
including file & description page history along with maintaining edit attribution & migrating
templates.

Please also see the [FileExporter extension](https://www.mediawiki.org/wiki/Extension:FileExporter)
which provides a link on the file pages of a MediaWiki site to link to a wiki that is running the
FileImporter extension.


#### Config

The **FileImporterSourceSiteServices** setting allows extensions and modifications to the services that retrieve details for imports.
The default setting only allows files to be imported from sites that are in the sites table.
Using the "FileImporterAnyMediaWikiSite" service here would allow you to import files from any site.

**FileImporterMaxRevisions** specifies the maximum number of revisions (file or text) a file can
have in order to be imported. This is restricted to a hard-coded default of 100, which can be
lowered via configuration, but not raised.

**FileImporterMaxAggregatedBytes** specifies the maximum aggregated size of versions a file can have
in order to be imported. This is restricted to a hard-coded default of 250 MB, which can be lowered
via configuration, but not raised.

#### Process Walkthrough

1) The user enters the extension on the special page,
   either with a source URL as a URL parameter in the request,
   or the user will be presented with an input field to enter the URL.
    - The special page requires:
      - the right as configured in wgFileImporterRequiredRight to operate.
      - the rights required to be able to upload files.
      - uploads to be enabled on the site.
      - the user to not be blocked locally or globally.
2) When a SourceUrl is submitted to the special page the SourceSiteLocator service is used to find a SourceSite service which can handle the SourceUrl.
      - SourceSite services are composed of various other services.
      - Multiple SourceSite services can be enabled at once (see config above) and the default can also be removed.
      - The SourceSiteLocator service is used to find a SourceSite service which can handle the SourceUrl of the ImportPlan.
4) An ImportPlan is then constructed using any requested modifications made by the user in an ImportRequest object
   and the details retrieved from the SourceSite in an ImportDetails object.
5) The ImportPlan is then validated using the ImportPlanValidator which performs various checks such as:
      - Checking if the target title is available on wiki
      - Checking to see if the file already exists on wiki
      - etc.
6) An ImportPreviewPage is then displayed to the user where they can make various changes.
   These changes essentially change the ImportRequest object of the ImportPlan.
7) On import, after hash and token checks, the ImportPlan and current User are given to the Importer to import the file.
   For Importer specifics please see the docs of the Importer class.

#### Command and data flow

In general the extension is build to be modular when it comes to the systems to import from. Code
that deals with retrieving data from a source, transforming it and inserting it into corresponding
MediaWiki objects (e.g. wiki pages and histories) is put into services that could be loaded
depending on the type of the source system.

For now only code is included to import from other MediaWiki installations.

Direct database access to a remote MediaWiki installation is not required. All request are done
server-side utilizing MediaWiki's HttpRequest infrastructure (see `HttpRequestExecutor`). MediaWiki
typically utilizes CURL for this, depending on the servers configuration (see
`MWHttpRequest::factory` and `HttpRequestFactory`).

- The import process starts with the user providing the URL of a file description page they want to
  transfer.
- The FileImporter tries to discover the source wiki's query API endpoint by fetching and parsing
  the given HTML page from the source wiki (see `HttpApiLookup`).
  - The file description page is parsed as a DOM object.
  - The API endpoint is derived from the `<link rel="EditURI" href="…" />` in the header of the
    HTML page.
- The FileImporter extension then calls the source wikis `action=query` API to request all
  non-binary information (see `ApiDetailRetriever`).
  - All revisions of the wiki page describing the file are requested (`prop=revisions` in the query
    API).
  - All information about the file revisions are requested (`prop=imageinfo` in the query API).
  - This is done in a single request combining both.
- All information FileImporter gets from the source wiki are collected in individual `TextRevision`
  and `FileRevision` objects, in `FileRevisions` and `TextRevisions` collections, and in a combined
  `ImportDetails` object.
- FileImporter iterates all file revisions and uses the `url` it got from the ImageInfo query API
  (see `FileRevisionFromRemoteUrl::prepare`). These are the "fully-qualified URL to the file" (see
  `File::getFullUrl`).
- FileImporter requests each binary file via MediaWiki's HttpRequest infrastructure, as before (see
  `HttpRequestExecutor`).
  - MediaWiki's ImageInfo API made sure the current file revision is requested first.
- For each file revision, FileImporter creates a temporary file in the local file system of the web
  server currently running the import (see `FileRevisionFromRemoteUrl::prepare`).
  - `TempFSFile` from MediaWiki's FileBackend library is utilized for this.
  - Writing to the binary file is done via `fopen( …, 'wb' )` (see `FileChunkSaver`).
- The temporary file is moved to the final location via MediaWiki's FileRepo infrastructure (see
  `FileRevisionFromRemoteUrl::commit`).
  - The code for this is in MediaWiki's Import component (see `UploadRevisionImporter`).
  - For the most recent file revision `LocalFile::upload` is called, and `OldLocalFile::uploadOld`
    for archived file revisions.
- Text revisions are imported the same way, utilizing MediaWiki Import infrastructure (see
  `OldRevisionImporter`).

When failures occur:
- One or more temporary files will remain for a short time until they are purged when the script is
  shutdown. `TempFSFile` takes care of this.
- No database changes are made before all file revisions have been successfully received.
- Text revisions are intentionally committed before file revisions (see `Importer::importInternal`),
  as discussed in https://phabricator.wikimedia.org/T147451. This means a failure while moving a
  temporary file to it's final location might leave a file description page behind with no or not
  all expected file revisions. This page might need to be deleted manually.
- It's impossible to end in a situation where the most recent file revision is missing, because the
  order is guaranteed by the ImageInfo API. Either all file revisions are missing, or one or more
  archived ones.
