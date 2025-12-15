// ============================================
// INITIALISATION DU BOUTON DATASET
// ============================================

const currentDataset = CURRENT_DATASET;
const boutonDataset = document.getElementById('boutonDataset');

if (currentDataset === 'dataPoitiers.csv') {
    boutonDataset.textContent = '🗺️ Voir France entière';
    boutonDataset.style.background = 'linear-gradient(135deg, #4169E1 0%, #1E90FF 100%)';
}

boutonDataset.addEventListener('click', function() {
    const newDataset = currentDataset === 'dataFinal.csv' ? 'dataPoitiers.csv' : 'dataFinal.csv';
    window.location.href = `?dataset=${encodeURIComponent(newDataset)}`;
});

// ============================================
// GESTION DU CHARGEMENT DE DONNÉES
// ============================================

document.getElementById('boutonCharger').addEventListener('click', function() {
    const datéDebut = document.getElementById('datéDebut').value;
    const datéFin = document.getElementById('datéFin').value;
    
    if (!datéDebut || !datéFin) {
        afficherMessage('Veuillez sélectionner les deux dates', 'erreur');
        return;
    }
    
    if (new Date(datéDebut) > new Date(datéFin)) {
        afficherMessage('La date de début doit être antérieure à la date de fin', 'erreur');
        return;
    }
    
    afficherChargement(true);
    
    fetch('./chargerDonnees.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: `date_debut=${encodeURIComponent(datéDebut)}&date_fin=${encodeURIComponent(datéFin)}`
    })
    .then(response => response.text())
    .then(text => {
        afficherChargement(false);
        try {
            const data = JSON.parse(text);
            if (data.succes) {
                afficherMessage('Données chargées avec succès ! La page va se rafraîchir...', 'succes');
                setTimeout(function() {
                    location.reload();
                }, 1500);
            } else {
                afficherMessage('Erreur : ' + data.message, 'erreur');
            }
        } catch (e) {
            console.error('Erreur de parsing JSON, actualisation automatique...', e);
            afficherMessage('Traitement des données en cours, actualisation de la page...', 'succes');
            setTimeout(function() {
                location.reload();
            }, 2000);
        }
    })
    .catch(error => {
        afficherChargement(false);
        console.error('Erreur de communication :', error);
        afficherMessage('Traitement des données en cours, actualisation de la page...', 'succes');
        setTimeout(function() {
            location.reload();
        }, 2000);
    });
});

function afficherChargement(afficher) {
    document.getElementById('indicateurChargement').style.display = afficher ? 'block' : 'none';
}

function afficherMessage(texte, type) {
    const messageDiv = document.getElementById('messageRésultat');
    messageDiv.textContent = texte;
    messageDiv.style.display = 'block';
    
    if (type === 'succes') {
        messageDiv.style.backgroundColor = '#d4edda';
        messageDiv.style.color = '#155724';
    } else {
        messageDiv.style.backgroundColor = '#f8d7da';
        messageDiv.style.color = '#721c24';
    }
}

// Données PHP converties en JSON
const allData = ALL_DATA;


// ============================================
// INDICATEUR 1 : CARTE INTERACTIVE FOLIUM
// ============================================

// Calcul des températures moyennes par commune
const temperatureByCommune = {};
const temperatureByCommuneByMonth = {};

