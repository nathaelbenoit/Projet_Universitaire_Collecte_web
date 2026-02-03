/**
 * GESTION DU BASCULEMENT ENTRE DATASETS
 * Ce code permet de basculer entre deux jeux de données: dataFinal.csv et dataPoitiers.csv
 */

// Récupère le dataset actuellement chargé (variable injectée par PHP)
const currentDataset = CURRENT_DATASET;

// Sélectionne le bouton dans le DOM avec getElementById
// Le DOM (Document Object Model) est la représentation en mémoire de la page HTML
const boutonDataset = document.getElementById('boutonDataset');

// Si on est sur le dataset Poitiers, on change le texte et le style du bouton
// pour indiquer qu'un clic nous ramènera vers la France entière
if (currentDataset === 'dataPoitiers.csv') {
    boutonDataset.textContent = '🗺️ Voir France entière';
    // linear-gradient crée un dégradé CSS de couleur
    boutonDataset.style.background = 'linear-gradient(135deg, #4169E1 0%, #1E90FF 100%)';
}

// addEventListener attache une fonction qui s'exécute au clic
boutonDataset.addEventListener('click', () => {
    // Bascule entre les deux datasets
    const newDataset = currentDataset === 'dataFinal.csv' ? 'dataPoitiers.csv' : 'dataFinal.csv';
    
    // window.location.href modifie l'URL et recharge la page avec le nouveau paramètre
    window.location.href = `?dataset=${encodeURIComponent(newDataset)}`;
});

/**
 *  CHARGEMENT DE NOUVELLES DONNÉES VIA L'API
 * Cette fonction utilise fetch() pour communiquer avec le serveur PHP
 * et récupérer de nouvelles données météo selon une période sélectionnée.
 */

document.getElementById('boutonCharger').addEventListener('click', function() {
    // .value récupère la valeur saisie dans un input HTML
    const datéDebut = document.getElementById('datéDebut').value;
    const datéFin = document.getElementById('datéFin').value;
    
    // Validation côté client: vérifie que les dates sont renseignées
    if (!datéDebut || !datéFin) return afficherMessage('Veuillez sélectionner les deux dates', 'erreur');
    
    // Vérifie que la date de début est bien avant la date de fin
    if (new Date(datéDebut) > new Date(datéFin)) return afficherMessage('La date de début doit être antérieure à la date de fin', 'erreur');
    
    // Affiche l'indicateur de chargement pendant le traitement
    afficherChargement(true);
    
    // Envoie une requête HTTP au serveur
    // Le code ne bloque pas, il continue pendant que le serveur traite
    fetch('./php/collecterApi.php', {
        method: 'POST', // Méthode HTTP POST (envoie des données)
        // Headers HTTP: métadonnées de la requête
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        // body: données envoyées au serveur au format URL-encoded (clé=valeur&clé=valeur)
        body: `date_debut=${encodeURIComponent(datéDebut)}&date_fin=${encodeURIComponent(datéFin)}`
    })

    // response.text() lit le corps de la réponse comme texte brut
    .then(response => response.text())
    .then(text => {
        afficherChargement(false);
        try {
            // JSON.parse() convertit une chaîne JSON en objet JavaScript
            const data = JSON.parse(text);
            if (data.succes) {
                afficherMessage('Données chargées avec succès ! La page va se rafraîchir...', 'succes');
                // setTimeout() exécute une fonction après un délai (en millisecondes)
                // location.reload() recharge la page entière
                setTimeout(() => location.reload(), 1500);
            } else {
                afficherMessage('Erreur : ' + data.message, 'erreur');
            }
        } catch (e) {
            // Si le JSON est invalide, on actualise quand même la page
            afficherMessage('Traitement des données en cours, actualisation de la page...', 'succes');
            setTimeout(() => location.reload(), 2000);
        }
    })
    // .catch() capture toutes les erreurs réseau (serveur inaccessible, timeout, etc.)
    .catch(() => {
        afficherChargement(false);
        afficherMessage('Traitement des données en cours, actualisation de la page...', 'succes');
        setTimeout(() => location.reload(), 2000);
    });
});

