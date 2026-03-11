# Развёртывание приложения (Symfony)

## Настраиваем виртуальную машину

1. Подключаемся к ВМ и устанавливаем окружение командами (**команды для ubuntu 24.04**)
    ```shell
    sudo apt update
    sudo apt install curl git unzip mc
    ```
2. Устанавливаем зависимости для docker (выполняем по одной команде за раз)
    ```shell
    sudo apt install ca-certificates
    sudo install -m 0755 -d /etc/apt/keyrings
    sudo curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
    sudo chmod a+r /etc/apt/keyrings/docker.asc
   ```
   Это выполняется одной командой (скопировать-вставить всё сразу)
    ```shell
    sudo tee /etc/apt/sources.list.d/docker.sources <<EOF
    Types: deb
    URIs: https://download.docker.com/linux/ubuntu
    Suites: $(. /etc/os-release && echo "${UBUNTU_CODENAME:-$VERSION_CODENAME}")
    Components: stable
    Signed-By: /etc/apt/keyrings/docker.asc
    EOF
    ```
3. Устанавливаем сам docker и docker compose
    ```shell
    sudo apt update
    sudo apt install docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
    ```
4. Проверяем, что всё установилось корректно и работает
    ```shell
    sudo systemctl status docker
    ```
5. Создаём директорию `/app` и меняем владельца на нашего пользователя ВМ
    ```shell
    sudo mkdir /app
    sudo chown 1000:1000 /app
    ```

## Устанавливаем и настраиваем Gitlab и Gitlab Runner

1. Cоздаём файл `/app/docker-compose.yml`
    ```yaml
    services:
       gitlab:
         image: gitlab/gitlab-ce:latest
         container_name: gitlab
         restart: always
         hostname: 'SERVER_URL_OR_HOST'
         environment:
           GITLAB_OMNIBUS_CONFIG: |
             external_url 'http://SERVER_URL_OR_HOST:7778'
             gitlab_rails['gitlab_shell_ssh_port'] = 9022
         ports:
           - '7778:7778'
           - '9022:22'
         volumes:
           - gitlab_config:/etc/gitlab
           - gitlab_logs:/var/log/gitlab
           - gitlab_data:/var/opt/gitlab
         shm_size: '256m'
    
    volumes:
       gitlab_config:
       gitlab_logs:
       gitlab_data:
    ```
   **Не забудьте заменить `SERVER_URL_OR_HOST` на актуальный адрес сервера/ВМ**
2. Запускаем контейнер командой `sudo docker compose up -d`
3. Ждём запуска Gitlab. Следить за ходом старта контейнера можно командой `sudo docker logs --follow gitlab`
4. Подключаемся к контейнеру Gitlab командой `sudo docker exec -it gitlab bash`
5. В контейнере запускаем консоль Rails командой `gitlab-rails console -e production`
6. В консоли ввести команды (`PASSWORD` – требуемый пароль):
    ```
    user = User.where(id: 1).first
    user.password = PASSWORD
    user.password_confirmation = PASSWORD
    user.save
    exit
    ```
7. Выходим из контейнера
8. Заходим в браузере по адресу `http://SERVER_URL_OR_HOST:7778`
    1. логинимся с логином `root` и указанным паролем
    2. Создаём группу и публичный репозиторий в ней
9. **В ВМ** выполняем команды по одной
    ```shell
    curl -s https://packages.gitlab.com/install/repositories/runner/gitlab-runner/script.deb.sh | sudo bash
    sudo apt install -y gitlab-runner
    ```
   Актуальные команды можно посмотреть в Gitlab-репозитории в браузере, на вкладке `Settings -> CI/CD -> Runners`
10. В файл `/etc/sudoers` добавляем строку
     ```
     gitlab-runner ALL=(ALL) NOPASSWD:ALL
     ```
    Вариант запуска: `sudo visudo` и вносим те же правки
