# Scores — Application de gestion de parties de jeux

Application web PHP complète pour enregistrer et suivre vos parties de jeux de société, jeux de cartes et autres jeux entre amis.

## Fonctionnalités

- **Espaces collaboratifs** : créez des espaces de jeu, invitez des membres avec des rôles (admin, gestionnaire, membre, invité)
- **Types de jeu** : configurez vos jeux avec différentes conditions de victoire (score le plus élevé/bas, victoire/défaite, classement)
- **Joueurs** : gérez les joueurs de votre groupe, liez-les optionnellement à des comptes utilisateurs
- **Parties** : créez et suivez des parties avec statuts (en attente, en cours, en pause, terminée)
- **Manches & Scores** : enregistrez les scores manche par manche avec calcul automatique des totaux et du classement
- **Commentaires** : commentez les parties en temps réel
- **Statistiques** : tableaux de bord avec top joueurs, taux de victoire, activité mensuelle, stats par type de jeu
- **Recherche** : recherchez joueurs, types de jeu, parties et commentaires dans un espace
- **Administration** : panneau d'admin global pour la gestion des utilisateurs et espaces
- **Profils** : page de profil avec avatar, bio et changement de mot de passe
- **Responsive** : interface adaptée mobile, tablette et desktop

## Prérequis

- Docker & Docker Compose
- ou PHP 8.0+, MySQL 8.0+, Apache/Nginx, Composer

## Installation avec Docker

```bash
# Cloner le projet
git clone <url-du-repo> scores
cd scores

# Copier la configuration
cp .env.example .env

# Lancer les conteneurs
docker-compose up -d

# Installer les dépendances PHP
docker-compose exec app composer install

# Importer la base de données
docker-compose exec db mysql -u root -proot_password scores_db < database/migrations/001_initial.sql
```

L'application sera accessible sur :
- **Application** : http://localhost:8089
- **phpMyAdmin** : http://localhost:8090 (accessible uniquement en local)

### Configuration selon l'environnement

Le fichier `docker-compose.yml` actuel est configuré pour le **déploiement VPS** avec Traefik. Après un `git pull`, il faut ajuster les éléments suivants selon l'environnement cible :

#### Éléments à vérifier / modifier

| Élément | VPS (production) | Local (développement) |
|---------|-------------------|-----------------------|
| **Port de l'app** (`services.app.ports`) | `8089:80` | `8080:80` (ou autre port libre) |
| **Labels Traefik** (`services.app.labels`) | Présents — routage vers `scores.leofranz.fr` avec SSL | **À supprimer** (pas de Traefik en local) |
| **Réseau proxy** (`services.app.networks`) | `proxy` + `scores_network` | `scores_network` uniquement |
| **Réseau externe** (`networks.proxy`) | `external: true` | **À supprimer** |
| **Timezone MySQL** (`services.db.command`) | `--default-time-zone='+02:00'` | Ajuster selon le fuseau local (ex : `'+01:00'` pour CET) |
| **Port phpMyAdmin** (`services.phpmyadmin.ports`) | `127.0.0.1:8090:80` (local uniquement) | `8081:80` (accessible depuis le réseau) |

#### Fichier `.env`

| Variable | VPS | Local |
|----------|-----|-------|
| `APP_URL` | `https://scores.leofranz.fr` | `http://localhost:8080` |
| `APP_DEBUG` | `false` | `true` |
| `APP_KEY` | Chaîne aléatoire sécurisée | Peut rester la valeur par défaut |
| `SMTP_*` | Identifiants du serveur mail de production | Identifiants de test ou désactivé |

#### Exemple : passer en mode local après un pull

```yaml
# docker-compose.yml — supprimer les labels Traefik du service app :
    # labels:
    #   - "traefik.enable=true"
    #   - ...

# Modifier le port de l'app :
    ports:
      - "8080:80"

# Retirer le réseau proxy du service app :
    networks:
      - scores_network

# Supprimer le réseau proxy en fin de fichier :
# networks:
#   proxy:
#     external: true
```

