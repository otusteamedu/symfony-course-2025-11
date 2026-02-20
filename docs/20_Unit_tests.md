# Unit-тестирование

Запускаем контейнеры командой `docker compose up -d`

## Устанавливаем PHPUnit и организуем тесты

1. Заходим в контейнер командой `docker exec -it php-11 bash`. Дальнейшие команды выполняем из контейнера
2. Добавляем пакет в **dev-режиме**:
   ```shell
   composer require --dev symfony/test-pack
   ```
   Обращаем внимание на то что поставился `symfony/phpunit-bridge` и древняя версия `phpunit/phpunit` 9.5, в которой
   даже атрибуты не поддерживаются
   ```
   "phpunit/phpunit": "^9.5",
   "symfony/browser-kit": "7.1.*",
   "symfony/css-selector": "7.1.*",
   "symfony/phpunit-bridge": "^7.2"
   ```
   Бридж устарел и удаляем `symfony/phpunit-bridge`, а так же остальное лишнее, что нам не нужно:
   ```shell
   composer remove symfony/phpunit-bridge symfony/browser-kit symfony/css-selector --dev
   composer require --update-with-dependencies phpunit/phpunit:^12.1.5 --dev
   ```
3. Показываем, что можно протестировать в проекте с помощью юнитов:
    - `Infratructure` - не подходит, это для интеграционного тестирования
    - `Controllers` - не подходит, для интеграционного тестирования. Максимум можно схему API ответа покрыть юнитами.
    - `Domain/Entity` - обязательно нужно тестировать, но в проекте анемичные модели! тесты бесмыслленны
    - `Domain/Service` - остаются только они.

   Для юнит-тестирования лучше всего подходит доменная модель и сервисы с бизнес логикой.
    - Агрегаты, сущности, value objects
    - Сервисы

4. Добавляем папку `tests/unit`
5. Исправляем в composer.json секцию `autoload-dev`
    ```json
    "autoload-dev": {
        "psr-4": {
            "UnitTests\\": "tests/unit"
        }
    },
    ```
6. Генерируем `phpunit.xml` и добавляем тестсьют
   ```shell
   ./vendor/bin/phpunit --generate-configuration
   ```
   Добавляем в секцию `<testsuites>`:
    ```xml
    <testsuite name="unit">
        <directory>tests/unit</directory>
    </testsuite>
    ```

7. Добавляем примитивный тест
   - показываем базовый класс
   - показываем два вариант синтаксиса тестов в PhpUnit 12:
       - через префикс
       - через атрибуты
   - Обращаем внимание на `#[CoversNothing]` и соответствующую настройку в phpunit.xml

      ```php
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
      ```

8. Запускаем `vendor/bin/phpunit --testdox`, видим, что тесты прошли

## Пишем тест для Value Object

1. Исправляем VO `App\Domain\ValueObject\CommunicationChannel`
   ```php
   <?php
   
   namespace App\Domain\ValueObject;
   
   use InvalidArgumentException;
   
   final readonly class CommunicationChannel
   {
       private const EMAIL = 'email';
       private const PHONE = 'phone';
       private const ALLOWED_VALUES = [self::PHONE, self::EMAIL];
   
       private function __construct(
           private string $value
       ) {
           if (!in_array($value, self::ALLOWED_VALUES, true)) {
               throw new InvalidArgumentException('Invalid communication channel value');
           }
       }
   
       public static function fromString(string $value): self
       {
           return new self($value);
       }
   
       public function getValue(): string
       {
           return $this->value;
       }
   }
   ```

2. Создаем тест

   ```php
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
   ```

3. Обращаем внимание на:
    - `#[CoversClass(CommunicationChannel::class)]` - указываем какой класс покрывает класс
    - `#[DataProvider('validChannelsProvider')]` - используем провайдер данных

## Пишем тест с мок-сервисом

1. Добавляем класс `UnitTests\Service\UserServiceTest`
   Обращаем внимание на:

   - трехактная струра Arrange, Act, Assert
   - интересный момент с ассертами:
     - Можно использовать только 1 ассерт на тест, где будут проверятся массивы с $expectedData  - сразу видно все расхождения в данных с ожидаемыми
     - Можно использовать ассерт на каждый проверяемый момент

   ```php
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
           $userRepository = $this->createStub(UserRepository::class);
   //        $userRepository = $this->createMock(UserRepository::class);
   //        $userRepository->expects($this->once())
   //            ->method('create')
   //            ->willReturnCallback(function($user) {
   //                $user->setId(1);
   //                $user->setCreatedAt();
   //                $user->setUpdatedAt();
   //                return 1;
   //            });
   
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
   ```
2. Запускаем тесты, видим 2 ошибки
3. В интерфейсе `App\Domain\Entity\EntityInterface` исправляем декларацию метода `getId`
    ```php
    public function getId(): ?int;
    ```
4. В классе `App\Domain\Entity\User` исправляем метод `getId`
    ```php
    public function getId(): ?int
    {
        return $this->id;
    }
    ```
5. Ещё раз запускаем тесты, видим дальнейшую ошибку.

## Добавляем поведение к мок-методу

1. Отменяем правки в интерфейсе `App\Domain\Entity\EntityInteface` и классе `App\Domain\Entity\User`
2. В классе `UnitTests\Service\UserServiceTest` исправляем метод `prepareUserService`
    ```php
   private function prepareUserService(): UserService
       {
   //        $userRepository = $this->createStub(UserRepository::class);
           $userRepository = $this->createMock(UserRepository::class);
           $userRepository->expects($this->once())
               ->method('create')
               ->willReturnCallback(function($user) {
                   $user->setId(1);
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
    ```

3. У сущностей не должно быть `setId()`, убираем метод и показываем как через рефлексию поставить setId()
   ```php
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
   ```

## Coverage

1. Добавляем (или раскомментируем) в `Dockerfile` расширение `xdebug`
   ```dockerfile
   RUN pecl install xdebug \
       && docker-php-ext-enable xdebug \
       && echo "xdebug.mode=debug" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
       && echo "xdebug.start_with_request=no" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
       && echo "xdebug.client_host=host.docker.internal" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
       && echo "xdebug.client_port=9003" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
   ```
2. Добавляем в `phpunit.xml` секцию `coverage`
   ```xml
    <coverage>
        <report>
            <html outputDirectory="/var/coverage"/>
        </report>
    </coverage>
   ```
3. Перезапускаем контейнеры с пересборкой образов `docker compose up -d --build`
4. Запускаем phpunit в режиме coverage:
   ```shell
    XDEBUG_MODE=coverage  ./vendor/bin/phpunit --coverage-html var/coverage
   ```
5. Смотрим репорт в `var/coverage`
6. Зачем нужен аттрибут `#[CoversClass]`

## Дополнительные лайфхаки:

1. `UniqueIncrementIdFactory` - уникальный id для всех сущностей создаваемых в рамках тестов
2. `AutoIncrementImmitator` - имитатор автоинкремента для сущностей, удобно в willRuturnCallback() репозитория
   засовывать
3. Флаг `--filter`
4. Билдеры
5. `EventDispatcherSpy`
6. `InMemoryRepository`