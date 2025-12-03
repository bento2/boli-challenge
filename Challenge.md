# Test Technique - Ingénieur Backend Symfony / MongoDB / RabbitMQ / DevOps

## 📋 Informations générales

**Durée estimée** : 4-6 heures  
**Niveau** : Expert  
**Contexte** : Application de santé patient avec architecture microservices

## 🎯 Objectifs du test

Ce test évalue vos compétences sur :
- **Symfony 7.3** : Architecture, services, commandes, tests
- **MongoDB** : Doctrine ODM, requêtes complexes, agrégations, multi-bases
- **RabbitMQ** : Symfony Messenger, routing, retry, dead letter queues
- **DevOps** : Docker, Kubernetes, scripts bash, monitoring

## 📦 Contexte du projet

Vous travaillez sur une application de santé patient composée de microservices Symfony. Chaque service :
- Utilise MongoDB avec Doctrine ODM (multi-bases : test, diabetes, wellness, maternity)
- Communique via RabbitMQ avec Symfony Messenger
- Est déployé sur Kubernetes
- Expose une API REST avec documentation OpenAPI

## 🚀 Exercice 1 : Architecture Symfony et Services (1h30)

### Contexte
Vous devez créer un nouveau service `notification-api` qui gère les notifications push pour les patients.

### Tâches

#### 1.1 Création du service de base
- Créer la structure de base du service Symfony
- Configurer Doctrine MongoDB ODM avec support multi-bases (test, diabetes, wellness, maternity)
- Configurer Symfony Messenger avec RabbitMQ :
  - Transport `notifications` (exchange topic `notification-api`, routing key `notification.*`)
  - Transport `services` (exchange fanout `services`)
  - Transport `failed` pour les messages en échec
- Créer une route `/health` retournant un JSON avec `status`, `timestamp`, `version`

#### 1.2 Service de gestion des notifications
Créer un service `NotificationService` qui :
- Gère l'envoi de notifications push via Firebase Cloud Messaging (FCM)
- Supporte plusieurs types de notifications (alert, reminder, info)
- Gère le rate limiting (max 10 notifications/minute par utilisateur)
- Log toutes les opérations avec Monolog

**Contraintes** :
- Utiliser l'injection de dépendances Symfony
- Implémenter une interface `NotificationServiceInterface`
- Gérer les erreurs de manière appropriée
- Utiliser le cache Symfony pour le rate limiting

#### 1.3 Commande console de test
Créer une commande `app:notification:test` qui :
- Accepte un `userId` et un `type` en paramètres
- Envoie une notification de test
- Affiche le résultat (succès/échec) avec des couleurs
- Gère les erreurs et affiche des messages clairs

**Bonus** : Ajouter une option `--dry-run` pour simuler l'envoi sans réellement envoyer.

### Livrables attendus
- Code source complet du service
- Configuration YAML (doctrine_mongodb.yaml, messenger.yaml)
- Tests unitaires pour `NotificationService` (couverture > 80%)
- Documentation technique (README.md) expliquant l'architecture

---

## 🗄️ Exercice 2 : MongoDB - Requêtes complexes et agrégations (1h)

### Contexte
Vous devez optimiser et créer des requêtes MongoDB complexes pour analyser les données de notifications.

### Tâches

#### 2.1 Document MongoDB
Créer un document `Notification` avec les champs suivants :
```php
- id (MongoDB ObjectId)
- userId (string, indexé)
- type (string: alert|reminder|info)
- title (string)
- body (string)
- data (array, flexible)
- status (string: pending|sent|failed)
- sentAt (MongoDB\BSON\UTCDateTime, nullable)
- createdAt (MongoDB\BSON\UTCDateTime)
- serviceName (string: diabetes|wellness|maternity)
- readAt (MongoDB\BSON\UTCDateTime, nullable)
```

**Contraintes** :
- Index composé sur `userId` et `createdAt` (descendant)
- Index sur `status` et `serviceName`
- Index TTL sur `createdAt` (expiration après 90 jours) 

#### 2.2 Repository avec requêtes complexes
Créer un `NotificationRepository` avec les méthodes suivantes :

1. **`findUnreadByUser(string $userId, string $serviceName, int $limit = 20)`**
   - Retourne les notifications non lues d'un utilisateur
   - Triées par date de création décroissante
   - Limitées à `$limit` résultats
   - Utilise une projection pour ne récupérer que les champs nécessaires

2. **`countByStatusAndService(string $status, string $serviceName, \DateTime $startDate, \DateTime $endDate)`**
   - Compte les notifications par statut et service dans une période
   - Utilise une agrégation MongoDB optimisée