/**
 * Afficher/Masquer l'indicateur de chargement
 */
function afficherChargement(afficher) {
    // .style.display modifie directement le CSS de l'élément
    document.getElementById('indicateurChargement').style.display = afficher ? 'block' : 'none';
}

/**
 * Afficher un message à l'utilisateur
 * Modifie le contenu et le style d'une div pour afficher succès ou erreur
 */
function afficherMessage(texte, type) {
    const messageDiv = document.getElementById('messageRésultat');
    // .textContent définit le texte brut (sans HTML) d'un élément
    messageDiv.textContent = texte;
    messageDiv.style.display = 'block';
    // Change les couleurs selon le type de message (vert=succès, rouge=erreur)
    messageDiv.style.backgroundColor = type === 'succes' ? '#d4edda' : '#f8d7da';
    messageDiv.style.color = type === 'succes' ? '#155724' : '#721c24';
}

/**
 * RÉCUPÉRATION DES DONNÉES MÉTÉOROLOGIQUES
 * ALL_DATA est une variable JavaScript injectée par PHP
 * Elle contient toutes les observations météo sous forme de tableau d'objets
 * Chaque objet représente une observation avec: température, humidité, coordonnées, etc.
 */
const allData = ALL_DATA;

/**
 * INDICATEUR 1: CARTE INTERACTIVE AVEC LEAFLET
 * 
 * Objectif: Afficher une carte de France avec des marqueurs colorés représentant
 * la température moyenne de chaque station météorologique.
 */

// Objet qui stockera toutes les données agrégées par commune
const temperatureByCommune = {};

// Parcourt chaque élément du tableau
allData.forEach(row => {
    // Si row['Libellé géographique'] est undefined/null/vide, utilise 'Inconnu'
    const commune = row['Libellé géographique'] || 'Inconnu';
    
    // Convertit une chaîne en nombre décimal
    const lat = parseFloat(row['Latitude']);
    const lon = parseFloat(row['Longitude']);
    const temp = parseFloat(row['Température (°C)']);
    
    // Extraction du mois depuis la date ISO
    const dateStr = row['Date et heure d\'observation'];

    // Extrait les caractères aux positions 5 et 6 (le mois)
    const mois = dateStr ? dateStr.substring(5, 7) : '00';
    
    // Vérifie donc que c'est bien un nombre valide
    if (!isNaN(lat) && !isNaN(lon) && !isNaN(temp)) {
        // Si cette commune n'existe pas encore dans notre objet, on l'initialise
        if (!temperatureByCommune[commune]) {
            temperatureByCommune[commune] = {
                lat, lon, 
                temps: [],         
                tempsParMois: {},  
                region: row['Nom région']
            };
        }
        
        // Ajoute un élément à la fin d'un tableau
        temperatureByCommune[commune].temps.push(temp);
        
        // Stockage organisé par mois pour permettre le filtrage ultérieur
        if (!temperatureByCommune[commune].tempsParMois[mois]) {
            temperatureByCommune[commune].tempsParMois[mois] = [];
        }
        temperatureByCommune[commune].tempsParMois[mois].push(temp);
    }
});

/**
 * CALCUL DES MOYENNES
 */
Object.keys(temperatureByCommune).forEach(commune => {
    const data = temperatureByCommune[commune];
    
    // .reduce() est une méthode d'agrégation qui "réduit" un tableau à une seule valeur en accumulant
    const somme = data.temps.reduce((a, b) => a + b, 0);
    data.avg = somme / data.temps.length; // Moyenne = somme / nombre d'éléments
});

/**
 * Associe une couleur à une température: bleu (froid) → vert → orange → rouge (chaud)
 */
function getTemperatureColor(temp) {
    if (temp < 0) return '#0033FF';
    if (temp < 5) return '#00CCFF';
    if (temp < 10) return '#00FF00';
    if (temp < 15) return '#90EE90';
    if (temp < 20) return '#FF6600';
    if (temp < 30) return '#FF0000';
    return '#000000';
}

