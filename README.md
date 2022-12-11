# FileImporter extension

This MediaWiki extension allows for the easy importing of a file from one site to another.
The word site is chosen specifically here as there is no reason code can not be written allowing the
importing of files from sites that are not MediaWiki wikis.

This extension has been created as part of the
[2013 German Community Technical Wishlist](https://meta.wikimedia.org/wiki/WMDE_Technical_Wishes/Move_files_to_Commons)
where a wish requested that it be possible to
["Correctly move files from Wikipedia to Commons"](https://phabricator.wikimedia.org/T140462)
including file & description page history along with maintaining edit attribution &
[migrating templates](docs/wikitext-cleanup.md).

Please also see the [FileExporter extension](https://www.mediawiki.org/wiki/Extension:FileExporter)
which provides a link on the file pages of a MediaWiki site to link to a wiki that is running the
FileImporter extension.

#### Configuration

**FileImporterRequiredRight** specifies the user right required to use the special page. Default is
"upload" – the same right [[Special:Upload]] requires.

The **FileImporterSourceSiteServices** setting lists enabled services responsible for imports from different sources.
Each service allows extensions and modifications of data retrieved by it and limits the type of sites where files can be
imported from. The default empty list `[]` allows files to be imported from any MediaWiki site. Set the list to
`[ 'FileImporter-WikimediaSitesTableSite' ]` if you only want to allow imports from sites that are in the sites table.

**FileImporterMaxRevisions** specifies the maximum number of revisions (file or text) a file can
have in order to be imported. This is restricted to a hard-coded default of 100, which can be
lowered via configuration, but not raised.

**FileImporterMaxAggregatedBytes** specifies the maximum aggregated size of versions a file can have
in order to be imported. This is restricted to a hard-coded default of 250 MB, which can be lowered
via configuration, but not raised.

**FileImporterShowInputScreen** enables the FileImporter special page to be used without being directed there by the
`FileExporter` extension's link. If set to `true` an input field shows on the `Special:ImportFile` that can be used to
import files. Default is `false`.

**FileImporterInterWikiMap** (deprecated) manually maps host names to multi-hop interwiki prefixes,
e.g. `[ 'de.wikisource.org' => 's:de' ]`. This was a temporary solution before
[T225515](https://phabricator.wikimedia.org/T225515) was resolved, and never needed for single
prefixes.

**FileImporterCommonsHelperServer** and **FileImporterCommonsHelperBasePageName** specify the
location of CommonsHelper2-compatible rule sets that specify
[transfer rules and template migrations](docs/wikitext-cleanup.md)
for individual source wikis. For example, with server and base page name set to
`https://www.mediawiki.org/` and `Extension:FileImporter/Data/`, imports from en.wikipedia.org will
be restricted by the rules specified on the page
[mw:Extension:FileImporter/Data/en.wikipedia](https://www.mediawiki.org/wiki/Extension:FileImporter/Data/en.wikipedia).
See the later for a self-documenting example of such a rule set.

It is assumed the server holding these pages shares configuration with the server running
FileImporter, particularly the [$wgScriptPath](https://www.mediawiki.org/wiki/Manual:$wgScriptPath)
and [$wgArticlePath](https://www.mediawiki.org/wiki/Manual:$wgArticlePath) settings.

Setting the server to an empty string turns the feature off.

**FileImporterCommonsHelperHelpPage** specifies the location of the on-wiki help for the
configuration described above.

**FileImporterCommentForPostImportRevision** defines the text used for the edit summary of a post import revision.
Default is `Imported with FileImporter from $1` where `$1` is the URL of the source file.

**FileImporterTextForPostImportRevision** defines the text added to the top of the imported page's wikitext.
Default is `<!--This file was moved here using FileImporter from $1-->\n` where `$1` is the URL of the source file.

#### Custom messages

**fileimporter-post-import-revision-annotation** This message defaults to
empty, but can be used to add any custom text to the file info page during
import.  The message takes two parameters, `$1` is a full URL to the imported
file on the source wiki.  `$2` is the time of import in an ISO 8601 format.
This makes it simple to categorize imports based on the source wiki domain,
or month of import.

For example, to categorize by source wiki one could include text in the message
`{{#invoke:UrlToImportCategory|main|url=$1}}`, where the supporting Lua module
looks like:

```
local p = {}

function toDomain(url)
    return mw.uri.new(url).host
end

function p.main(frame)
    return "[[Category:Imported from " .. toDomain(frame.args.url) .. "]]"
end

return p
```

#### Process walkthrough

1) The user enters the extension on the special page,
   either with a source URL as a URL parameter in the request,
   or the user will be presented with an input field to enter the URL.
    - The special page requires:
      - the right as configured in FileImporterRequiredRight to operate.
      - the rights required to be able to upload files.
      - uploads to be enabled on the site.
      - the user to not be blocked locally or globally.
2) When a SourceUrl is submitted to the special page the SourceSiteLocator service is used to find a
   SourceSite service which can handle the SourceUrl.
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

#### Detailed command and data flow

In general the extension is build to be modular when it comes to the systems to import from. Code
that deals with retrieving data from a source, transforming it and inserting it into corresponding
MediaWiki objects (e.g. wiki pages and histories) is put into services that could be loaded
depending on the type of the source system.

For now only code is included to import from other MediaWiki installations.

Direct database access to a remote MediaWiki installation is not required. All request are done
server-side utilizing MediaWiki's HttpRequest infrastructure (see `HttpRequestExecutor`). MediaWiki
typically utilizes CURL for this, depending on the servers configuration (see `HttpRequestFactory`).

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
  - See [file-backends.md](docs/file-backends.md) for documentation on alternative backends, e.g.
    Swift.
- For each file revision, FileImporter creates a temporary file in the local file system of the web
  server currently running the import (see `FileRevisionFromRemoteUrl::prepare`).
  - `TempFSFile` from MediaWiki's FileBackend infrastructure is utilized for this.
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
- See [throttling.md](docs/throttling.md) for throttling options.

#### More documentation
- This extension on mediawiki.org: https://www.mediawiki.org/wiki/Extension:FileImporter
- User documentation: https://www.mediawiki.org/wiki/Help:Extension:FileImporter
- Project documentation: https://meta.wikimedia.org/wiki/WMDE_Technical_Wishes/Move_files_to_Commons
