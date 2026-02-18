<?php

declare(strict_types=1);

namespace App\Repositories\MyBike;

use App\Models\MaintenanceLog;
use App\Models\MyBike;

final class MaintenanceLogRepository
{
    /**
     * 整備記録を作成
     */
    public function create(MyBike $myBike, array $data): MaintenanceLog
    {
        return $myBike->maintenanceLogs()->create($data);
    }
}