// Parcourt chaque ligne de données
allData.forEach(row => {
    const commune = row['Libellé géographique'] || 'Inconnu'; // Communes inconnues = 'Inconnu'
    const lat = parseFloat(row['Latitude']);   // Convertit en nombre
    const lon = parseFloat(row['Longitude']);  // Convertit en nombre
    const temp = parseFloat(row['Température (°C)']); // Convertit en nombre
    const dateStr = row['Date et heure d\'observation'];
    // substring(5, 7) extrait les caractères 5-6 de la date: "2024-12-13" -> "12" (mois)
    const mois = dateStr ? dateStr.substring(5, 7) : '00';
    
    // Vérifie que les données ne sont pas NaN (Not a Number)
    if (!isNaN(lat) && !isNaN(lon) && !isNaN(temp)) {
        // Crée une entrée pour cette commune si elle n'existe pas
        if (!temperatureByCommune[commune]) {
            temperatureByCommune[commune] = {
                lat: lat,
                lon: lon,
                temps: [],           // Tableau de toutes les températures
                tempsParMois: {},    // Objet avec mois comme clé
                region: row['Nom région']
            };
        }
        // Ajoute la température au tableau global
        temperatureByCommune[commune].temps.push(temp);
        
        // Stocke aussi par mois (pour le filtrage)
        if (!temperatureByCommune[commune].tempsParMois[mois]) {
            temperatureByCommune[commune].tempsParMois[mois] = [];
        }
        temperatureByCommune[commune].tempsParMois[mois].push(temp);
    }
});

// Calculer la moyenne pour chaque commune
Object.keys(temperatureByCommune).forEach(commune => {
    const data = temperatureByCommune[commune];
    // reduce() accumule les valeurs du tableau (a = accumulation, b = élément courant)
    // Somme tous les éléments puis divise par le nombre d'éléments = moyenne
    const avg = data.temps.reduce((a, b) => a + b, 0) / data.temps.length;
    data.avg = avg; // Stocke la moyenne
});

// Fonction pour obtenir la couleur en fonction de la température
function getTemperatureColor(temp) {
    // Retourne une couleur hexadécimale selon la plage de température
    if (temp < 0) return '#0033FF';      // Bleu foncé: très froid
    if (temp < 5) return '#00CCFF';      // Bleu ciel: froid
    if (temp < 10) return '#00FF00';     // Vert: frais
    if (temp < 15) return '#90EE90';     // Vert clair: tempéré
    if (temp < 20) return '#FF6600';     // Orange: chaud
    return '#FF0000';                    // Rouge: très chaud
}

// Initialiser la carte Leaflet
// L.map() crée une instance de carte, setView([lat, lon], zoom)
const map = L.map('map').setView([46.5, 2.0], 5); // Centre France, zoom 5

// Ajoute les tuiles OpenStreetMap à la carte
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors',
    maxZoom: 19
}).addTo(map); // .addTo(map) ajoute la couche à la carte

// Layer Group = groupe de marqueurs qu'on peut ajouter/supprimer ensemble
let markersLayer = L.layerGroup().addTo(map);
let selectedMonth = ''; // Mois sélectionné ('' = tous les mois)

// Fonction pour rafraîchir la carte
function updateMap(mois) {
    selectedMonth = mois; // Met à jour le mois sélectionné
    markersLayer.clearLayers(); // Supprime tous les marqueurs existants
    
    // Parcourt chaque commune
    Object.keys(temperatureByCommune).forEach(commune => {
        const data = temperatureByCommune[commune];
        let avg;
        
        // Calcule la moyenne selon le filtre de mois
        if (mois === '') {
            // Tous les mois: utilise la moyenne globale
            avg = data.avg;
        } else {
            // Filtre par mois spécifique
            if (data.tempsParMois[mois] && data.tempsParMois[mois].length > 0) {
                // Calcule la moyenne pour ce mois
                avg = data.tempsParMois[mois].reduce((a, b) => a + b, 0) / data.tempsParMois[mois].length;
            } else {
                return; // Pas de données pour ce mois, passe à la commune suivante
            }
        }
        
        // Obtient la couleur selon la température
        const color = getTemperatureColor(avg);
        
        // Crée un marqueur circulaire sur la carte
        L.circleMarker([data.lat, data.lon], {
            radius: 8,             // Rayon en pixels
            fillColor: color,      // Couleur de remplissage
            color: '#333',         // Couleur de la bordure
            weight: 1,             // Largeur de la bordure
            opacity: 0.8,          // Transparence de la bordure
            fillOpacity: 0.8       // Transparence du remplissage
        }).bindPopup(
            // Crée le contenu de la popup (info-bulle)
            `<b>${commune}</b><br>` +
            `Température: ${avg.toFixed(2)}°C<br>` + // toFixed(2) = 2 décimales
            `Région: ${data.region}<br>` +
            // Affiche le nombre d'observations selon le filtre
            `Observations: ${mois === '' ? data.temps.length : (data.tempsParMois[mois] ? data.tempsParMois[mois].length : 0)}`
        ).addTo(markersLayer); // Ajoute le marqueur au layer group
    });
}