/**
 * 🗺️ INITIALISATION DE LA CARTE LEAFLET
 */

// Crée la carte centrée sur la France (zoom 5 = vue pays entier)
const map = L.map('map').setView([46.5, 2.0], 5);

// Charge les tuiles OpenStreetMap
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors',
    maxZoom: 19
}).addTo(map);

// LayerGroup pour gérer tous les marqueurs en une seule opération
let markersLayer = L.layerGroup().addTo(map);

/**
 * Rafraîchit la carte en fonction du mois sélectionné
 */
function updateMap(mois) {
    markersLayer.clearLayers();
    
    Object.keys(temperatureByCommune).forEach(commune => {
        const data = temperatureByCommune[commune];
        let avg;
        
        if (mois === '') {
            avg = data.avg;
        } else {
            if (data.tempsParMois[mois] && data.tempsParMois[mois].length > 0) {
                const tempsDuMois = data.tempsParMois[mois];
                avg = tempsDuMois.reduce((a, b) => a + b, 0) / tempsDuMois.length;
            } else {
                return;
            }
        }
        
        // Crée un marqueur circulaire coloré selon la température
        L.circleMarker([data.lat, data.lon], {
            radius: 8,
            fillColor: getTemperatureColor(avg),
            color: '#333',
            weight: 1,
            opacity: 0.8,
            fillOpacity: 0.8
        })
        .bindPopup(
            `<b>${commune}</b><br>` +
            `Température: ${avg.toFixed(2)}°C<br>` +
            `Région: ${data.region}<br>` +
            `Observations: ${mois === '' ? data.temps.length : (data.tempsParMois[mois] ? data.tempsParMois[mois].length : 0)}`
        )
        .addTo(markersLayer);
    });
}

updateMap('');

/**
 * GÉNÉRATION DYNAMIQUE DU SÉLECTEUR DE MOIS
 */

// Set stocke les mois uniques (élimine automatiquement les doublons)
const moisDisponibles = new Set();

const nomsMois = {
    '01': 'Janvier', '02': 'Février', '03': 'Mars', '04': 'Avril',
    '05': 'Mai', '06': 'Juin', '07': 'Juillet', '08': 'Août',
    '09': 'Septembre', '10': 'Octobre', '11': 'Novembre', '12': 'Décembre'
};

// Collecte des mois disponibles dans les données
Object.keys(temperatureByCommune).forEach(commune => {
    const moisCommune = temperatureByCommune[commune].tempsParMois;
    Object.keys(moisCommune).forEach(mois => {
        moisDisponibles.add(mois);
    });
});

const moisTriés = Array.from(moisDisponibles).sort();
const selectMois = document.getElementById('moisFiltre');

// Crée dynamiquement les options du select
moisTriés.forEach(mois => {
    const option = document.createElement('option');
    option.value = mois;
    option.textContent = nomsMois[mois];
    selectMois.appendChild(option);
});

// Rafraîchit la carte au changement de sélection
document.getElementById('moisFiltre').addEventListener('change', function() {
    updateMap(this.value);
});

/**
 * INDICATEUR 2: GRAPHIQUE COMPARATIF PLUVIOMÉTRIE/HUMIDITÉ
 * Technique dual axis: deux échelles Y pour comparer deux unités différentes
 */

// Agrégation par région-année (ex: "Nouvelle-Aquitaine-2024")
const dataPluieHumiditéParRegionAnnée = {};

allData.forEach(row => {
    const region = row['Nom région'] || 'Inconnu';
    const year = row['Date et heure d\'observation'].substring(0, 4);
    const pluie = parseFloat(row['Précipitations 24h (mm)']) || 0;
    const humidite = parseFloat(row['Humidité relative (%)']) || 0;
    const key = `${region}-${year}`;
    
    if (!dataPluieHumiditéParRegionAnnée[key]) {
        dataPluieHumiditéParRegionAnnée[key] = {
            region, year,
            pluies: [],
            humidites: []
        };
    }
    
    if (pluie > 0) dataPluieHumiditéParRegionAnnée[key].pluies.push(pluie);
    if (humidite > 0) dataPluieHumiditéParRegionAnnée[key].humidites.push(humidite);
});

