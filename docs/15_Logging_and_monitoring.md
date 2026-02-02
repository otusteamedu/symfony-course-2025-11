# Логирование и мониторинг

## Включаем xDebug и отладку

1. В файле `docker/Dockerfile` добавляем/расскоментируем
   ```dockerfile
   RUN pecl install xdebug \
       && docker-php-ext-enable xdebug
   
   RUN echo "xdebug.mode=debug" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
   RUN echo "xdebug.start_with_request=yes" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
   RUN echo "xdebug.client_host=host.docker.internal" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
   RUN echo "xdebug.client_port=9003" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini
   
   RUN pecl clear-cache \
       && apk del .build-dependencies
   ```
   **Для MacOS - скорее всего `client_host` нужен будет `docker.for.mac.localhost`**
2. Запускаем контейнеры с пересборкой командой `docker-compose up -d --build`
3. Настраиваем **PHPStorm**:
   1. `File -> Settings -> PHP -> Servers` - Добавляем сервер 
   2. В `Name` и `Host` указываем `localhost`, порт - `80`, `Debugger` - `Xdebug`
   3. Ставим галочку `Use path mappings ...` и настраиваем маппинг файлов
   4. В `File/Directory` указываем полный путь до проекта. Например, для `WSL` примерно так: `//wsl.localhost/Ubuntu/home/USER/dev/otus/symfony-course-2025-11`
   5. В `Absolute path on the server` - тот же путь, что указан в `working_dir` в `docker-compose.yml`, в данном случае: `/app`
   6. Нажимаем `Start Listening for PHP Debug Connections`, ставим точку останова и проверяем 
## Логирование с помощью Monolog

### Добавляем monolog-bundle и логируем сообщения

1. Входим в контейнер командой `docker exec -it php sh`. Дальнейшие команды выполняются из контейнера
2. Устанавливаем пакет `symfony/monolog-bundle`
3. Устанавливаем пакет `symfony/web-profiler-bundle`
4. Исправляем класс `App\Controller\Web\CreateUser\v2\Manager`
    ```php
    <?php
    
    namespace App\Controller\Web\CreateUser\v2;
    
    use App\Controller\Web\CreateUser\v2\Input\CreateUserDTO;
    use App\Controller\Web\CreateUser\v2\Output\CreatedUserDTO;
    use App\Domain\Entity\EmailUser;
    use App\Domain\Entity\PhoneUser;
    use App\Domain\Model\CreateUserModel;
    use App\Domain\Service\ModelFactory;
    use App\Domain\Service\UserService;
    use App\Domain\ValueObject\CommunicationChannelEnum;
    use Psr\Log\LoggerInterface;
    
    readonly class Manager
    {
        public function __construct(
            /** @var ModelFactory<CreateUserModel> */
            private ModelFactory $modelFactory,
            private UserService $userService,
            private LoggerInterface $logger,
        ) {
        }
    
        public function create(CreateUserDTO $createUserDTO): CreatedUserDTO
        {
            $this->addLogs();
            $communicationMethod = $createUserDTO->phone ?? $createUserDTO->email;
            $communicationChannel = $createUserDTO->phone === null ? CommunicationChannelEnum::Email : CommunicationChannelEnum::Phone;
            $createUserModel = $this->modelFactory->makeModel(
                CreateUserModel::class,
                $createUserDTO->login,
                $communicationMethod,
                $communicationChannel,
                $createUserDTO->password,
                $createUserDTO->age,
                $createUserDTO->isActive,
                $createUserDTO->roles
            );
            $user = $this->userService->create($createUserModel);
    
            return new CreatedUserDTO(
                $user->getId(),
                $user->getLogin(),
                $user->getAvatarLink(),
                $user->getRoles(),
                $user->getCreatedAt()->format('Y-m-d H:i:s'),
                $user->getUpdatedAt()->format('Y-m-d H:i:s'),
                $user instanceof PhoneUser ? $user->getPhone() : null,
                $user instanceof EmailUser ? $user->getEmail() : null,
            );
        }
        
        private function addLogs(): void
        {
            $this->logger->debug('This is debug message');
            $this->logger->info('This is info message');
            $this->logger->notice('This is notice message');
            $this->logger->warning('This is warning message');
            $this->logger->error('This is error message');
            $this->logger->critical('This is critical message');
            $this->logger->alert('This is alert message');
            $this->logger->emergency('This is emergency message');
        }
    }
    ```
5. Выполняем запрос Add user v2 из Postman-коллекции v6 и проверяем, что логи попадают в файл `var/log/dev.log`

### Настраиваем минимальный уровень логирования и убираем ненужные каналы

