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
     * @param array $pricing
     */
    public function __construct(private readonly string $apiKey, private readonly string $model, private readonly ProviderCallStorage $callStorage, private readonly array $pricing = []) {
        $this->client = OpenAI::factory()
            ->withApiKey($this->apiKey)
            ->withHttpClient(new \GuzzleHttp\Client([
                'timeout' => 120, 
                'connect_timeout' => 10
            ]))->make();
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
            $params = [
                'model' => $this->model,
                'messages' => [
                    [
                        'role'    => 'user',
                        'content' => $prompt 
                    ]
                ],
            ];
            
            if (!empty($context['reasoning_effort'])) {
                $params['reasoning_effort'] = $context['reasoning_effort'];
            }
            
            if (!empty($params['reasoning_effort'])) {
                $raw = (new \GuzzleHttp\Client(['timeout' => 300, 'connect_timeout' => 10]))->post('https://api.openai.com/v1/chat/completions', [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $this->apiKey,
                        'Content-Type'  => 'application/json',
                    ],
                    'json' => $params,
                ]);
                
                $body = json_decode($raw->getBody()->getContents(), true);
                
                $content = $body['choices'][0]['message']['content'] ?? '';
                $model = $body['model'] ?? $this->model;
                $inputTokens = $body['usage']['prompt_tokens'] ?? 0;
                $outputTokens = $body['usage']['completion_tokens'] ?? 0;
            } else {
                $response = $this->client->chat()->create($params);
                
                $content = $response->choices[0]->message->content;
                $model = $response->model;
                $inputTokens = $response->usage->promptTokens;
                $outputTokens = $response->usage->completionTokens;
            }
            
            $latencyMs = (int) ((microtime(true) - $start) * 1000);

            $this->callStorage->log(
                providerKind:   self::PROVIDER_KIND,
                providerName:   self::PROVIDER_OPENAI,
                requestPurpose: $context['purpose'] ?? 'completion',
                status:         self::STATUS_SUCCESS,
                latencyMs:      $latencyMs,
                inputTokens:    $inputTokens,
                outputTokens:   $outputTokens,
                runId:          $context['run_id'] ?? null,
                fallbackUsed:   $context['fallback_used'] ?? false,
                estimatedCostUsd: $this->calculateCost($model, $inputTokens, $outputTokens)    
            );
            
            return new LLMResponse(
                content:      $content,
                model:        $model,
                inputTokens:  $inputTokens,
                outputTokens: $outputTokens,
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
    
    /**
     * Calculate estimated cost of LLM call
     * 
     * @param string $model
     * @param int $inputTokens
     * @param int $outputTokens
     * @return float|null
     */
    private function calculateCost(string $model, int $inputTokens, int $outputTokens): ?float
    {
        $rates = $this->pricing[$model] ?? null;
        
        if (!$rates) {
            foreach ($this->pricing as $key => $r) {
                if (str_starts_with($model, $key)) {
                    $rates = $r;
                    break;
                }
            }
        }
        
        if (!$rates) {
            return null;
        }
        
        return ($inputTokens / 1_000_000 * $rates['input']) + ($outputTokens / 1_000_000 * $rates['output']);
    }
}

