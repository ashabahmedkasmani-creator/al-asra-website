<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /** Every number is computed from live database rows. */
    public function __invoke(Request $request)
    {
        $byStatus = Order::selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status');
        $stats = [
            'orders_total' => (int) $byStatus->sum(),
            'orders_by_status' => collect(OrderStatus::cases())->mapWithKeys(fn ($s) => [$s->value => (int) ($byStatus[$s->value] ?? 0)]),
            'orders_today' => Order::whereDate('created_at', today())->count(),
            'products_total' => Product::count(),
            'products_published' => Product::where('is_published', true)->count(),
            'products_unavailable' => Product::whereIn('availability', ['unavailable', 'discontinued'])->count(),
            'recent_orders' => Order::latest('id')->limit(5)->get(['reference', 'status', 'customer_name', 'grand_total', 'created_at']),
        ];

        // Financial figures and customer counts are admin-only (least privilege).
        if ($request->user()->hasPermission('reports.view')) {
            $stats['customers_total'] = User::where('role', Role::Customer->value)->count();
            $stats['revenue_paid'] = (float) Order::where('payment_status', PaymentStatus::Paid->value)->sum('grand_total');
            $stats['revenue_pending'] = (float) Order::where('payment_status', PaymentStatus::Pending->value)
                ->whereNotIn('status', [OrderStatus::Cancelled->value, OrderStatus::Returned->value])->sum('grand_total');
            $stats['orders_last_7_days'] = Order::where('created_at', '>=', now()->subDays(6)->startOfDay())
                ->selectRaw('DATE(created_at) as day, COUNT(*) as orders, SUM(grand_total) as total')
                ->groupBy('day')->orderBy('day')->get();
        }
        return response()->json(['data' => $stats]);
    }
}