11. Удаляем файл `/home/gitlab-runner/.bash_logout` или комментируем его содержимое (**если создастся**)
12. На вкладке `Settings -> CI/CD -> Variables` добавляем переменную `DEPLOY_DIR` со значением `/app/deploy`
13. Выполняем команду регистрации из той же вкладки `Settings -> CI/CD -> Runners`, соглашаемся со всеми значениями по умолчанию, в качестве `executor` выбираем `shell`
    ```shell
    sudo gitlab-runner register --url http://SERVER_URL_OR_HOST:7778/ --registration-token <TOKEN>
    ```
14. В файл `/etc/gitlab-runner/config.toml` добавляем строку `shell = "bash"` после параметра `executor`
    ```
    shell = "bash"
    ```
15. Перезапускаем gitlab-runner:
   ```shell
   sudo gitlab-runner restart
   ```
16. Проверяем в интерфейсе, что runner появился

## Устанавливаем `node.js` и `yarn`

1. Устанавливаем Node.js (для Webpack Encore)
    ```shell
    curl -fsSL https://deb.nodesource.com/setup_lts.x | sudo -E bash -
    sudo apt install -y nodejs
    ```
2. Устанавливаем yarn
    ```shell
    sudo npm install -g yarn
    ```

## Создаём директории для деплоев

1. Переходим в каталог `/app`
    ```shell
    cd /app
    ```
2. Создаём каталоги для релизов
    ```shell
    mkdir -p deploy/releases
    ```
3. Даём всем полные права на директории
    ```shell
    chmod a+rwx -R deploy
    ```
4. Создаём директории `deploy/docker` и `deploy/docker/supervisor`:
   ```shell
   mkdir -p deploy/docker/supervisor
   ```
5. Создаём файл `deploy/docker/Dockerfile` и копируем в него содержимое файла из проекта (`docker/Dockerfile`)
6. Создаём файл `deploy/docker/supervisor/Dockerfile` и копируем в него содержимое файла из проекта (`docker/supervisor/Dockerfile`)
7. Переходим в директорию `releases`
    ```shell
    cd /app/deploy/releases
    ```
8. Создаём директории релизов и даём всем полные права
    ```shell
    mkdir shared
    mkdir blue
    mkdir green
    mkdir test
    chmod a+rwx shared blue green test
    ```
9. Копируем файл `.env` проекта в директорию `/app/deploy/releases/shared/`
   **Не забудьте поменять значение параметра `DEFAULT_URI` на адрес сервера/ВМ (`SERVER_URL_OR_HOST`)**
10. Меняем параметры в файле:
     ```shell
     APP_ENV=prod
     POSTGRES_DB=app
     POSTGRES_USER=prod
     POSTGRES_PASSWORD=prod
     ```
11. Добавляем простой action-healthcheck. Создаём класс `App\Controller\Web\Healthcheck\Controller`:
   ```php
   <?php
   
   namespace App\Controller\Web\Healthcheck;
   
   use Symfony\Component\HttpFoundation\JsonResponse;
   use Symfony\Component\HttpFoundation\Response;
   use Symfony\Component\HttpKernel\Attribute\AsController;
   use Symfony\Component\Routing\Attribute\Route;
   
   #[AsController]
   class Controller
   {
       #[Route(path: '/healthcheck', name: 'healthcheck')]
       public function __invoke(): Response
       {
           return new JsonResponse(['ok']);
       }
   }
   ```
12. Исправляем секцию `access_control` в `config/packages/security.yaml`:
   ```yaml
       access_control:
           - { path: ^/healthcheck, roles: PUBLIC_ACCESS }
           - { path: ^/api/doc, roles: ROLE_ADMIN }
           - { path: ^/api/v2/user, roles: ROLE_ADMIN, methods: [POST] }
   ```
13. Создаём файл `.env.test` — копию файла `.env` проекта в директории `/app/deploy/releases/shared/`
14. Меняем в файле `DATABASE_URL` и **удаляем переменные** `POSTGRES_DB`, `POSTGRES_USER`, `POSTGRES_PASSWORD`:
     ```shell
     APP_ENV=test
     DATABASE_URL=postgresql://testing:testing@test_postgres:5432/testing?serverVersion=17&charset=utf8
     ```
