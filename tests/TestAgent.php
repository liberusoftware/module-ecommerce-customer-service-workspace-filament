<?php

declare(strict_types=1);

namespace Liberu\Ecommerce\CustomerServiceWorkspace\Filament\Tests;

/** Who the test panel is acting as. Nobody, when a test is about a desk with no agent at it. */
final class TestAgent
{
    public const PRIMARY = 'agent-1';

    public const OTHER = 'agent-2';

    private static ?string $current = self::PRIMARY;

    public static function current(): ?string
    {
        return self::$current;
    }

    public static function use(?string $agentRef): void
    {
        self::$current = $agentRef;
    }

    public static function reset(): void
    {
        self::$current = self::PRIMARY;
    }
}
