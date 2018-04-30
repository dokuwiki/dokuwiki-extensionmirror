# DokuWiki Extension Mirror Script

This tool will download all available DokuWiki plugins/templates and extract them to `data/src`. This is useful for DokuWiki developers who need to check if a certain DokuWiki function is used by any plugins.

It's also helpful to figure out which extensions are no longer downloadable. An error log is placed into `./data/meta/error.log`.

Downloaded archives are kept in `data/meta` when extraction fails to ease debugging.

The tool uses the DokuWiki [plugin repository API](https://github.com/splitbrain/dokuwiki-plugin-pluginrepo/blob/master/README-API) and will only download a plugin again when it's version changes.