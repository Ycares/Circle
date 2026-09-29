# Marque-Page

Réseau social de lecture. Les utilisateurs suivent leur bibliothèque personnelle et rejoignent des
clubs de lecture qui avancent au même rythme grâce à un système de discussions par chapitre, avec un
garde-spoiler intégré (voir plus bas).

## Prérequis

- PHP 8.4
- PostgreSQL 16
- Composer
- Docker et Docker Compose (pour l'environnement conteneurisé, recommandé)

## Installation

### Avec Docker (recommandé)

```bash
git clone <url-du-dépôt> marque-page
cd marque-page
cp .env.example .env.local
```

Renseigne au moins `APP_SECRET` (une chaîne aléatoire quelconque) et `GOOGLE_BOOKS_API_KEY` dans
`.env.local` (voir la section [Variables d'environnement](#variables-denvironnement) ci-dessous).

```bash
docker compose up -d --build
docker compose exec php composer install
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec php php bin/console doctrine:fixtures:load --no-interaction
```

L'application est ensuite disponible sur http://localhost:8080.

### En local, sans Docker

```bash
git clone <url-du-dépôt> marque-page
cd marque-page
cp .env.example .env.local
composer install
```

Adapte `DATABASE_URL` dans `.env.local` pour pointer vers ta base PostgreSQL locale, puis :

```bash
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console doctrine:fixtures:load --no-interaction
symfony server:start   # ou : php -S 127.0.0.1:8000 -t public
```

## Mode démo

Les fixtures (`doctrine:fixtures:load`) créent un club de lecture avec trois comptes prêts à
l'emploi — aucune inscription n'est nécessaire pour explorer l'application :

| Email               | Mot de passe  | Situation dans le club « Les Fondations » |
|---------------------|---------------|--------------------------------------------|
| alice@example.com   | `password123` | Hôte du club, a lu jusqu'au chapitre 5 (fin) |
| bob@example.com     | `password123` | N'a lu que le chapitre 2                    |
| chloe@example.com   | `password123` | A lu jusqu'au chapitre 5 (fin)              |

Connecte-toi avec `bob@example.com`, ouvre le club « Les Fondations » : les channels des chapitres 3
à 5 apparaissent verrouillés, et le message de Chloé qui spoile la fin du livre (posté dans le
chapitre 5) n'est ni visible ni présent dans le HTML de la page. Reconnecte-toi ensuite avec
`chloe@example.com` ou `alice@example.com` pour voir ce même message apparaître.

## Variables d'environnement

Toutes les variables se configurent dans `.env.local` (non commité) — voir `.env.example` pour la
liste complète.

```dotenv
# Base de données
DATABASE_URL="postgresql://user:password@127.0.0.1:5432/marque_page?serverVersion=16&charset=utf8"

# Google Books API
GOOGLE_BOOKS_API_KEY=

# Sécurité
APP_SECRET=
```

### Obtenir une clé Google Books API

1. Ouvre la [console Google Cloud](https://console.cloud.google.com/) et crée un projet (ou
   réutilise un projet existant).
2. Dans **APIs & Services > Library**, active l'**API Google Books**.
3. Dans **APIs & Services > Credentials**, crée une **clé API**.
4. Colle la clé obtenue dans `GOOGLE_BOOKS_API_KEY` de ton `.env.local`.

Le quota gratuit de l'API est largement suffisant pour un usage de démonstration. Les recherches de
livres sont mises en cache côté serveur (1h) pour limiter les appels.

## Règle métier centrale : le garde-spoiler par chapitre

C'est la fonctionnalité qui justifie l'existence du projet, implémentée dans
`src/Domain/Service/SpoilerGuardService.php` (couverture de tests unitaires à 100%).

**Principe** : quand l'hôte d'un club définit le livre en cours, il indique son nombre de chapitres.
Un channel de discussion est généré pour chaque chapitre. Chaque membre déclare sa progression sous
forme d'un numéro de chapitre atteint. Un message posté dans le channel du chapitre *N* n'est visible
qu'aux membres ayant déclaré avoir atteint (au moins) le chapitre *N* — il existe aussi un channel
**global**, non lié à un chapitre, toujours visible à tous quelle que soit la progression de chacun.

Ce filtrage est appliqué **côté serveur**, avant l'envoi au template : un message non autorisé n'est
jamais présent dans le HTML renvoyé au navigateur, même masqué en CSS. C'est vérifié explicitement
par un test fonctionnel (`tests/UI/Controller/ClubControllerTest.php`) qui inspecte le corps brut de
la réponse HTTP.

### Exemple concret

Un club lit *Fondation* (5 chapitres). Chloé, qui a terminé le livre, poste dans le channel du
**chapitre 5** : « Le retournement final avec le plan Seldon est juste incroyable ! ». Bob n'a
déclaré avoir atteint que le **chapitre 2** :

- Bob ne voit **ni le channel du chapitre 5 déverrouillé, ni le message de Chloé** — ce message
  n'existe simplement pas dans la page qui lui est envoyée.
- Le channel **global** du club (annonces, organisation) reste visible à Bob comme à Chloé, quelle
  que soit leur progression.
- Dès que Bob déclare avoir atteint le chapitre 5, le message de Chloé devient visible pour lui.

### Changement de livre en cours

Quand l'hôte change le livre en cours d'un club, tous les chapitres existants **et leurs
discussions** sont supprimés définitivement (aucun historique conservé) ; le channel global, lui,
n'est jamais supprimé. Une confirmation explicite est demandée côté interface avant cette action,
et un test fonctionnel dédié vérifie que les anciens chapitres/discussions disparaissent bien tandis
que le channel global persiste.

## Tests et qualité de code

```bash
# Tests (unitaires + fonctionnels)
php bin/phpunit

# Couverture de tests (nécessite Xdebug ou PCOV)
php bin/phpunit --coverage-text

# Analyse statique (PHPStan niveau 6)
vendor/bin/phpstan analyse

# Cohérence du mapping Doctrine avec le schéma de base
php bin/console doctrine:schema:validate
```

Les tests fonctionnels nécessitent une base PostgreSQL de test accessible via la `DATABASE_URL` de
`.env.test` (suffixée automatiquement `_test` par Symfony). La CI GitHub Actions
(`.github/workflows/ci.yml`) exécute lint, PHPStan et PHPUnit à chaque push.

## Architecture

Clean Architecture en quatre couches, avec des dépendances qui vont toujours de l'extérieur vers
l'intérieur :

```
src/
├── Domain/           # Entités métier, Value Objects, services métier purs, interfaces — zéro dépendance framework
├── Application/       # Use Cases, interfaces de services externes (Ports)
├── Infrastructure/    # Implémentations concrètes (Doctrine, Google Books, Security)
└── UI/                # Controllers, templates Twig, contrôleurs Stimulus
```

- `Domain/` n'importe jamais Symfony, Doctrine ou tout autre framework.
- `Application/` ne connaît que des interfaces, jamais des implémentations concrètes.
- `UI/` délègue toute la logique métier aux Use Cases : aucun Controller ne contient de règle
  métier.
- Le mapping Doctrine des entités vit entièrement hors de `src/`, dans `config/doctrine/*.orm.xml`.
