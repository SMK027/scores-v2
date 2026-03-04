Je souhaite créer une application PHP pour enregistrer mes parties de jeux.
Elle utilisera:
- PHP version 8.0 ou supérieure
- MySQL pour la base de données
- HTML/CSS pour l'interface utilisateur
- JavaScript pour les interractions dynamiques

De plus, pour faciliter son déploiement sur serveur, on utilisera un Docker avec:
- Un conteneur pour l'application PHP
- Un conteneur pour la base de données MySQL
- Un conteneur pour un serveur web comme Apache ou Nginx

Afin que le code soit maintenable, l'application devra utiliser un modèle vue-contrôleur et la programation orientée objet. Les liaisons à la base de données se feront à partir de PDO. On mettra en place un sigleton pour qu'une seule instance de la base de données soit utilisée.

L'application devra impérativement être responsive afin qu'elle puisse être utilisée sur mobile (téléphones et tablettes), en plus des ordinateurs portables et fixes.

Voici les fonctionalités attendues au minimum:
- gestion des types de jeux (ex: Tarot, Belotte, Yams etc.)
- gestion des joueurs
- gestion des parties
- affichage des statistiques de jeu (ex: nombre de parties jouées, nombre de victoires par joueur, etc.)
- possibilité d'ajouter des commentaires ou des notes pour chaque partie
- gestion des manches de chaque parties (création, mise à jour des scores, suppression et mise en pause)
- possibilité de rechercher des parties par joueur, type de jeu ou date

J'ai quelques spécifications supplémentaires pour la réalisation de l'application:
- L'application doit utiliser des requêtes préparées pour se protéger des injections SQL
- L'application devra être prémunie pour résister aux attaques CSRF
- L'application devra être sécurisée contre les attaques XSS
- L'application devra être testée pour assurer sa fiabilité et sa robustesse
- L'application devra être documentée pour faciliter sa maintenance et son évolution future

Voici quelques détails techniques à intégrer:
- Chaque jeu possède une condition de victoire (score le plus élevé, score le plus faible, victoire/défaite, classement)
- Les joueurs peuvent participer à plusieurs parties et chaque partie peut avoir plusieurs joueurs
- L'application fonctionnera par espace. Chaque espace possèdera ses joueurs, ses parties, ses types de jeux, ses statistiques et ses rôles
- Les rôles permettront de différencier les utilisateurs (ex: administrateur, joueur, invité) et de gérer les permissions d'accès aux différentes fonctionnalités de l'application
- Le site permettra une inscription ouverte sans nécessiter l'intervention systématique d'un administrateur pour se connecter
- Les utilisateurs pourront personnaliser leur profil avec une photo, une biographie et d'autres informations pertinentes
- Malgré le système d'espaces pour la gestion des parties, des personnes seront chargées de vérifier que l'ensemble du site fonctionne correctement et assurer une gestion des demandes diverses des utilisateurs. Il sera nécessaire de créer un système de rôles globaux pour les membres de l'équipe.

Met en place un dépôt Git local et effectue des commits à chaque étape pour permettre une historisation des versions du code, puis crée l'ensemble des fonctionnalités demandées.
