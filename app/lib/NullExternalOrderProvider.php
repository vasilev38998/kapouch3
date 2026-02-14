<?php

declare(strict_types=1);

namespace App\Lib;

class NullExternalOrderProvider implements ExternalOrderProviderInterface {
    public function fetchOrderByExternalId(string $externalId): ?array {
        return null;
    }
}
