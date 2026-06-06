<?php

namespace App\Domain\Promo\Enums;

enum PromoApplicableType: string
{
    case Both = 'both';
    case Packages = 'packages';
    case DropIns = 'drop_ins';
}
