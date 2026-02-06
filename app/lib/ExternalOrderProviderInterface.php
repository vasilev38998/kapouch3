<?php

declare(strict_types=1);

namespace App\Lib;

interface ExternalOrderProviderInterface {
    public function fetchOrderByExternalId(string $externalId): ?array;
}
