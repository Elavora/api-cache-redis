<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ComposerJsonTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $composer;

    protected function setUp(): void
    {
        $contents = file_get_contents(dirname(__DIR__) . '/composer.json');
        self::assertIsString($contents);
        $this->composer = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
    }

    public function testRequiresPhp84(): void
    {
        self::assertSame('>=8.4', $this->composer['require']['php'] ?? null);
    }

    public function testRequiresExtRedis(): void
    {
        self::assertSame('*', $this->composer['require']['ext-redis'] ?? null);
    }

    public function testRequiresFramework3(): void
    {
        self::assertSame('^4.1 || ^5.0 || ^6.0', $this->composer['require']['elavora/api-framework'] ?? null);
    }

    public function testRequiresApiRedis3(): void
    {
        $constraint = $this->composer['require']['elavora/api-redis'] ?? null;
        self::assertSame('^3.0', $constraint);
    }

    public function testBranchAliasIs30Dev(): void
    {
        self::assertSame('3.0-dev', $this->composer['extra']['branch-alias']['dev-main'] ?? null);
    }

    public function testAutoloadUnchanged(): void
    {
        self::assertSame(
            ['Elavora\\Api\\Extension\\CacheRedis\\' => 'src/'],
            $this->composer['autoload']['psr-4'] ?? null,
        );
    }
}