> ⚠️ Le réseau externe `proxy` doit **exister au préalable** sur le VPS (`docker network create proxy`) pour que Traefik puisse router le trafic.

## Installation manuelle

```bash
# Installer les dépendances
composer install

# Configurer la base de données
# Éditer les variables d'environnement ou créer un fichier .env
export DB_HOST=localhost
export DB_PORT=3306
export DB_DATABASE=scores_db
export DB_USERNAME=votre_user
export DB_PASSWORD=votre_password

# Importer le schéma
mysql -u root -p scores_db < database/migrations/001_initial.sql

# Lancer le serveur de développement PHP
php -S localhost:8080 -t public/
```

## Compte administrateur par défaut

| Champ | Valeur |
|-------|--------|
| Email | `admin@scores.local` |
| Mot de passe | `password` |

> ⚠️ Changez ce mot de passe immédiatement après la première connexion.

## Architecture

```
scores/
├── app/
│   ├── Config/          # Configuration (Database singleton PDO)
│   ├── Controllers/     # Contrôleurs MVC
│   ├── Core/            # Framework (Router, Controller, Model, Session, CSRF, Middleware)
│   ├── Helpers/         # Fonctions utilitaires
│   ├── Models/          # Modèles de données
│   └── Views/           # Vues PHP
│       ├── admin/       # Panneau d'administration
│       ├── auth/        # Connexion, inscription
│       ├── errors/      # Pages d'erreur
│       ├── game_types/  # Types de jeu
│       ├── games/       # Parties
│       ├── home/        # Page d'accueil
│       ├── layouts/     # Layout principal
│       ├── partials/    # Composants réutilisables
│       ├── players/     # Joueurs
│       ├── profile/     # Profil utilisateur
│       ├── search/      # Recherche
│       ├── spaces/      # Espaces
│       └── stats/       # Statistiques
├── database/
│   └── migrations/      # Scripts SQL
├── public/
│   ├── css/             # Feuilles de style
│   ├── js/              # JavaScript
│   ├── uploads/         # Fichiers uploadés (avatars)
│   ├── index.php        # Front controller + routes
│   └── .htaccess        # Réécriture Apache
├── tests/
│   └── Unit/            # Tests unitaires PHPUnit
├── docker-compose.yml
├── Dockerfile
├── composer.json
└── phpunit.xml
```

## Stack technique

| Composant | Technologie |
|-----------|-------------|
| Backend | PHP 8.2, architecture MVC custom |
| Base de données | MySQL 8.0 avec PDO (requêtes préparées) |
| Frontend | HTML5, CSS3 (mobile-first, variables CSS, Flexbox/Grid), JavaScript vanilla |
| Serveur | Apache avec mod_rewrite |
| Conteneurisation | Docker / Docker Compose |
| Tests | PHPUnit 9.6 |
| Autoloading | Composer PSR-4 |

## Sécurité

- **Injection SQL** : toutes les requêtes utilisent des requêtes préparées PDO
- **XSS** : échappement systématique via la fonction `e()` (htmlspecialchars)
- **CSRF** : token unique par session, validé sur chaque formulaire POST
- **Mots de passe** : hashés avec `password_hash()` (bcrypt)
- **Sessions** : régénération d'ID après connexion

## Rôles

### Rôles globaux
| Rôle | Permissions |
|------|-------------|
| superadmin | Tous les droits, gestion des rôles |
| admin | Accès au panneau d'administration |
| moderator | Accès au panneau d'administration |
| user | Utilisateur standard |

### Rôles d'espace
| Rôle | Permissions |
|------|-------------|
| admin | Tous les droits sur l'espace |
| manager (gestionnaire) | Gestion des membres, parties, joueurs |
| member (membre) | Créer et participer aux parties |
| guest (invité) | Lecture seule |

## Tests

```bash
# Lancer tous les tests
./vendor/bin/phpunit

# Lancer uniquement les tests unitaires
./vendor/bin/phpunit --testsuite Unit

# Avec couverture de code
./vendor/bin/phpunit --coverage-html coverage/
```

## Licence

Ce projet est un projet personnel à usage privé.
