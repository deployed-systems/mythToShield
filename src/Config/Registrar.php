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

use Deployed\MythToShield\Authenticators\MythCompatSession;

class Registrar
{
    public static function Auth(): array
    {
        return ['authenticators' => ['session' => MythCompatSession::class]];
    }
}