3. **`getStatisticsByService(string $serviceName, \DateTime $startDate, \DateTime $endDate)`**
   - Retourne des statistiques complètes :
     - Total de notifications
     - Par type (alert, reminder, info)
     - Par statut (pending, sent, failed)
     - Taux de succès (%)
     - Temps moyen de traitement (différence entre createdAt et sentAt)
   - Utilise une pipeline d'agrégation MongoDB

4. **`findFailedNotificationsOlderThan(int $hours)`**
   - Trouve les notifications en échec plus anciennes que X heures
   - Pour retry automatique
   - Utilise une requête avec opérateurs MongoDB

#### 2.3 Migration de données
Créer une commande `app:notification:migrate` qui :
- Migre les anciennes notifications d'un format vers le nouveau
- Gère les multi-bases (diabetes, wellness, maternity)
- Affiche une barre de progression
- Supporte le rollback
- Log toutes les opérations

**Contraintes** :
- Traiter par batch de 1000 documents
- Utiliser des transactions MongoDB si possible
- Gérer les erreurs et permettre la reprise

### Livrables attendus
- Document MongoDB avec annotations Doctrine
- Repository avec toutes les méthodes
- Tests d'intégration MongoDB (utiliser MongoDB Memory pour les tests)
- Script de migration avec rollback

---

## 🐰 Exercice 3 : RabbitMQ et Symfony Messenger (1h)

### Contexte
Vous devez implémenter un système de notifications asynchrones avec retry et dead letter queue.

### Tâches

#### 3.1 Messages Messenger
Créer les messages suivants :

1. **`SendNotificationMessage`**
   - Contient : userId, type, title, body, data, serviceName
   - Routé vers le transport `notifications`
   - Routing key : `notification.send`

2. **`NotificationStatusUpdateMessage`**
   - Contient : notificationId, status, sentAt
   - Routé vers le transport `services` (fanout)
   - Pour notifier les autres services

3. **`BulkNotificationMessage`**
   - Contient : array de notifications
   - Routé vers le transport `notifications`
   - Routing key : `notification.bulk`

#### 3.2 Handlers avec retry et gestion d'erreurs
Créer les handlers correspondants :

1. **`SendNotificationHandler`**
   - Envoie la notification via `NotificationService`
   - En cas d'échec, retry 3 fois avec backoff exponentiel (2s, 4s, 8s)
   - Après 3 échecs, envoie vers la dead letter queue
   - Log toutes les tentatives

2. **`BulkNotificationHandler`**
   - Traite les notifications en batch
   - En cas d'échec partiel, réessaie uniquement les notifications échouées
   - Utilise un middleware de transaction MongoDB

3. **`NotificationStatusUpdateHandler`**
   - Met à jour le statut dans MongoDB
   - Gère les conflits de mise à jour (optimistic locking)

#### 3.3 Middleware personnalisé
Créer un middleware `NotificationMetricsMiddleware` qui :
- Mesure le temps de traitement de chaque message
- Compte les messages traités/échoués
- Expose ces métriques via un service `NotificationMetricsService`
- Log les métriques toutes les 100 messages

#### 3.4 Dead Letter Queue Handler
Créer un handler pour la dead letter queue qui :
- Analyse les messages en échec
- Envoie une alerte (email ou log critique)
- Tente une dernière fois après 1 heure
- Archive les messages définitivement échoués

### Livrables attendus
- Tous les messages et handlers
- Configuration Messenger complète
- Tests unitaires pour les handlers
- Documentation du flux de messages (diagramme ASCII ou Mermaid)

---

## 🐳 Exercice 4 : DevOps - Docker et Kubernetes (1h30)

### Contexte
Vous devez containeriser le service `notification-api` et le déployer sur Kubernetes.

### Tâches

#### 4.1 Dockerfile optimisé
Créer un `Dockerfile` pour le service qui :
- Utilise une image de base appropriée (PHP 8.4 avec extensions)
- Multi-stage build (build + runtime)
- Installe les dépendances Composer
- Configure PHP-FPM ou le serveur Symfony
- Expose le port 8009
- Crée un utilisateur non-root pour la sécurité
- Optimise les layers Docker (cache des dépendances)

**Contraintes** :
- Image finale < 200MB
- Support des health checks
- Variables d'environnement pour la configuration

#### 4.2 Docker Compose
Créer un `compose.yaml` pour le développement local qui :
- Démarre le service notification-api
- Configure MongoDB, RabbitMQ
- Démarre les workers Messenger automatiquement
- Configure les volumes pour le développement
- Définit un réseau Docker

#### 4.3 Manifests Kubernetes
Créer les manifests Kubernetes suivants :

1. **Deployment**
   - 2 replicas minimum
   - Health checks (liveness + readiness)
   - Resource limits (CPU: 500m, Memory: 512Mi)
   - Variables d'environnement depuis ConfigMap et Secrets
   - Init container pour vérifier MongoDB et RabbitMQ

