#!/bin/env php
<?php

require 'vendor/autoload.php';

$cli = new \splitbrain\DokuWikiExtensionMirror\Downloader();
$cli->run();