<?php declare(strict_types=1);

namespace Tbessenreither\PhpMultithread\Dto;

use InvalidArgumentException;
use ReflectionClass;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\Uid\UuidV7;
use Tbessenreither\PhpMultithread\Service\RuntimeAutowireService;
use Tbessenreither\PhpMultithread\Trait\SerializeFunctionsTrait;


class ThreadDto
{
    use SerializeFunctionsTrait;

    private string $uuid;
    private ?ResponseDto $response = null;
    private ?string $runner = null;
    private ?int $batchNumber = null;

    public function __construct(
        private string $class,
        private string $method,
        private array $parameters = [],
    ) {
        $this->uuid = UuidV7::v7()->toRfc4122();

        if (!class_exists($this->class)) {
            throw new InvalidArgumentException("The given class does not exist. " . $this->class . "");
        }
        if (!method_exists($this->class, $this->method)) {
            throw new InvalidArgumentException("The given method does not exist in the class. " . $this->class . "::" . $this->method . "()");
        }
        // check if the given class has the RuntimeAutowireService::AUTOWIRE_TAG tag
        $reflection = new ReflectionClass($this->class);
        $hasTag = false;
        $attributes = $reflection->getAttributes();
        foreach ($attributes as $attr) {
            if ($attr->getName() === AsTaggedItem::class) {
                if ($instance = $attr->newInstance()->index === RuntimeAutowireService::AUTOWIRE_TAG) {
                    $hasTag = true;
                    break;
                }
            }
        }
        if (!$hasTag) {
            throw new InvalidArgumentException("The given class must be tagged with the AUTOWIRE_TAG tag to ensure proper runtime autowiring. " . $this->class . " >#[AsTaggedItem(RuntimeAutowireService::AUTOWIRE_TAG)]<");
        }
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function getClass(): string
    {
        return $this->class;
    }

    public function getClassName(): string
    {
        $parts = explode('\\', $this->class);

        return end($parts);
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function setResponse(ResponseDto $response): void
    {
        $this->response = $response;
    }

    public function hasResponse(): bool
    {
        return $this->response !== null;
    }

    public function getResponse(): ?ResponseDto
    {
        return $this->response;
    }

    /**
     * @param class-string $runner
     * @return void
     */
    public function setRunner(string $runner): void
    {
        $this->runner = $runner;
    }

    public function getRunner(): string
    {
        return $this->runner ?? 'n/a';
    }

    public function getRunnerName(): string
    {
        if ($this->runner === null) {
            return 'n/a';
        }

        $parts = explode('\\', $this->runner);

        return end($parts);
    }

    public function setBatchNumber(int $batchNumber): void
    {
        $this->batchNumber = $batchNumber;
    }

    public function getBatchNumber(): int
    {
        return $this->batchNumber ?? 0;
    }

}
