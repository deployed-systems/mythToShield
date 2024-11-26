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

namespace Deployed\MythToShield\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Exception;

/**
 * Class MapCommand
 */
class Cleanup extends BaseCommand
{
    /**
     * Group
     *
     * @var string
     */
    protected $group = 'mythToShield';

    /**
     * Command Name
     *
     * @var string
     */
    protected $name = 'mythToShield:cleanup';

    /**
     * Description
     *
     * @var string
     */
    protected $description = 'Cleanup Myth Auth Compatibility';

    /**
     * Usage
     *
     * @var string
     */
    protected $usage = 'mythToShield:cleanup';

    /**
     * Run
     *
     * @param array $params Params
     *
     * @throws Exception
     */
    public function run(array $params): void
    {
        $tables = config('Auth')->tables;
        $db     = config('Database')::connect();
        $forge  = config('Database')::forge();
        if ($db->fieldExists('myth_hash', $tables['identities'])) {
            $forge->dropColumn($tables['identities'], 'myth_hash');
        }
        CLI::write('Myth Auth Compatibility Column Deleted', 'green');
    }
}
