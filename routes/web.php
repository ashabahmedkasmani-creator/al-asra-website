<?php

use App\Http\Controllers\AdminPanelController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\DemoAccountController;
use App\Http\Controllers\StorefrontStaticController;
use Illuminate\Support\Facades\Route;

// Public storefront (the original animated Next.js export is served by Laravel).
Route::get('/', [StorefrontStaticController::class, 'home'])->name('store.home');
Route::get('/product/{slug}', [StorefrontStaticController::class, 'product'])
    ->where('slug', '[A-Za-z0-9_-]+')
    ->name('store.product');

foreach (['shop', 'cart', 'checkout', 'track', 'about', 'contact', 'faq'] as $page) {
    Route::get('/'.$page, [StorefrontStaticController::class, 'page'])
        ->defaults('page', $page)
        ->name('store.'.$page);
}

// Customer session authentication and account pages.
Route::middleware('guest')->group(function (): void {
    Route::get('/login', [DemoAccountController::class, 'loginPage'])->name('login');
    Route::post('/login', [DemoAccountController::class, 'login'])->middleware('throttle:login');
    Route::get('/register', [DemoAccountController::class, 'registerPage'])->name('register');
    Route::post('/register', [DemoAccountController::class, 'register'])->middleware('throttle:register');
});

Route::post('/logout', [DemoAccountController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

Route::middleware('auth')->group(function (): void {
    Route::get('/my-orders', [DemoAccountController::class, 'account'])->name('my-orders');
    Route::get('/my-account', [DemoAccountController::class, 'account'])->name('account');
    Route::post('/my-account/profile', [DemoAccountController::class, 'update'])->name('account.update');
    Route::get('/my-account/orders/{reference}/invoice', [InvoiceController::class, 'show'])
        ->name('account.invoice');
});

// Admin portal shell. Data and permissions are handled by protected Sanctum API endpoints.
Route::prefix('admin')->name('admin.')->group(function (): void {
    Route::redirect('/', '/admin/login');
    Route::get('/login', [AdminPanelController::class, 'login'])->name('login');
    Route::get('/dashboard', [AdminPanelController::class, 'dashboard'])->name('dashboard');
    Route::get('/{page}', [AdminPanelController::class, 'page'])
        ->whereIn('page', ['orders', 'products', 'categories', 'coupons', 'banners', 'faqs', 'reviews', 'customers', 'staff', 'settings', 'audit'])
        ->name('page');
    Route::get('/{page}/{id}', [AdminPanelController::class, 'detail'])
        ->whereIn('page', ['orders', 'products', 'coupons', 'banners', 'faqs'])
        ->where('id', 'new|[0-9]+')
        ->name('detail');
});

// Compatibility redirects for earlier admin URL naming.
Route::redirect('/admin-panel', '/admin/login');
Route::redirect('/admin-panel/{path}', '/admin/{path}')->where('path', '.*');
