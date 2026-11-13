<?php

namespace App\Models\Traits;

use App\Models\Concerns\BelongsToTenant as BelongsToTenantConcern;

trait BelongsToTenant
{
    use BelongsToTenantConcern;
}
