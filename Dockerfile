#Image standard php pour Symfony
FROM php:8.4-cli



# Mise à jour des listes de paquets et installation du binaire 'git' (requis par Composer/dépôts)
# On installe également 'libzip-dev' pour l'extension 'zip'
RUN apt update && apt install -y \
    git \
    libzip-dev \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    # Nettoyage pour réduire la taille de l'image
    && rm -rf /var/lib/apt/lists/*

#pour les extensions php https://github.com/mlocati/docker-php-extension-installer
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/


# Installation des extensions requises par le challenge
# - intl : requis par Symfony
# - mongodb : requis par Doctrine ODM
# - amqp : requis par RabbitMQ / Messenger
# - zip : pour Composer
RUN install-php-extensions intl mongodb amqp zip opcache

# Installation de Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
# Installation de PHPStan (via Composer global)
RUN composer global require phpstan/phpstan phpcompatibility/php-compatibility "squizlabs/php_codesniffer=*"

# Ajouter le binaire Composer global au PATH
ENV PATH="$PATH:/root/.composer/vendor/bin"
RUN phpcs --config-set installed_paths /root/.composer/vendor/phpcompatibility/php-compatibility/, /root/.composer/vendor/phpcs/phpcs/CodeSniffer/Standards/
#Installation de PHP CS Fixer
RUN curl -L https://github.com/FriendsOfPHP/PHP-CS-Fixer/releases/latest/download/php-cs-fixer.phar -o /usr/local/bin/php-cs-fixer && chmod +x /usr/local/bin/php-cs-fixer

# On définit le dossier de travail
WORKDIR /var/www/html

#pour utiliser directement le server de php
EXPOSE 8009
CMD ["php", "-S", "0.0.0.0:8009", "-t", "public"]
