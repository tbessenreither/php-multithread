<?php declare(strict_types=1);

namespace Tbessenreither\PhpMultithread\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Tbessenreither\PhpMultithread\DataCollector\PhpMultithreadDataCollector;
use Tbessenreither\PhpMultithread\DataCollector\ThreadStatistics;
use Tbessenreither\PhpMultithread\Dto\ResponseDto;
use Tbessenreither\PhpMultithread\Dto\ThreadDto;
use Tbessenreither\PhpMultithread\Interface\ThreadRunnerInterface;
use Tbessenreither\PhpMultithread\Service\Runners\CommandRunner;
use Tbessenreither\PhpMultithread\Service\Runners\PcntlRunner;


class MultithreadService
{

    public function __construct(
        #[Autowire(service: PcntlRunner::class)]
        private ThreadRunnerInterface $primaryRunner,
        #[Autowire(service: CommandRunner::class)]
        private ThreadRunnerInterface $secondaryRunner,
        private PhpMultithreadDataCollector $phpMultithreadDataCollector,
    ) {
    }

    /**
     * @param ThreadDto[] $threadDto
     * @return ResponseDto[]
     */
    public function runThreads(array $threadDtos): array
    {
        $runner = $this->getRunner();
        $this->prepareThreadDtos(
            runner: CommandRunner::class,
            threadDtos: $threadDtos,
        );

        switch ($runner) {
            case PcntlRunner::class:

                return $this->primaryRunner->run(
                    threadDtos: $threadDtos,
                );
            case CommandRunner::class:
            default:

                return $this->secondaryRunner->run(
                    threadDtos: $threadDtos,
                );
        }
    }

    private function prepareThreadDtos(string $runner, array $threadDtos): void
    {
        foreach ($threadDtos as $threadDto) {
            $threadDto->setRunner($runner);
            $this->phpMultithreadDataCollector->addRun($threadDto);
        }
    }

    private function getRunner(): string
    {
        if (function_exists('pcntl_fork')) {
            return PcntlRunner::class;
        }

        return CommandRunner::class;
    }

}
