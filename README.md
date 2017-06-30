# FileImporter extension

This MediaWiki extension allows for the easy importing of a file from one site to another.
The word site is chosen specifically here as there is no reason code can not be written allowing the importing of Files from sites that are not MediaWiki wikis.

This extension has been created as part of the 2013 German Community Technical Wishlist https://phabricator.wikimedia.org/T140462 where a wish requested that it be possible to "Correctly move files from Wikipedia to Commons" including file & description page history along with maintaining edit attribution & migrating templates.

Please also see the FileExporter extension which provides a link on the file pages of a MediaWiki site to link to a wiki that is running the FileImporter extension.


#### Config

The **FileImporterSourceSiteServices** setting allows extensions and modifications to the services that retrieve details for imports.
The default setting only allows files to be imported from sites that are in the sites table.
Using the "FileImporterAnyMediaWikiSite" service here would allow you to import files from any site.

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