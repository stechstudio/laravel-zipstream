<?php

namespace STS\ZipStream;

use Illuminate\Support\Collection;
use STS\ZipStream\Contracts\FileContract;

class Queue extends Collection
{
    public function addItem(FileContract $file): self
    {
        if ($this->has($file->getZipPath()) && config('zipstream.conflict_strategy') === 'rename') {
            $file = $this->uniqueZipPath($file);
        }

        if (!$this->has($file->getZipPath()) || config('zipstream.conflict_strategy') === 'replace') {
            $this->put($file->getZipPath(), $file);
        }

        return $this;
    }

    protected function uniqueZipPath(FileContract $file): FileContract
    {
        $filename = pathinfo($file->getZipPath(), PATHINFO_FILENAME);
        $extension = rtrim('.' . pathinfo($file->getZipPath(), PATHINFO_EXTENSION), '.');

        $i = 1;
        while ($this->has($filename . '_' . $i)) {
            $i++;
        }

        return $file->setZipPath($filename . '_' . $i . $extension);
    }
}