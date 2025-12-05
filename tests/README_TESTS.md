# phpunit.xml pour exclure les tests d'intégration par défaut

Pour exécuter uniquement les tests unitaires (rapides, sans MongoDB) :

```bash
docker compose exec app php bin/phpunit --exclude-group integration --testdox
```

Pour exécuter TOUS les tests y compris les tests d'intégration (nécessite MongoDB) :

```bash
docker compose exec app php bin/phpunit --testdox
```

Pour exécuter uniquement les tests de la commande de migration :

```bash
docker compose exec app php bin/phpunit tests/Command/NotificationMigrateCommandTest.php --testdox
```