updateMap(''); // Charge la carte avec tous les mois au démarrage

// Générer dynamiquement les options des mois disponibles
const moisDisponibles = new Set(); // Set = ensemble (pas de doublons)
const nomsMois = {
    '01': 'Janvier', '02': 'Février', '03': 'Mars', '04': 'Avril',
    '05': 'Mai', '06': 'Juin', '07': 'Juillet', '08': 'Août',
    '09': 'Septembre', '10': 'Octobre', '11': 'Novembre', '12': 'Décembre'
};

// Parcourt toutes les communes et collecte les mois présents
Object.keys(temperatureByCommune).forEach(commune => {
    // Parcourt chaque mois disponible pour cette commune
    Object.keys(temperatureByCommune[commune].tempsParMois).forEach(mois => {
        moisDisponibles.add(mois); // Ajoute le mois au Set
    });
});

// Convertit le Set en Array et trie numériquement (comme strings)
const moisTriés = Array.from(moisDisponibles).sort();
const selectMois = document.getElementById('moisFiltre'); // Récupère le select du DOM

// Ajoute une option pour chaque mois disponible
moisTriés.forEach(mois => {
    const option = document.createElement('option'); // Crée un élément <option>
    option.value = mois;
    option.textContent = nomsMois[mois];
    selectMois.appendChild(option);
});

document.getElementById('moisFiltre').addEventListener('change', function() {
    updateMap(this.value);
});


// ============================================
// INDICATEUR 2 : GRAPHIQUE COMPARATIF
// ============================================
// Cet indicateur affiche un graphique double axe Y:
// - Axe Y gauche: Pluviométrie (mm)
// - Axe Y droit: Humidité relative (%)

// Objet pour agréger les données: clé = "région-année"
const dataPluieHumiditéParRegionAnnée = {};

// Parcourt chaque observation météo
allData.forEach(row => {
    const region = row['Nom région'] || 'Inconnu'; // Récupère la région
    // substring(0, 4) extrait "2024" de "2024-12-13 10:30:00"
    const year = row['Date et heure d\'observation'].substring(0, 4);
    const pluie = parseFloat(row['Précipitations 24h (mm)']) || 0; // || 0 = valeur par défaut
    const humidite = parseFloat(row['Humidité relative (%)']) || 0;
    
    // Crée une clé unique pour région-année (ex: "Nouvelle-Aquitaine-2024")
    const key = `${region}-${year}`;
    if (!dataPluieHumiditéParRegionAnnée[key]) {
        // Crée une entrée si elle n'existe pas
        dataPluieHumiditéParRegionAnnée[key] = {
            region: region,
            year: year,
            pluies: [],       // Tableau des précipitations
            humidites: []     // Tableau des humidités
        };
    }
    
    // Ajoute les valeurs si elles sont positives
    if (pluie > 0) dataPluieHumiditéParRegionAnnée[key].pluies.push(pluie);
    if (humidite > 0) dataPluieHumiditéParRegionAnnée[key].humidites.push(humidite);
});

// Tableaux pour le graphique
const labels = [];                // Noms des régions
const pluieMoyenne = [];          // Moyennes de pluviométrie
const humiditeAverage = [];       // Moyennes d'humidité

// Prend les 15 premiers (slice(0, 15)) pour limiter le nombre de barres
Object.keys(dataPluieHumiditéParRegionAnnée).slice(0, 15).forEach(key => {
    const data = dataPluieHumiditéParRegionAnnée[key];
    // Tronque le nom de région à 10 caractères pour l'affichage
    labels.push(`${data.region.substring(0, 10)}`);
    
    // Calcule la moyenne de pluviométrie
    const pluieAvg = data.pluies.length > 0 
        ? data.pluies.reduce((a, b) => a + b, 0) / data.pluies.length  // Somme / nombre
        : 0; // Si pas de données, utilise 0
    
    // Calcule la moyenne d'humidité
    const humAvg = data.humidites.length > 0 
        ? data.humidites.reduce((a, b) => a + b, 0) / data.humidites.length
        : 0;
    
    pluieMoyenne.push(pluieAvg);
    humiditeAverage.push(humAvg);
});

