# DokuWiki Extension Mirror Script

This tool will download all available DokuWiki plugins/templates and extract them to `data/src`. This is useful for DokuWiki developers who need to check if a certain DokuWiki function is used by any plugins.

It's also helpful to figure out which extensions are no longer downloadable. An error log is placed into `./data/meta/error.log`.

Downloaded archives are kept in `data/meta` when extraction fails to ease debugging.

The tool uses the DokuWiki [plugin repository API](https://github.com/splitbrain/dokuwiki-plugin-pluginrepo/blob/master/README-API) and will only download a plugin again when it's version changes.

## Setup

Just use composer:

    composer install

## Running

Call the mainfile:

    ./extensionmirror.php

Optionally, specify where to put the data:

    ./extensionmirror.php -d /path/to/data

You can also download the current `master` of DokuWiki itself (in addition to all extensions) using the `-w` option:

    ./extensionmirror.php -w

## Use OpenGrok for fast search

Use docker to run [Opengrok](http://oracle.github.io/opengrok/) on top of the `data/src` directory:

    docker run --rm -t -v `pwd`/data/src:/src -p 8080:8080 nagui/opengrok:latest

The image will start to index the sources, this takes several minutes. Once it's done, you can search through the code at [http://localhost:8080/source](http://localhost:8080/source). A Cross-Reference Browser is available at [http://localhost:8080/source/xref/](http://localhost:8080/source/xref/)
