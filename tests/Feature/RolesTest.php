<?php

namespace Tests\Feature;

use Statamic\Facades\Role;
use Tests\TestCase;

class RolesTest extends TestCase
{
    protected function role()
    {
        $role = Role::find('byraredaktor');

        $this->assertNotNull($role, 'Rollen byraredaktor finns inte.');

        return $role;
    }

    public function test_the_agency_role_can_manage_agencies(): void
    {
        $role = $this->role();

        foreach (['access cp', 'view agencies entries', 'create agencies entries', 'edit agencies entries', 'publish agencies entries', 'delete agencies entries'] as $permission) {
            $this->assertTrue($role->hasPermission($permission), "Rollen saknar \"{$permission}\".");
        }
    }

    public function test_the_agency_role_cannot_touch_anything_else(): void
    {
        $role = $this->role();

        foreach ([
            'view pages entries', 'edit pages entries', 'create pages entries',
            'view integrations entries', 'edit integrations entries', 'delete integrations entries',
            'edit foretagare_funktioner entries', 'create foretagare_funktioner entries', 'delete foretagare_funktioner entries',
            'view reviews entries', 'edit reviews entries',
            'delete assets assets', 'configure collections', 'manage users',
        ] as $permission) {
            $this->assertFalse($role->hasPermission($permission), "Rollen ska inte ha \"{$permission}\".");
        }
    }

    public function test_the_agency_role_can_pick_features_and_upload_a_logo(): void
    {
        $role = $this->role();

        $this->assertTrue($role->hasPermission('view foretagare_funktioner entries'));
        $this->assertTrue($role->hasPermission('view assets assets'));
        $this->assertTrue($role->hasPermission('upload assets assets'));
    }
}
