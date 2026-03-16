<?php

namespace app\Provider;

use app\Provider\Response\LLMResponse;

/**
 * Contract for all LLM provider implementations.
 *
 * Any LLM provider (OpenAI, Gemini, Claude) must implement this interface.
 * This allows the run orchestrator to call any provider the same way,
 * without knowing which provider is active. Swap providers by changing
 * the active adapter — no orchestrator changes needed.
 */
interface LLMProviderAdapter
{
    /**
     * Send a prompt to the LLM and return a structured response.
     *
     * @param string $prompt   The instruction or question sent to the model
     * @param array  $context  Optional additional data passed alongside the prompt
     */
    public function complete(string $prompt, array $context = []): LLMResponse;
}
