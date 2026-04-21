<?php

namespace app\Service;

use app\Provider\LLM\OpenAIAdapter;                                           
use app\Storage\{                                                             
    ProviderCallStorage,                                                      
    ReportAnalyticsStorage,                                                   
    SystemSettingsStorage
};


class ReportAnalyticsService
{
    /**
     * Openai model used for analytics queries 
     */
    const ANALYTICS_MODEL = 'gpt-4o-mini';
    
    /**
     * Generate analytics prompt 
     * 
     * @param int $reportId
     * @param int $revisionId
     * @param array $structuredPayload
     * @param string $reportText
     * @return array
     * @throws \RuntimeException
     */
    public function generate(int $reportId, int $revisionId, array $structuredPayload, string $reportText = ''): array
    {
        $systemSettingsStorage = new SystemSettingsStorage;
        $profiles = $systemSettingsStorage->get('provider_profiles');
        $fallbackChain = $systemSettingsStorage->get('provider_fallback_chain');
        $chainNames = $fallbackChain['chain'] ?? (array)$fallbackChain;
        $firstProfile = $profiles[$chainNames[0]] ?? [];
        
        if (empty($firstProfile['llm']['api_key'])) {
            throw new \RuntimeException('LLM API key not configured');
        }
        
        $llmAdapter = new OpenAIAdapter($firstProfile['llm']['api_key'], self::ANALYTICS_MODEL, new ProviderCallStorage());
        
        $prompt = $this->buildPrompt($structuredPayload, $reportText);
        $response = $llmAdapter->complete($prompt, ['purpose' => 'report_analytics']);
        
        if (!$response->success) {
            throw new \RuntimeException('Analytics generation failed: ' . $response->errorMessage);
        }
        
        $raw = preg_replace('/^```(?:json)?\s*/m', '', $response->content);
        $raw = preg_replace('/```\s*$/m', '', $raw);
        $analytics = json_decode(trim($raw), true) ?? [];
        
        (new ReportAnalyticsStorage())->save($reportId, $revisionId, $analytics);
      
        return $analytics;  
    }
  
    /**
     * Build the OpenAI prompt for analytics generation.                                                                                   
     *                                          
     * @param array $structuredPayload                                                                                                  
     * @param string $reportText                                                                                                  
     * @return string                                                             
     */             
    private function buildPrompt(array $structuredPayload, string $reportText = ''): string
    {
        $findings = array_map([$this, 'mapFinding'], $structuredPayload);
        $findingsJson = json_encode($findings, JSON_PRETTY_PRINT);
        
        return <<<PROMPT
            You are a funding research analyst for German companies.

            Below are funding program findings. Return a single JSON object
            with this exact structure:

            {
                "report_de": "Full report text translated to German",
                "executive_summary": {
                    "en": ["Key finding 1", "Key finding 2", "Key finding 3"],                               
                    "de": ["Wichtige Erkenntnis 1", "Wichtige Erkenntnis 2", "Wichtige Erkenntnis 3"]
                },                                                                                           
                "recommendations": {
                    "en": "Which 1-2 programs to prioritize and why",                                        
                    "de": "Welche 1-2 Programme priorisiert werden sollten und warum"                        
                },                                                                                           
                "risk_summary": {                                                                            
                    "en": "Consolidated overview of risks across all programs, empty string if none",        
                    "de": "Konsolidierte Risikoübersicht aller Programme, leerer String wenn keine"          
                },
                "findings_analysis": [
                    {
                        "finding_key": "...",
                        "en": {
                            "description": "2-3 sentence description of the program",
                            "strong_sides": ["strength 1", "strength 2", "strength 3"],
                            "use_cases": "In which situation or for which company type this program is best suited",
                            "eligibility": "Translate eligibility text in English",
                            "next_steps": "When to apply, what documents are typically needed, who to contact"
                        },                                                                      
                        "de": {
                            "description": "2-3 Sätze Beschreibung des Programms",                
                            "strong_sides": ["Stärke 1", "Stärke 2", "Stärke 3"],
                            "use_cases": "Für welche Situation oder welchen Unternehmenstyp dieses Programm am besten geeignet ist",
                            "eligibility": "Übersetzter Berechtigungstext auf Deutsch",
                            "next_steps": "Wann zu bewerben, welche Dokumente benötigt werden, wen zu kontaktieren"
                        }                                                                       
                    }
                ]                                                               
            }               

            "report_de" must contain the full REPORT TEXT translated to German.
            findings_analysis must have one entry per finding in the same order.
            "executive_summary" must contain 3-5 bullet points summarizing the most relevant findings.
            "recommendations" must state which 1-2 programs to prioritize and clearly explain why.       
            "risk_summary" must consolidate all risk flags across programs into one paragraph — use empty string if no risks exist.                                                                   
            "next_steps" per finding must describe concrete actions: when to apply, what to prepare, who to contact.  
            Return only valid JSON. No explanation text.   
        
        
            FINDINGS:                                                                     
            {$findingsJson}
                
            REPORT TEXT:
            {$reportText}
            PROMPT;
    }    

    /**
     * Map a structured payload item to a flat array for the analytics prompt.    
     *                                                                        
     * @param array $item                       
     * @return array                        
     */   
    private function mapFinding(array $item): array {
        $n = $item['normalized'] ?? [];
        
        return [
            'title' => $item['title'] ?? '',
            'finding_key'        => $item['finding_key'] ?? '',
            'description'        => $n['description'] ?? '',
            'eligibility'        => $n['eligibility'] ?? '',
            'funding_amount_min' => $n['funding_amount_min'] ?? null,
            'funding_amount_max' => $n['funding_amount_max'] ?? null,
            'deadline'           => $n['deadline'] ?? null,
            'confidence_score'   => $item['confidence'] ?? 0
        ];
    }
}
