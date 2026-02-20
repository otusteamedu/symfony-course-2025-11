<?php

declare(strict_types=1);

namespace UnitTests\Domain\ValueObject;

use App\Domain\ValueObject\CommunicationChannel;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(CommunicationChannel::class)]
final class CommunicationChannelTest extends TestCase
{
    #[Test]
    #[DataProvider('validChannelsProvider')]
    public function it_can_create_valid_channel(string $channel): void
    {
        $communicationChannel = CommunicationChannel::fromString($channel);

        self::assertEquals($channel, $communicationChannel->getValue());
    }

    #[Test]
    #[DataProvider('invalidChannelsProvider')]
    public function it_throws_exception_for_invalid_channel(string $invalidChannel): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid communication channel value');

        CommunicationChannel::fromString($invalidChannel);
    }

    #[Test]
    public function it_is_immutable(): void
    {
        $channel = CommunicationChannel::fromString('email');

        self::assertNotSame(
            $channel,
            CommunicationChannel::fromString('email')
        );
    }

    public static function validChannelsProvider(): array
    {
        return [
            'email channel' => ['email'],
            'phone channel' => ['phone'],
        ];
    }

    public static function invalidChannelsProvider(): array
    {
        return [
            'empty string'          => [''],
            'only spaces'           => ['   '],
            'uppercase email'       => ['EMAIL'],
            'uppercase phone'       => ['PHONE'],
            'mixed case'            => ['EmAiL'],
            'with spaces'           => ['email '],
            'with special chars'    => ['email@'],
            'with numbers'          => ['email123'],
            'with unicode'          => ['email📱'],
            'with html'             => ['<email>'],
            'with sql injection'    => ["email'; DROP TABLE users; --"],
            'with xss'              => ['<script>alert("email")</script>'],
            'with emoji'            => ['📧'],
            'with null byte'        => ["email\0"],
            'with control chars'    => ["email\x1F"],
        ];
    }
}