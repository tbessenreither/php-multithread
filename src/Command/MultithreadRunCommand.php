<?php

declare(strict_types=1);

namespace Tbessenreither\PhpMultithread\Command;

use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Tbessenreither\PhpMultithread\Dto\ResponseDto;
use Tbessenreither\PhpMultithread\Dto\ThreadDto;
use Tbessenreither\PhpMultithread\Service\MultithreadSignatureService;
use Tbessenreither\PhpMultithread\Service\RuntimeAutowireService;

#[AsCommand(
    name: 'multithread:run',
    description: 'Runs a service method in a separate thread and returns the result serialized by php serialize() on stdout',
)]


class MultithreadRunCommand extends Command
{

    public function __construct(
        private MultithreadSignatureService $multithreadSignatureService,
        private RuntimeAutowireService $runtimeAutowireService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $responseDto = new ResponseDto();
        ob_start();
        try {
            $payload = $this->getPayload();
            $responseDto->setUuid(
                uuid: $payload->getUuid(),
            );

            $classInstance = $this->getClassInjection(
                class: $payload->getClass(),
            );

            $result = call_user_func_array(
                callback: [$classInstance, $payload->getMethod()],
                args: $payload->getParameters(),
            );

            $responseDto->setResult(
                result: $result,
            );

        } catch (Exception $e) {
            $responseDto->setError(
                error: $e,
            );
        }
        $output = ob_get_clean();
        $responseDto->setOutput(
            output: $output,
        );

        echo $responseDto->getSerialized();

        return $responseDto->hasError() ? Command::FAILURE : Command::SUCCESS;
    }

    private function getPayload(): ThreadDto
    {
        $serializedPayload = file_get_contents('php://stdin');

        $checkedPayload = $this->multithreadSignatureService->verify(
            data: $serializedPayload,
        );

        if ($checkedPayload === null) {
            throw new Exception('Invalid or tampered payload signature.');
        }

        return ThreadDto::fromSerialized(
            serialized: $checkedPayload,
        );

    }

    private function getClassInjection(string $class): object
    {
        return $this->runtimeAutowireService->create($class);
    }

}
