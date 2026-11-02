<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['code' => 'cod', 'name' => 'Cash on delivery', 'sort_order' => 1,
                'instructions' => 'Pay in cash when your order is delivered.'],
            ['code' => 'bank_transfer', 'name' => 'Bank transfer', 'sort_order' => 2,
                'instructions' => 'Our team will contact you with bank details after confirming your order. Your order is marked paid only after we verify the transfer.'],
        ];
        foreach ($rows as $r) {
            $m = PaymentMethod::firstOrNew(['code' => $r['code']]);
            $m->forceFill($r + ['is_manual' => true, 'is_active' => true])->save();
        }
    }
}
