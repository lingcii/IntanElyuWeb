<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class SpotImageService
{
    protected string $disk = 'public';

    public function sanitizeName(string $name): string
    {
        $name = strtolower(trim($name));
        $name = preg_replace('/[^a-z0-9\s.-]/', '', $name);
        $name = preg_replace('/\s+/', '_', $name);
        $name = preg_replace('/_+/', '_', $name);
        return trim($name, '_');
    }

    public function generateRelativePath(string $municipalityName, string $spotName, string $extension, bool $checkConflicts = true): string
    {
        $baseName = $this->sanitizeName($spotName);
        $municipalityDir = strtoupper(trim($municipalityName));
        $ext = ltrim($extension, '.');

        $filename = $baseName . '.' . $ext;
        $relative = 'municipalities/' . $municipalityDir . '/' . $filename;

        if (!$checkConflicts || !Storage::disk($this->disk)->exists($relative)) {
            return $relative;
        }

        $counter = 1;
        while (true) {
            $filename = $baseName . '_' . $counter . '.' . $ext;
            $relative = 'municipalities/' . $municipalityDir . '/' . $filename;
            if (!Storage::disk($this->disk)->exists($relative)) {
                return $relative;
            }
            $counter++;
        }
    }

    public function store(UploadedFile $file, string $municipalityName, string $spotName): string
    {
        $extension = $file->getClientOriginalExtension();
        $relativePath = $this->generateRelativePath($municipalityName, $spotName, $extension);

        $file->storeAs(dirname($relativePath), basename($relativePath), ['disk' => $this->disk]);

        return $relativePath;
    }
}
