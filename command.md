phpstan analyse -c ./tests/phpstan.neon --xdebug -v
phpcs --standard=PHPCompatibility --runtime-set testVersion 8.4- --ignore=./src/Kernel.php ./src -vv
