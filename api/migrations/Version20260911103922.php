<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Seeds roles and role_permissions per
 * docs/decisions/0002-roles-and-permissions.md. A future change to this
 * list ships as a new migration (CLAUDE.md — never edit a committed one).
 */
final class Version20260911103922 extends AbstractMigration
{
    private const ROLES = [
        'owner' => 'Owner',
        'admin' => 'Admin',
        'billing' => 'Billing',
        'stock' => 'Stock',
        'accountant' => 'Accountant',
        'read_only' => 'Read only',
    ];

    private const ALL_PERMISSIONS = [
        'company.manage', 'company.delete', 'members.manage', 'series.manage',
        'documents.issue', 'documents.cancel', 'documents.read',
        'customers.manage', 'customers.read',
        'products.manage', 'products.read',
        'stock.manage', 'stock.read',
        'purchases.manage', 'purchases.read',
        'accounts.read', 'reports.read',
    ];

    private const READ_ONLY_PERMISSIONS = [
        'documents.read', 'customers.read', 'products.read', 'stock.read',
        'purchases.read', 'accounts.read', 'reports.read',
    ];

    private const ROLE_PERMISSIONS = [
        'billing' => [
            'documents.issue', 'documents.cancel', 'documents.read', 'series.manage',
            'customers.manage', 'customers.read', 'accounts.read', 'reports.read',
        ],
        'stock' => [
            'products.manage', 'products.read', 'stock.manage', 'stock.read',
            'purchases.manage', 'purchases.read',
        ],
    ];

    public function getDescription(): string
    {
        return 'Seed roles and role_permissions (docs/decisions/0002-roles-and-permissions.md)';
    }

    public function up(Schema $schema): void
    {
        foreach (self::ROLES as $code => $name) {
            $this->addSql('INSERT INTO roles (code, name) VALUES (:code, :name)', ['code' => $code, 'name' => $name]);
        }

        foreach (self::ALL_PERMISSIONS as $permission) {
            $this->addSql(
                'INSERT INTO role_permissions (role_code, permission) VALUES (:role, :permission)',
                ['role' => 'owner', 'permission' => $permission],
            );
        }

        foreach (self::ALL_PERMISSIONS as $permission) {
            if ('company.delete' === $permission) {
                continue;
            }
            $this->addSql(
                'INSERT INTO role_permissions (role_code, permission) VALUES (:role, :permission)',
                ['role' => 'admin', 'permission' => $permission],
            );
        }

        foreach (self::ROLE_PERMISSIONS as $role => $permissions) {
            foreach ($permissions as $permission) {
                $this->addSql(
                    'INSERT INTO role_permissions (role_code, permission) VALUES (:role, :permission)',
                    ['role' => $role, 'permission' => $permission],
                );
            }
        }

        foreach (['accountant', 'read_only'] as $role) {
            foreach (self::READ_ONLY_PERMISSIONS as $permission) {
                $this->addSql(
                    'INSERT INTO role_permissions (role_code, permission) VALUES (:role, :permission)',
                    ['role' => $role, 'permission' => $permission],
                );
            }
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DELETE FROM role_permissions');
        $this->addSql('DELETE FROM roles');
    }
}
