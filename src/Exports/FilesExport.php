<?php

namespace Druc\LaravelWire\Exports;

use Druc\LaravelWire\NameOfFileInZip;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Finder\SplFileInfo;
use ZipArchive;

class FilesExport
{
    private array $paths;
    private array $excludedPaths;
    private string $exportPath;

    private array $files;

    public function __construct(array $params = [])
    {
        $this->paths = $params['paths'] ?? config('wire.paths', ['public', 'storage']);
        $this->excludedPaths = $params['excluded_paths'] ?? config('wire.excluded_paths', []);
        $this->exportPath = $params['zip_path'] ?? storage_path('wire/wire.zip');
    }

    public function path(): string
    {
        File::ensureDirectoryExists(storage_path('wire'));

        if (count($this->files()) === 0) {
            File::put($this->zipPath(), base64_decode("UEsFBgAAAAAAAAAAAAAAAAAAAAAAAA=="));
        } else {
            $zip = new ZipArchive();
            $zip->open($this->zipPath(), ZipArchive::CREATE | ZipArchive::OVERWRITE);

            foreach ($this->files() as $file) {
                $zip->addFile(
                    $file->getPathname(),
                    (string) new NameOfFileInZip($file->getPathname(), $this->zipPath(), base_path())
                );
            }

            $zip->close();
        }

        return $this->zipPath();
    }

    private function zipPath(): string
    {
        return $this->exportPath;
    }

    private function shouldExcludePath($path): bool
    {
        return Collection::make($this->excludedPaths())
            ->contains(function ($item) use ($path) {
                return Str::startsWith($path, $item);
            });
    }

    private function paths(): array
    {
        return Collection::make($this->paths)
            ->map(function ($path) {
                return base_path($path);
            })->toArray();
    }

    private function excludedPaths(): array
    {
        return Collection::make($this->excludedPaths)
            ->map(function ($path) {
                return base_path($path);
            })->toArray();
    }

    private function files(): array
    {
        if (isset($this->files)) {
            return $this->files;
        }

        $files = Collection::make([]);

        foreach ($this->paths() as $path) {
            if (File::isFile($path)) {
                $files->push(new SplFileInfo($path, $path, $path));
            } else {
                $files->push(...File::allFiles($path));
            }
        }

        return $this->files = $files->filter(function ($file) {
            return !$this->shouldExcludePath($file->getRealPath()) && $file->getRealPath() !== $this->zipPath();
        })->toArray();
    }
}
