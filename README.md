# FileImporter extension

This MediaWiki extension allows for the easy importing of a file from one site to another.
The word site is chosen specifically here as there is no reason code can not be written allowing the importing of Files from sites that are not MediaWiki wikis.

This extension has been created as part of the 2013 German Community Technical Wishlist https://phabricator.wikimedia.org/T140462 where a wish requested that it be possible to "Correctly move files from Wikipedia to Commons" including file & description page history along with maintaining edit attribution & migrating templates.

Please also see the FileExporter extension which provides a link on the file pages of a MediaWiki site to link to a wiki that is running the FileImporter extension.


#### Config

The **FileImporterSourceSiteServices** setting allows extensions and modifications to the services that retrieve details for imports.
The default setting only allows files to be imported from sites that are in the sites table.
Using the "FileImporterAnyMediaWikiSite" service here would allow you to import files from any site.


#### Major TODOs

 - Special page design / UI layout
 - Find a way to present possible problems to the user
 - Present a diff of the changes made to a page when importing (if changes are made)
 - Actually do the Import
 - Decide on the need for the ImportTransformations object, as adjustments should mainly be configurable (seen above) maybe all that is needed here is a final version of the modified text?