<?php

declare(strict_types=1);

namespace Tbessenreither\PhpMultithread\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Tbessenreither\PhpMultithread\Dto\ThreadDto;
use Tbessenreither\PhpMultithread\Service\MultithreadService;
use Tbessenreither\PhpMultithread\Service\RuntimeAutowireService;

#[AsTaggedItem(RuntimeAutowireService::AUTOWIRE_TAG)]


class ParallelExecService
{
    private mixed $storedCallable;

    public function __construct(
        private MultithreadService $multithreadService,
        private LoggerInterface $logger,
    ) {
    }

    public function parallel(callable $callable, array $parameterArray, int $threads = 4): array
    {
        if (!$this->supportsForking()) {
            $this->logger->warning('Forking is not supported on this system. Falling back to synchronous execution.');

            return $this->fallbackSyncRunner($callable, $parameterArray);
        }
        $this->storedCallable = $callable;

        $chunks = array_chunk($parameterArray, (int) ceil(count($parameterArray) / $threads), true);
        $threads = [];
        foreach ($chunks as $chunk) {
            $threads[] = new ThreadDto(
                class: self::class,
                method: 'executeThread',
                parameters: [$chunk],
            );
        }
        $this->multithreadService->runThreads($threads);

        $results = [];
        foreach ($threads as $threadKey => $thread) {
            if ($thread->getResponse()->hasError()) {
                $this->logger->error("Thread $threadKey error: " . $thread->getResponse()->getError(), [
                    'threadId' => $thread->getUuid(),
                    'threadKey' => $threadKey,
                    'parameters' => $thread->getParameters(),
                    'error' => $thread->getResponse()->getError(),
                ]);
                continue;
            }
            $results = array_merge($results, $thread->getResponse()->getResult());
        }

        return $results;

    }

    public function executeThread(array $parameterArray): array
    {
        $callable = $this->storedCallable;

        $result = [];
        foreach ($parameterArray as $parameterKey => $parameters) {
            $result[$parameterKey] = call_user_func_array($callable, $parameters);
        }

        return $result;
    }

    private function fallbackSyncRunner(callable $callable, array $parameterArray): array
    {
        $result = [];
        foreach ($parameterArray as $parameterKey => $parameters) {
            $result[$parameterKey] = call_user_func_array($callable, $parameters);
        }

        return $result;
    }

    protected function supportsForking(): bool
    {
        return function_exists('pcntl_fork');
    }

}