15. **Внимание! Для корректной сборки (компиляции) кэша для prod-окружения нужно исправить `config/packages/cache.yaml` и `config/packages/doctrine.yaml`** - без этого проект не поднимется.
   ```yaml
   ## cache.yaml
   framework:
       cache:
           app: cache.adapter.redis
           default_redis_provider: '%env(REDIS_DSN)%'
           pools:
               doctrine.result_cache_pool:
                   adapter: cache.adapter.memcached
                   provider: doctrine_memcached_provider
               doctrine.metadata_cache_pool:
                   adapter: cache.adapter.memcached
                   provider: doctrine_memcached_provider
   ```
   ```yaml
   ## doctrine.yaml
   doctrine:
       dbal:
           url: '%env(resolve:DATABASE_URL)%'
   
           profiling_collect_backtrace: '%kernel.debug%'
           use_savepoints: true
           types:
               communicationChannel: App\Application\Doctrine\Types\CommunicationChannelType
       orm:
           metadata_cache_driver:
               type: pool
               pool: doctrine.metadata_cache_pool
           query_cache_driver:
               type: pool
               pool: doctrine.metadata_cache_pool
           result_cache_driver:
               type: pool
               pool: doctrine.result_cache_pool
           auto_generate_proxy_classes: true
           enable_lazy_ghost_objects: true
           report_fields_where_declared: true
           validate_xml_mapping: true
           naming_strategy: doctrine.orm.naming_strategy.underscore_number_aware
           identity_generation_preferences:
               Doctrine\DBAL\Platforms\PostgreSQLPlatform: identity
           auto_mapping: true
           mappings:
               App:
                   type: attribute
                   is_bundle: false
                   dir: '%kernel.project_dir%/src/Domain/Entity'
                   prefix: 'App\Domain\Entity'
                   alias: App
           controller_resolver:
               auto_mapping: false
           filters:
               soft_delete_filter:
                   class: App\Application\Doctrine\SoftDeletedFilter
                   enabled: true
                   parameters:
                       checkTime: true
   
   when@test:
       doctrine:
           dbal:
               dbname_suffix: '_test%env(default::TEST_TOKEN)%'
   
   when@prod:
       doctrine:
           orm:
               auto_generate_proxy_classes: false
               proxy_dir: '%kernel.build_dir%/doctrine/orm/Proxies'
               query_cache_driver:
                   type: pool
                   pool: doctrine.metadata_cache_pool
               result_cache_driver:
                   type: pool
                   pool: doctrine.result_cache_pool
   
       framework:
           cache:
               pools:
                   doctrine.result_cache_pool:
                       adapter: cache.app
                   doctrine.system_cache_pool:
                       adapter: cache.system
   
   ```
## Добавляем конфиг `haproxy`
1. Создаём директорию `/app/deploy/haproxy`
    ```shell
    mkdir /app/deploy/haproxy
    ```
2. Создаём файл `/app/deploy/haproxy/Dockerfile`
    ```dockerfile
    FROM haproxy:alpine
    USER root
    RUN apk add --no-cache socat
    RUN mkdir /sock && chown -R haproxy:haproxy /sock
    USER haproxy
    ```
3. Создаём файл `/app/deploy/haproxy/gateway.cfg`
    ```shell
    global
      stats socket /sock/admin.sock user haproxy group haproxy mode 660 level admin
      log stdout format raw local0 info
    
    defaults
      mode               http
      log                global
      timeout connect    5s
      timeout http-request 15s
      timeout queue      30s
      timeout client     300s
      timeout server     300s
    
    frontend  fe_main
      bind :80           # direct HTTP access
      default_backend    blue_green
    
    backend blue_green
      mode http
    
      option httpchk GET /healthcheck
      http-check expect status 200
    
      balance          roundrobin
      # green nginx
      server green	172.20.0.10:80 check inter 1s fall 2 rise 1
      # blue nginx
      server blue	172.20.0.11:80 check inter 1s fall 2 rise 1
    ```
   **В конце конфига нужна пустая строка. Здесь её нет, чтобы редактор нормально подсвечивал `yaml`-разметку**

