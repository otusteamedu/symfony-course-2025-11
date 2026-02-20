<?php

declare(strict_types=1);

namespace UnitTests;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class ExampleTest extends TestCase
{
    #[Test]
    public function test_it_can_perform_basic_assertions(): void
    {
        self::assertTrue(true);
        self::assertEquals(2, 1 + 1);
    }

    public function testStringOperations(): void
    {
        $string = 'Hello World';

        self::assertStringContainsString('World', $string);
        self::assertStringStartsWith('Hello', $string);
    }
}