1. В файле `config/packages/monolog.yaml` исправляем содержимое секции `when@dev.monolog.handlers.main`
    ```yaml
    type: stream
    path: "%kernel.logs_dir%/%kernel.environment%.log"
    level: critical
    channels: ["!event", "!doctrine", "!cache"]
    ```
2. Выполняем запрос Add user v2 из Postman-коллекции v6 и проверяем, что в файл `var/log/dev.log` попадают только
   сообщения с уровнями `critical`, `alert` и `emergency`

### Настраиваем режим fingers crossed

1. В файле `config/packages/monolog.yaml`
    1. Заменяем содержимое секции `when@dev.monolog.handlers.main`
        ```yaml
        type: fingers_crossed
        action_level: error
        handler: nested
        buffer_size: 3
        channels: ["!event", "!doctrine", "!cache"]
        ```
    2. Добавляем в секцию `when@dev.monolog.handlers`
        ```yaml
        nested:
            type: stream
            path: "%kernel.logs_dir%/%kernel.environment%.log"
            level: debug
        ```
2. Выполняем запрос Add user v2 из Postman-коллекции v6 и проверяем, что в файл `var/log/dev.log` дополнительно попадают
   сообщение с уровнем `error` и два предыдущих сообщения с уровнем ниже

### Добавляем форматирование

1. В файле `config/packages/monolog.yaml`
    1. Добавляем секцию `monolog.services`
        ```yaml
        services:
           monolog.formatter.app_formatter:
                class: Monolog\Formatter\LineFormatter
                arguments:
                    - "[%%level_name%%]: [%%datetime%%] %%message%%\n"
        ```
    2. В секцию `when@dev.monolog.handlers.main` добавляем форматтер
        ```yaml
        formatter: monolog.formatter.app_formatter
        ```
2. Выполняем запрос Add user v2 из Postman-коллекции v6 и проверяем, что в файл `var/log/dev.log` новые сообщения
   попадают с новом формате

## Best practices реализации логирования

### Декорируем сервис

1. Исправялем класс `App\Controller\Web\CreateUser\v2\Manager`
    ```php
    <?php
    
    namespace App\Controller\Web\CreateUser\v2;
    
    use App\Controller\Web\CreateUser\v2\Input\CreateUserDTO;
    use App\Controller\Web\CreateUser\v2\Output\CreatedUserDTO;
    use App\Domain\Entity\EmailUser;
    use App\Domain\Entity\PhoneUser;
    use App\Domain\Model\CreateUserModel;
    use App\Domain\Service\ModelFactory;
    use App\Domain\Service\UserService;
    use App\Domain\ValueObject\CommunicationChannelEnum;
    
    readonly class Manager
    {
        public function __construct(
            /** @var ModelFactory<CreateUserModel> */
            private ModelFactory $modelFactory,
            private UserService $userService,
        ) {
        }
    
        public function create(CreateUserDTO $createUserDTO): CreatedUserDTO
        {
            $communicationMethod = $createUserDTO->phone ?? $createUserDTO->email;
            $communicationChannel = $createUserDTO->phone === null ? CommunicationChannelEnum::Email : CommunicationChannelEnum::Phone;
            $createUserModel = $this->modelFactory->makeModel(
                CreateUserModel::class,
                $createUserDTO->login,
                $communicationMethod,
                $communicationChannel,
                $createUserDTO->password,
                $createUserDTO->age,
                $createUserDTO->isActive,
                $createUserDTO->roles
            );
            $user = $this->userService->create($createUserModel);
    
            return new CreatedUserDTO(
                $user->getId(),
                $user->getLogin(),
                $user->getAvatarLink(),
                $user->getRoles(),
                $user->getCreatedAt()->format('Y-m-d H:i:s'),
                $user->getUpdatedAt()->format('Y-m-d H:i:s'),
                $user instanceof PhoneUser ? $user->getPhone() : null,
                $user instanceof EmailUser ? $user->getEmail() : null,
            );
        }
    }
    ```
