<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class AdminPanelController extends Controller
{
    private const NAVIGATION = [
        'dashboard' => 'Dashboard',
        'orders' => 'Orders',
        'products' => 'Products',
        'categories' => 'Categories',
        'coupons' => 'Coupons',
        'banners' => 'Banners',
        'faqs' => 'FAQs',
        'reviews' => 'Reviews',
        'customers' => 'Customers',
        'staff' => 'Staff',
        'settings' => 'Settings',
        'audit' => 'Audit log',
    ];

    public function login(): View
    {
        return $this->render('dashboard');
    }

    public function dashboard(): View
    {
        return $this->render('dashboard');
    }

    public function page(string $page): View
    {
        abort_unless(array_key_exists($page, self::NAVIGATION), 404);

        return $this->render($page);
    }

    public function detail(string $page, string $id): View
    {
        abort_unless(in_array($page, ['orders', 'products', 'coupons', 'banners', 'faqs'], true), 404);
        abort_unless($id === 'new' || ctype_digit($id), 404);

        return $this->render($page, $id);
    }

    private function render(string $page, ?string $id = null): View
    {
        return view('panel.page', [
            'page' => $page,
            'id' => $id,
            'nav' => self::NAVIGATION,
            'label' => self::NAVIGATION[$page] ?? 'Admin',
        ]);
    }
}