// Récupère le contexte de rendu 2D du canvas "comparativeChart"
const ctx = document.getElementById('comparativeChart').getContext('2d');
// Crée un nouveau graphique Chart.js
new Chart(ctx, {
    type: 'bar', // Type de graphique: barres
    data: {
        labels: labels, // Noms des régions
        datasets: [
            {
                label: 'Pluviométrie 24h (mm)',
                data: pluieMoyenne,
                backgroundColor: 'rgba(135, 206, 250, 0.8)', // Bleu ciel semi-transparent
                borderColor: 'rgba(135, 206, 250)', // Bordure bleu ciel
                borderWidth: 1,
                yAxisID: 'y' // Utilise l'axe Y gauche
            },
            {
                label: 'Humidité (%)',
                data: humiditeAverage,
                backgroundColor: 'rgba(176, 224, 230, 0.8)', // Bleu poudré
                borderColor: 'rgba(176, 224, 230)',
                borderWidth: 1,
                yAxisID: 'y1' // Utilise l'axe Y droit (dual axis)
            }
        ]
    },
    options: {
        responsive: true,           // Adapte la taille au conteneur
        maintainAspectRatio: false, // Permet de forcer une hauteur
        interaction: { 
            mode: 'index',          // Au survol, met en évidence l'index
            intersect: false        // Affiche même si pas sur la barre exacte
        },
        plugins: { 
            legend: { position: 'top' } // Légende en haut
        },
        scales: {
            y: {
                // Axe Y gauche pour la pluviométrie
                type: 'linear',
                display: true,
                position: 'left',
                title: { display: true, text: 'Pluviométrie (mm)' }
            },
            y1: {
                // Axe Y droit pour l'humidité (dual axis)
                type: 'linear',
                display: true,
                position: 'right',
                title: { display: true, text: 'Humidité (%)' },
                grid: { drawOnChartArea: false } // Ne dessine pas la grille pour y1
            }
        }
    }
});

// ============================================
// INDICATEUR 3 : VITESSE DU VENT PAR RÉGION
// ============================================
// Cet indicateur affiche un graphique horizontal montrant
// la vitesse moyenne du vent pour chaque région

// Objet pour agréger vitesses du vent par région
const vitesseVentParRégion = {};

// Parcourt chaque observation météo
allData.forEach(row => {
    const région = row['Nom région'] || 'Inconnu'; // Région de l'observation
    const vitesseVent = parseFloat(row['Vitesse du vent moyen 10mn']) || 0;
    
    // Vérifie que la valeur est valide (pas NaN) et positive
    if (!isNaN(vitesseVent) && vitesseVent >= 0) {
        // Initialise le tableau si c'est la première observation pour cette région
        if (!vitesseVentParRégion[région]) {
            vitesseVentParRégion[région] = [];
        }
        // Ajoute cette vitesse au tableau de la région
        vitesseVentParRégion[région].push(vitesseVent);
    }
});

// Tableaux pour le graphique
const étiquettesVitesseRégions = [];  // Noms des régions
const vitesseMoyenneParRégion = [];   // Vitesses moyennes

// Parcourt toutes les régions dans l'ordre alphabétique (.sort())
Object.keys(vitesseVentParRégion).sort().forEach(région => {
    const vitesses = vitesseVentParRégion[région]; // Tableau de toutes les vitesses
    // Calcule la moyenne: somme de tous les éléments / nombre d'éléments
    const moyenne = vitesses.length > 0 ? vitesses.reduce((a, b) => a + b, 0) / vitesses.length : 0;
    // Limite le nom de région à 20 caractères
    étiquettesVitesseRégions.push(région.substring(0, 20));
    // toFixed(2) = arrondit à 2 décimales
    vitesseMoyenneParRégion.push(moyenne.toFixed(2));
});

