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

namespace Deployed\MythToShield\Authenticators;

use CodeIgniter\Shield\Authentication\Authenticators\Session;
use CodeIgniter\Shield\Authentication\Passwords;
use CodeIgniter\Shield\Models\UserIdentityModel;
use CodeIgniter\Shield\Result;

class MythCompatSession extends Session
{
    /**
     * Checks a user's $credentials to see if they match an
     * existing user.
     *
     * @phpstan-param array{email?: string, username?: string, password?: string} $credentials
     */
    public function check(array $credentials): Result
    {
        // Can't validate without a password.
        if (empty($credentials['password']) || count($credentials) < 2) {
            return new Result([
                'success' => false,
                'reason'  => lang('Auth.badAttempt'),
            ]);
        }

        // Remove the password from credentials, so we can
        // check afterword.
        $givenPassword = $credentials['password'];
        unset($credentials['password']);

        // Find the existing user
        $user = $this->provider->findByCredentials($credentials);

        if ($user === null) {
            return new Result([
                'success' => false,
                'reason'  => lang('Auth.badAttempt'),
            ]);
        }

        /** @var Passwords $passwords */
        $passwords = service('passwords');

        $emailIdentity = $user->getEmailIdentity();
        $myth_hash     = $emailIdentity->myth_hash;

        $hashedPassword = $myth_hash
            ? base64_encode(hash('sha384', $givenPassword, true))
            : $givenPassword;

        // Now, try matching the passwords.
        if (! $passwords->verify($hashedPassword, $user->password_hash)) {
            return new Result([
                'success' => false,
                'reason'  => lang('Auth.invalidPassword'),
            ]);
        }

        // Check to see if the password needs to be rehashed.
        // This would be due to the hash algorithm or hash
        // cost changing since the last time that a user
        // logged in.
        if ($myth_hash || $passwords->needsRehash($user->password_hash)) {
            $user->password_hash = $passwords->hash($givenPassword);
            $this->provider->save($user);
            if ($myth_hash) {
                $identityModel = new UserIdentityModel();
                $identityModel->setAllowedFields(['myth_hash']);
                $emailIdentity->myth_hash = false;
                $identityModel->save($emailIdentity);
            }
        }

        return new Result([
            'success'   => true,
            'extraInfo' => $user,
        ]);
    }
}
