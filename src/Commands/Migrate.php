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
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Database\Forge;
use CodeIgniter\Exceptions\ConfigException;
use Deployed\MythToShield\MigrationRunner;
use Exception;

/**
 * Class MapCommand
 */
class Migrate extends BaseCommand
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
    protected $name = 'mythToShield:migrate';

    /**
     * Description
     *
     * @var string
     */
    protected $description = 'Migrate Myth Auth to CodeIgniter Shield';

    /**
     * Usage
     *
     * @var string
     */
    protected $usage = 'mythToShield:migrate';

    protected BaseConnection $db;
    private array $tables;
    private array $attributes;
    private Forge $forge;
    private array $mythHistory;

    /**
     * Run
     *
     * @param array $params Params
     *
     * @throws Exception
     */
    public function run(array $params): void
    {
        $this->db         = config('Database')::connect();
        $this->tables     = config('Auth')->tables;
        $this->attributes = ($this->db->getPlatform() === 'MySQLi') ? ['ENGINE' => 'InnoDB'] : [];
        $this->forge      = config('Database')::forge();

        if (! $this->checkMythAuthMigrated()) {
            CLI::write('Myth Auth Already Migrated', 'yellow');

            return;
        }

        if (! $this->checkMythTablesExists()) {
            CLI::write('MythAuth Tables Does Not Exist', 'red');

            return;
        }

        $this->ensureSettingsTables();

        $this->preMigrate($params);

        $this->ensureUsersTableName();
        $this->addMissingFieldsToUsersTable();
        $this->createIdentitiesTable();
        $this->migrateIdentities();
        $this->migrateLoginsTable();
        $this->createTokenLoginsTable();
        $this->migrateRememberTokensTable();
        $this->migrateAuthGroups();
        $this->migrateGroupsUsersTable();
        $this->migratePermissionsUsersTable();
        $this->dropAuthResetAttemptsTable();
        $this->dropAuthActivationAttemptsTable();
        $this->dropAuthGroupPermissionsTable();
        $this->dropGroupsTable();
        $this->dropPermissionsTable();

        $this->dropMythFieldsFromUsersTable();
        $this->writeShieldMigrationHistory();
        $this->clearMythMigrationHistory();

        $this->postMigrate($params);

        CLI::write('MythToShield migration completed successfully', 'green');
    }

    protected function ensureSettingsTables(): void
    {
        $runner = new MigrationRunner(config('Migrations'));
        $runner->setNamespace('CodeIgniter\Settings');
        $runner->latest();
    }

    protected function preMigrate(array $params): void
    {
        foreach (config('MythToShield')->preMigrateCommands as $command) {
            if (! is_subclass_of($command, BaseCommand::class)) {
                throw new ConfigException('error');
            }
            $c = new $command($this->logger, $this->commands);
            $c->run($params);
        }
    }

    protected function checkMythAuthMigrated(): bool
    {
        $runner = new MigrationRunner(config('Migrations'));
        $runner->setNamespace('Myth\Auth');
        $this->mythHistory = $runner->getHistory();

        return $this->mythHistory !== [];
    }

    protected function checkMythTablesExists(): bool
    {
        return $this->db->tableExists('users')
            && $this->db->tableExists('auth_logins')
            && $this->db->tableExists('auth_tokens')
            && $this->db->tableExists('auth_reset_attempts')
            && $this->db->tableExists('auth_activation_attempts')
            && $this->db->tableExists('auth_groups')
            && $this->db->tableExists('auth_permissions')
            && $this->db->tableExists('auth_groups_permissions')
            && $this->db->tableExists('auth_groups_users')
            && $this->db->tableExists('auth_users_permissions');
    }

    protected function ensureUsersTableName(): void
    {
        if ($this->tables['users'] === 'users') {
            return;
        }
        $this->forge->renameTable('users', $this->tables['users']);
    }

    protected function addMissingFieldsToUsersTable(): void
    {
        $this->forge->addColumn($this->tables['users'], [
            'last_active' => ['type' => 'datetime', 'null' => true, 'after' => 'active'],
        ]);

        $sql = 'update ' . $this->tables['users'] . ' set last_active = (SELECT max(date) from auth_logins where user_id = users.id limit 1 )';
        $this->db->query($sql);
    }

    protected function createIdentitiesTable(): void
    {
        /*
         * Auth Identities Table
         * Used for storage of passwords, access tokens, social login identities, etc.
         */
        $this->forge->addField([
            'id'           => ['type' => 'int', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'user_id'      => ['type' => 'int', 'constraint' => 11, 'unsigned' => true],
            'type'         => ['type' => 'varchar', 'constraint' => 255],
            'name'         => ['type' => 'varchar', 'constraint' => 255, 'null' => true],
            'secret'       => ['type' => 'varchar', 'constraint' => 255],
            'secret2'      => ['type' => 'varchar', 'constraint' => 255, 'null' => true],
            'expires'      => ['type' => 'datetime', 'null' => true],
            'extra'        => ['type' => 'text', 'null' => true],
            'force_reset'  => ['type' => 'tinyint', 'constraint' => 1, 'default' => 0],
            'last_used_at' => ['type' => 'datetime', 'null' => true],
            'created_at'   => ['type' => 'datetime', 'null' => true],
            'updated_at'   => ['type' => 'datetime', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['type', 'secret']);
        $this->forge->addKey('user_id');
        $this->forge->addForeignKey('user_id', $this->tables['users'], 'id', '', 'CASCADE');
        $this->forge->createTable($this->tables['identities'], false, $this->attributes);
    }

    protected function migrateIdentities(): void
    {
        $this->forge->addColumn($this->tables['identities'], [
            'myth_hash' => ['type' => 'tinyint', 'constraint' => 1, 'default' => 0, 'after' => 'last_used_at'],
        ]);

        $sql = 'insert into ' . $this->tables['identities'] .
        ' ( user_id, type, secret, secret2, force_reset, last_used_at, myth_hash, created_at, updated_at)
        select id, \'email_password\' as type, email as secret, password_hash as secret2, force_pass_reset as force_reset,
        (SELECT date from auth_logins where user_id = users.id limit 1 ) as last_used_at, 1, created_at, updated_at from users';
        $this->db->query($sql);
    }

    protected function migrateLoginsTable(): void
    {
        if ($this->tables['logins'] !== 'auth_logins') {
            $this->forge->renameTable('auth_logins', $this->tables['logins']);
        }

        $this->forge->addColumn($this->tables['logins'], [
            'user_agent' => ['type' => 'varchar', 'constraint' => 255, 'null' => true, 'after' => 'ip_address'],
            'id_type'    => ['type' => 'varchar', 'constraint' => 255, 'after' => 'user_agent'],
        ]);

        $this->forge->modifyColumn($this->tables['logins'], [
            'ip_address' => ['name' => 'ip_address', 'type' => 'varchar', 'constraint' => 255, 'null' => false],
            'email'      => ['name' => 'identifier', 'type' => 'varchar', 'constraint' => 255, 'null' => false],
        ]);

        $this->forge->dropKey($this->tables['logins'], 'email');
        $this->forge->addKey(['id_type', 'identifier']);
        $this->forge->processIndexes($this->tables['logins']);

        $this->db->table($this->tables['logins'])->update(
            ['id_type' => 'email']
        );
    }

    protected function createTokenLoginsTable(): void
    {
        $this->forge->addField([
            'id'         => ['type' => 'int', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'ip_address' => ['type' => 'varchar', 'constraint' => 255],
            'user_agent' => ['type' => 'varchar', 'constraint' => 255, 'null' => true],
            'id_type'    => ['type' => 'varchar', 'constraint' => 255],
            'identifier' => ['type' => 'varchar', 'constraint' => 255],
            'user_id'    => ['type' => 'int', 'constraint' => 11, 'unsigned' => true, 'null' => true], // Only for successful logins
            'date'       => ['type' => 'datetime'],
            'success'    => ['type' => 'tinyint', 'constraint' => 1],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['id_type', 'identifier']);
        $this->forge->addKey('user_id');
        // NOTE: Do NOT delete the user_id or identifier when the user is deleted for security audits
        $this->forge->createTable($this->tables['token_logins'], false, $this->attributes);
    }

    protected function migrateRememberTokensTable(): void
    {
        if ($this->tables['remember_tokens'] !== 'auth_tokens') {
            $this->forge->renameTable('auth_tokens', $this->tables['remember_tokens']);
        }
        $this->forge->addColumn($this->tables['remember_tokens'], [
            'created_at' => ['type' => 'datetime', 'null' => false],
            'updated_at' => ['type' => 'datetime', 'null' => false],
        ]);

        $query = $this->db->table($this->tables['remember_tokens']);
        $query->set('created_at', 'DATE_ADD(expires, INTERVAL -30 DAY)', false);
        $query->set('updated_at', 'DATE_ADD(expires, INTERVAL -30 DAY)', false);
        $query->update();
    }

    protected function migrateAuthGroups(): void
    {
        $groups      = $this->db->table('auth_groups')->get()->getResult();
        $permissions = $this->db->table('auth_permissions')->get()->getResult();

        $groupsPermissions = $this->db->table('auth_groups_permissions')
            ->select('auth_groups_permissions.*, auth_permissions.name as permissionName, auth_groups.name as groupName')
            ->join('auth_permissions', 'auth_permissions.id=auth_groups_permissions.permission_id')
            ->join('auth_groups', 'auth_groups.id=auth_groups_permissions.group_id')
            ->get()->getResult();

        if ($permissions !== []) {
            $permissions = array_reduce($permissions, static function ($carry, $permission) {
                $carry[$permission->name] = $permission->description;

                return $carry;
            }, []);
            service('settings')->set('AuthGroups.permissions', $permissions);
        }

        if ($groups !== []) {
            $groups = array_reduce($groups, static function ($carry, $group) {
                $carry[$group->name] = [
                    'title'       => $group->description,
                    'description' => $group->description,
                ];

                return $carry;
            });
            service('settings')->set('AuthGroups.groups', $groups);
        }

        if ($groupsPermissions !== []) {
            $groupsPermissions = array_reduce($groupsPermissions, static function ($carry, $groupPermission) {
                $carry[$groupPermission->groupName][] = $groupPermission->permissionName;

                return $carry;
            });
            service('settings')->set('AuthGroups.matrix', $groupsPermissions);
        }
    }

    protected function migrateGroupsUsersTable(): void
    {
        if ($this->tables['groups_users'] !== 'auth_groups_users') {
            $this->forge->renameTable('auth_groups_users', $this->tables['groups_users']);
        }

        $this->forge->dropForeignKey($this->tables['groups_users'], 'auth_groups_users_group_id_foreign');
        // $this->forge->dropForeignKey($this->tables['groups_users'], 'auth_groups_users_user_id_foreign');

        $this->dropKeys($this->tables['groups_users']);

        $idSql = 'ALTER TABLE ' . $this->tables['groups_users'] . ' ADD id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT FIRST, ADD PRIMARY KEY (id)';
        $this->db->query($idSql);

        $this->forge->addColumn($this->tables['groups_users'], [
            'group'      => ['type' => 'varchar', 'constraint' => 255, 'null' => false],
            'created_at' => ['type' => 'datetime', 'null' => false],
        ]);
        // $this->forge->addForeignKey('user_id', $this->tables['users'], 'id', '', 'CASCADE');

        $query = $this->db->table($this->tables['groups_users']);
        $query->set('group', '(SELECT auth_groups.name FROM auth_groups WHERE auth_groups.id = group_id)', false);
        $query->set('created_at', '(SELECT users.created_at FROM users WHERE users.id = user_id)', false);
        $query->update();

        $this->forge->dropColumn($this->tables['groups_users'], 'group_id');
    }

    protected function migratePermissionsUsersTable(): void
    {
        if ($this->tables['permissions_users'] !== 'auth_users_permissions') {
            $this->forge->renameTable('auth_users_permissions', $this->tables['permissions_users']);
        }

        $this->forge->dropForeignKey($this->tables['permissions_users'], 'auth_users_permissions_permission_id_foreign');
        $this->forge->dropForeignKey($this->tables['permissions_users'], 'auth_users_permissions_user_id_foreign');
        $this->dropKeys($this->tables['permissions_users']);

        $idSql = 'ALTER TABLE ' . $this->tables['permissions_users'] . ' ADD id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT FIRST, ADD PRIMARY KEY (id)';
        $this->db->query($idSql);

        $this->forge->addColumn($this->tables['permissions_users'], [
            'permission' => ['type' => 'varchar', 'constraint' => 255, 'null' => false],
            'created_at' => ['type' => 'datetime', 'null' => false],
        ]);
        $this->forge->addForeignKey('user_id', $this->tables['users'], 'id', '', 'CASCADE');

        $query = $this->db->table($this->tables['permissions_users']);
        $query->set('permission', '(SELECT auth_permissions.name FROM auth_permissions WHERE auth_permissions.id = permission_id)', false);
        $query->set('created_at', '(SELECT users.created_at FROM users WHERE users.id = user_id)', false);
        $query->update();

        $this->forge->dropColumn($this->tables['permissions_users'], 'permission_id');
    }

    protected function dropAuthResetAttemptsTable(): void
    {
        $this->forge->dropTable('auth_reset_attempts');
    }

    protected function dropAuthActivationAttemptsTable(): void
    {
        $this->forge->dropTable('auth_activation_attempts');
    }

    protected function dropAuthGroupPermissionsTable(): void
    {
        $this->forge->dropTable('auth_groups_permissions');
    }

    protected function dropGroupsTable(): void
    {
        $this->forge->dropTable('auth_groups');
    }

    protected function dropPermissionsTable(): void
    {
        $this->forge->dropTable('auth_permissions');
    }

    protected function dropMythFieldsFromUsersTable(): void
    {
        $this->forge->dropColumn($this->tables['users'], [
            'email',
            'password_hash',
            'reset_hash',
            'reset_at',
            'reset_expires',
            'activate_hash',
            'force_pass_reset',
        ]);
    }

    protected function writeShieldMigrationHistory(): void
    {
        $runner = new MigrationRunner(config('Migrations'));
        $runner->setNamespace('CodeIgniter\Shield');

        $migrations = $runner->findMigrations();

        foreach ($migrations as $migration) {
            if ($migration->namespace === 'CodeIgniter\Shield') {
                $runner->addHistory($migration, $runner->getLastBatch());
            }
        }
    }

    protected function clearMythMigrationHistory(): void
    {
        $runner = new MigrationRunner(config('Migrations'));

        foreach ($this->mythHistory as $history) {
            $runner->removeHistory($history);
        }
    }

    protected function dropKeys(string $table): void
    {
        $indexQuery = 'SHOW INDEXES FROM ' . $table;
        $keys       = [];

        foreach ($this->db->query($indexQuery)->getResult() as $index) {
            if (! in_array($index->Key_name, $keys, true)) {
                $keys[] = $index->Key_name;
            }
        }

        foreach ($keys as $key) {
            try {
                $this->forge->dropKey($table, $key);
            } catch (DatabaseException $e) {
                if ($e->getCode() !== 1553) {
                    throw $e;
                }
            }
        }
    }

    protected function postMigrate(array $params): void
    {
        foreach (config('MythToShield')->postMigrateCommands as $command) {
            if (! is_subclass_of($command, BaseCommand::class)) {
                throw new ConfigException('error');
            }
            $c = new $command($this->logger, $this->commands);
            $c->run($params);
        }
    }
}
