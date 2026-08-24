<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Tests;

/**
 * The plaintext claims this suite has been issued.
 *
 * The module returns one once, at the moment a conversation opens, and stores
 * only its hash — so a test that needs to act as the customer has to keep the
 * one it was given, exactly as a host would. Nothing in `src/` can reach these,
 * which is the point: this desk has no claim and cannot rate anything.
 */
final class Claims
{
    /** @var array<string, string> */
    private static array $issued = [];

    public static function remember(string $reference, string $claim): void
    {
        self::$issued[$reference] = $claim;
    }

    public static function of(string $reference): string
    {
        return self::$issued[$reference] ?? '';
    }
}