const labels = [];
const pluieMoyenne = [];
const humiditeAverage = [];

// Limite à 15 éléments pour éviter surcharge visuelle
Object.keys(dataPluieHumiditéParRegionAnnée).slice(0, 15).forEach(key => {
    const data = dataPluieHumiditéParRegionAnnée[key];
    labels.push(`${data.region.substring(0, 10)}`);
    pluieMoyenne.push(
        data.pluies.length > 0 
            ? data.pluies.reduce((a, b) => a + b, 0) / data.pluies.length 
            : 0
    );
    humiditeAverage.push(
        data.humidites.length > 0 
            ? data.humidites.reduce((a, b) => a + b, 0) / data.humidites.length 
            : 0
    );
});

const ctx = document.getElementById('comparativeChart').getContext('2d');

new Chart(ctx, {
    type: 'bar',
    data: {
        labels,
        datasets: [{
            label: 'Pluviométrie 24h (mm)',
            data: pluieMoyenne,
            backgroundColor: 'rgba(135, 206, 250, 0.8)',
            borderColor: 'rgba(135, 206, 250)',
            borderWidth: 1,
            yAxisID: 'y'
        }, {
            label: 'Humidité (%)',
            data: humiditeAverage,
            backgroundColor: 'rgba(176, 224, 230, 0.8)',
            borderColor: 'rgba(176, 224, 230)',
            borderWidth: 1,
            yAxisID: 'y1'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: {
            mode: 'index',
            intersect: false
        },
        plugins: {
            legend: {position: 'top'}
        },
        scales: {
            y: {
                type: 'linear',
                display: true,
                position: 'left',
                title: {display: true, text: 'Pluviométrie (mm)'}
            },
            y1: {
                type: 'linear',
                display: true,
                position: 'right',
                title: {display: true, text: 'Humidité (%)'},
                grid: {drawOnChartArea: false}
            }
        }
    }
});

/**
 * INDICATEUR 3: VITESSE DU VENT PAR RÉGION
 * Graphique horizontal (indexAxis: 'y') pour meilleure lisibilité des noms de régions
 */

const vitesseVentParRégion = {};

allData.forEach(row => {
    const région = row['Nom région'] || 'Inconnu';
    const vitesseVent = parseFloat(row['Vitesse du vent moyen 10mn']) || 0;
    
    if (!isNaN(vitesseVent) && vitesseVent >= 0) {
        if (!vitesseVentParRégion[région]) vitesseVentParRégion[région] = [];
        vitesseVentParRégion[région].push(vitesseVent);
    }
});

const étiquettesVitesseRégions = [];
const vitesseMoyenneParRégion = [];

Object.keys(vitesseVentParRégion).sort().forEach(région => {
    const vitesses = vitesseVentParRégion[région];
    const moyenne = vitesses.length > 0 ? vitesses.reduce((a, b) => a + b, 0) / vitesses.length : 0;
    étiquettesVitesseRégions.push(région.substring(0, 20));
    vitesseMoyenneParRégion.push(moyenne.toFixed(2) * 3.6); // Conversion m/s → km/h
});

const ctxAnnée = document.getElementById('yearChart').getContext('2d');

new Chart(ctxAnnée, {
    type: 'bar',
    data: {
        labels: étiquettesVitesseRégions,
        datasets: [{
            label: 'Vitesse du vent moyen (km/h)',
            data: vitesseMoyenneParRégion,
            backgroundColor: 'rgba(135, 206, 250, 0.8)',
            borderColor: 'rgba(135, 206, 250)',
            borderWidth: 1
        }]
    },
    options: {
        indexAxis: 'y', // Graphique horizontal
        responsive: true,
        maintainAspectRatio: false,
        plugins: {legend: {display: true, position: 'top'}},
        scales: {
            x: {
                title: {display: true, text: 'Vitesse du vent (km/h)'}
            }
        }
    }
});

/**
 * INDICATEUR 4: TEMPÉRATURE MOYENNE PAR MOIS
 * Visualise la saisonnalité des températures
 */

const tempParMois = {};

allData.forEach(row => {
    const chaîneDate = row['Date et heure d\'observation'];
    const mois = chaîneDate.substring(5, 7);
    const nomsMoisTemp = ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Jun', 'Jul', 'Aoû', 'Sep', 'Oct', 'Nov', 'Déc'];
    const étiquetteMois = nomsMoisTemp[parseInt(mois) - 1] || `Mois ${mois}`;
    const temp = parseFloat(row['Température (°C)']) || 0;
    
    if (!isNaN(temp) && temp !== 0) {
        if (!tempParMois[mois]) tempParMois[mois] = {
            étiquette: étiquetteMois,
            temperatures: []
        };
        tempParMois[mois].temperatures.push(temp);
    }
});

// Tri numérique des mois pour affichage chronologique
const étiquettesMois = Object.keys(tempParMois)
    .sort((a, b) => parseInt(a) - parseInt(b))
    .map(m => tempParMois[m].étiquette);

const températuresMoyennesMois = Object.keys(tempParMois)
    .sort((a, b) => parseInt(a) - parseInt(b))
    .map(m => {
        const temperatures = tempParMois[m].temperatures;
        return temperatures.length > 0 
            ? temperatures.reduce((a, b) => a + b, 0) / temperatures.length 
            : 0;
    });

const ctxMois = document.getElementById('monthChart').getContext('2d');

new Chart(ctxMois, {
    type: 'bar',
    data: {
        labels: étiquettesMois,
        datasets: [{
            label: 'Température moyenne (°C)',
            data: températuresMoyennesMois,
            backgroundColor: 'rgba(135, 206, 250, 0.8)',
            borderColor: 'rgba(135, 206, 250)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {legend: {display: true, position: 'top'}},
        scales: {
            y: {
                title: {display: true, text: 'Température (°C)'}
            }
        }
    }
});

/**
 * INDICATEUR 5: HAUTEUR DE LA BASE DES NUAGES PAR RÉGION
 */

const hauteurNuagesParRégion = {};

allData.forEach(row => {
    const région = row['Nom région'] || 'Inconnu';
    const hauteur = parseFloat(row['Hauteur base nuages (m)']) || 0;
    
    if (!isNaN(hauteur) && hauteur >= 0) {
        if (!hauteurNuagesParRégion[région]) hauteurNuagesParRégion[région] = [];
        hauteurNuagesParRégion[région].push(hauteur);
    }
});

const étiquettesRégions = [];
const hauteurMoyenneParRégion = [];

Object.keys(hauteurNuagesParRégion).sort().forEach(région => {
    const hauteurs = hauteurNuagesParRégion[région];
    const moyenne = hauteurs.length > 0 
        ? hauteurs.reduce((a, b) => a + b, 0) / hauteurs.length 
        : 0;
    étiquettesRégions.push(région.substring(0, 20));
    hauteurMoyenneParRégion.push(moyenne.toFixed(0));
});

const ctxRégion = document.getElementById('regionChart').getContext('2d');

new Chart(ctxRégion, {
    type: 'bar',
    data: {
        labels: étiquettesRégions,
        datasets: [{
            label: 'Hauteur base nuages (m)',
            data: hauteurMoyenneParRégion,
            backgroundColor: 'rgba(176, 224, 230, 0.8)',
            borderColor: 'rgba(176, 224, 230)',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {legend: {display: true, position: 'top'}},
        scales: {
            y: {
                beginAtZero: true,
                title: {display: true, text: 'Hauteur (m)'}
            }
        }
    }
});
