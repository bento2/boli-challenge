#Image standard php pour Symfony
FROM php:8.4-cli



# Mise à jour des listes de paquets et installation du binaire 'git' (requis par Composer/dépôts)
# On installe également 'libzip-dev' pour l'extension 'zip'
RUN apt update && apt install -y \
    git \
    libzip-dev \
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


# On définit le dossier de travail
WORKDIR /var/www/html

#pour utiliser directement le server de php
EXPOSE 8009
CMD ["php", "-S", "0.0.0.0:8009", "-t", "public"]
