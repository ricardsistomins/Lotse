<?php

namespace app\tasks;

use Phalcon\Cli\Task;

/**
 * CLI task for triggering research pipeline operations.
 * Called by cron or manually from the command line.
 *
 * Usage:
 *   php cli.php lotse trigger "AI funding Germany 2025"
 */
class LotseTask extends Task
{
    /**
     * Trigger a research run from the command line.
     * First param is the search query.
     *
     * /var/www/lotse/ php cli.php lotse trigger "AI funding Germany 2025"
     */
    public function triggerAction(): void
    {
        $query = $this->dispatcher->getParam(0) ?? 'AI funding Germany';

        echo "Starting research run for: {$query}" . PHP_EOL;

        $runId = $this->orchestrator->run('cron', $query, null);

        echo "Run completed. ID: {$runId}" . PHP_EOL;
    }
}

