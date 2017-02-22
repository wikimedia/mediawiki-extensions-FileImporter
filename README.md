# FileImporter extension

This MediaWiki extension allows for the easy importing of a file from one site to another.
The word site is chosen specifically here as there is no reason code can not be written allowing the importing of Files from sites that are not MediaWiki wikis.

This extension has been created as part of the 2013 German Community Technical Wishlist https://phabricator.wikimedia.org/T140462 where a wish requested that it be possible to "Correctly move files from Wikipedia to Commons" including file & description page history along with maintaining edit attribution & migrating templates.

Please also see the FileExporter extension which provides a link on the file pages of a MediaWiki site to link to a wiki that is running the FileImporter extension.


#### Config

The **FileImporterDetailRetrieverServices** setting allows extensions and modifications to the services that retrieve detials for imports.
This means that an extension could, for example, add a service allowing importing of files from Flickr.
It also means that setup specific retrievers can be added / defaults can be replaced.

The **FileImporterTextPotentialProblems** setting allows for a per domain list of text to be disallowed during imports.
For the Wikimedia usecase this could disallow imports to Commons of Files on enwiki that include the {{Non-free logo template.

TODO is this going to be regex? Example: https://raw.githubusercontent.com/atlight/ForTheCommonGood/master/ForTheCommonGood/en.wikipedia.wiki

The **FileImporterTextReplacements** setting allows for per domain switching of text.
For the Wikimedia usecase this could include switching the {{PD-self}} template with the {{PD-user}} template.

TODO is this going to be regex? Example: https://raw.githubusercontent.com/atlight/ForTheCommonGood/master/ForTheCommonGood/en.wikipedia.wiki

#### Major TODOs

 - Special page design / UI layout
 - Find a way to present possible problems to the user
 - Present a diff of the changes made to a page when importing (if changes are made)
 - Actually do the Import
 - Decide on the need for the ImportTransformations object, as adjustments should mainly be configurable (seen above) maybe all that is needed here is a final version of the modified text?