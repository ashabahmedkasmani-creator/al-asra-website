<?php

use App\Http\Controllers\Api\Admin;
use App\Http\Controllers\Api\Auth\AddressController;
use App\Http\Controllers\Api\Auth\AuthController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CatalogController;
use App\Http\Controllers\Api\CheckoutController;
use App\Http\Controllers\Api\ContentController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\TrackingController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\SupportTicketController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:api')->group(function () {

    Route::get('health', HealthController::class);

    // ---- Public storefront -------------------------------------------------
    Route::get('home', [ContentController::class, 'home']);
    Route::get('products', [CatalogController::class, 'index']);
    Route::get('products/{slug}', [CatalogController::class, 'show']);
    Route::get('products/{slug}/reviews', [ReviewController::class, 'index']);
    Route::get('categories', [CatalogController::class, 'categories']);
    Route::get('banners', [ContentController::class, 'banners']);
    Route::get('faqs', [ContentController::class, 'faqs']);
    Route::get('settings', [ContentController::class, 'settings']);
    Route::get('payment-methods', [ContentController::class, 'paymentMethods']);
    Route::get('shipping-zones', fn () => response()->json(['data' => \App\Models\ShippingZone::where('is_active', true)->orderBy('name')->get()]));

    // ---- Auth (public) -----------------------------------------------------
    Route::post('auth/register', [AuthController::class, 'register'])->middleware('throttle:register');
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('auth/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:password');
    Route::post('auth/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:password');

    // ---- Cart / checkout / tracking (guests allowed) -----------------------
    Route::get('cart', [CartController::class, 'show']);
    Route::post('cart/items', [CartController::class, 'addItem']);
    Route::patch('cart/items/{item}', [CartController::class, 'updateItem'])->whereNumber('item');
    Route::delete('cart/items/{item}', [CartController::class, 'removeItem'])->whereNumber('item');
    Route::delete('cart', [CartController::class, 'clear']);
    Route::post('checkout/quote', [CheckoutController::class, 'quote'])->middleware('throttle:quote');
    Route::post('checkout', [CheckoutController::class, 'store'])->middleware('throttle:checkout');
    Route::post('orders/track', [TrackingController::class, 'track'])->middleware('throttle:track');

    // ---- Authenticated customers ------------------------------------------
    Route::middleware(['auth:sanctum', 'active'])->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::post('auth/logout-all', [AuthController::class, 'logoutAll']);
        Route::get('me', [AuthController::class, 'me']);
        Route::patch('me', [AuthController::class, 'updateProfile']);
        Route::post('me/password', [AuthController::class, 'changePassword'])->middleware('throttle:sensitive');
        Route::get('notifications', [NotificationController::class, 'index']);
        Route::post('notifications/{id}/read', [NotificationController::class, 'read']);
        Route::post('notifications/read-all', [NotificationController::class, 'readAll']);
        Route::get('support/tickets', [SupportTicketController::class, 'index']);
        Route::post('support/tickets', [SupportTicketController::class, 'store']);
        Route::get('support/tickets/{ticket}', [SupportTicketController::class, 'show'])->whereNumber('ticket');
        Route::post('support/tickets/{ticket}/messages', [SupportTicketController::class, 'reply'])->whereNumber('ticket');

        Route::get('addresses', [AddressController::class, 'index']);
        Route::post('addresses', [AddressController::class, 'store']);
        Route::patch('addresses/{address}', [AddressController::class, 'update']);
        Route::delete('addresses/{address}', [AddressController::class, 'destroy']);

        Route::get('orders', [OrderController::class, 'index']);
        Route::get('orders/{reference}', [OrderController::class, 'show']);
        Route::get('orders/{reference}/invoice', [InvoiceController::class, 'show']);
        Route::post('orders/{reference}/cancel', [OrderController::class, 'cancel']);

        Route::post('products/{slug}/reviews', [ReviewController::class, 'store'])->middleware('throttle:sensitive');
    });

    // ---- Admin / staff -----------------------------------------------------
    Route::prefix('admin')->middleware(['auth:sanctum', 'active', 'throttle:admin'])->group(function () {
        Route::get('dashboard', Admin\DashboardController::class)->middleware('permission:dashboard.view');

        Route::middleware('permission:products.manage')->group(function () {
            Route::get('products', [Admin\ProductController::class, 'index']);
            Route::post('products/import-csv', Admin\ProductImportController::class)->middleware('throttle:uploads');
            Route::post('products', [Admin\ProductController::class, 'store']);
            Route::get('products/{product}', [Admin\ProductController::class, 'show']);
            Route::patch('products/{product}', [Admin\ProductController::class, 'update']);
            Route::delete('products/{product}', [Admin\ProductController::class, 'destroy']);
            Route::post('products/{product}/publish', [Admin\ProductController::class, 'publish']);
            Route::post('products/{product}/unpublish', [Admin\ProductController::class, 'unpublish']);

            Route::middleware('throttle:uploads')->group(function () {
                Route::post('products/{product}/images', [Admin\ProductImageController::class, 'store']);
                Route::post('products/{product}/images/{image}/replace', [Admin\ProductImageController::class, 'replace']);
            });
            Route::patch('products/{product}/images/{image}', [Admin\ProductImageController::class, 'update']);
            Route::delete('products/{product}/images/{image}', [Admin\ProductImageController::class, 'destroy']);

            Route::post('products/{product}/variants', [Admin\ProductVariantController::class, 'store']);
            Route::patch('products/{product}/variants/{variant}', [Admin\ProductVariantController::class, 'update']);
            Route::delete('products/{product}/variants/{variant}', [Admin\ProductVariantController::class, 'destroy']);
        });

        Route::middleware('permission:categories.manage')->group(function () {
            Route::get('categories', [Admin\CategoryController::class, 'index']);
            Route::post('categories', [Admin\CategoryController::class, 'store']);
            Route::patch('categories/{category}', [Admin\CategoryController::class, 'update']);
            Route::delete('categories/{category}', [Admin\CategoryController::class, 'destroy']);
        });

        Route::middleware('permission:orders.view')->group(function () {
            Route::get('orders', [Admin\OrderController::class, 'index']);
            Route::get('orders/{order}', [Admin\OrderController::class, 'show']);
        });
        Route::middleware('permission:orders.update')->group(function () {
            Route::patch('orders/{order}/assign', [Admin\OrderController::class, 'assign']);
            Route::patch('orders/{order}/status', [Admin\OrderController::class, 'updateStatus']);
            Route::put('orders/{order}/shipment', [Admin\OrderController::class, 'updateShipment']);
        });
        Route::middleware(['permission:payments.verify', 'throttle:sensitive'])->group(function () {
            Route::post('orders/{order}/payment/verify', [Admin\OrderController::class, 'verifyPayment']);
            Route::post('orders/{order}/payment/refund', [Admin\OrderController::class, 'refundPayment']);
        });

        Route::middleware('permission:customers.view')->group(function () {
            Route::get('customers', [Admin\CustomerController::class, 'index']);
            Route::get('customers/{user}', [Admin\CustomerController::class, 'show']);
        });

        Route::middleware(['permission:users.manage', 'throttle:sensitive'])->group(function () {
            Route::get('users', [Admin\UserController::class, 'index']);
            Route::post('users', [Admin\UserController::class, 'store']);
            Route::patch('users/{user}/role', [Admin\UserController::class, 'updateRole']);
            Route::patch('users/{user}/active', [Admin\UserController::class, 'setActive']);
        });

        Route::middleware('permission:coupons.manage')->group(function () {
            Route::get('coupons', [Admin\CouponController::class, 'index']);
            Route::post('coupons', [Admin\CouponController::class, 'store']);
            Route::patch('coupons/{coupon}', [Admin\CouponController::class, 'update']);
            Route::delete('coupons/{coupon}', [Admin\CouponController::class, 'destroy']);
        });

        Route::middleware('permission:settings.manage')->group(function () {
            Route::get('settings', [Admin\SettingController::class, 'index']);
            Route::patch('settings', [Admin\SettingController::class, 'update'])->middleware('throttle:sensitive');
        });

        Route::middleware('permission:banners.manage')->group(function () {
            Route::get('banners', [Admin\BannerController::class, 'index']);
            Route::post('banners', [Admin\BannerController::class, 'store'])->middleware('throttle:uploads');
            // POST (not PATCH) so multipart image uploads work from browsers/PHP.
            Route::post('banners/{banner}', [Admin\BannerController::class, 'update'])->middleware('throttle:uploads');
            Route::delete('banners/{banner}', [Admin\BannerController::class, 'destroy']);
        });

        Route::middleware('permission:faqs.manage')->group(function () {
            Route::get('faqs', [Admin\FaqController::class, 'index']);
            Route::post('faqs', [Admin\FaqController::class, 'store']);
            Route::patch('faqs/{faq}', [Admin\FaqController::class, 'update']);
            Route::delete('faqs/{faq}', [Admin\FaqController::class, 'destroy']);
        });

        Route::middleware('permission:reviews.moderate')->group(function () {
            Route::get('reviews', [Admin\ReviewController::class, 'index']);
            Route::post('reviews/{review}/approve', [Admin\ReviewController::class, 'approve']);
            Route::delete('reviews/{review}', [Admin\ReviewController::class, 'destroy']);
        });

        Route::get('audit-logs', [Admin\AuditLogController::class, 'index'])->middleware('permission:audit.view');

        Route::middleware('permission:products.manage')->group(function () {
            Route::get('inventory', [Admin\InventoryController::class, 'stock']);
            Route::get('inventory/movements', [Admin\InventoryController::class, 'index']);
            Route::post('products/{product}/inventory/adjust', [Admin\InventoryController::class, 'adjust']);
        });
        Route::middleware('permission:settings.manage')->group(function () {
            Route::get('shipping-zones', [Admin\ShippingZoneController::class, 'index']);
            Route::post('shipping-zones', [Admin\ShippingZoneController::class, 'store']);
            Route::patch('shipping-zones/{shippingZone}', [Admin\ShippingZoneController::class, 'update']);
            Route::delete('shipping-zones/{shippingZone}', [Admin\ShippingZoneController::class, 'destroy']);
        });
        Route::get('payments', [Admin\PaymentController::class, 'index'])->middleware('permission:payments.verify');
        Route::get('reports', Admin\ReportsController::class)->middleware('permission:reports.view');
        Route::middleware('permission:support.manage')->prefix('support/tickets')->group(function () {
            Route::get('/', [Admin\SupportTicketController::class, 'index']);
            Route::get('{ticket}', [Admin\SupportTicketController::class, 'show']);
            Route::patch('{ticket}', [Admin\SupportTicketController::class, 'update']);
            Route::post('{ticket}/messages', [Admin\SupportTicketController::class, 'reply']);
        });
    });
});
