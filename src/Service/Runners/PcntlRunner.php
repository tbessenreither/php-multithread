<?php declare(strict_types=1);

namespace Tbessenreither\PhpMultithread\Service\Runners;

use RuntimeException;
use Tbessenreither\PhpMultithread\DataCollector\PhpMultithreadDataCollector;
use Tbessenreither\PhpMultithread\Dto\ResponseDto;
use Tbessenreither\PhpMultithread\Dto\ThreadDto;
use Tbessenreither\PhpMultithread\Interface\ThreadRunnerInterface;
use Tbessenreither\PhpMultithread\Service\RuntimeAutowireService;
use Throwable;


class PcntlRunner implements ThreadRunnerInterface
{

    public function __construct(
        private RuntimeAutowireService $runtimeAutowireService,
    ) {
    }

    /**
     * @param ThreadDto[] $threadDtos
     * @return ResponseDto[]
     */
    public function run(array $threadDtos): array
    {
        $sockets = [];
        $pids = [];
        $responseDtos = [];
        $threadsDtoByUuid = [];
        $startingTime = time();

        $this->startThreads(
            threadDtos: $threadDtos,
            threadsDtoByUuid: $threadsDtoByUuid,
            sockets: $sockets,
            pids: $pids,
        );

        $this->waitForThreadsToFinish(
            pids: $pids,
            threadsDtoByUuid: $threadsDtoByUuid,
            sockets: $sockets,
            responseDtos: $responseDtos,
            startingTime: $startingTime,
        );

        $this->updateThreadDtos(
            threadDtos: $threadDtos,
            responseDtos: $responseDtos,
        );

        return $responseDtos;
    }

    private function startThreads(array $threadDtos, array &$threadsDtoByUuid, array &$sockets, array &$pids): void
    {
        foreach ($threadDtos as $threadDto) {
            $threadsDtoByUuid[$threadDto->getUuid()] = $threadDto;

            $domain = (strtoupper(substr(PHP_OS, 0, 3)) == 'WIN' ? STREAM_PF_INET : STREAM_PF_UNIX);
            $socketsPair = stream_socket_pair($domain, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
            if (!$socketsPair) {
                continue;
            }

            $pid = pcntl_fork();

            if ($pid == -1) {
                continue;
            } elseif ($pid) {
                // Parent
                fclose($socketsPair[0]); // Close child's socket
                $sockets[$threadDto->getUuid()] = $socketsPair[1];
                $pids[$threadDto->getUuid()] = $pid;
            } else {
                // Child
                fclose($socketsPair[1]); // Close parent's socket

                $this->fixPcntlIssues();

                $responseDto = new ResponseDto();
                $responseDto->setUuid($threadDto->getUuid());

                ob_start();
                try {
                    $classInstance = $this->runtimeAutowireService->create($threadDto->getClass());
                    $result = call_user_func_array(
                        [$classInstance, $threadDto->getMethod()],
                        $threadDto->getParameters()
                    );
                    $responseDto->setResult($result);
                } catch (Throwable $e) {
                    $responseDto->setError($e);
                }
                $output = ob_get_clean();
                $responseDto->setOutput($output);

                fwrite($socketsPair[0], $responseDto->getSerialized());
                fclose($socketsPair[0]);
                exit(0);
            }
        }
    }

    private function waitForThreadsToFinish(array $pids, array &$threadsDtoByUuid, array &$sockets, array &$responseDtos, int $startingTime): void
    {
        foreach ($pids as $uuid => $pid) {
            try {
                $threadDto = $threadsDtoByUuid[$uuid];
                $timeout = $threadDto->getTimeout();
                $status = null;
                $socket = $sockets[$uuid];
                $resourceUsage = [];

                if ($timeout !== null) {
                    $this->waitForThreadWithTimeout(
                        uuid: $uuid,
                        pid: $pid,
                        timeout: $timeout,
                        startingTime: $startingTime,
                        resourceUsage: $resourceUsage,
                    );
                } else {
                    pcntl_waitpid($pid, $status, 0, $resourceUsage);
                }

                $responseDtos[$uuid] = $this->getDataFromSocket($socket);
                if (isset($resourceUsage['ru_maxrss'])) {
                    $responseDtos[$uuid]->getResourceUsageDto()->setMaxMemory($resourceUsage['ru_maxrss']);
                }

            } catch (Throwable $e) {
                $responseDtos[$uuid] = new ResponseDto(
                    uuid: $uuid,
                    error: $e,
                );
            } finally {
                fclose($socket);
            }
        }
    }

    private function getDataFromSocket($socket): ResponseDto
    {
        $content = stream_get_contents($socket);
        if ($content) {
            return ResponseDto::fromSerialized($content);
        }

        throw new RuntimeException("No data received from socket.");
    }

    private function waitForThreadWithTimeout(string $uuid, int $pid, int $timeout, int $startingTime, array &$resourceUsage): void
    {
        while (true) {
            $res = pcntl_waitpid($pid, $status, WNOHANG, $resourceUsage);
            if ($res == -1 || $res > 0) {
                break;
            }

            if ((time() - $startingTime) > $timeout) {
                // timeout reached: try graceful termination, then force
                @posix_kill($pid, SIGTERM);
                usleep(200000); // 200ms grace
                if (pcntl_waitpid($pid, $status, WNOHANG, $resourceUsage) == 0) {
                    @posix_kill($pid, SIGKILL);
                    pcntl_waitpid($pid, $status, 0, $resourceUsage);
                }

                throw new RuntimeException("Thread {$uuid} timed out and terminated after {$timeout} seconds.");
            }
            usleep(100000); // 100ms poll interval
        }
    }

    /**
     *
     * @param ThreadDto[] $threadDtos
     * @param ResponseDto[] $responseDtos
     * @return void
     */
    private function updateThreadDtos(array $threadDtos, array $responseDtos): void
    {
        foreach ($threadDtos as $threadDto) {
            $uuid = $threadDto->getUuid();
            if (isset($responseDtos[$uuid])) {
                $threadDto->setResponse($responseDtos[$uuid]);
            }
        }
    }

    private function fixPcntlIssues(): void
    {
        $this->fixPcntlRandIssues();
    }

    private function fixPcntlRandIssues(): void
    {
        for ($i = 0; $i < random_int(0, 1024); $i++) {
            usleep(random_int(0, 10));
            rand(random_int(0, 512), random_int(513, 1024));
        }
    }

}
