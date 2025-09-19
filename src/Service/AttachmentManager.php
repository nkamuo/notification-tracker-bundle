<?php

declare(strict_types=1);

namespace Nkamuo\NotificationTrackerBundle\Service;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class AttachmentManager
{
    private readonly Filesystem $filesystem;

    public function __construct(
        private readonly string $attachmentDirectory,
        private readonly int $maxSize,
        private readonly array $allowedMimeTypes
    ) {
        $this->filesystem = new Filesystem();
    }

    public function store(string $content, string $filename): string
    {
        if (strlen($content) > $this->maxSize) {
            throw new \InvalidArgumentException(sprintf(
                'Attachment size exceeds maximum allowed size of %d bytes',
                $this->maxSize
            ));
        }

        $directory = $this->attachmentDirectory . '/' . date('Y/m/d');
        $this->filesystem->mkdir($directory);

        $path = $directory . '/' . uniqid() . '_' . $filename;
        file_put_contents($path, $content);

        return $path;
    }

    public function storeUploadedFile(UploadedFile $file): string
    {
        if (!in_array($file->getMimeType(), $this->allowedMimeTypes)) {
            throw new \InvalidArgumentException(sprintf(
                'File type %s is not allowed',
                $file->getMimeType()
            ));
        }

        if ($file->getSize() > $this->maxSize) {
            throw new \InvalidArgumentException(sprintf(
                'File size exceeds maximum allowed size of %d bytes',
                $this->maxSize
            ));
        }

        $directory = $this->attachmentDirectory . '/' . date('Y/m/d');
        $this->filesystem->mkdir($directory);

        $filename = uniqid() . '_' . $file->getClientOriginalName();
        $file->move($directory, $filename);

        return $directory . '/' . $filename;
    }

    public function delete(string $path): void
    {
        if ($this->filesystem->exists($path)) {
            $this->filesystem->remove($path);
        }
    }

    public function getContent(string $path): ?string
    {
        if (!$this->filesystem->exists($path)) {
            return null;
        }

        return file_get_contents($path);
    }
}