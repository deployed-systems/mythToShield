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

namespace Deployed\MythToShield\Config;

use CodeIgniter\Config\BaseConfig;

class MythToShield extends BaseConfig
{
    /**
     * Commands in order to be executed before migration
     * value should contain list of classes which extend BaseCommand class
     * method run() of each class instance will be executed without any parameters
     *
     * @var list<string>
     */
    public array $preMigrateCommands = [];

    /**
     * Commands in order to be executed after migration
     * * value should contain list of classes which extend BaseCommand class
     * * method run() of each class instance will be executed without any parameters
     *
     * @var list<string>
     */
    public array $postMigrateCommands = [];
}
