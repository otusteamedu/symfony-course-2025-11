<?php

namespace UnitTests\Service;

use App\Domain\Entity\EmailUser;
use App\Domain\Entity\PhoneUser;
use App\Domain\Model\CreateUserModel;
use App\Domain\Service\UserService;
use App\Domain\ValueObject\CommunicationChannelEnum;
use App\Infrastructure\Repository\UserRepository;
use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[CoversClass(\App\Domain\Service\UserService::class)]
class UserServiceTest extends TestCase
{
    private const PASSWORD_HASH = 'my_hash';
    private const DEFAULT_AGE = 18;
    private const DEFAULT_IS_ACTIVE = true;
    private const DEFAULT_ROLES = ['ROLE_USER'];

    #[DataProvider('createTestCases')]
    public function testCreate(CreateUserModel $createUserModel, array $expectedData): void
    {
        $userService = $this->prepareUserService();

        $user = $userService->create($createUserModel);

        $actualData = [
            'class' => get_class($user),
            'login' => $user->getLogin(),
            'email' => ($user instanceof EmailUser) ? $user->getEmail() : null,
            'phone' => ($user instanceof PhoneUser) ? $user->getPhone() : null,
            'passwordHash' => $user->getPassword(),
            'age' => $user->getAge(),
            'isActive' => $user->isActive(),
            'roles' => $user->getRoles(),
        ];
        self::assertSame($expectedData, $actualData);
    }

    public static function createTestCases(): Generator
    {
        yield [
            new CreateUserModel(
                'someLogin',
                'somePhone',
                CommunicationChannelEnum::Phone
            ),
            [
                'class' => PhoneUser::class,
                'login' => 'someLogin',
                'email' => null,
                'phone' => 'somePhone',
                'passwordHash' => self::PASSWORD_HASH,
                'age' => self::DEFAULT_AGE,
                'isActive' => self::DEFAULT_IS_ACTIVE,
                'roles' => self::DEFAULT_ROLES,
            ]
        ];

        yield [
            new CreateUserModel(
                'otherLogin',
                'someEmail',
                CommunicationChannelEnum::Email
            ),
            [
                'class' => EmailUser::class,
                'login' => 'otherLogin',
                'email' => 'someEmail',
                'phone' => null,
                'passwordHash' => self::PASSWORD_HASH,
                'age' => self::DEFAULT_AGE,
                'isActive' => self::DEFAULT_IS_ACTIVE,
                'roles' => self::DEFAULT_ROLES,
            ]
        ];
    }

    private function prepareUserService(): UserService
    {
//        $userRepository = $this->createStub(UserRepository::class);
        $userRepository = $this->createMock(UserRepository::class);
        $userRepository->expects($this->once())
            ->method('create')
            ->willReturnCallback(function($user) {
                $reflectionProperty = (new \ReflectionClass($user))->getProperty('id');
                $reflectionProperty->setAccessible(true);
                $reflectionProperty->setValue($user, 1);
                $user->setCreatedAt();
                $user->setUpdatedAt();
                return 1;
            });

        $userPasswordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $userPasswordHasher->expects($this->once())
            ->method('hashPassword')
            ->willReturn(self::PASSWORD_HASH);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())
            ->method('dispatch');

        return new UserService($userRepository, $userPasswordHasher, $eventDispatcher);
    }
}