## Добавляем `docker-compose.yml` для деплоев
1. Создаём файл `/app/deploy/docker-compose.yml`
    ```yaml
    services:
        gateway:
            build: haproxy
            container_name: gateway
            ports:
                - '80:80'
            volumes:
                - './haproxy/gateway.cfg:/usr/local/etc/haproxy/haproxy.cfg:r'
            networks:
                - main

        green_nginx:
            image: nginx:alpine
            container_name: greennginx
            volumes:
                - './nginx/green.conf:/etc/nginx/conf.d/default.conf:r'
                - './releases/green:/app:delegated'
            networks:
                main:
                    ipv4_address: 172.20.0.10
            depends_on:
                - green
            healthcheck:
                test: ["CMD", "curl", "-f", "http://localhost/"]
                interval: 5s
                timeout: 10s
                retries: 3

        blue_nginx:
            image: nginx:alpine
            container_name: bluenginx
            volumes:
                - './nginx/blue.conf:/etc/nginx/conf.d/default.conf:r'
                - './releases/blue:/app:delegated'
            networks:
                main:
                    ipv4_address: 172.20.0.11
            depends_on:
                - blue
            healthcheck:
                test: ["CMD", "curl", "-f", "http://localhost/"]
                interval: 5s
                timeout: 10s
                retries: 3

        blue:
            build: docker
            container_name: blue
            restart: unless-stopped
            environment:
                COLOR: blue
            volumes:
                - ./releases/blue:/app:delegated
                - ./releases/shared/.env:/app/.env:r
            working_dir: /app
            networks:
                main:
                    ipv4_address: 172.20.0.20

        green:
            build: docker
            container_name: green
            restart: unless-stopped
            environment:
                COLOR: green
            volumes:
                - ./releases/green:/app:delegated
                - ./releases/shared/.env:/app/.env:r
            working_dir: /app
            networks:
                main:
                    ipv4_address: 172.20.0.21

        blue_supervisor:
            build: docker/supervisor
            container_name: blue_supervisor
            restart: unless-stopped
            volumes:
                - ./releases/blue:/app:delegated
                - ./releases/shared/.env:/app/.env:r
                - ./supervisor/supervisord.conf:/etc/supervisor/supervisord.conf:r
            working_dir: /app
            depends_on:
                - blue
                - rabbitmq
            networks:
                - main

        green_supervisor:
            build: docker/supervisor
            container_name: green_supervisor
            restart: unless-stopped
            volumes:
                - ./releases/green:/app:delegated
                - ./releases/shared/.env:/app/.env:r
                - ./supervisor/supervisord.conf:/etc/supervisor/supervisord.conf:r
            working_dir: /app
            depends_on:
                - green
                - rabbitmq
            networks:
                - main

        test:
            build: docker
            container_name: test
            restart: unless-stopped
            environment:
                APP_ENV: test
            depends_on:
                test_postgres:
                    condition: service_healthy
            volumes:
                - ./releases/test:/app
                - ./releases/shared/.env.test:/app/.env.test:r
            working_dir: /app
            networks:
                - main
                - default

        test_postgres:
            image: postgres:17
            container_name: test_postgres
            environment:
                POSTGRES_DB: testing
                POSTGRES_USER: testing
                POSTGRES_PASSWORD: testing
            healthcheck:
                test: ["CMD-SHELL", "pg_isready -U testing -d testing"]
                timeout: 20s
                retries: 10

        postgres:
            image: postgres:17
            container_name: postgres
            restart: always
            environment:
                POSTGRES_DB: app
                POSTGRES_USER: prod
                POSTGRES_PASSWORD: prod
            volumes:
                - ./postgres_data:/var/lib/postgresql/data
            networks:
                - main

        redis:
            image: redis:alpine
            container_name: redis
            restart: always
            networks:
                - main

        memcached:
            image: memcached:latest
            container_name: memcached
            restart: always
            networks:
                - main

        rabbitmq:
            image: rabbitmq:3-management
            container_name: rabbitmq
            hostname: rabbit-mq
            restart: always
            environment:
                RABBITMQ_DEFAULT_USER: user
                RABBITMQ_DEFAULT_PASS: password
            ports:
                - '15672:15672'
            networks:
                - main

        elasticsearch:
            image: elasticsearch:9.2.0
            container_name: elasticsearch
            restart: always
            environment:
                - cluster.name=docker-cluster
                - bootstrap.memory_lock=true
                - discovery.type=single-node
                - "ES_JAVA_OPTS=-Xms512m -Xmx512m"
                - ELASTIC_PASSWORD=gpKUgKj84=AG8k6erd3b
            ulimits:
                memlock:
                    soft: -1
                    hard: -1
            networks:
                - main

        kibana:
            image: kibana:9.2.0
            container_name: kibana
            restart: always
            depends_on:
                - elasticsearch
            ports:
                - '5601:5601'
            networks:
                - main

        graphite:
            image: graphiteapp/graphite-statsd
            container_name: graphite
            restart: always
            ports:
                - '8000:80'
                - '2003:2003'
                - '8125:8125/udp'
            networks:
                - main

        grafana:
            image: grafana/grafana
            container_name: grafana
            restart: always
            ports:
                - '3000:3000'
            networks:
                - main

    networks:
        main:
            driver: bridge
            ipam:
                config:
                    - subnet: 172.20.0.0/24
    ```

