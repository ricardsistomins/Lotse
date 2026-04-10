<?php

namespace app\Provider\LLM;

use app\Provider\LLMProviderAdapter;
use app\Provider\Response\LLMResponse;
use app\Storage\ProviderCallStorage;
use OpenAI;

/**
* OpenAI GPT implementation of LLMProviderAdapter.
* Receives credentials and model from the caller — adapter has no knowledge
* of how or where credentials are stored.
*/
class OpenAIAdapter implements LLMProviderAdapter
{
    private \OpenAI\Client $client;

    const STATUS_SUCCESS  = 'succeeded';
    const STATUS_FAILED   = 'failed';
    const PROVIDER_OPENAI = 'openai';
    const PROVIDER_KIND   = 'llm';
    
    /**
     * Constructor
     * 
     * @param string $apiKey
     * @param string $model
     * @param ProviderCallStorage $callStorage
     */
    public function __construct(private readonly string $apiKey, private readonly string $model, private readonly ProviderCallStorage $callStorage) {
        $this->client = OpenAI::client($this->apiKey);
    }

    /**
     * Completes AI response
     * 
     * @param string $prompt
     * @param array $context
     * @return LLMResponse
     */
    public function complete(string $prompt, array $context = []): LLMResponse
    {
        $start = microtime(true);

        try {
            $response = $this->client->chat()->create([
                'model'    => $this->model,
                'messages' => [
                    [
                        'role' => 'user', 
                        'content' => $prompt
                    ]
                ],
            ]);

            $latencyMs = (int) ((microtime(true) - $start) * 1000);

            $this->callStorage->log(
                providerKind:   self::PROVIDER_KIND,
                providerName:   self::PROVIDER_OPENAI,
                requestPurpose: $context['purpose'] ?? 'completion',
                status:         self::STATUS_SUCCESS,
                latencyMs:      $latencyMs,
                inputTokens:    $response->usage->promptTokens,
                outputTokens:   $response->usage->completionTokens,
                runId:          $context['run_id'] ?? null,
                fallbackUsed:   $context['fallback_used'] ?? false
            );
            
            return new LLMResponse(
                content:      $response->choices[0]->message->content,
                model:        $response->model,
                inputTokens:  $response->usage->promptTokens,
                outputTokens: $response->usage->completionTokens,
                latencyMs:    $latencyMs,
                success:      true
            );
        } catch (\Throwable $e) {
            $latencyMs = (int) ((microtime(true) - $start) * 1000);

            $this->callStorage->log(
                providerKind:   self::PROVIDER_KIND,
                providerName:   self::PROVIDER_OPENAI,
                requestPurpose: $context['purpose'] ?? 'completion',
                status:         self::STATUS_FAILED,
                latencyMs:      $latencyMs,
                inputTokens:    0,
                outputTokens:   0,
                runId:          $context['run_id'] ?? null,
                errorMessage:   $e->getMessage(),   
                fallbackUsed:   $context['fallback_used'] ?? false    
            );
            
            return new LLMResponse(
                content:      '',
                model:        $this->model,
                inputTokens:  0,
                outputTokens: 0,
                latencyMs:    $latencyMs,
                success:      false,
                errorMessage: $e->getMessage()
            );
        }
    }
}

