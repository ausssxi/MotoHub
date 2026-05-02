<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Shop;
use App\Services\Parking\AddressParser;

class ShopObserver
{
    public function __construct(private readonly AddressParser $parser) {}

    public function creating(Shop $shop): void
    {
        $this->fillCity($shop);
    }

    public function updating(Shop $shop): void
    {
        if ($shop->isDirty('address')) {
            $this->fillCity($shop);
        }
    }

    private function fillCity(Shop $shop): void
    {
        if (empty($shop->address)) {
            return;
        }

        $result = $this->parser->parse($shop->address);
        if ($result['city'] !== '') {
            $shop->city = $result['city'];
        }
    }
}
