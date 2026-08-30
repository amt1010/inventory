<?php

namespace App\Services\Clerk;

final class ClerkIdentity
{
    public function __construct(
        public readonly string $id,
        public readonly string $email,
        public readonly ?string $name,
    ) {
    }
}
