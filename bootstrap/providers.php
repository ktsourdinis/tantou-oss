<?php

use App\Cloud\CloudServiceProvider;
use App\Providers\AppServiceProvider;

$providers = [
    AppServiceProvider::class,
];

if (class_exists(CloudServiceProvider::class)) {
    $providers[] = CloudServiceProvider::class;
}

return $providers;