## Добавляем конфиг supervisor для воркеров
1. Создаём директорию `/app/deploy/supervisor`
    ```shell
    mkdir /app/deploy/supervisor
    ```
2. Создаём файл `/app/deploy/supervisor/supervisord.conf`
    ```ini
    [supervisord]
    nodaemon=true
    logfile=/var/log/supervisord.log

    [program:messenger-consumer]
    command=php /app/bin/console messenger:consume async --time-limit=3600 --memory-limit=128M
    autostart=true
    autorestart=true
    numprocs=2
    process_name=%(program_name)s_%(process_num)02d
    stdout_logfile=/dev/stdout
    stdout_logfile_maxbytes=0
    stderr_logfile=/dev/stderr
    stderr_logfile_maxbytes=0
    ```
   **Настройте `command`, `numprocs` и транспорт (`async`) под ваш проект**

## Добавляем скрипты деплоев

1. В директории `/app/deploy` создаём директорию `scripts` и даём всем полные права
    ```shell
    mkdir scripts
    chmod a+rwx scripts
    ```
2. Добавляем скрипт `/app/deploy/scripts/deploy.sh`
    ```shell
    #!/bin/bash
    
    cd /app/deploy/scripts
    
    if [ "$(sudo docker ps -q -f name=blue)" ]; then
        echo "Container 'blue' is running."
        CURRENT=green PREV=blue bash ./_deploy-general.sh
    else
        echo "Container 'blue' is not running."
        CURRENT=blue PREV=green bash ./_deploy-general.sh
    fi
    ```
3. Добавляем скрипт `/app/deploy/scripts/test.sh`
    ```shell
    #!/bin/bash
    
    function downloadNewCode {
        sudo rm -rf $DEPLOY_DIR/releases/test
        git clone http://gitlab-ci-token:${CI_JOB_TOKEN}@${CI_SERVER_FQDN}/${CI_PROJECT_PATH} $DEPLOY_DIR/releases/test
        rm -f $DEPLOY_DIR/releases/test/.env.test
    }
    
    function buildApp {
        cd $DEPLOY_DIR
    
        sudo docker compose up -d test
        sudo docker exec --user root test composer install --no-interaction --optimize-autoloader
    
        sudo docker exec --user root test php bin/console doctrine:migrations:migrate --no-interaction

        cd $DEPLOY_DIR/releases/test
    
        yarn install
        yarn encore production
    }
    
    function runTests {
        sudo docker exec --user root test php vendor/bin/codecept run Unit
        sudo docker exec --user root test php vendor/bin/codecept run Functional
    }
    
    function stopTestsContainer {
        cd $DEPLOY_DIR
    
        sudo docker compose down test test_postgres --remove-orphans -v
    }
    
    downloadNewCode
    buildApp
    
    runTests
    stopTestsContainer
    ```
