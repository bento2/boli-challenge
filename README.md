# Boli Challenge - Documentation Technique

## Introduction

Ce projet est une application Symfony conçue pour gérer des notifications et interagir avec plusieurs bases de données MongoDB (Diabetes, Wellness, Maternity). Il met en œuvre une architecture orientée services et utilise un système de messagerie asynchrone.

## Architecture

### Stack Technique

-   **Langage** : PHP 8.4+
-   **Framework** : Symfony 7.3
-   **Base de données** : MongoDB (avec Doctrine ODM)
-   **Message Broker** : RabbitMQ (via Symfony Messenger)
-   **Notification** : Symfony Notifier / Firebase (intégration prévue)

### Structure des Données (Multi-Database)

L'application est configurée pour se connecter à plusieurs bases de données MongoDB distinctes, isolant ainsi les données par domaine métier. Chaque domaine possède son propre `DocumentManager` :

| Domaine                | Base de données | Namespace                |
| ---------------------- | --------------- | ------------------------ |
| **Diabetes** (Default) | `diabetes_db`   | `App\Document\Diabetes`  |
| **Wellness**           | `wellness_db`   | `App\Document\Wellness`  |
| **Maternity**          | `maternity_db`  | `App\Document\Maternity` |
| **Test**               | `test_db`       | `App\Document\Test`      |

### Messaging & Asynchronisme

L'application utilise **Symfony Messenger** pour gérer les tâches asynchrones et la communication inter-services via RabbitMQ :

-   **Transport `notifications`**

    -   **Type** : Exchange `topic`
    -   **Routing Key** : `notification.*`
    -   **Usage** : Dédié à l'envoi et au traitement des notifications.

-   **Transport `services`**

    -   **Type** : Exchange `fanout`
    -   **Usage** : Diffusion d'événements globaux aux autres services.

-   **Transport `failed`**
    -   **Type** : Exchange `direct`
    -   **Usage** : Stockage des messages n'ayant pas pu être traités après les tentatives de redélivrance.

## Composants Clés

### Services

#### `NotificationService`

Service central responsable de l'envoi des notifications.

-   **Interface** : `NotificationServiceInterface`
-   **Fonctionnalités** :
    -   **Rate Limiting** : Utilise `RateLimiterFactoryInterface` pour limiter le nombre d'envois par utilisateur (clé : `notification_api_{userId}`).
    -   **Dry Run** : Permet de simuler l'envoi sans appel externe (utile pour les tests et le débogage).
    -   **Logging** : Trace les erreurs et les limitations de débit.

### API Endpoints

#### `GET /health`

Endpoint de surveillance retournant l'état de l'application.

-   **Réponse JSON** :
    ```json
    {
        "status": "ok",
        "timestamp": "2023-10-27T10:00:00+00:00",
        "version": "1.0.0"
    }
    ```

### Commandes CLI

#### `app:notification:test`

Commande utilitaire pour tester manuellement le service de notification.

-   **Arguments** :
    -   `userId` : Identifiant de l'utilisateur cible.
    -   `type` : Type de notification.
-   **Options** :
    -   `--dry-run` (`-d`) : Active le mode simulation.
-   **Exemple** :
    ```bash
    bin/console app:notification:test user123 alert --dry-run
    ```

## Configuration

### Variables d'Environnement

Les variables suivantes sont nécessaires au bon fonctionnement (fichier `.env`) :

-   **Base de données** :
    -   `MONGODB_URI`
    -   `MONGODB_USERNAME`
    -   `MONGODB_PASSWORD`
-   **Messaging** :
    -   `MESSENGER_TRANSPORT_DSN`
-   **Application** :
    -   `APP_VERSION`

### Doctrine ODM

La configuration multi-bases est définie dans `config/packages/doctrine_mongodb.yaml`. Les entités (Documents) sont mappées via des **Attributs PHP 8**.

## Installation et Démarrage

### Prérequis

-   Docker & Docker Compose
-   PHP 8.4 (optionnel si utilisation exclusive de Docker)

### Démarrage Rapide

1. **Lancer les conteneurs** :

    ```bash
    docker compose up -d
    ```

2. **Installation des dépendances** (si non fait au build) :

    ```bash
    docker compose exec php composer install
    ```

3. **Vérifier le statut** :
   Accédez à `http://localhost:8009/health` (ou le port configuré).
