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
    
    fetch('chargerDonnees.php', {
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
            // En cas d'erreur JSON, actualiser la page automatiquement
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
        // Actualiser automatiquement au lieu d'afficher l'erreur
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

// ============================================
// INDICATEUR 1 : CARTE INTERACTIVE FOLIUM
// ============================================

function initializeMap(allData) {
    // Calcul des températures moyennes par commune
    const temperatureByCommune = {};
    
    allData.forEach(row => {
        const commune = row['Libellé géographique'] || 'Inconnu';
        const lat = parseFloat(row['Latitude']);
        const lon = parseFloat(row['Longitude']);
        const temp = parseFloat(row['Température (°C)']);
        const dateStr = row['Date et heure d\'observation'];
        const mois = dateStr ? dateStr.substring(5, 7) : '00';
        
        if (!isNaN(lat) && !isNaN(lon) && !isNaN(temp)) {
            if (!temperatureByCommune[commune]) {
                temperatureByCommune[commune] = {
                    lat: lat,
                    lon: lon,
                    temps: [],
                    tempsParMois: {},
                    region: row['Nom région']
                };
            }
            temperatureByCommune[commune].temps.push(temp);
            
            // Stocker aussi par mois
            if (!temperatureByCommune[commune].tempsParMois[mois]) {
                temperatureByCommune[commune].tempsParMois[mois] = [];
            }
            temperatureByCommune[commune].tempsParMois[mois].push(temp);
        }
    });
    
    // Calculer la moyenne pour chaque commune
    Object.keys(temperatureByCommune).forEach(commune => {
        const data = temperatureByCommune[commune];
        const avg = data.temps.reduce((a, b) => a + b, 0) / data.temps.length;
        data.avg = avg;
    });
    
    // Fonction pour obtenir la couleur en fonction de la température
    function getTemperatureColor(temp) {
        if (temp < 0) return '#0033FF';      // Bleu foncé
        if (temp < 5) return '#00CCFF';      // Bleu ciel
        if (temp < 10) return '#00FF00';     // Vert
        if (temp < 15) return '#90EE90';     // Vert clair
        if (temp < 20) return '#FF6600';     // Orange
        if (temp < 30) return '#FF0000';     // Rouge
        return '#000000';                     // Noir
    }
    
    // Initialiser la carte
    const map = L.map('map').setView([46.5, 2.0], 5);
    
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors',
        maxZoom: 19
    }).addTo(map);
    
    // Variables globales pour gérer les marqueurs
    let markersLayer = L.layerGroup().addTo(map);
    let selectedMonth = '';
    
    // Fonction pour rafraîchir la carte
    function updateMap(mois) {
        selectedMonth = mois;
        markersLayer.clearLayers();
        
        // Ajouter les marqueurs filtrés
        Object.keys(temperatureByCommune).forEach(commune => {
            const data = temperatureByCommune[commune];
            let avg;
            
            if (mois === '') {
                // Tous les mois
                avg = data.avg;
            } else {
                // Filtre par mois
                if (data.tempsParMois[mois] && data.tempsParMois[mois].length > 0) {
                    avg = data.tempsParMois[mois].reduce((a, b) => a + b, 0) / data.tempsParMois[mois].length;
                } else {
                    return; // Pas de données pour ce mois
                }
            }
            
            const color = getTemperatureColor(avg);
            
            L.circleMarker([data.lat, data.lon], {
                radius: 8,
                fillColor: color,
                color: '#333',
                weight: 1,
                opacity: 0.8,
                fillOpacity: 0.8
            }).bindPopup(
                `<b>${commune}</b><br>` +
                `Température: ${avg.toFixed(2)}°C<br>` +
                `Région: ${data.region}<br>` +
                `Observations: ${mois === '' ? data.temps.length : (data.tempsParMois[mois] ? data.tempsParMois[mois].length : 0)}`
            ).addTo(markersLayer);
        });
    }
    
    // Charger la carte avec tous les mois au démarrage
    updateMap('');
    
    // Générer dynamiquement les options des mois disponibles
    const moisDisponibles = new Set();
    const nomsMois = {
        '01': 'Janvier',
        '02': 'Février',
        '03': 'Mars',
        '04': 'Avril',
        '05': 'Mai',
        '06': 'Juin',
        '07': 'Juillet',
        '08': 'Août',
        '09': 'Septembre',
        '10': 'Octobre',
        '11': 'Novembre',
        '12': 'Décembre'
    };
    
    // Récupérer tous les mois présents dans les données
    Object.keys(temperatureByCommune).forEach(commune => {
        Object.keys(temperatureByCommune[commune].tempsParMois).forEach(mois => {
            moisDisponibles.add(mois);
        });
    });
    
    // Trier les mois et ajouter les options au sélecteur
    const moisTriés = Array.from(moisDisponibles).sort();
    const selectMois = document.getElementById('moisFiltre');
    
    moisTriés.forEach(mois => {
        const option = document.createElement('option');
        option.value = mois;
        option.textContent = nomsMois[mois];
        selectMois.appendChild(option);
    });
    
    // Ajouter le listener au sélecteur de mois
    document.getElementById('moisFiltre').addEventListener('change', function() {
        updateMap(this.value);
    });
}

