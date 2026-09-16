<?php

namespace App\Filesystem;

use Illuminate\Support\Facades\Log;
use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use Throwable;

/**
 * Slår in en riktig Flysystem-adapter och gör den ofarlig mot krascher,
 * eftersom "integrations_s3" i praktiken är en skrivskyddad bucket som
 * ägs och fylls av ett externt system (Sidekick) - vi har (medvetet)
 * bara läsbehörighet dit.
 *
 * Statamic försöker annars t.ex.:
 * - lista asset-containrar direkt via adaptern (bl.a. vid
 *   "statamic:stache:clear/warm", som körs vid varje deploy)
 * - skriva en ".meta"-cachefil bredvid varje bild första gången den visas
 *   (t.ex. via {{ avatar:url }} på en sida)
 * ...och bryr sig inte om Laravel-diskens "throw"-inställning. Utan det
 * här skulle en obehörig/otillgänglig/ännu okonfigurerad bucket krascha
 * hela deployen eller varje sida som visar en integrations-bild.
 *
 * Läsoperationer (get/read/exists m.m.) beter sig som vanligt och kan
 * fortfarande misslyckas tydligt, vilket är rätt när man faktiskt kör
 * "integrations:import" och vill se om något är fel.
 */
class ResilientListingAdapter implements FilesystemAdapter
{
    public function __construct(protected FilesystemAdapter $adapter)
    {
    }

    public function listContents(string $path, bool $deep): iterable
    {
        try {
            yield from $this->adapter->listContents($path, $deep);
        } catch (Throwable $e) {
            $this->logSwallowed('lista innehåll', $path, $e);
        }
    }

    public function write(string $path, string $contents, Config $config): void
    {
        $this->swallow('skriva fil', $path, fn () => $this->adapter->write($path, $contents, $config));
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        $this->swallow('skriva fil (stream)', $path, fn () => $this->adapter->writeStream($path, $contents, $config));
    }

    public function delete(string $path): void
    {
        $this->swallow('ta bort fil', $path, fn () => $this->adapter->delete($path));
    }

    public function deleteDirectory(string $path): void
    {
        $this->swallow('ta bort mapp', $path, fn () => $this->adapter->deleteDirectory($path));
    }

    public function createDirectory(string $path, Config $config): void
    {
        $this->swallow('skapa mapp', $path, fn () => $this->adapter->createDirectory($path, $config));
    }

    public function setVisibility(string $path, string $visibility): void
    {
        $this->swallow('sätta synlighet', $path, fn () => $this->adapter->setVisibility($path, $visibility));
    }

    public function move(string $source, string $destination, Config $config): void
    {
        $this->swallow('flytta fil', $source, fn () => $this->adapter->move($source, $destination, $config));
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        $this->swallow('kopiera fil', $source, fn () => $this->adapter->copy($source, $destination, $config));
    }

    /**
     * Kör en muterande operation men låter den aldrig krascha anroparen -
     * bucketen är skrivskyddad för oss, så det enda rimliga är att logga
     * och fortsätta (t.ex. utan cachad bildmetadata) istället för att
     * krascha sidan/kommandot som råkade trigga skrivförsöket.
     */
    protected function swallow(string $operation, string $path, callable $callback): void
    {
        try {
            $callback();
        } catch (Throwable $e) {
            $this->logSwallowed($operation, $path, $e);
        }
    }

    protected function logSwallowed(string $operation, string $path, Throwable $e): void
    {
        Log::warning("integrations_s3: kunde inte {$operation} för \"{$path}\", hoppar över: ".$e->getMessage());
    }

    public function fileExists(string $path): bool
    {
        return $this->adapter->fileExists($path);
    }

    public function directoryExists(string $path): bool
    {
        return $this->adapter->directoryExists($path);
    }

    public function read(string $path): string
    {
        return $this->adapter->read($path);
    }

    public function readStream(string $path)
    {
        return $this->adapter->readStream($path);
    }

    public function visibility(string $path): FileAttributes
    {
        return $this->adapter->visibility($path);
    }

    public function mimeType(string $path): FileAttributes
    {
        return $this->adapter->mimeType($path);
    }

    public function lastModified(string $path): FileAttributes
    {
        return $this->adapter->lastModified($path);
    }

    public function fileSize(string $path): FileAttributes
    {
        return $this->adapter->fileSize($path);
    }
}
