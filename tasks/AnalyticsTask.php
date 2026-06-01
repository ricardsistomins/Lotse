<?php

namespace app\tasks;

use Phalcon\Cli\Task;
use app\Storage\ReportRevisionStorage;
use app\Service\ReportAnalyticsService;

class AnalyticsTask extends Task
{
    /**
     * Generate analytics for a given report and revision.
     *
     * Usage:
     *   php cli.php analytics generate <reportId> <revisionId>
     */
    public function generateAction(): void 
    {
        $dispatcher = $this->dispatcher;
        
        $reportId = (int)($dispatcher->getParam(0) ?? 0);
        $revisionId = (int)($dispatcher->getParam(1) ?? 0);
        
        if (!$reportId || !$revisionId) {
            echo 'Usage: php cli.php analytics generate <reportId> <revisionId>' . PHP_EOL;
            return;
        }
        
        $revision = (new ReportRevisionStorage())->getById($revisionId);
        
        if (!$revision) {
            echo 'Revision not found: ' . $revisionId . PHP_EOL;
            return;
        }
        
        $structuredPayload = json_decode($revision->structuredPayload ?? '[]', true);
        
        (new ReportAnalyticsService())->generate($reportId, $revisionId, $structuredPayload, $revision->finalMarkdown ?? '');
        
        echo 'Analytics generated for report ' . $reportId . PHP_EOL;
    }
}
