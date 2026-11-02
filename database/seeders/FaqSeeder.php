<?php

namespace Database\Seeders;

use App\Models\Faq;
use Illuminate\Database\Seeder;

class FaqSeeder extends Seeder
{
    public function run(): void
    {
        $faqs = [
            ['How much is delivery?', 'Delivery is free on orders of PKR 10,000 or more. Smaller orders have a PKR 250 delivery charge, shown at checkout.'],
            ['How can I pay?', 'Cash on delivery or bank transfer.'],
            ['Do you deliver across Pakistan?', 'Yes. We ship from Karachi to all of Pakistan.'],
            ['How can I track my order?', 'Use the Track order page with your order number and phone number.'],
            ['When can I reach support?', 'Monday to Saturday, 11:00am to 8:30pm, on +92 315 9259927 or support@alasra.online.'],
        ];
        foreach ($faqs as $n => [$q, $a]) {
            Faq::firstOrCreate(['question' => $q], ['answer' => $a, 'sort_order' => $n + 1, 'is_active' => true]);
        }
    }
}