4. Добавляем скрипт `/app/deploy/scripts/_deploy-general.sh`
    ```shell
    #!/bin/bash
    
    if [ -z "$CURRENT" ]; then
        echo "Please set CURRENT variable"
        exit 1
    fi
    
    if [ -z "$PREV" ]; then
        echo "Please set PREV variable"
        exit 1
    fi
    
    function downloadNewCode {
        sudo rm -rf $DEPLOY_DIR/releases/$CURRENT
        git clone http://gitlab-ci-token:${CI_JOB_TOKEN}@${CI_SERVER_FQDN}/${CI_PROJECT_PATH} $DEPLOY_DIR/releases/$CURRENT
    }
    
    function buildApp {
        cd $DEPLOY_DIR
    
        sudo docker compose up -d $CURRENT
    
        sudo docker exec --user root $CURRENT composer install --no-dev --no-interaction --optimize-autoloader
        sudo docker exec --user root $CURRENT php bin/console doctrine:migrations:migrate --no-interaction
        sudo docker exec --user root $CURRENT chmod a+rwx -R var
    
        cd $DEPLOY_DIR/releases/$CURRENT
    
        yarn install
        yarn encore production
    }
    
    function optimizeResources {
        cd $DEPLOY_DIR
    
        sudo docker exec $CURRENT php bin/console cache:clear
        sudo docker exec $CURRENT php bin/console cache:warmup
    }
    
    function startCurrentRelease {
        cd $DEPLOY_DIR
    
        sudo docker compose up -d ${CURRENT}_nginx ${CURRENT}_supervisor
    
        sleep 10
    
        sudo docker exec --user root gateway sh -c "echo \"set server blue_green/${CURRENT} state ready\" | socat stdio unix-connect:/sock/admin.sock"
    }
    
    function stopPrevRelease {
        cd $DEPLOY_DIR
    
        sudo docker exec --user root gateway sh -c "echo \"set server blue_green/${PREV} state maint\" | socat stdio unix-connect:/sock/admin.sock"
    
        sleep 10
        sudo docker compose stop $PREV ${PREV}_nginx ${PREV}_supervisor
    }
    
    function changeOwnership {
        sudo chown 1000:1000 -R $DEPLOY_DIR/releases/$CURRENT
    }
    
    downloadNewCode
    buildApp
    
    changeOwnership
    
    optimizeResources
    
    startCurrentRelease
    stopPrevRelease
    ```

## Добавляем конфигурации `nginx` blue/green
1. В директории `/app/deploy` создаём директорию `nginx` и даём всем полные права
    ```shell
    mkdir nginx
    chmod a+rwx nginx
    ```
2. Создаём файл `/app/deploy/nginx/blue.conf`
    ```shell
    server {
        listen 80;
        server_name localhost;
        error_log  /var/log/nginx/error.log;
        access_log /var/log/nginx/access.log;
        root /app/public;

        rewrite ^/index\.php/?(.*)$ /$1 permanent;

        try_files $uri @rewriteapp;

        location @rewriteapp {
            rewrite ^(.*)$ /index.php/$1 last;
        }

        location ~ /\. {
            deny all;
        }

        location ~ ^/index\.php(/|$) {
            fastcgi_split_path_info ^(.+\.php)(/.*)$;
            include fastcgi_params;
            fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
            fastcgi_param PATH_INFO $fastcgi_path_info;
            fastcgi_index index.php;
            send_timeout 1800;
            fastcgi_read_timeout 1800;
            fastcgi_pass 172.20.0.20:9000;
        }
    }
    ```
