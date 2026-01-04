<?php declare(strict_types=1);

namespace Tbessenreither\PhpMultithread\Service;

use Psr\Container\ContainerInterface;


final class RuntimeAutowireService
{
    public const AUTOWIRE_TAG = 'php_multithread.runtime_service';

    public function __construct(
        private ContainerInterface $locator
    ) {
    }

    public function create(string $class): object
    {
        return $this->locator->get($class);

    }

}
