<?php

namespace app\Service;

class DuplicateRunException extends \RuntimeException
{
    public function __construct(public readonly int $existingRunId) 
    {
        parent::__construct('Duplicate run detected for idempotency key');
    }
}