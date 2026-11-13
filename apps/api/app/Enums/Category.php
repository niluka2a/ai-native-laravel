<?php

namespace App\Enums;

enum Category: string
{
    case Support = 'support';
    case Sales = 'sales';
    case Billing = 'billing';
    case Unknown = 'unknown';
}
