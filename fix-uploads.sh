#!/bin/bash
# Script pour corriger les permissions du dossier uploads

echo "Arrêt des conteneurs..."
docker-compose down

echo "Reconstruction de l'image avec les nouvelles permissions..."
docker-compose build --no-cache app

echo "Redémarrage des conteneurs..."
docker-compose up -d

echo "Vérification des permissions..."
docker-compose exec app ls -la /var/www/html/public/uploads/

echo "Terminé! Les permissions du dossier uploads ont été corrigées."