// ============================================
// INDICATEURS GRAPHIQUES
// ============================================

function initializeCharts(allData) {
    // INDICATEUR 2 : GRAPHIQUE COMPARATIF
    const dataPluieHumiditéParRegionAnnée = {};
    
    allData.forEach(row => {
        const region = row['Nom région'] || 'Inconnu';
        const year = row['Date et heure d\'observation'].substring(0, 4);
        const pluie = parseFloat(row['Précipitations 24h (mm)']) || 0;
        const humidite = parseFloat(row['Humidité relative (%)']) || 0;
        
        const key = `${region}-${year}`;
        if (!dataPluieHumiditéParRegionAnnée[key]) {
            dataPluieHumiditéParRegionAnnée[key] = {
                region: region,
                year: year,
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
    
    Object.keys(dataPluieHumiditéParRegionAnnée).slice(0, 15).forEach(key => {
        const data = dataPluieHumiditéParRegionAnnée[key];
        labels.push(`${data.region.substring(0, 10)}`);
        
        const pluieAvg = data.pluies.length > 0 
            ? data.pluies.reduce((a, b) => a + b, 0) / data.pluies.length 
            : 0;
        const humAvg = data.humidites.length > 0 
            ? data.humidites.reduce((a, b) => a + b, 0) / data.humidites.length 
            : 0;
        
        pluieMoyenne.push(pluieAvg);
        humiditeAverage.push(humAvg);
    });
    
    const ctx = document.getElementById('comparativeChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Pluviométrie 24h (mm)',
                    data: pluieMoyenne,
                    backgroundColor: 'rgba(135, 206, 250, 0.8)',
                    borderColor: 'rgba(135, 206, 250)',
                    borderWidth: 1,
                    yAxisID: 'y'
                },
                {
                    label: 'Humidité (%)',
                    data: humiditeAverage,
                    backgroundColor: 'rgba(176, 224, 230, 0.8)',
                    borderColor: 'rgba(176, 224, 230)',
                    borderWidth: 1,
                    yAxisID: 'y1'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            plugins: {
                legend: {
                    position: 'top',
                }
            },
            scales: {
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    title: {
                        display: true,
                        text: 'Pluviométrie (mm)'
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    title: {
                        display: true,
                        text: 'Humidité (%)'
                    },
                    grid: {
                        drawOnChartArea: false,
                    },
                },
            }
        }
    });
    
    // INDICATEUR 3 : VITESSE DU VENT PAR RÉGION
    const vitesseVentParRégion = {};
    
    allData.forEach(row => {
        const région = row['Nom région'] || 'Inconnu';
        const vitesseVent = parseFloat(row['Vitesse du vent moyen 10mn']) || 0;
        
        if (!isNaN(vitesseVent) && vitesseVent >= 0) {
            if (!vitesseVentParRégion[région]) {
                vitesseVentParRégion[région] = [];
            }
            vitesseVentParRégion[région].push(vitesseVent);
        }
    });
    
    const étiquettesVitesseRégions = [];
    const vitesseMoyenneParRégion = [];
    
    Object.keys(vitesseVentParRégion).sort().forEach(région => {
        const vitesses = vitesseVentParRégion[région];
        const moyenne = vitesses.length > 0 ? vitesses.reduce((a, b) => a + b, 0) / vitesses.length : 0;
        étiquettesVitesseRégions.push(région.substring(0, 20));
        vitesseMoyenneParRégion.push(moyenne.toFixed(2));
    });
    
    const ctxAnnée = document.getElementById('yearChart').getContext('2d');
    new Chart(ctxAnnée, {
        type: 'bar',
        data: {
            labels: étiquettesVitesseRégions,
            datasets: [{
                label: 'Vitesse du vent moyen (m/s)',
                data: vitesseMoyenneParRégion,
                backgroundColor: 'rgba(135, 206, 250, 0.8)',
                backgroundColor: 'rgba(135, 206, 250)',
                borderWidth: 1
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'top'
                }
            },
            scales: {
                x: {
                    title: {
                        display: true,
                        text: 'Vitesse du vent (m/s)'
                    }
                }
            }
        }
    });
    
    // INDICATEUR 4 : TEMPÉRATURE PAR MOIS
    const tempParMois = {};
    
    allData.forEach(row => {
        const chaîneDate = row['Date et heure d\'observation'];
        const mois = chaîneDate.substring(5, 7);
        const nomsMois = ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Jun', 'Jul', 'Aoû', 'Sep', 'Oct', 'Nov', 'Déc'];
        const étiquetteMois = nomsMois[parseInt(mois) - 1] || `Mois ${mois}`;
        const temp = parseFloat(row['Température (°C)']) || 0;
        
        if (!isNaN(temp) && temp !== 0) {
            if (!tempParMois[mois]) {
                tempParMois[mois] = {
                    étiquette: étiquetteMois,
                    temperatures: []
                };
            }
            tempParMois[mois].temperatures.push(temp);
        }
    });
    
    const étiquettesMois = Object.keys(tempParMois).sort((a, b) => parseInt(a) - parseInt(b)).map(m => tempParMois[m].étiquette);
    const températuresMoyennesMois = Object.keys(tempParMois).sort((a, b) => parseInt(a) - parseInt(b)).map(m => {
        const temperatures = tempParMois[m].temperatures;
        return temperatures.length > 0 ? temperatures.reduce((a, b) => a + b, 0) / temperatures.length : 0;
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
            plugins: {
                legend: {
                    display: true,
                    position: 'top'
                }
            },
            scales: {
                y: {
                    title: {
                        display: true,
                        text: 'Température (°C)'
                    }
                }
            }
        }
    });
    
    // INDICATEUR 5 : HAUTEUR DES NUAGES PAR RÉGION
    const hauteurNuagesParRégion = {};
    
    allData.forEach(row => {
        const région = row['Nom région'] || 'Inconnu';
        const hauteur = parseFloat(row['Hauteur base nuages (m)']) || 0;
        
        if (!isNaN(hauteur) && hauteur >= 0) {
            if (!hauteurNuagesParRégion[région]) {
                hauteurNuagesParRégion[région] = [];
            }
            hauteurNuagesParRégion[région].push(hauteur);
        }
    });
    
    const étiquettesRégions = [];
    const hauteurMoyenneParRégion = [];
    
    Object.keys(hauteurNuagesParRégion).sort().forEach(région => {
        const hauteurs = hauteurNuagesParRégion[région];
        const moyenne = hauteurs.length > 0 ? hauteurs.reduce((a, b) => a + b, 0) / hauteurs.length : 0;
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
            plugins: {
                legend: {
                    display: true,
                    position: 'top'
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Hauteur (m)'
                    }
                }
            }
        }
    });
}
