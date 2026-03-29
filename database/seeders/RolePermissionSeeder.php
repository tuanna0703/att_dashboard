<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    /**
     * Danh sách tất cả permissions theo module.
     * Format: {module}.{action}
     */
    private array $permissions = [
        // Khách hàng
        'customers.viewAny', 'customers.view', 'customers.create', 'customers.update', 'customers.delete',
        // Người liên hệ
        'customer_contacts.viewAny', 'customer_contacts.create', 'customer_contacts.update', 'customer_contacts.delete',
        // Hợp đồng
        'contracts.viewAny', 'contracts.view', 'contracts.create', 'contracts.update', 'contracts.delete',
        // Hoá đơn
        'invoices.viewAny', 'invoices.view', 'invoices.create', 'invoices.update', 'invoices.delete',
        // Đợt thanh toán
        'payment_schedules.viewAny', 'payment_schedules.view', 'payment_schedules.create', 'payment_schedules.update', 'payment_schedules.delete',
        // Phiếu thu
        'receipts.viewAny', 'receipts.view', 'receipts.create', 'receipts.update', 'receipts.delete',
        // Phòng ban
        'departments.viewAny', 'departments.view', 'departments.create', 'departments.update', 'departments.delete',
        // Users
        'users.viewAny', 'users.view', 'users.create', 'users.update', 'users.delete',
        // Báo cáo
        'reports.viewAll', 'reports.viewDepartment',
        // Hệ thống
        'roles.manage',
    ];

    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Tạo tất cả permissions
        foreach ($this->permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        // ─── CEO: Toàn quyền ─────────────────────────────────────────────────
        $ceo = Role::firstOrCreate(['name' => 'ceo', 'guard_name' => 'web']);
        $ceo->syncPermissions(Permission::all());

        // ─── COO: Toàn bộ operational, không quản lý roles/users ────────────
        $coo = Role::firstOrCreate(['name' => 'coo', 'guard_name' => 'web']);
        $coo->syncPermissions(array_filter($this->permissions, fn ($p) =>
            !in_array($p, ['roles.manage', 'users.delete', 'departments.delete'])
        ));

        // ─── Vice CEO: Xem & approve theo dept, không thao tác admin ────────
        $viceCeo = Role::firstOrCreate(['name' => 'vice_ceo', 'guard_name' => 'web']);
        $viceCeo->syncPermissions([
            'customers.viewAny', 'customers.view',
            'customer_contacts.viewAny',
            'contracts.viewAny', 'contracts.view', 'contracts.update',
            'invoices.viewAny', 'invoices.view',
            'payment_schedules.viewAny', 'payment_schedules.view',
            'receipts.viewAny', 'receipts.view',
            'departments.viewAny', 'departments.view',
            'users.viewAny', 'users.view',
            'reports.viewDepartment',
        ]);

        // ─── Finance Manager: Full finance, xem dept ─────────────────────────
        $financeManager = Role::firstOrCreate(['name' => 'finance_manager', 'guard_name' => 'web']);
        $financeManager->syncPermissions([
            'customers.viewAny', 'customers.view', 'customers.create', 'customers.update',
            'customer_contacts.viewAny', 'customer_contacts.create', 'customer_contacts.update', 'customer_contacts.delete',
            'contracts.viewAny', 'contracts.view', 'contracts.create', 'contracts.update',
            'invoices.viewAny', 'invoices.view', 'invoices.create', 'invoices.update', 'invoices.delete',
            'payment_schedules.viewAny', 'payment_schedules.view', 'payment_schedules.create', 'payment_schedules.update',
            'receipts.viewAny', 'receipts.view', 'receipts.create', 'receipts.update',
            'departments.viewAny', 'departments.view',
            'users.viewAny', 'users.view',
            'reports.viewDepartment',
        ]);

        // ─── Finance Staff: Thao tác cơ bản theo assignment ─────────────────
        $financeStaff = Role::firstOrCreate(['name' => 'finance_staff', 'guard_name' => 'web']);
        $financeStaff->syncPermissions([
            'customers.viewAny', 'customers.view', 'customers.create',
            'customer_contacts.viewAny', 'customer_contacts.create', 'customer_contacts.update',
            'contracts.viewAny', 'contracts.view',
            'invoices.viewAny', 'invoices.view', 'invoices.create', 'invoices.update',
            'payment_schedules.viewAny', 'payment_schedules.view', 'payment_schedules.update',
            'receipts.viewAny', 'receipts.view', 'receipts.create',
        ]);

        // ─── Seed Finance Department ─────────────────────────────────────────
        $financeDept = Department::firstOrCreate(
            ['code' => 'FIN'],
            ['name' => 'Tài chính', 'description' => 'Phòng Tài chính - Kế toán', 'is_active' => true]
        );

        // ─── Seed default users ───────────────────────────────────────────────
        $this->seedDefaultUsers($financeDept);

        $this->command->info('✅ Roles, Permissions, và default users đã được tạo.');
        $this->command->table(
            ['Role', 'Permissions'],
            collect(['ceo', 'coo', 'vice_ceo', 'finance_manager', 'finance_staff'])->map(fn ($r) => [
                $r,
                Role::findByName($r)->permissions()->count() . ' permissions',
            ])->toArray()
        );
    }

    private function seedDefaultUsers(Department $financeDept): void
    {
        $users = [
            [
                'name'          => 'CEO Admin',
                'email'         => 'ceo@att.vn',
                'password'      => Hash::make('Admin@123'),
                'department_id' => null,
                'role'          => 'ceo',
            ],
            [
                'name'          => 'Finance Manager',
                'email'         => 'finance.manager@att.vn',
                'password'      => Hash::make('Admin@123'),
                'department_id' => $financeDept->id,
                'role'          => 'finance_manager',
            ],
            [
                'name'          => 'Finance Staff 1',
                'email'         => 'finance.staff1@att.vn',
                'password'      => Hash::make('Admin@123'),
                'department_id' => $financeDept->id,
                'role'          => 'finance_staff',
            ],
        ];

        foreach ($users as $data) {
            $role = $data['role'];
            unset($data['role']);

            $user = User::firstOrCreate(['email' => $data['email']], $data);
            $user->syncRoles($role);
        }
    }
}
