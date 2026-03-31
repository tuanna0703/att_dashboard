<?php

namespace Database\Seeders;

use App\Models\ExpenseCategory;
use Illuminate\Database\Seeder;

class ExpenseCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            // ─── Chi phí hợp đồng ─────────────────────────────────────────────
            [
                'name'      => 'Chi phí hợp đồng',
                'code'      => 'CP-HD',
                'type'      => 'contract',
                'is_active' => true,
                'children'  => [
                    ['name' => 'Vật tư / linh phụ kiện', 'code' => 'CP-HD-VT', 'type' => 'contract'],
                    ['name' => 'Nhân công / thi công',   'code' => 'CP-HD-NC', 'type' => 'contract'],
                    ['name' => 'Vận chuyển',              'code' => 'CP-HD-VC', 'type' => 'contract'],
                    ['name' => 'Chụp ảnh / nghiệm thu',  'code' => 'CP-HD-NT', 'type' => 'contract'],
                    ['name' => 'Thuê media / màn hình',  'code' => 'CP-HD-MH', 'type' => 'contract'],
                    ['name' => 'Chi phí khác (HĐ)',      'code' => 'CP-HD-KH', 'type' => 'contract'],
                ],
            ],

            // ─── Chi phí hành chính ───────────────────────────────────────────
            [
                'name'      => 'Chi phí hành chính',
                'code'      => 'CP-HC',
                'type'      => 'general',
                'is_active' => true,
                'children'  => [
                    ['name' => 'Văn phòng phẩm',              'code' => 'CP-HC-VPP', 'type' => 'general'],
                    ['name' => 'Điện / nước / internet',      'code' => 'CP-HC-DNN', 'type' => 'general'],
                    ['name' => 'Đi lại / xăng xe',            'code' => 'CP-HC-DL',  'type' => 'general'],
                    ['name' => 'Tiếp khách / hội nghị',       'code' => 'CP-HC-TK',  'type' => 'general'],
                    ['name' => 'Lương / thưởng nhân sự',      'code' => 'CP-HC-NS',  'type' => 'general'],
                    ['name' => 'Phí ngân hàng',               'code' => 'CP-HC-NH',  'type' => 'general'],
                    ['name' => 'Chi phí hành chính khác',     'code' => 'CP-HC-KH',  'type' => 'general'],
                ],
            ],
        ];

        foreach ($categories as $cat) {
            $children = $cat['children'] ?? [];
            unset($cat['children']);

            $parent = ExpenseCategory::firstOrCreate(['code' => $cat['code']], $cat);

            foreach ($children as $child) {
                ExpenseCategory::firstOrCreate(
                    ['code' => $child['code']],
                    array_merge($child, ['parent_id' => $parent->id, 'is_active' => true])
                );
            }
        }
    }
}
