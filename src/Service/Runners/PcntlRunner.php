<?php declare(strict_types=1);

namespace Tbessenreither\PhpMultithread\Service\Runners;

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

        foreach ($threadDtos as $threadDto) {
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

        foreach ($pids as $uuid => $pid) {
            pcntl_waitpid($pid, $status);
            $socket = $sockets[$uuid];
            $content = stream_get_contents($socket);
            fclose($socket);

            if ($content) {
                $responseDtos[$uuid] = ResponseDto::fromSerialized($content);
            }
        }

        $this->updateThreadDtos($threadDtos, $responseDtos);

        return $responseDtos;
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

}
