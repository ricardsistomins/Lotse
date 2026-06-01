<?php

namespace app\tasks;

use Phalcon\Cli\Task;
use app\Storage\ResearchRunStorage;

class RunTask extends Task
{
    /**
     * Execute a research run by ID in the background.
     * The run row must already exist in the DB (created by dispatch()).
     *
     * Usage:
     *   php cli.php run execute <runId>
     */
    public function executeAction(): void
    {
        $runId = (int)($this->dispatcher->getParam(0) ?? 0);
        
        if (!$runId) {
            echo 'Usage: php cli.php run execute <runId>' . PHP_EOL;
            return;
        }
        
        $run = (new ResearchRunStorage())->getById($runId);
        
        if (!$run) {
            echo 'Run not found: ' . $runId . PHP_EOL;
            return;
        }
        
        $this->orchestrator->run(
            $run->triggerSource,
            $run->query,
            $run->createdByUserId,
            $this->db,
            $runId
        );
        
        echo 'Run completed: ' . $runId . PHP_EOL;
    }
}
