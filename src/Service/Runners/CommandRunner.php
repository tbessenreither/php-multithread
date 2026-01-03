<?php declare(strict_types=1);

namespace Tbessenreither\PhpMultithread\Service\Runners;

use Symfony\Component\Process\Process;
use Tbessenreither\PhpMultithread\Dto\ResponseDto;
use Tbessenreither\PhpMultithread\Dto\ThreadDto;
use Tbessenreither\PhpMultithread\Interface\ThreadRunnerInterface;
use Tbessenreither\PhpMultithread\Service\MultithreadSignatureService;


class CommandRunner implements ThreadRunnerInterface
{

    public function __construct(
        private MultithreadSignatureService $signatureService,
    ) {
    }

    /**
     * @param ThreadDto[] $threads
     * @return ResponseDto[]
     */
    public function run(array $threadDtos): array
    {
        $processes = [];
        $responseDtos = [];

        // Start processes for each ThreadDto
        foreach ($threadDtos as $threadDto) {
            $process = new Process([
                'php',
                'bin/console',
                'multithread:run'
            ]);
            $process->setInput($this->getSerializedAndSignedThreadDto($threadDto));
            $process->start();
            $processes[$threadDto->getUuid()] = $process;
        }

        // Wait for processes to finish and collect responses
        foreach ($processes as $uuid => $process) {
            $process->wait();
            $processOutput = $process->getOutput();
            $responseDto = ResponseDto::fromSerialized(
                serialized: $processOutput,
            );
            $responseDtos[$uuid] = $responseDto;
        }

        $this->updateThreadDtos($threadDtos, $responseDtos);

        return $responseDtos;
    }

    private function updateThreadDtos(array $threadDtos, array $responseDtos): void
    {
        foreach ($threadDtos as $threadDto) {
            $uuid = $threadDto->getUuid();
            if (isset($responseDtos[$uuid])) {
                $threadDto->setResponse($responseDtos[$uuid]);
            }
        }
    }

    private function getSerializedAndSignedThreadDto(ThreadDto $threadDto): string
    {
        return $this->signatureService->signString(
            string: $threadDto->getSerialized()
        );
    }

}
