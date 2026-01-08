# Projet Météo - API et Web Scraping

Ce projet contient deux applications indépendantes.

---

## 📁 Structure du Projet

```
Final/
├── API/                   # Application web de visualisation météo
│   ├── index.php          # Point d'entrée principal
│   ├── php/               # Scripts PHP backend
│   ├── scripts/           # JavaScript frontend
│   ├── styles/            # Feuilles de style CSS
│   └── programme/         # Scripts Python et données CSV
│
└── WEBSCRAPING/           # Application web sur les formations en France
    └── WEBSCRAPING.py
```

---

## 🌐 Dossier API - Application Web

### Description
Application web complète avec 5 indicateurs météorologiques interactifs :
- 🗺️ Carte Leaflet avec marqueurs de température
- 📉 Graphique comparatif pluviométrie/humidité
- 💨 Vitesse du vent par région
- ☀️ Température moyenne par mois
- ☁️ Hauteur de la base des nuages

### Prérequis
- **Serveur HTTP** (Apache, Nginx, EasyPHP, XAMPP, WAMP, etc.)
- **PHP 7.0+**
- **Python 3.x** (pour la collecte de données via API)

### Installation

1. **Placer le dossier dans un serveur HTTP**
   ```
   Copiez le dossier API dans le répertoire web de votre serveur
   Exemple avec EasyPHP : eds-www/API/
   Exemple avec XAMPP : htdocs/API/
   ```

2. **Vérifier les permissions**
   - Le dossier `programme/` doit être accessible en lecture/écriture
   - Les fichiers CSV doivent pouvoir être créés/modifiés

### Utilisation

1. **Démarrer le serveur HTTP**
   - Lancez votre serveur local (EasyPHP, XAMPP, etc.)

2. **Accéder à l'application**
   ```
   http://localhost/API/index.php
   ou
   http://127.0.0.1/API/index.php
   ```

3. **Fonctionnalités disponibles**
   - **Visualisation** : Les 5 indicateurs s'affichent automatiquement
   - **Basculement de dataset** : Bouton pour alterner entre France entière et Poitiers
   - **Collecte de données** : Sélectionner une période pour récupérer de nouvelles données météo

### Fichiers principaux
- `index.php` : Interface principale avec les 5 indicateurs
- `php/lireCsv.php` : Lecture des fichiers CSV existants
- `php/collecterApi.php` : Exécution du script Python pour collecter les données
- `php/fonctions.php` : Fonctions utilitaires réutilisables
- `scripts/script.js` : Toute la logique frontend (Leaflet, Chart.js)
- `programme/API.py` : Script Python de collecte via API OpenDataSoft

---

## 🕷️ Dossier WEBSCRAPING - Script Autonome

### Description
Script Python autonome qui effectue du web scraping et génère automatiquement un site web statique avec les données de différentes formations autour de la thématique de l'informatique.

### Prérequis
- **Python 3.x**
- **Modules Python** (à installer selon les imports du script)

### Installation

1. **Accéder au dossier WEBSCRAPING**
   ```bash
   cd "b:\Programmes\EasyPHP-Devserver-17\eds-www\API git\Final\WEBSCRAPING"
   ```

2. **Installer les dépendances Python** (si nécessaire)
   ```bash
   pip install requests beautifulsoup4 pandas
   # Ajustez selon les modules requis par le script
   ```

### Utilisation

1. **Exécuter le script Python**
   ```bash
   python "WEBSCRAPING.py"
   ```

2. **Le script va automatiquement :**
   - Effectuer le scraping des données
   - Traiter les informations collectées
   - Générer un site web HTML
   - Ouvrir le site dans votre navigateur par défaut

3. **Résultat**
   - Un site web statique sera créé et ouvert automatiquement
   - Les données scrappées seront visualisées dans le navigateur

### Notes
- Ce script fonctionne **indépendamment** du dossier API
- Aucun serveur HTTP requis (site statique)
- Le site généré s'ouvre automatiquement à la fin de l'exécution

---

## 🔧 Dépannage

### API - Problèmes courants

**Erreur "Page introuvable"**
- Vérifiez que le serveur HTTP est démarré
- Vérifiez le chemin d'accès : `http://localhost/API/index.php`

**Les données ne s'affichent pas**
- Vérifiez que les fichiers CSV existent dans `programme/`
- Vérifiez les permissions en lecture sur les fichiers CSV

**Erreur lors de la collecte de données**
- Vérifiez que Python est installé et accessible depuis PHP
- Testez manuellement : `python programme/API.py "2024-01-01" "2024-01-31"`

### WEBSCRAPING - Problèmes courants

**Erreur de module Python manquant**
```bash
pip install [nom_du_module]
```

**Le site ne s'ouvre pas automatiquement**
- Recherchez un fichier HTML généré dans le dossier
- Ouvrez-le manuellement avec un navigateur

---

## 📝 Remarques

- Les deux applications sont **totalement indépendantes**
- API nécessite un serveur, WEBSCRAPING est autonome
- Les données météo de l'API proviennent d'OpenDataSoft (SYNOP)
- Le code est commenté à des fins pédagogiques

---

## 👨‍💻 Auteur

Projet développé dans un cadre universitaire pour l'apprentissage du développement web et de la collecte de données.
