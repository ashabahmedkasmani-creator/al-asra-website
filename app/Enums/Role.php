<?php

namespace App\Enums;

enum Role: string
{
    case Customer = 'customer';
    case Staff = 'staff';
    case Admin = 'admin';

    /** @return string[] */
    public function permissions(): array
    {
        return match ($this) {
            self::Admin => ['*'],
            self::Staff => [
                'dashboard.view', 'products.manage', 'categories.manage',
                'orders.view', 'orders.update', 'banners.manage',
                'faqs.manage', 'reviews.moderate', 'support.manage',
            ],
            self::Customer => [],
        };
    }

    public function can(string $permission): bool
    {
        $p = $this->permissions();
        return in_array('*', $p, true) || in_array($permission, $p, true);
    }
}