2. Добавляем класс `App\Controller\Web\CreateUser\v2\ManagerLoggerDecorator`
    ```php
    <?php
    
    namespace App\Controller\Web\CreateUser\v2;
    
    use App\Controller\Web\CreateUser\v2\Input\CreateUserDTO;
    use App\Controller\Web\CreateUser\v2\Output\CreatedUserDTO;
    use App\Domain\Model\CreateUserModel;
    use App\Domain\Service\ModelFactory;
    use App\Domain\Service\UserService;
    use Psr\Log\LoggerInterface;
    
    readonly class ManagerLoggerDecorator extends Manager
    {
        public function __construct(
            /** @var ModelFactory<CreateUserModel> */
            private ModelFactory $modelFactory,
            private UserService $userService,
            private LoggerInterface $logger,
        ) {
            parent::__construct($this->modelFactory, $this->userService);
        }
    
        public function create(CreateUserDTO $createUserDTO): CreatedUserDTO
        {
            $this->addLogs();
            
            return parent::create($createUserDTO);
        }
    
        private function addLogs(): void
        {
            $this->logger->debug('This is debug message');
            $this->logger->info('This is info message');
            $this->logger->notice('This is notice message');
            $this->logger->warning('This is warning message');
            $this->logger->error('This is error message');
            $this->logger->critical('This is critical message');
            $this->logger->alert('This is alert message');
            $this->logger->emergency('This is emergency message');
        }
    }
    ```
3. В файле `config/services.yaml` добавляем описание нового сервиса
    ```php
    App\Controller\Web\CreateUser\v2\ManagerLoggerDecorator:
        decorates: App\Controller\Web\CreateUser\v2\Manager
    ```
4. Выполняем запрос Add user v2 из Postman-коллекции v6 и проверяем, что в файл `var/log/dev.log` попадают сообщения

### Используем классический паттерн "Декоратор"

1. Создаем интерфейс `App\Controller\Web\CreateUser\v1\ManagerInterface`
    ```php
    <?php

    namespace App\Controller\Web\CreateUser\v2;
    
    use App\Controller\Web\CreateUser\v2\Input\CreateUserDTO;
    use App\Controller\Web\CreateUser\v2\Output\CreatedUserDTO;
    
    interface ManagerInterface
    {
        public function create(CreateUserDTO $createUserDTO): CreatedUserDTO;
    }
    ```
2. Имплементируем его в `App\Controller\Web\CreateUser\v2\Manager`
3. Исправляем класс `App\Controller\Web\CreateUser\v2\ManagerLoggerDecorator`
    ```php
    <?php
    
    namespace App\Controller\Web\CreateUser\v2;
    
    use App\Controller\Web\CreateUser\v2\Input\CreateUserDTO;
    use App\Controller\Web\CreateUser\v2\Output\CreatedUserDTO;
    use Psr\Log\LoggerInterface;
    
    readonly class ManagerLoggerDecorator implements ManagerInterface
    {
        public function __construct(
            private ManagerInterface $manager,
            private LoggerInterface $logger,
        ) {
        }
    
        public function create(CreateUserDTO $createUserDTO): CreatedUserDTO
        {
            $this->addLogs();
    
            return $this->manager->create($createUserDTO);
        }
    
        private function addLogs(): void
        {
            $this->logger->debug('This is debug message');
            $this->logger->info('This is info message');
            $this->logger->notice('This is notice message');
            $this->logger->warning('This is warning message');
            $this->logger->error('This is error message');
            $this->logger->critical('This is critical message');
            $this->logger->alert('This is alert message');
            $this->logger->emergency('This is emergency message');
        }
    }
    ```
4. В классе `App\Controller\Web\CreateUser\v2\Controller` делаем инъекцию зависимости через интерфейс
5. В `config/services.yaml`
   1. Исправляем описание сервиса `App\Controller\Web\CreateUser\v2\ManagerLoggerDecorator`
       ```yaml
       App\Controller\Web\CreateUser\v2\ManagerLoggerDecorator:
           arguments:
               $manager: '@App\Controller\Web\CreateUser\v2\Manager'
       ```
   2. Добавляем биндинг интерфейса
       ```yaml
       App\Controller\Web\CreateUser\v2\ManagerInterface:
           alias: App\Controller\Web\CreateUser\v2\ManagerLoggerDecorator
       ```
6. Выполняем запрос Add user v2 из Postman-коллекции v6 и проверяем, что в файл `var/log/dev.log` попадают сообщения

### Логируем с помощью событий

1. Добавляем класс `App\Domain\Event\UserIsCreatedEvent`
    ```php
    <?php
    
    namespace App\Domain\Event;
    
    readonly class UserIsCreatedEvent
    {
        public function __construct(
            public int $id,
            public string $login,
        ) {
        }
    }
    ```
