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
        }

        return $needsReview ? self::STATUS_REVIEW : self::STATUS_PASSED;
    }
}

