<?php declare(strict_types=1);

namespace Tbessenreither\PhpMultithread\Dto;


class ResourceUsageDto
{
    private ?int $maxMemory = null;

    public function __construct(
    ) {
    }

    public function setMaxMemory(int $maxMemory): self
    {
        $this->maxMemory = $maxMemory;

        return $this;
    }

    public function getMaxMemory(): ?int
    {
        return $this->maxMemory;
    }

    public function getMaxMemoryHumanReadable(): string
    {
        return $this->formatBytesHumanReadable($this->maxMemory);
    }

    private function formatBytesHumanReadable(?int $bytes): string
    {
        if ($bytes === null) {
            return 'N/A';
        }

        $units = ['KB', 'MB', 'GB', 'TB'];
        $power = $bytes > 0 ? floor(log($bytes, 1024)) : 0;

        return round($bytes / pow(1024, $power), 2) . ' ' . $units[$power];
    }

}
