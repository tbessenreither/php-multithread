<?php declare(strict_types=1);

namespace Tbessenreither\PhpMultithread\Interface;

use Tbessenreither\PhpMultithread\Dto\ResponseDto;
use Tbessenreither\PhpMultithread\Dto\ThreadDto;


interface ThreadRunnerInterface
{

    /**
     * @param ThreadDto[] $threads
     * @return ResponseDto[]
     */
    public function run(array $threadDtos): array;

}
