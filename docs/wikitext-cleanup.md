# Clean-ups in the file description wikitext

FileImporter is modelled after and as a possible replacement for several former tools, most notably
[CommonsHelper](https://tools.wmflabs.org/commonshelper/) by Magnus Manske. This and other existing
file transfer tools can apply clean-ups to the wikitext of the file description page, e.g. replace
localized templates with the canonical English ones available on Wikimedia Commons.

During development of the first stable version (MVP) of the FileImporter extension in early 2018 it
was decided to reuse the configuration format introduced for CommonsHelper2.

This FileImporter feature is sometimes referred to as "CommonsHelper2 support".

The configuration pages relevant for CommonsHelper2 have originally been stored at
https://meta.wikimedia.org/wiki/CommonsHelper2, while the FileImporter installation on the Wikimedia
cluster uses the pages at https://www.mediawiki.org/wiki/Extension:FileImporter/Data. The format is
the same.

Currently supported CommonsHelper2 features:

- Block imports of files that are tagged with a "bad template". If no templates are listed, no file
  is blocked.
- Block imports of files that are tagged with a "bad category". If no categories are listed, no file
  is blocked.
- Block imports of files that miss a "good template". If no templates are listed, all files are
  allowed.
- Remove obsolete templates.
- Translate template and parameter names according to a sequence of "transfer" rules.
- Wrap file descriptions in language templates like `{{en|…}}`.
- Insert mandatory template parameters.
- If CentralAuth is available on both the source and target wikis, the source file can be deleted or
  marked with `{{Now Commons}}`.

All sections in a configuration page are mandatory, but can be empty.

Currently **not** supported CommonsHelper2 features:

- [T198609](https://phabricator.wikimedia.org/T198609): CommonsHelper2 understands magic words like
  `%AUTHOR%`. These are obsolete and not supported.
- [T223359](https://phabricator.wikimedia.org/T223359): No `{{Information|…}}` template is created
  if the original page did not contain a template. Please use other tools to build up incomplete
  file description pages on Wikimedia Commons.
- Existing values are not touched. Other tools try to localize dates, or add specific templates like
  [`{{own work by original uploader}}`](https://commons.wikimedia.org/wiki/Template:Own_work_by_original_uploader)
  that only exist on Wikimedia Commons. Again, please use other tools to maintain file description
  pages on Wikimedia Commons.

Sources:
- [CommonsHelper2 source code](https://phabricator.wikimedia.org/diffusion/MCHT/)
- [Phabricator ticket introducing CommonsHelper2 support in FileImporter](https://phabricator.wikimedia.org/T193614)

## Unused designs, and why

- A regular request is to support regular expressions, as (technical) community members are often
  familiar with these and would like to provide wikitext clean-up rules that way. However, this can
  not be done in a MediaWiki extension written in PHP due to technical constraints: Perl regular
  expressions (PCRE, as used in PHP) can not be sandboxed and time-constrained, making the system
  vulnerable to accidental or malicious denial of service attacks via
  [catastrophic backtracking](https://www.regular-expressions.info/catastrophic.html).
  See the Lua suggestion below for a more likely alternative.
- [For The Common Good](https://en.wikipedia.org/wiki/User:This,_that_and_the_other/For_the_Common_Good)
  (FtCG) is a sophisticated replacement for CommonsHelper and CommonsHelper2, developed by TTO. It
  relies substantially on regular expression support, and is currently not used as a model for
  FileImporter because of this (see above). See https://phabricator.wikimedia.org/T171605#3485931
  for a lengthy analysis by the author TTO.
- [Move to Commons!](https://en.wikipedia.org/wiki/Wikipedia:MTC!) (MTC!) uses a series of
  [on-wiki block- and allowlists](https://en.wikipedia.org/wiki/Special:PrefixIndex/Wikipedia:MTC!/)
  as well as a series of
  [hard-coded wikitext replacements and clean-ups](https://github.com/fastily/mtc/blob/master/mtc-shared/src/main/java/mtc/MTC.java),
  but is limited to the English Wikipedia only.

## Other possible designs

- Instead of parsing configuration written in wikitext, it might be worth introducing a dedicated
  JSON format. This is easier to parse, less error-prone, almost equally human-readable, and can be
  edited with any existing JSON editor. It might be necessary to provide an editor interface,
  similar to the [TemplateData editor](https://www.mediawiki.org/wiki/Help:TemplateData#TemplateData_editor).
- The items already stored on Wikidata could be used to tag categories and templates as "compatible
  with Wikimedia Commons". A property for this currently does not exist, but might become available
  with the [Structured Commons](https://commons.wikimedia.org/wiki/Commons:Structured_data) project.
- It might be possible to allow the communities to maintain source-specific Lua modules (including
  Lua-flavored regular expressions) that perform all kinds of clean-ups on the imported wikitext.
  This Lua code would be executed either in addition to the rules specified via the CommonsHelper2
  configuration pages above, or instead of them.