3. Создаём файл `/app/deploy/nginx/green.conf`
    ```shell
    server {
        disable_symlinks off;
        listen 80;
        server_name localhost;
        error_log  /var/log/nginx/error.log;
        access_log /var/log/nginx/access.log;
        root /app/public;

        rewrite ^/index\.php/?(.*)$ /$1 permanent;

        try_files $uri @rewriteapp;

        location @rewriteapp {
            rewrite ^(.*)$ /index.php/$1 last;
        }

        location ~ /\. {
            deny all;
        }

        location ~ ^/index\.php(/|$) {
            fastcgi_split_path_info ^(.+\.php)(/.*)$;
            include fastcgi_params;
            fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
            fastcgi_param PATH_INFO $fastcgi_path_info;
            fastcgi_index index.php;
            send_timeout 1800;
            fastcgi_read_timeout 1800;
            fastcgi_pass 172.20.0.21:9000;
        }
    }
    ```
4. В **проекте** создаём файл `.gitlab-ci.yml`
    ```yml    
    stages:
        - test
        - deploy
    
    tests:
        stage: test
        script:
            - cd $DEPLOY_DIR
            - bash ./scripts/test.sh
        only:
            - master
    
    deploy:
        stage: deploy
        script:
            - cd $DEPLOY_DIR
            - bash ./scripts/deploy.sh
        when: manual
    ```
5. Переходим в директорию `/app/deploy` и запускаем инфраструктурные контейнеры
    ```shell
    sudo docker compose up postgres redis memcached rabbitmq elasticsearch kibana graphite grafana gateway -d
    ```
6. Пушим код в ветку `master`. Видим в `Build -> Pipelines`, что пайплайн запустился. Пробуем запустить `deploy`

## Добавляем Rollback
1. Добавляем скрипт `/app/deploy/scripts/rollback.sh`
    ```shell
    #!/bin/bash
    
    cd $DEPLOY_DIR/scripts
    
    if [ "$(sudo docker ps -q -f name=green)" ]; then
        echo "Container 'green' is running."
        echo "Starting rollback"
        PREV=blue CURRENT=green bash ./_rollback-general.sh
    else
        echo "Container 'green' is not running."
        echo "Starting rollback"
        PREV=green CURRENT=blue bash ./_rollback-general.sh
    fi
    ```
2. Добавляем скрипт `/app/deploy/scripts/_rollback-general.sh`
    ```shell
    #!/bin/bash
    
    if [ -z "$CURRENT" ]; then
         echo "Please set CURRENT variable"
         exit 1
    fi
    
    if [ -z "$PREV" ]; then
         echo "Please set PREV variable"
         exit 1
    fi
    
    function startPrevRelease {
         cd $DEPLOY_DIR
         echo "Starting previous container: ${PREV}"
         sudo docker compose up -d $PREV ${PREV}_nginx ${PREV}_supervisor
    
         sleep 10
    
         sudo docker exec --user root gateway sh -c "echo \"set server blue_green/${PREV} state ready\" | socat stdio unix-connect:/sock/admin.sock"
    }
    
    function stopCurrentRelease {
         cd $DEPLOY_DIR
         sudo docker exec --user root gateway sh -c "echo \"set server blue_green/${CURRENT} state maint\" | socat stdio unix-connect:/sock/admin.sock"
    
         sleep 10
    
         sudo docker compose stop $CURRENT ${CURRENT}_nginx ${CURRENT}_supervisor
    }
    
    startPrevRelease
    stopCurrentRelease
    ```
3. Обновляем файл `.gitlab-ci.yml`
    ```yaml
    stages:
      - test
      - deploy
      - rollback
    
    tests:
      stage: test
      script:
        - cd $DEPLOY_DIR
        - bash ./scripts/test.sh
      only:
        - master
    
    deploy:
      stage: deploy
      script:
        - cd $DEPLOY_DIR
        - bash ./scripts/deploy.sh
      when: manual
    
    rollback:
      stage: rollback
      script:
        - cd $DEPLOY_DIR
        - bash ./scripts/rollback.sh
      when: manual
    ```
4. Пушим код в ветку `master`. Видим в `Build -> Pipelines`, что добавился пайплайн `rollback`. Пробуем запустить `rollback`
