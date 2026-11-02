<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';      // set only by an authorised admin (or a verified provider webhook in future)
    case Failed = 'failed';
    case Refunded = 'refunded';
}
