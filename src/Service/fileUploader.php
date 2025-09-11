<?php 

namespace App\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;

class fileUploader
{
  private $targetDirectory;

  public function __construct(string $targetDirectory)
  {
    $this->targetDirectory = $targetDirectory;
  }

  public function upload(UploadedFile $file): string
  {
    $newFilename = uniqid() . '.' . $file->getExtension();
    $file->move($this->targetDirectory, $newFilename);
    return $newFilename;
  }
}