// Récupère le contexte du canvas "yearChart"
const ctxAnnée = document.getElementById('yearChart').getContext('2d');
// Crée un graphique horizontal (indexAxis: 'y')
new Chart(ctxAnnée, {
    type: 'bar',  // Type: barres
    data: {
        labels: étiquettesVitesseRégions, // Noms des régions
        datasets: [{
            label: 'Vitesse du vent moyen (m/s)',
            data: vitesseMoyenneParRégion,
            backgroundColor: 'rgba(135, 206, 250, 0.8)', // Bleu ciel
            borderColor: 'rgba(135, 206, 250)',
            borderWidth: 1
        }]
    },
    options: {
        // indexAxis: 'y' = affiche le graphique horizontalement (barres de gauche à droite)
        // Par défaut (sans cette option), c'est vertical (indexAxis: 'x')
        indexAxis: 'y',
        responsive: true,           // Adapte à la taille du conteneur
        maintainAspectRatio: false, // Permet de forcer une hauteur
        plugins: { 
            legend: { 
                display: true,      // Affiche la légende
                position: 'top'     // Positionne la légende en haut
            } 
        },
        scales: { 
            x: { 
                // Axe horizontal (car indexAxis: 'y')
                title: { 
                    display: true, 
                    text: 'Vitesse du vent (m/s)' // Titre de l'axe X
                } 
            } 
        }
    }
});

// ============================================
// INDICATEUR 4 : TEMPÉRATURE PAR MOIS
// ============================================
// Cet indicateur montre l'évolution de la température moyenne
// mois par mois (janvier à décembre)

// Objet pour agréger températures par mois
const tempParMois = {};

// Parcourt chaque observation météo
allData.forEach(row => {
    // Récupère la date complète (ex: "2024-12-13")
    const chaîneDate = row['Date et heure d\'observation'];
    // substring(5, 7) extrait le mois: "2024-12-13" -> "12"
    const mois = chaîneDate.substring(5, 7);
    // Tableau pour convertir numéro mois en nom français
    const nomsMoisTemp = ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Jun', 'Jul', 'Aoû', 'Sep', 'Oct', 'Nov', 'Déc'];
    // Convertit "12" en "Déc" (parseInt("12") = 12, 12-1 = 11, nomsMoisTemp[11] = "Déc")
    const étiquetteMois = nomsMoisTemp[parseInt(mois) - 1] || `Mois ${mois}`;
    const temp = parseFloat(row['Température (°C)']) || 0;
    
    // Vérifie que la température est valide (pas NaN) et non zéro
    if (!isNaN(temp) && temp !== 0) {
        // Crée une entrée pour ce mois si elle n'existe pas
        if (!tempParMois[mois]) {
            tempParMois[mois] = { 
                étiquette: étiquetteMois,      // Nom du mois (Déc, Jan, etc.)
                temperatures: []               // Tableau de toutes les températures
            };
        }
        // Ajoute cette température au tableau du mois
        tempParMois[mois].temperatures.push(temp);
    }
});

// Récupère les clés triées numériquement (01, 02, ..., 12)
// Object.keys() retourne ['12', '01', '03', ...] en ordre d'insertion
// .sort((a, b) => parseInt(a) - parseInt(b)) trie numériquement
const étiquettesMois = Object.keys(tempParMois).sort((a, b) => parseInt(a) - parseInt(b))
    .map(m => tempParMois[m].étiquette); // Transforme en ['Jan', 'Fév', ...]

// Calcule les moyennes de température par mois
const températuresMoyennesMois = Object.keys(tempParMois).sort((a, b) => parseInt(a) - parseInt(b))
    .map(m => {
        const temperatures = tempParMois[m].temperatures;
        // Calcule la moyenne: somme de tous / nombre d'éléments
        return temperatures.length > 0 ? temperatures.reduce((a, b) => a + b, 0) / temperatures.length : 0;
    });