2. **Service**
   - ClusterIP pour communication interne
   - Port 8009

3. **ConfigMap**
   - Configuration de base (URLs, timeouts)
   - Support multi-environnements (dev, stage, prod)

4. **HorizontalPodAutoscaler**
   - Auto-scaling basé sur CPU (50-80%)
   - Min: 2 pods, Max: 10 pods

5. **PodDisruptionBudget**
   - Minimum 1 pod disponible lors des mises à jour

#### 4.4 Scripts de déploiement
Créer des scripts bash :

1. **`build-and-push.sh`**
   - Construit l'image Docker
   - Tag avec version (git tag ou timestamp)
   - Push vers un registry (configurable)
   - Gère les erreurs

2. **`deploy.sh`**
   - Déploie sur Kubernetes
   - Applique les manifests dans le bon ordre
   - Attend que les pods soient ready
   - Vérifie les health checks
   - Rollback automatique en cas d'échec

3. **`monitor.sh`**
   - Affiche les logs en temps réel
   - Surveille les métriques (CPU, mémoire)
   - Détecte les erreurs et alerte
   - Affiche le statut des queues RabbitMQ

### Livrables attendus
- Dockerfile optimisé
- Docker Compose complet
- Tous les manifests Kubernetes
- Scripts de déploiement et monitoring
- Documentation de déploiement

---

## 🧪 Exercice 5 : Tests et Qualité (30 min)

### Tâches

#### 5.1 Tests unitaires
- Couverture de code > 80%
- Tests pour tous les services critiques
- Mocks appropriés pour les dépendances externes

#### 5.2 Tests d'intégration
- Tests MongoDB avec base de données de test
- Tests Messenger avec transport in-memory
- Tests d'API avec Symfony Test Client

#### 5.3 Qualité de code
- Configuration PHPStan (niveau 8 minimum)
- Configuration PHP CS Fixer
- Pas de code dupliqué
- Documentation PHPDoc complète

### Livrables attendus
- Suite de tests complète
- Configuration des outils de qualité
- Rapport de couverture de code

---

## 🎯 Exercice Bonus : Cas pratique complet (optionnel, +1h)

### Scénario
Un patient diabétique doit recevoir des rappels de prise de médicaments. Le système doit :
1. Planifier des notifications récurrentes (tous les jours à 8h et 20h)
2. Vérifier que le patient n'a pas déjà pris son médicament (via une API externe)
3. Envoyer la notification uniquement si nécessaire
4. Suivre les statistiques d'ouverture
5. Adapter la fréquence selon le taux d'ouverture

### Tâches
- Créer un scheduler (commande Symfony avec cron ou Symfony Scheduler)
- Intégrer avec l'API externe (mock acceptable)
- Implémenter la logique métier
- Créer un dashboard de statistiques (API endpoint)
- Gérer les cas d'erreur et edge cases

---

## 📝 Critères d'évaluation

### Symfony (30%)
- Architecture propre et respect des bonnes pratiques
- Utilisation appropriée des composants Symfony
- Gestion des erreurs et logging
- Tests unitaires et d'intégration

### MongoDB (25%)
- Modélisation des documents
- Requêtes optimisées avec index appropriés
- Agrégations complexes
- Gestion multi-bases

### RabbitMQ (20%)
- Configuration Messenger correcte
- Gestion des retries et dead letter queues
- Middleware personnalisés
- Documentation du flux

### DevOps (25%)
- Dockerfile optimisé
- Manifests Kubernetes complets
- Scripts robustes avec gestion d'erreurs
- Bonnes pratiques de sécurité

---

## 🚀 Instructions de soumission

1. Créer un repository Git (ou fork du projet)
2. Implémenter tous les exercices dans des branches séparées
3. Créer une Pull Request pour chaque exercice
4. Documenter chaque exercice dans un README.md
5. Fournir un script de setup pour tester rapidement

**Format de soumission** :
```
notification-api/
├── src/
├── config/
├── tests/
├── k8s/
├── docker/
├── scripts/
├── README.md
└── TEST_RESULTS.md (résultats des tests, métriques)
```

---

## 💡 Conseils

- **Priorisez la qualité** : Mieux vaut un code propre et testé qu'un code complet mais bugué
- **Documentez** : Expliquez vos choix techniques
- **Testez** : Les tests sont aussi importants que le code
- **Optimisez** : Pensez performance et scalabilité
- **Sécurité** : Ne négligez pas les aspects sécurité (secrets, validation, etc.)

---

## ❓ Questions ?

Si vous avez des questions sur le test, n'hésitez pas à les poser. Il vaut mieux clarifier que de faire des suppositions.

**Bonne chance ! 🚀**

