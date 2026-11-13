<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function (User $user, int $id): bool {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('tenant.{tenantId}', function (User $user, int $tenantId): bool {
    return (int) $user->tenant_id === (int) $tenantId;
});
