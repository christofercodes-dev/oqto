<?php

namespace App\Filesystem;

use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Slår in en riktig Flysystem-adapter och gör listContents() ofarlig.
 *
 * Statamic listar asset-containrar direkt via adaptern (bl.a. vid
 * "statamic:stache:clear/warm", som körs vid varje deploy) och bryr sig
 * inte om Laravel-diskens "throw"-inställning. Om S3-bucketen saknar
 * behörighet, inte finns, eller inte är konfigurerad än skulle det annars
 * krascha hela deployen - inte bara integrationsimporten. Alla andra
 * operationer (get/put/exists m.m.) beter sig som vanligt och kan
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
            Log::warning('integrations_s3: kunde inte lista innehåll, hoppar över: '.$e->getMessage());
        }
    }

    public function fileExists(string $path): bool
    {
        return $this->adapter->fileExists($path);
    }

    public function directoryExists(string $path): bool
    {
        return $this->adapter->directoryExists($path);
    }

    public function write(string $path, string $contents, Config $config): void
    {
        $this->adapter->write($path, $contents, $config);
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        $this->adapter->writeStream($path, $contents, $config);
    }

    public function read(string $path): string
    {
        return $this->adapter->read($path);
    }

    public function readStream(string $path)
    {
        return $this->adapter->readStream($path);
    }

    public function delete(string $path): void
    {
        $this->adapter->delete($path);
    }

    public function deleteDirectory(string $path): void
    {
        $this->adapter->deleteDirectory($path);
    }

    public function createDirectory(string $path, Config $config): void
    {
        $this->adapter->createDirectory($path, $config);
    }

    public function setVisibility(string $path, string $visibility): void
    {
        $this->adapter->setVisibility($path, $visibility);
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

    public function move(string $source, string $destination, Config $config): void
    {
        $this->adapter->move($source, $destination, $config);
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        $this->adapter->copy($source, $destination, $config);
    }
}
