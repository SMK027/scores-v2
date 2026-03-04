# Correction des permissions pour l'upload d'avatars

## Problème
Le dossier `public/uploads/` n'a pas les bonnes permissions pour que le serveur web Docker (www-data) puisse y écrire des fichiers.

## Solution 1 : Utiliser le script automatique (recommandé)
```bash
./fix-uploads.sh
```

Ce script va :
1. Arrêter les conteneurs Docker
2. Reconstruire l'image avec les bonnes permissions
3. Redémarrer les conteneurs
4. Vérifier que les permissions sont correctes

## Solution 2 : Correction manuelle
Si vous ne voulez pas reconstruire l'image, vous pouvez corriger les permissions manuellement :

```bash
# Donner les permissions au dossier local
chmod -R 777 public/uploads/

# OU avec sudo si nécessaire
sudo chmod -R 777 public/uploads/
sudo chown -R $(whoami):$(whoami) public/uploads/
```

Puis redémarrer les conteneurs :
```bash
docker-compose restart
```

## Vérification
Pour vérifier que les permissions sont correctes :
```bash
ls -la public/uploads/
```

Le dossier devrait avoir les permissions `drwxrwxrwx` (777).
