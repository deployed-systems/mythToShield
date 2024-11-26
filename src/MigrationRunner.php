<?php

declare(strict_types=1);

/**
 * This file is part of MythToShield.
 *
 * (c) Deployed Systems Software <info@deployed.systems>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Deployed\MythToShield;

use CodeIgniter\Database\MigrationRunner as CIMigrationRunner;

class MigrationRunner extends CIMigrationRunner
{
    /**
     * Add a history to the table.
     *
     * @param object $migration
     */
    public function addHistory($migration, int $batch): void
    {
        parent::addHistory($migration, $batch);
    }

    /**
     * Removes a single history
     *
     * @param object $history
     */
    public function removeHistory($history): void
    {
        parent::removeHistory($history);
    }
}
