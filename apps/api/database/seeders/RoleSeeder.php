<?php

namespace Database\Seeders;

use App\Enums\Channel;
use App\Enums\PermissionAction;
use App\Enums\ResourceType;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * System roles with baseline permissions; starting defaults that admins can adjust.
 */
class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = $this->seedBaselinePermissions();

        $this->seedRole('Admin', 'Full access across the instance.', true, $permissions->pluck('id')->all());

        $this->seedRole('Program Manager', 'Oversees a theme/program across projects.', true,
            $this->pick($permissions, [
                [ResourceType::Beneficiary, PermissionAction::View, Channel::Any],
                [ResourceType::Beneficiary, PermissionAction::Export, Channel::Any],
                [ResourceType::Household, PermissionAction::View, Channel::Any],
                [ResourceType::FormResponse, PermissionAction::View, Channel::Any],
                [ResourceType::FormResponse, PermissionAction::Approve, Channel::Any],
                [ResourceType::FormResponse, PermissionAction::Reject, Channel::Any],
                [ResourceType::FormDefinition, PermissionAction::Assign, Channel::Web],
                [ResourceType::Report, PermissionAction::View, Channel::Any],
                [ResourceType::Report, PermissionAction::Export, Channel::Any],
                [ResourceType::Dashboard, PermissionAction::View, Channel::Any],
            ])
        );

        $this->seedRole('Project Manager', 'Runs projects; assigns field staff; verifies data.', true,
            $this->pick($permissions, [
                [ResourceType::Beneficiary, PermissionAction::View, Channel::Any],
                [ResourceType::Beneficiary, PermissionAction::Create, Channel::Any],
                [ResourceType::Beneficiary, PermissionAction::Edit, Channel::Any],
                [ResourceType::Beneficiary, PermissionAction::SoftDelete, Channel::Web],
                [ResourceType::Household, PermissionAction::View, Channel::Any],
                [ResourceType::Household, PermissionAction::SoftDelete, Channel::Web],
                [ResourceType::FormResponse, PermissionAction::View, Channel::Any],
                [ResourceType::FormResponse, PermissionAction::Approve, Channel::Web],
                [ResourceType::FormResponse, PermissionAction::Reject, Channel::Web],
                [ResourceType::FormDefinition, PermissionAction::Assign, Channel::Web],
                [ResourceType::Report, PermissionAction::View, Channel::Any],
                [ResourceType::Report, PermissionAction::Export, Channel::Any],
                [ResourceType::Dashboard, PermissionAction::View, Channel::Any],
            ])
        );

        $this->seedRole('Field Staff', 'Registers beneficiaries and captures activity data on the ground.', true,
            $this->pick($permissions, [
                [ResourceType::Beneficiary, PermissionAction::View, Channel::Any],
                [ResourceType::Beneficiary, PermissionAction::Create, Channel::Any],
                [ResourceType::Beneficiary, PermissionAction::Edit, Channel::Any],
                [ResourceType::Beneficiary, PermissionAction::Search, Channel::Any],
                [ResourceType::Household, PermissionAction::View, Channel::Any],
                [ResourceType::Household, PermissionAction::Create, Channel::Any],
                [ResourceType::Household, PermissionAction::Edit, Channel::Any],
                [ResourceType::FormResponse, PermissionAction::View, Channel::Any],
                [ResourceType::FormResponse, PermissionAction::Create, Channel::Any],
                [ResourceType::FormResponse, PermissionAction::Edit, Channel::Any],
            ])
        );

        $this->seedRole('M&E', 'Ensures data quality and produces reports.', true,
            $this->pick($permissions, [
                [ResourceType::Beneficiary, PermissionAction::View, Channel::Any],
                [ResourceType::FormResponse, PermissionAction::View, Channel::Any],
                [ResourceType::FormResponse, PermissionAction::Verify, Channel::Any],
                [ResourceType::Report, PermissionAction::View, Channel::Any],
                [ResourceType::Report, PermissionAction::Export, Channel::Any],
                [ResourceType::Dashboard, PermissionAction::View, Channel::Any],
            ])
        );

        $this->seedRole('Management', 'Monitors impact and makes decisions across programs.', true,
            $this->pick($permissions, [
                [ResourceType::Report, PermissionAction::View, Channel::Any],
                [ResourceType::Report, PermissionAction::Export, Channel::Any],
                [ResourceType::Dashboard, PermissionAction::View, Channel::Any],
            ])
        );

        $this->seedRole('Viewer', 'Read-only scoped access (e.g. donor/external viewer).', true,
            $this->pick($permissions, [
                [ResourceType::Report, PermissionAction::View, Channel::Any],
                [ResourceType::Dashboard, PermissionAction::View, Channel::Any],
            ])
        );
    }

    /** @return Collection<int, Permission> */
    private function seedBaselinePermissions(): Collection
    {
        $resourceTypes = ResourceType::cases();
        $actions = PermissionAction::cases();

        $rows = [];
        foreach ($resourceTypes as $resourceType) {
            foreach ($actions as $action) {
                $rows[] = [
                    'resource_type' => $resourceType->value,
                    'action' => $action->value,
                    'channel' => Channel::Any->value,
                ];
            }
        }

        // Channel-specific override: soft-delete allowed only on web, never on mobile.
        foreach ([ResourceType::Beneficiary, ResourceType::Household] as $resourceType) {
            $rows[] = [
                'resource_type' => $resourceType->value,
                'action' => PermissionAction::SoftDelete->value,
                'channel' => Channel::Web->value,
            ];
        }

        foreach ($rows as $row) {
            Permission::query()->firstOrCreate($row);
        }

        return Permission::all();
    }

    private function pick(Collection $all, array $tuples): array
    {
        $ids = [];
        foreach ($tuples as [$resourceType, $action, $channel]) {
            $match = $all->first(fn (Permission $p) => $p->resource_type === $resourceType
                && $p->action === $action
                && $p->channel === $channel);

            if ($match !== null) {
                $ids[] = $match->id;
            }
        }

        return $ids;
    }

    private function seedRole(string $name, string $description, bool $isSystem, array $permissionIds): void
    {
        $role = Role::query()->firstOrCreate(
            ['name' => $name, 'project_id' => null],
            ['description' => $description, 'is_system' => $isSystem]
        );

        $role->permissions()->sync($permissionIds);
    }
}
