<?php

namespace app\Provider\Response;

/**
* Holds the response returned by an LLM provider after a completion call.
* Passed back to the run orchestrator for finding extraction and logging.
*/
class LLMResponse
{
    /**
     * Construct 
     * 
     * @param string $content
     * @param string $model
     * @param int $inputTokens
     * @param int $outputTokens
     * @param int $latencyMs
     * @param bool $success
     * @param string|null $errorMessage
     */
    public function __construct(public readonly string $content, public readonly string $model, public readonly int $inputTokens, public readonly int $outputTokens, public readonly int $latencyMs, public readonly bool $success, public readonly ?string $errorMessage = null) {}
}

