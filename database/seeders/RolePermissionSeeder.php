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
        // Phiếu chi
        'expenses.viewAny', 'expenses.view', 'expenses.create', 'expenses.update', 'expenses.delete', 'expenses.approve',
        // Nhà cung cấp
        'vendors.viewAny', 'vendors.view', 'vendors.create', 'vendors.update', 'vendors.delete',
        // Danh mục chi phí
        'expense_categories.viewAny', 'expense_categories.create', 'expense_categories.update', 'expense_categories.delete',
        // Phòng ban
        'departments.viewAny', 'departments.view', 'departments.create', 'departments.update', 'departments.delete',
        // Users
        'users.viewAny', 'users.view', 'users.create', 'users.update', 'users.delete',
        // Báo cáo
        'reports.viewAll', 'reports.viewDepartment',
        // Hệ thống
        'roles.manage',

        // ── Booking Module ────────────────────────────────────────────────────
        // Mạng lưới quảng cáo
        'ad_networks.viewAny', 'ad_networks.create', 'ad_networks.update', 'ad_networks.delete',
        // Brief
        'briefs.viewAny', 'briefs.view', 'briefs.create', 'briefs.update', 'briefs.delete',
        // Plan
        'plans.viewAny', 'plans.view', 'plans.create', 'plans.update', 'plans.delete',
        // Booking
        'bookings.viewAny', 'bookings.view', 'bookings.update',
        // Media Buying Order
        'media_buying_orders.viewAny', 'media_buying_orders.view',
        'media_buying_orders.create', 'media_buying_orders.update', 'media_buying_orders.delete',
        'media_buying_orders.approve_dept', 'media_buying_orders.approve_finance',
        // Màn hình (Screens)
        'screens.viewAny', 'screens.create', 'screens.update', 'screens.delete',
    ];

    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        foreach ($this->permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        // ─── CEO: Toàn quyền ─────────────────────────────────────────────────
        $ceo = Role::firstOrCreate(['name' => 'ceo', 'guard_name' => 'web']);
        $ceo->syncPermissions(Permission::all());

        // ─── COO: Toàn bộ operational, duyệt MBO ─────────────────────────────
        $coo = Role::firstOrCreate(['name' => 'coo', 'guard_name' => 'web']);
        $coo->syncPermissions(array_filter($this->permissions, fn ($p) =>
            ! in_array($p, ['roles.manage', 'users.delete', 'departments.delete'])
        ));

        // ─── Vice CEO: Xem & approve theo dept, duyệt MBO ───────────────────
        $viceCeo = Role::firstOrCreate(['name' => 'vice_ceo', 'guard_name' => 'web']);
        $viceCeo->syncPermissions([
            'customers.viewAny', 'customers.view',
            'customer_contacts.viewAny',
            'contracts.viewAny', 'contracts.view', 'contracts.update',
            'invoices.viewAny', 'invoices.view',
            'payment_schedules.viewAny', 'payment_schedules.view',
            'receipts.viewAny', 'receipts.view',
            'expenses.viewAny', 'expenses.view', 'expenses.approve',
            'vendors.viewAny', 'vendors.view',
            'expense_categories.viewAny',
            'departments.viewAny', 'departments.view',
            'users.viewAny', 'users.view',
            'reports.viewDepartment',
            // Booking module
            'ad_networks.viewAny',
            'screens.viewAny',
            'briefs.viewAny', 'briefs.view',
            'plans.viewAny', 'plans.view',
            'bookings.viewAny', 'bookings.view',
            'media_buying_orders.viewAny', 'media_buying_orders.view',
            'media_buying_orders.approve_dept',
        ]);

        // ─── Finance Manager: Full finance + duyệt MBO cấp 2 ────────────────
        $financeManager = Role::firstOrCreate(['name' => 'finance_manager', 'guard_name' => 'web']);
        $financeManager->syncPermissions([
            'customers.viewAny', 'customers.view', 'customers.create', 'customers.update',
            'customer_contacts.viewAny', 'customer_contacts.create', 'customer_contacts.update', 'customer_contacts.delete',
            'contracts.viewAny', 'contracts.view', 'contracts.create', 'contracts.update',
            'invoices.viewAny', 'invoices.view', 'invoices.create', 'invoices.update', 'invoices.delete',
            'payment_schedules.viewAny', 'payment_schedules.view', 'payment_schedules.create', 'payment_schedules.update',
            'receipts.viewAny', 'receipts.view', 'receipts.create', 'receipts.update',
            'expenses.viewAny', 'expenses.view', 'expenses.create', 'expenses.update', 'expenses.delete', 'expenses.approve',
            'vendors.viewAny', 'vendors.view', 'vendors.create', 'vendors.update', 'vendors.delete',
            'expense_categories.viewAny', 'expense_categories.create', 'expense_categories.update', 'expense_categories.delete',
            'departments.viewAny', 'departments.view',
            'users.viewAny', 'users.view',
            'reports.viewDepartment',
            // Booking module
            'ad_networks.viewAny',
            'screens.viewAny',
            'briefs.viewAny', 'briefs.view',
            'plans.viewAny', 'plans.view',
            'bookings.viewAny', 'bookings.view',
            'media_buying_orders.viewAny', 'media_buying_orders.view',
            'media_buying_orders.approve_finance',
        ]);

        // ─── Finance Staff: Thao tác cơ bản + xem booking ───────────────────
        $financeStaff = Role::firstOrCreate(['name' => 'finance_staff', 'guard_name' => 'web']);
        $financeStaff->syncPermissions([
            'customers.viewAny', 'customers.view', 'customers.create',
            'customer_contacts.viewAny', 'customer_contacts.create', 'customer_contacts.update',
            'contracts.viewAny', 'contracts.view',
            'invoices.viewAny', 'invoices.view', 'invoices.create', 'invoices.update',
            'payment_schedules.viewAny', 'payment_schedules.view', 'payment_schedules.update',
            'receipts.viewAny', 'receipts.view', 'receipts.create',
            'expenses.viewAny', 'expenses.view', 'expenses.create', 'expenses.update',
            'vendors.viewAny', 'vendors.view',
            'expense_categories.viewAny',
            // Booking module
            'bookings.viewAny', 'bookings.view',
            'media_buying_orders.viewAny', 'media_buying_orders.view',
        ]);

        // ─── Sale: Quản lý Brief, theo dõi Booking, tạo Contract ─────────────
        $sale = Role::firstOrCreate(['name' => 'sale', 'guard_name' => 'web']);
        $sale->syncPermissions([
            'customers.viewAny', 'customers.view', 'customers.create', 'customers.update',
            'customer_contacts.viewAny', 'customer_contacts.create', 'customer_contacts.update',
            'contracts.viewAny', 'contracts.view', 'contracts.create', 'contracts.update',
            'ad_networks.viewAny',
            'screens.viewAny',
            'briefs.viewAny', 'briefs.view', 'briefs.create', 'briefs.update', 'briefs.delete',
            'plans.viewAny', 'plans.view',
            'bookings.viewAny', 'bookings.view', 'bookings.update',
            'media_buying_orders.viewAny', 'media_buying_orders.view',
        ]);

        // ─── AdOps: Nhận Brief, tạo planning, tạo MBO, làm nghiệm thu ────────
        $adops = Role::firstOrCreate(['name' => 'adops', 'guard_name' => 'web']);
        $adops->syncPermissions([
            'customers.viewAny', 'customers.view',
            'contracts.viewAny', 'contracts.view',
            'ad_networks.viewAny',
            'screens.viewAny', 'screens.create', 'screens.update', 'screens.delete',
            'briefs.viewAny', 'briefs.view', 'briefs.update',
            'plans.viewAny', 'plans.view', 'plans.create', 'plans.update', 'plans.delete',
            'bookings.viewAny', 'bookings.view',
            'media_buying_orders.viewAny', 'media_buying_orders.view',
            'media_buying_orders.create', 'media_buying_orders.update', 'media_buying_orders.delete',
        ]);

        // ─── MBO Manager: Duyệt MBO, quản lý media buying ─────────────────────
        $mboManager = Role::firstOrCreate(['name' => 'mbo_manager', 'guard_name' => 'web']);
        $mboManager->syncPermissions([
            'customers.viewAny', 'customers.view',
            'contracts.viewAny', 'contracts.view',
            'ad_networks.viewAny',
            'screens.viewAny',
            'briefs.viewAny', 'briefs.view',
            'plans.viewAny', 'plans.view',
            'bookings.viewAny', 'bookings.view',
            'media_buying_orders.viewAny', 'media_buying_orders.view',
            'media_buying_orders.create', 'media_buying_orders.update', 'media_buying_orders.delete',
            'media_buying_orders.approve_dept',
        ]);

        // ─── Media Buyer: Nhận MBO, thực thi mua inventory ───────────────────
        $mediaBuyer = Role::firstOrCreate(['name' => 'media_buyer', 'guard_name' => 'web']);
        $mediaBuyer->syncPermissions([
            'contracts.viewAny', 'contracts.view',
            'bookings.viewAny', 'bookings.view',
            'media_buying_orders.viewAny', 'media_buying_orders.view',
            'media_buying_orders.create', 'media_buying_orders.update',
        ]);

        // ─── Departments ──────────────────────────────────────────────────────
        $financeDept = Department::firstOrCreate(
            ['code' => 'FIN'],
            ['name' => 'Tài chính', 'description' => 'Phòng Tài chính - Kế toán', 'is_active' => true]
        );

        $salesDept = Department::firstOrCreate(
            ['code' => 'SALES'],
            ['name' => 'Kinh doanh', 'description' => 'Phòng Kinh doanh / Sale', 'is_active' => true]
        );

        $adsDept = Department::firstOrCreate(
            ['code' => 'ADS'],
            ['name' => 'Quảng cáo', 'description' => 'Phòng AdOps / Media Buying', 'is_active' => true]
        );

        // ─── Default users ────────────────────────────────────────────────────
        $this->seedDefaultUsers($financeDept, $salesDept, $adsDept);

        $this->command->info('✅ Roles, Permissions, Departments, và default users đã được tạo.');
        $allRoles = ['ceo', 'coo', 'vice_ceo', 'finance_manager', 'finance_staff', 'sale', 'adops', 'mbo_manager', 'media_buyer'];
        $this->command->table(
            ['Role', 'Permissions'],
            collect($allRoles)->map(fn ($r) => [
                $r,
                Role::findByName($r)->permissions()->count() . ' permissions',
            ])->toArray()
        );
    }

    private function seedDefaultUsers(Department $financeDept, Department $salesDept, Department $adsDept): void
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
            // ── Booking team ─────────────────────────────────────────────────
            [
                'name'          => 'Sale 1',
                'email'         => 'sale1@att.vn',
                'password'      => Hash::make('Admin@123'),
                'department_id' => $salesDept->id,
                'role'          => 'sale',
            ],
            [
                'name'          => 'AdOps 1',
                'email'         => 'adops1@att.vn',
                'password'      => Hash::make('Admin@123'),
                'department_id' => $adsDept->id,
                'role'          => 'adops',
            ],
            [
                'name'          => 'Media Buyer 1',
                'email'         => 'buyer1@att.vn',
                'password'      => Hash::make('Admin@123'),
                'department_id' => $adsDept->id,
                'role'          => 'media_buyer',
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