2. Исправляем класс `App\Domain\EventSubscriber\UserEventSubscriber`
    ```php
    <?php
    
    namespace App\Domain\EventSubscriber;
    
    use App\Domain\Event\CreateUserEvent;
    use App\Domain\Event\UserIsCreatedEvent;
    use App\Domain\Service\UserService;
    use Psr\Log\LoggerInterface;
    use Symfony\Component\EventDispatcher\EventSubscriberInterface;
    
    readonly class UserEventSubscriber implements EventSubscriberInterface
    {
        public function __construct(
            private UserService $userService,
            private LoggerInterface $logger,
        ) {
        }
    
        public static function getSubscribedEvents(): array
        {
            return [
                CreateUserEvent::class => 'onCreateUser',
                UserIsCreatedEvent::class => 'onUserIsCreated',
            ];
        }
    
        public function onCreateUser(CreateUserEvent $event): void
        {
            $user = null;
    
            if ($event->phone !== null) {
                $user = $this->userService->createWithPhone($event->login, $event->phone);
            } elseif ($event->email !== null) {
                $user = $this->userService->createWithEmail($event->login, $event->email);
            }
    
            $event->id = $user?->getId();
    
        }
    
        public function onUserIsCreated(UserIsCreatedEvent $event): void
        {
            $this->logger->info("User is created: id {$event->id}, login {$event->login}");
        }
    }
    ```
3. В классе `App\Domain\Service\UserService`
   1. добавляем зависимость от `EventDispatcherInterface` 
   2. исправляем метод `create`
       ```php
       public function create(CreateUserModel $createUserModel): User
       {
           $user = match($createUserModel->communicationChannel) {
                CommunicationChannelEnum::Email => (new EmailUser())->setEmail($createUserModel->communicationMethod),
                CommunicationChannelEnum::Phone => (new PhoneUser())->setPhone($createUserModel->communicationMethod),
           };
           $user->setLogin($createUserModel->login);
           $user->setPassword($this->userPasswordHasher->hashPassword($user, $createUserModel->password));
           $user->setAge($createUserModel->age);
           $user->setIsActive($createUserModel->isActive);
           $user->setRoles($createUserModel->roles);
           $this->userRepository->create($user);
           $this->eventDispatcher->dispatch(new UserIsCreatedEvent($user->getId(), $user->getLogin()));
    
           return $user;
       }
       ```
4. В файле `config/packages/monolog.yaml` в секции `when@dev.monolog.handlers` удаляем подсекцию `nested` и исправляем
   секцию `main`
    ```yaml
    main:
        type: stream
        path: "%kernel.logs_dir%/%kernel.environment%.log"
        level: debug
        channels: ["!event", "!doctrine", "!cache"]
        formatter: monolog.formatter.app_formatter 
    ```
5. Выполняем запрос Add user v2 из Postman-коллекции v6 и проверяем, что в файл `var/log/dev.log` попадают новые
   сообщения

## Elasticsearch и Kibana для логов

1. Добавляем сервисы `elasticsearch` и `kibana` в `docker-compose.yml`
    ```yaml
    elasticsearch:
        image: elasticsearch:9.2.0
        container_name: 'elasticsearch'
        environment:
            - cluster.name=docker-cluster
            - bootstrap.memory_lock=true
            - discovery.type=single-node
            - "ES_JAVA_OPTS=-Xms512m -Xmx512m"
            - ELASTIC_PASSWORD=${ELASTICSEARCH_PASSWORD}
        ulimits:
            memlock:
                soft: -1
                hard: -1
        ports:
            - 9200:9200
            - 9300:9300

    kibana:
        image: kibana:9.2.0
        container_name: 'kibana'
        depends_on:
            - elasticsearch
        ports:
            - 5601:5601
    ```
2. Добавляем в .env новые переменные
    ```bash
    ELASTICSEARCH_USER=elastic
    ELASTICSEARCH_PASSWORD=gpKUgKj84=AG8k6erd3b
    ELASTICSEARCH_HOST=https://elasticsearch:9200
    ```
3. Выходим из контейнера и запускаем новые контейнеры командой `docker-compose up -d`
4. Заходим в контейнер командой `docker exec -it php sh`. Дальнейшие команды выполняются из контейнера
5. Устанавливаем пакет `symfony/http-client`
6. В файле `config/packages/monolog.yaml`
    1. добавляем в `monolog.channels` новый канал `elasticsearch`
    2. Добавляем новый обработчик в секцию `monolog.handlers`
        ```yaml
        elasticsearch:
            type: service
            id: Symfony\Bridge\Monolog\Handler\ElasticsearchLogstashHandler
            channels: elasticsearch
        ```
    3. Создаём файл `config/packages/http_client.yaml`
    ```yaml
    framework:
        http_client:
            default_options:
                verify_peer: false
                verify_host: false
            scoped_clients:
                es_client:
                    base_uri: '%env(ELASTICSEARCH_HOST)%'
                    auth_basic: '%env(ELASTICSEARCH_USER)%:%env(ELASTICSEARCH_PASSWORD)%'
    ``` 
    4. Добавляем новые сервисы в секцию `services`:
        ```yaml
        Symfony\Bridge\Monolog\Handler\ElasticsearchLogstashHandler:
            arguments:
                - '%env(ELASTICSEARCH_HOST)%'
                - 'monolog'
                - '@es_client'
                - DEBUG
                - true
                - 9.2.0
        ```
