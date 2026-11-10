-- Test database used by `php artisan test` (see apps/api/phpunit.xml).
CREATE DATABASE IF NOT EXISTS request_desk_test;
GRANT ALL PRIVILEGES ON request_desk_test.* TO 'request_desk'@'%';
FLUSH PRIVILEGES;