// Récupère le contexte du canvas "monthChart"
const ctxMois = document.getElementById('monthChart').getContext('2d');
// Crée un graphique en barres
new Chart(ctxMois, {
    type: 'bar', // Type: barres verticales
    data: {
        labels: étiquettesMois,        // Mois (Jan, Fév, ...)
        datasets: [{
            label: 'Température moyenne (°C)',
            data: températuresMoyennesMois,
            backgroundColor: 'rgba(135, 206, 250, 0.8)', // Bleu ciel semi-transparent
            borderColor: 'rgba(135, 206, 250)',         // Bordure bleu ciel
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,           // Adapte à la taille du conteneur
        maintainAspectRatio: false, // Permet de forcer une hauteur personnalisée
        plugins: { 
            legend: { 
                display: true,      // Affiche la légende
                position: 'top'     // En haut du graphique
            } 
        },
        scales: { 
            y: { 
                title: { 
                    display: true, 
                    text: 'Température (°C)' // Titre de l'axe Y (vertical)
                } 
            } 
        }
    }
});

// ============================================
// INDICATEUR 5 : HAUTEUR DES NUAGES PAR RÉGION
// ============================================
// Cet indicateur affiche la hauteur moyenne de la base des nuages
// pour chaque région (en mètres)

// Objet pour agréger hauteurs de nuages par région
const hauteurNuagesParRégion = {};

// Parcourt chaque observation météo
allData.forEach(row => {
    const région = row['Nom région'] || 'Inconnu'; // Région de l'observation
    const hauteur = parseFloat(row['Hauteur base nuages (m)']) || 0;
    
    // Vérifie que la hauteur est valide (pas NaN) et positive
    if (!isNaN(hauteur) && hauteur >= 0) {
        // Initialise le tableau si c'est la première observation pour cette région
        if (!hauteurNuagesParRégion[région]) {
            hauteurNuagesParRégion[région] = [];
        }
        // Ajoute cette hauteur au tableau de la région
        hauteurNuagesParRégion[région].push(hauteur);
    }
});

// Tableaux pour le graphique
const étiquettesRégions = [];      // Noms des régions
const hauteurMoyenneParRégion = []; // Hauteurs moyennes

// Parcourt toutes les régions dans l'ordre alphabétique (.sort())
Object.keys(hauteurNuagesParRégion).sort().forEach(région => {
    const hauteurs = hauteurNuagesParRégion[région]; // Tableau de toutes les hauteurs
    // Calcule la moyenne: somme de tous les éléments / nombre d'éléments
    const moyenne = hauteurs.length > 0 ? hauteurs.reduce((a, b) => a + b, 0) / hauteurs.length : 0;
    // Limite le nom de région à 20 caractères
    étiquettesRégions.push(région.substring(0, 20));
    // toFixed(0) = arrondit à 0 décimales (nombre entier)
    hauteurMoyenneParRégion.push(moyenne.toFixed(0));
});

// Récupère le contexte du canvas "regionChart"
const ctxRégion = document.getElementById('regionChart').getContext('2d');
// Crée un graphique en barres
new Chart(ctxRégion, {
    type: 'bar', // Type: barres verticales
    data: {
        labels: étiquettesRégions,     // Noms des régions
        datasets: [{
            label: 'Hauteur base nuages (m)',
            data: hauteurMoyenneParRégion,
            backgroundColor: 'rgba(176, 224, 230, 0.8)', // Bleu poudré semi-transparent
            borderColor: 'rgba(176, 224, 230)',         // Bordure bleu poudré
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,           // Adapte à la taille du conteneur
        maintainAspectRatio: false, // Permet de forcer une hauteur personnalisée
        plugins: { 
            legend: { 
                display: true,      // Affiche la légende
                position: 'top'     // Positionnée en haut
            } 
        },
        scales: { 
            y: { 
                beginAtZero: true,  // Axe Y commence à 0 (pas de valeur négative)
                title: { 
                    display: true, 
                    text: 'Hauteur (m)' // Titre de l'axe Y (vertical)
                } 
            } 
        }
    }
});
