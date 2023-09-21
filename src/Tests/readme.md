# Test instructions

## How to prepare for tests and setup test database

1. Make sure you have svn installed (`apt install -y subversion`)
2. Make sure you have mysql client installed (`apt install -y default-mysql-client`)
3. Make sure you have phpunit installed (`wget -O phpunit.phar https://phar.phpunit.de/phpunit-9.phar && chmod +x phpunit.phar && mv phpunit.phar /usr/local/bin/phpunit`)
4. Go to the plugin's folder and type `bin/install-wp-tests.sh wordpress_test root 'root' woodb latest`  (more about this [here](https://make.wordpress.org/cli/handbook/misc/plugin-unit-tests/#3-initialize-the-testing-environment-locally))
5. Add the following to the first line of rm-pagbank/tests/bootstrap.php: `define('WP_TESTS_PHPUNIT_POLYFILLS_PATH', dirname(__FILE__) . '/../vendor/yoast/phpunit-polyfills');`
6. Make sure the Woocommerce you have installed is the developer edition (check if wp-content/plugins/woocommerce/tests exists)
7. Run `composer install --dev` from the plugin's folder (make sure to not commit your changes in vendor and composer.lock)
8. Run `phpunit` from the plugin's folder to make sure everything is working and unit tests are passing


## Troubleshooting installation

### Test is not running
Delete `/tmp/wordpress-tests-lib` folder and rerun step 4 above

### Xdebug missing
Check if xdebug is installed by running `php -m | grep xdebug`. If it's not installed, run `docker-php-ext-enable xdebug`

## How to run tests

Go to the plugin's folder and type `phpunit --configuration src/Tests/phpunit.xml`

Alternatively, you can specify the test file you want to run, like this: `phpunit --configuration src/Tests/phpunit.xml src/Tests/Unit/ExampleTest.php` or filtering by test name: `phpunit --configuration src/Tests/phpunit.xml --filter testExample src/Tests/Unit/ExampleTest.php`





