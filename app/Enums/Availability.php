<?php

namespace App\Enums;

enum Availability: string
{
    case Available = 'available';
    case Limited = 'limited';
    case SupplierConfirmation = 'supplier_confirmation_required';
    case Unavailable = 'unavailable';
    case Discontinued = 'discontinued';

    public function isPurchasable(): bool
    {
        return in_array($this, [self::Available, self::Limited, self::SupplierConfirmation], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Available => 'In stock',
            self::Limited => 'Limited availability',
            self::SupplierConfirmation => 'Availability confirmed after ordering',
            self::Unavailable => 'Currently unavailable',
            self::Discontinued => 'Discontinued',
        };
    }
}