7. В классе `App\Domain\EventSubscriber\UserEventSubscriber`
    1. Изменяем название параметра `$logger` на `$elasticsearchLogger`
    2. исправляем метод `onUserIsCreated`
        ```php
        public function onUserIsCreated(UserIsCreatedEvent $event): void
        {
            $this->elasticsearchLogger->info("User is created: id {$event->id}, login {$event->login}");
        }
        ```
8. Выполняем запрос Add user v2 из Postman-коллекции v6
9. Из заголовков ответа берём ссылку на профайлер и смотрим запрос к эластику.
10. Заходим в Kibana `http://localhost:5601`.
11. В консоли вне контейнера выполняем `docker compose exec -it elasticsearch bin/elasticsearch-create-enrollment-token --scope kibana`
12. Копируем полученный токен идём в браузер и вставляем его.
13. Возвращаемся в консоль и вводим `docker compose exec -it kibana bin/kibana-verification-code`
14. Копируем полученный код в кибану
15. Заходим в Discover
16. В левом верхнем углу разворачиваем `Data view`
17. В правом нижнем углу менюшки `Create a data view`
18. Создаём index pattern на базе индекса `monolog`

## Grafana для сбора метрик, интеграция с Graphite

1. Устанавливаем пакет `slickdeals/statsd`
2. Добавляем сервисы Graphite и Grafana в `docker-compose.yml`
    ```yaml
    graphite:
        image: graphiteapp/graphite-statsd
        container_name: 'graphite'
        restart: always
        ports:
          - 8000:80
          - 2003:2003
          - 2004:2004
          - 2023:2023
          - 2024:2024
          - 8125:8125/udp
          - 8126:8126

    grafana:
        image: grafana/grafana
        container_name: 'grafana'
        restart: always
        ports:
          - 3000:3000
    ```
3. Выходим из контейнера `php` и запускаем новые контейнеры командой `docker-compose up -d`
4. Проверяем, что можем зайти в интерфейс Graphite по адресу `localhost:8000`
5. Проверяем, что можем зайти в интерфейс Grafana по адресу `localhost:3000`, логин / пароль - `admin` / `admin`
6. Добавляем класс `App\Storage\MetricsStorage`
    ```php
    <?php
    
    namespace App\Infrastructure\Storage;
    
    use Domnikl\Statsd\Client;
    use Domnikl\Statsd\Connection\UdpSocket;
    
    class MetricsStorage
    {
        public const USER_CREATED = 'user_created';

        private const DEFAULT_SAMPLE_RATE = 1.0;
    
        private Client $client;
    
        public function __construct(string $host, int $port, string $namespace)
        {
            $connection = new UdpSocket($host, $port);
            $this->client = new Client($connection, $namespace);
        }
    
        public function increment(string $key, ?float $sampleRate = null, ?array $tags = null): void
        {
            $this->client->increment($key, $sampleRate ?? self::DEFAULT_SAMPLE_RATE, $tags ?? []);
        }
    }
    ```
7. В файле `config/services.yaml` добавляем новое описание сервиса
    ```yaml
    App\Infrastructure\Storage\MetricsStorage:
        arguments: 
            - graphite
            - 8125
            - my_app
    ```
8. В классе `App\Domain\EventSubscriber\UserEventSubscriber`
   1. Добавляем зависимость от `MetricsStorage`
   2. Исправляем метод `onUserIsCreated`
       ```php
       public function onUserIsCreated(UserIsCreatedEvent $event): void
       {
           $this->elasticsearchLogger->info("User is created: id {$event->id}, login {$event->login}");
           $this->metricsStorage->increment(MetricsStorage::USER_CREATED);
       }
       ```
9. Выполняем несколько раз запрос Add user v2 из Postman-коллекции v6 и проверяем, что в Graphite появляются события
10. Настраиваем график в Grafana
    1. добавляем в Data source с типом Graphite и адресом `graphite:80`
    2. добавляем новый Dashboard
    3. на дашборде добавляем панель с запросом в Graphite счётчика `stats_counts.my_app.user_created`
    4. видим график с запросами
11. Выполняем ещё несколько раз запрос Add user v2 из Postman-коллекции v6 и проверяем, что в Grafana обновились данные
