<?php

namespace splitbrain\DokuWikiExtensionMirror;

use splitbrain\PHPArchive\Tar;
use splitbrain\PHPArchive\Zip;
use splitbrain\phpcli\CLI;
use splitbrain\phpcli\Exception;
use splitbrain\phpcli\Options;

class Downloader extends CLI
{

    const API = 'http://www.dokuwiki.org/lib/plugins/pluginrepo/api.php?fmt=php&order=lastupdate';

    protected $datadir;
    protected $logfile;

    /**
     * Register options and arguments on the given $options object
     *
     * @param Options $options
     * @return void
     *
     * @throws Exception
     */
    protected function setup(Options $options)
    {
        $options->setHelp('Download all known DokuWiki extensions');
        $options->registerOption('datadir', 'Where to store downloaded data', 'd', 'directory');
    }

    /**
     * Dowload all the files
     *
     * @param Options $options
     * @return void
     *
     * @throws Exception
     */
    protected function main(Options $options)
    {
        $this->datadir = $options->getOpt('datadir', './data');
        $this->initDir($this->datadir . '/meta/plugin');
        $this->initDir($this->datadir . '/meta/template');
        $this->initDir($this->datadir . '/src/plugin');
        $this->initDir($this->datadir . '/src/template');

        $this->logfile = $this->datadir . '/meta/error.log';
        if (file_exists($this->logfile)) unlink($this->logfile);

        $dls = $this->getDownloads();
        foreach ($dls as $dl) {
            $this->info('Fetching {p}...', ['p' => $dl['name']]);
            try {
                $this->download($dl['name'], $dl['url'], $dl['date']);
            } catch (\Exception $e) {
                $this->error($e->getMessage());
                file_put_contents(
                    $this->logfile,
                    $dl['name'] . "\t" . $e->getMessage() . "\n",
                    FILE_APPEND
                );
            }
        }
    }

    /**
     * Create the given dir
     *
     * @param string $dir
     * @return bool
     * @throws Exception
     */
    protected function initDir($dir) {
        if (is_dir($dir)) return true;
        $ok = mkdir($dir, 0777, true);
        if(!$ok) throw new Exception('Could not create dir '.$dir);
        return $ok;
    }

    /**
     * @param $name
     * @param $url
     * @param $version
     * @throws Exception
     */
    protected function download($name, $url, $version)
    {
        $last = $this->datadir . '/meta/' . $name . '.last';
        $target = $this->datadir . '/src/' . $name;
        $tmp = $this->datadir . '/meta/' . $name . '.tmp';
        $archive = $this->datadir . '/meta/' . $name . '.archive';

        $this->info('Downloading {url}', ['url' => $url]);
        $request = \EasyRequest\Client::request($url, 'GET', ['follow_redirects' => true]);
        $response = $request->send();
        $body = (string)$response->getBody();
        if ($response->getStatusCode() >= 400) {
            throw new Exception('Download failed. Status ' . $response->getStatusCode());
        }
        if (strlen($body) < 100) {
            throw new Exception('Download not an archive: ' . $body);
        }
        unset($response);

        if (substr($body, 0, 4) === "\x50\x4b\x03\x04") {
            $extractor = new Zip();
        } else {
            $extractor = new Tar();
        }
        if (file_exists($archive)) unlink($archive);
        file_put_contents($archive, $body);
        $this->info('Downloaded {b} bytes', ['b' => strlen($body)]);
        unset($body);

        if (is_dir($tmp)) $this->delTree($tmp);
        $extractor->open($archive);
        $extractor->extract($tmp);
        $extractor->close();
        $this->info('Extracted archive');

        $path = $this->getFilesPath($tmp);
        if (is_dir($target)) $this->delTree($target);
        rename($path, $target);
        file_put_contents($last, $version);

        unlink($archive);
        $this->delTree($tmp);
        $this->success('Downloaded {p} version {d}', ['p'=>$name, 'd' => $version]);
    }


    /**
     * Get all releases that have not been downloaded, yet
     *
     * @return array
     */
    protected function getDownloads()
    {
        $request = \EasyRequest\Client::request(self::API);
        $response = $request->send();
        $results = unserialize($response->getBody());

        $this->info('{cnt} extensions found', ['cnt' => count($results)]);

        $downloads = array();
        foreach ($results as $extension) {
            @list($type, $name) = explode(':', $extension['plugin'], 2);
            if (empty($name)) {
                $name = $type;
                $type = 'plugin';
            }
            $fullname = "$type/$name";

            if (empty($extension['downloadurl'])) {
                $this->error('No download for {ext}', ['ext' => $fullname]);
                file_put_contents(
                    $this->logfile,
                    $fullname . "\tno download URL\n",
                    FILE_APPEND
                );
                continue;
            }

            if ($this->needsDownload($fullname, $extension['lastupdate'])) {
                $downloads[] = [
                    'name' => $fullname,
                    'url' => $extension['downloadurl'],
                    'date' => $extension['lastupdate']
                ];
            }
        }

        $this->info('{cnt} extensions need updating', ['cnt' => count($downloads)]);

        return ($downloads);
    }

    /**
     * Check if the version changes since last download
     *
     * @param string $fullname
     * @param string $date
     * @return bool
     */
    protected function needsDownload($fullname, $date)
    {
        $file = $this->datadir . '/meta/' . $fullname . '.last';
        if (!file_exists($file)) return true;
        $last = trim(file_get_contents($file));
        return ($last != $date);
    }


    /**
     * Recursively delete a directory
     *
     * @link http://php.net/manual/de/function.rmdir.php#110489
     * @param string $dir
     * @return bool
     */
    protected function delTree($dir)
    {
        if (!is_dir($dir)) return false;
        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? $this->delTree("$dir/$file") : unlink("$dir/$file");
        }
        return rmdir($dir);
    }

    /**
     * Find the first directory containing php files
     *
     * @param string $dir
     * @return string
     */
    protected function getFilesPath($dir)
    {
        $files = glob("$dir/*.php");
        $dirs = glob("$dir/*", GLOB_ONLYDIR);

        if (!count($files) && count($dirs) === 1) {
            return $this->getFilesPath($dirs[0]); // go one deeper
        }
        return $dir;
    }

}