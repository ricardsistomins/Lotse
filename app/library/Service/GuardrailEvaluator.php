<?php

namespace app\Service;

/**
* Evaluates the quality of findings produced by a research run.
* !! Must be called after findings are saved, before report creation !!
*
* Returns one of three outcomes:
*   pass    — findings are solid, run may proceed to report creation
*   review  — findings have issues, run proceeds but requires QA attention
*   blocked — findings are too weak or incomplete, run cannot be approved
*/
class GuardrailEvaluator
{
    const STATUS_PASSED  = 'pass';
    const STATUS_BLOCKED = 'blocked';
    const STATUS_REVIEW  = 'review';
    
   /**
    * Evaluate a set of findings and return the guardrail status.
    *
    * what it checks:                                                                                                                         
    * 1. Source presence — no sources collected at all -> blocked                    
    * 2. Findings presence — LLM returned no findings -> blocked
    * 3. Extraction completeness — any finding missing title or finding_type -> blocked                                                                       
    * 4. Confidence score — any finding with confidence_score < 0.5 - review        
    * 5. High-impact fields — any finding missing funding_body or eligibility -> review                                                                        
    * 6. Risk flags — any finding with non-empty risk_flags -> review                
    * 7. Duplicate detection — any two findings share the same dedupe_hash -> review 
    *                                                                                
    * If none of the review or block rules trigger → pass.  
    * 
    * @param  array  $findings  Raw findings array decoded from LLM response
    * @param  int    $sourceCount  Number of sources collected during the run
    * @return string  'pass', 'review', or 'blocked'
    */
    public function evaluate(array $findings, int $sourceCount): string
    {
        // Rule 1 — no sources collected at all
        if ($sourceCount === 0) {
            return self::STATUS_BLOCKED;
        }

        // Rule 2 — LLM returned no findings
        if (empty($findings)) {
            return self::STATUS_BLOCKED;
        }

        // Rule 3 — extraction produced unparseable or incomplete results
        foreach ($findings as $finding) {
            if (empty($finding['title']) || empty($finding['finding_type'])) {
                return self::STATUS_BLOCKED;
            }
        }

        $needsReview = false;

        foreach ($findings as $finding) {
            // Rule 4 — low confidence score flags as review
            $confidence = (float)($finding['confidence_score'] ?? 0.0);

            if ($confidence < 0.5) {
                $needsReview = true;
            }

            // Rule 5 — high-impact fields missing flags as review
            if (empty($finding['funding_body']) || empty($finding['eligibility'])) {
                $needsReview = true;
            }

            // Rule 6 — risk flags present flags as review
            if (!empty($finding['risk_flags'])) {
                $needsReview = true;
            }
            
            // Rule 7 — duplicate findings flagged as review 
            $dedupeHashes = array_column($findings, 'dedupe_hash');   
            
            if (count($dedupeHashes) !== count(array_unique($dedupeHashes))) {            
                $needsReview = true;                                                      
            }      
        }

        return $needsReview ? self::STATUS_REVIEW : self::STATUS_PASSED;
    }
}

