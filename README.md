# Projet_Universitaire_Collecte_web

## Carte des températures moyennes par commune

Un script PHP (`map.php`) génère une carte interactive Leaflet affichant la température moyenne par commune à partir du fichier CSV `API/data.csv`.

### Format attendu du CSV
En-têtes acceptées (minuscules ou variantes) :
- Commune : `commune`, `ville`, `municipalite`, `city`
- Température : `temperature`, `temp`, `t`
- Latitude : `latitude`, `lat`
- Longitude : `longitude`, `lon`, `lng`

Chaque ligne représente une mesure brute. Le script calcule la moyenne par commune.

### Lancer le serveur local
Dans le répertoire racine du projet :

```
php -S localhost:8000
```

Puis ouvrir dans le navigateur :

```
http://localhost:8000/map.php
```

### Couleurs (classes de température)
| Intervalle (°C) | Couleur |
|-----------------|---------|
| < 5             | #2c7bb6 |
| 5 - 10          | #abd9e9 |
| 10 - 15         | #ffffbf |
| 15 - 20         | #fdae61 |
| 20 - 25         | #f46d43 |
| ≥ 25            | #d73027 |
| Indéfini        | #999999 |

### Personnalisation
Adapter les seuils/couleurs dans `map.php` (fonction `temperatureColor`).

### Remarque Folium
Folium est une bibliothèque Python. Ici l’équivalent interactif est réalisé avec Leaflet côté navigateur via un script PHP qui prépare les données.
