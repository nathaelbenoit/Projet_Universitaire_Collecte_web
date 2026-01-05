import requests
from datetime import datetime, timedelta
import pandas as pd
import sys
import os
import math

# Coordonnées de Poitiers pour le filtrage local
POITIERS_LAT = 46.580224
POITIERS_LON = 0.340375

url_de_base = "https://public.opendatasoft.com/api/explore/v2.1/catalog/datasets/donnees-synop-essentielles-omm/records"

def obtenirComptageEntreDates(date_debut, date_fin):
    """
    Obtient le nombre total d'enregistrements disponibles dans l'API pour une période donnée.
    
    Cette fonction effectue une requête à l'API OpenDataSoft pour compter le nombre d'enregistrements
    météorologiques SYNOP disponibles entre deux dates, sans récupérer les données elles-mêmes.
    Cela permet de vérifier la quantité de données avant de les télécharger et d'adapter la stratégie
    de pagination en fonction du volume.
    
    Paramètres d'entrée:
        date_debut (str): Date de début au format ISO 8601 (ex: '2025-01-01T00:00:00Z')
        date_fin (str): Date de fin au format ISO 8601 (ex: '2025-01-31T00:00:00Z')
    
    Paramètres de sortie:
        int: Nombre total d'enregistrements disponibles pour la période spécifiée.
             Retourne 0 en cas d'erreur ou si aucune donnée n'est disponible.
    """
    parametres = {
        "limit": 0,
        "where": f"date >= '{date_debut}' AND date < '{date_fin}'"
    }
    reponse = requests.get(url_de_base, params=parametres)
    if reponse.status_code != 200:
        print("Erreur API lors du comptage")
        return 0
    donnees_api = reponse.json()
    return donnees_api.get("total_count", 0)

def recupererDonneesEntreDates(date_debut, date_fin):
    """
    Récupère toutes les données météorologiques disponibles dans l'API pour une période donnée.
    
    Cette fonction gère la pagination automatique de l'API OpenDataSoft qui limite chaque requête
    à 100 résultats maximum. Elle effectue des requêtes successives avec un offset croissant
    jusqu'à récupérer l'intégralité des données de la période spécifiée.
    
    Paramètres d'entrée:
        date_debut (str): Date de début au format ISO 8601 (ex: '2025-01-01T00:00:00Z')
        date_fin (str): Date de fin au format ISO 8601 (ex: '2025-01-31T00:00:00Z')
    
    Paramètres de sortie:
        list: Liste de dictionnaires contenant les enregistrements météorologiques bruts.
              Chaque dictionnaire représente une observation SYNOP avec tous ses champs.
              Retourne une liste vide en cas d'erreur ou si aucune donnée n'est disponible.
    """
    resultats = []
    decalage = 0      # offset: nombre d'enregistrements à ignorer
    limite = 100      # limit: nombre de résultats par requête
    
    while True:
        # Paramètres de la requête API
        parametres = {
            "limit": limite,   # Limite à 100 résultats
            "offset": decalage, # Commence à "decalage" résultats
            # Filtre WHERE: récupère les données entre deux dates
            "where": f"date >= '{date_debut}' AND date < '{date_fin}'"
        }
        
        # Effectue la requête HTTP GET
        reponse = requests.get(url_de_base, params=parametres)
        
        # Vérifie que la requête a réussi (code 200)
        if reponse.status_code != 200:
            print(f"Erreur API pour {date_debut} - {date_fin}, décalage {decalage}")
            break
        
        # Extrait les résultats de la réponse JSON
        # .get("results", []) retourne la clé "results" ou [] si absent
        lot = reponse.json().get("results", [])
        
        if not lot:  # Si aucun résultat, on arrête la boucle
            break
        
        resultats.extend(lot)  # Ajoute tous les éléments du lot à resultats
        decalage += limite     # Avance le décalage de 100 (prochaine page)
        
        # Si moins de 100 résultats, c'est la dernière page
        if len(lot) < limite:
            break
    
    return resultats

def traiterDonnees(donnees):
    """
    Transforme, nettoie et filtre les données météorologiques brutes récupérées de l'API.
    
    Cette fonction effectue plusieurs opérations de traitement sur les données:
    - Normalise les données JSON en DataFrame pandas
    - Renomme toutes les colonnes avec des noms descriptifs en français
    - Supprime les colonnes entièrement vides (sans aucune valeur)
    - Élimine les colonnes jugées non pertinentes pour l'analyse finale
    
    Le traitement permet d'obtenir un jeu de données propre et exploitable, avec uniquement
    les variables météorologiques essentielles dans un format lisible.
    
    Paramètres d'entrée:
        donnees (list): Liste de dictionnaires contenant les enregistrements bruts de l'API.
                        Chaque dictionnaire représente une observation météorologique SYNOP.
    
    Paramètres de sortie:
        pandas.DataFrame: DataFrame nettoyé contenant les données météorologiques traitées,
                          avec colonnes renommées en français et données non pertinentes supprimées.
    """
    df = pd.json_normalize(donnees)

    dictionnaire_renommage = {
        "numer_sta": "Numéro de station OMM",
        "date": "Date et heure d'observation",
        "pmer": "Pression au niveau de la mer",
        "tend": "Variation de pression en 3h",
        "cod_tend": "Code de tendance barométrique",
        "dd": "Direction du vent (degrés)",
        "ff": "Vitesse du vent moyen 10mn",
        "t": "Température (Kelvin)",
        "td": "Point de rosée (Kelvin)",
        "u": "Humidité relative (%)",
        "vv": "Visibilité horizontale (m)",
        "ww": "Nebulosité totale",
        "w1": "Nuages étage inférieur",
        "w2": "Nuages étage moyen",
        "n": "Nuages étage supérieur",
        "nbas": "Hauteur base nuages inférieurs",
        "hbas": "Hauteur base nuages (m)",
        "cl": "Type nuages étage inférieur",
        "cm": "Type nuages étage moyen",
        "ch": "Type nuages étage supérieur",
        "pres": "Pression au niveau station",
        "niv_bar": "Niveau barométrique",
        "geop": "Géopotentiel",
        "tend24": "Variation pression 24h",
        "tn12": "T° minimale 12h (Kelvin)",
        "tn24": "T° minimale 24h (Kelvin)",
        "tx12": "T° maximale 12h (Kelvin)",
        "tx24": "T° maximale 24h (Kelvin)",
        "tminsol": "T° min sol 12h (Kelvin)",
        "sw": "Méthode mesure T° humide",
        "tw": "T° thermomètre mouillé",
        "raf10": "Rafale 10 dernières minutes",
        "rafper": "Rafales sur période",
        "per": "Période mesure rafale",
        "etat_sol": "État du sol",
        "ht_neige": "Hauteur totale neige/glace",
        "ssfrai": "Hauteur neige fraîche",
        "perssfrai": "Période neige fraîche",
        "rr1": "Précipitations 1h (mm)",
        "rr3": "Précipitations 3h (mm)",
        "rr6": "Précipitations 6h (mm)",
        "rr12": "Précipitations 12h (mm)",
        "rr24": "Précipitations 24h (mm)",
        "phenspe1": "Phénomène spécial 1",
        "phenspe2": "Phénomène spécial 2",
        "phenspe3": "Phénomène spécial 3",
        "phenspe4": "Phénomène spécial 4",
        "nnuage1": "Nebulosité couche 1",
        "ctype1": "Type nuages couche 1",
        "hnuage1": "Hauteur base couche 1",
        "nnuage2": "Nebulosité couche 2",
        "ctype2": "Type nuages couche 2",
        "hnuage2": "Hauteur base couche 2",
        "nnuage3": "Nebulosité couche 3",
        "ctype3": "Type nuages couche 3",
        "hnuage3": "Hauteur base couche 3",
        "nnuage4": "Nebulosité couche 4",
        "ctype4": "Type nuages couche 4",
        "hnuage4": "Hauteur base couche 4",
        "nom": "Nom de la station",
        "type_de_tendance_barometrique": "Type tendance barométrique",
        "temps_passe_1": "Temps passé (période 1)",
        "temps_present": "Temps présent",
        "tc": "Température (°C)",
        "tn12c": "T° min 12h (°C)",
        "tn24c": "T° min 24h (°C)",
        "tx12c": "T° max 12h (°C)",
        "tx24c": "T° max 24h (°C)",
        "tminsolc": "T° min sol 12h (°C)",
        "latitude": "Latitude",
        "longitude": "Longitude",
        "altitude": "Altitude (m)",
        "libgeo": "Libellé géographique",
        "codegeo": "Code INSEE commune",
        "nom_epci": "Nom EPCI",
        "code_epci": "Code EPCI",
        "nom_dept": "Nom département",
        "code_dep": "Code département",
        "nom_reg": "Nom région",
        "code_reg": "Code région",
        "mois_de_l_annee": "Mois de l'année",
        "coordonnees.lon": "Longitude (coordonnées)",
        "coordonnees.lat": "Latitude (coordonnées)"
    }

    df.rename(columns=dictionnaire_renommage, inplace=True)
    df_propre = df.dropna(axis=1, how='all')

    colonnes_a_supprimer = [
        "Pression au niveau de la mer",
        "Variation de pression en 3h",
        "Code de tendance barométrique",
        "Température (Kelvin)",
        "Point de rosée (Kelvin)",
        "Nuages étage inférieur",
        "Nuages étage moyen",
        "Nuages étage supérieur",
        "Hauteur base nuages inférieurs",
        "Type nuages étage inférieur",
        "Type nuages étage moyen",
        "Type nuages étage supérieur",
        "Niveau barométrique",
        "Géopotentiel",
        "Variation pression 24h",
        "T° minimale 12h (Kelvin)",
        "T° minimale 24h (Kelvin)",
        "T° maximale 12h (Kelvin)",
        "T° maximale 24h (Kelvin)",
        "T° min sol 12h (Kelvin)",
        "Méthode mesure T° humide",
        "T° thermomètre mouillé",
        "Rafales sur période",
        "Période mesure rafale",
        "État du sol",
        "Hauteur totale neige/glace",
        "Hauteur neige fraîche",
        "Période neige fraîche",
        "Précipitations 1h (mm)",
        "Précipitations 3h (mm)",
        "Précipitations 6h (mm)",
        "Précipitations 12h (mm)",
        "Phénomène spécial 1",
        "Phénomène spécial 2",
        "Phénomène spécial 3",
        "Phénomène spécial 4",
        "Nebulosité couche 1",
        "Type nuages couche 1",
        "Hauteur base couche 1",
        "Nebulosité couche 2",
        "Type nuages couche 2",
        "Hauteur base couche 2",
        "Nebulosité couche 3",
        "Type nuages couche 3",
        "Hauteur base couche 3",
        "Nebulosité couche 4",
        "Type nuages couche 4",
        "Hauteur base couche 4",
        "Type tendance barométrique",
        "Temps passé (période 1)",
        "Temps présent",
        "T° min 12h (°C)",
        "T° max 12h (°C)",
        "T° min sol 12h (°C)",
        "Code INSEE commune",
        "Nom EPCI",
        "Code EPCI",
        "Latitude (coordonnées)",
        "Longitude (coordonnées)"
    ]

    # List comprehension: créé une liste avec uniquement les colonnes à supprimer qui existent
    colonnes_existantes = [col for col in colonnes_a_supprimer if col in df_propre.columns]
    # Supprime les colonnes inutiles du dataframe
    df_propre = df_propre.drop(columns=colonnes_existantes)

    return df_propre


def calculer_distance_km(lat1, lon1, lat2, lon2):
    """
    Calcule la distance orthodromique (plus court chemin) entre deux points géographiques.
    
    Cette fonction utilise la formule de Haversine pour calculer la distance à la surface
    d'une sphère entre deux points définis par leurs coordonnées GPS. La formule prend en
    compte la courbure de la Terre et fournit une précision suffisante pour des distances
    courtes et moyennes (quelques centaines de kilomètres).
    
    La formule de Haversine est: d = 2r × arcsin(√(sin²(Δφ/2) + cos(φ1) × cos(φ2) × sin²(Δλ/2)))
    où φ représente la latitude, λ la longitude, et r le rayon terrestre.
    
    Paramètres d'entrée:
        lat1 (float): Latitude du premier point en degrés décimaux (ex: 46.580224)
        lon1 (float): Longitude du premier point en degrés décimaux (ex: 0.340375)
        lat2 (float): Latitude du second point en degrés décimaux
        lon2 (float): Longitude du second point en degrés décimaux
    
    Paramètres de sortie:
        float: Distance entre les deux points en kilomètres.
    """
    r = 6371.0  # Rayon terrestre en km
    
    # Convertit degrés en radians pour les calculs trigonométriques
    lat1_rad, lon1_rad = math.radians(lat1), math.radians(lon1)
    lat2_rad, lon2_rad = math.radians(lat2), math.radians(lon2)
    
    # Calcule les différences entre les deux points
    dlat = lat2_rad - lat1_rad
    dlon = lon2_rad - lon1_rad

    # Formule de Haversine pour calculer la distance à la surface d'une sphère
    # a = sin²(Δφ/2) + cos φ1 ⋅ cos φ2 ⋅ sin²(Δλ/2)
    a = math.sin(dlat / 2) ** 2 + math.cos(lat1_rad) * math.cos(lat2_rad) * math.sin(dlon / 2) ** 2
    # c = 2 ⋅ atan2( √a, √(1−a) )
    c = 2 * math.atan2(math.sqrt(a), math.sqrt(1 - a))
    
    return r * c  # Distance en km

def executerAvecDates(date_debut_str, date_fin_str):
    """
    Orchestre le processus complet de récupération, traitement et exportation des données météo.
    
    Cette fonction principale coordonne toutes les étapes du pipeline de collecte de données:
    1. Récupération intelligente des données avec gestion dynamique des plages de dates
       (ajuste automatiquement la taille des plages pour respecter les limites de l'API)
    2. Traitement et nettoyage des données récupérées
    3. Export des données en deux fichiers CSV:
       - dataFinal.csv: toutes les données de la période
       - dataPoitiers.csv: uniquement les stations dans un rayon de 100km autour de Poitiers
    
    La fonction utilise une stratégie de récupération adaptative: elle commence par des plages
    de 30 jours et réduit progressivement la taille si le nombre d'enregistrements dépasse
    la limite de l'API (10000). Cela permet d'optimiser le nombre de requêtes tout en
    garantissant la récupération complète des données.
    
    Paramètres d'entrée:
        date_debut_str (str): Date de début au format 'YYYY-MM-DD' (ex: '2025-01-01')
        date_fin_str (str): Date de fin au format 'YYYY-MM-DD' (ex: '2025-01-31')
    
    Paramètres de sortie:
        bool: True si l'exécution s'est terminée avec succès (données récupérées et exportées),
              False en cas d'erreur ou si aucune donnée n'a été récupérée.
    """
    try:
        # Convertit les strings en objets datetime
        date_debut = datetime.strptime(date_debut_str, "%Y-%m-%d")
        date_fin = datetime.strptime(date_fin_str, "%Y-%m-%d")
        # Ajoute 1 jour à la fin pour inclure les données du dernier jour (avant minuit)
        date_fin = date_fin + timedelta(days=1)
        
        print(f"Récupération des données du {date_debut.date()} au {date_fin.date()}", file=sys.stderr)
        
        max_enregistrements = 10000  # Limite de l'API par requête
        date_courante = date_debut
        toutes_donnees = []
        
        # Boucle principale: parcourt chaque jour
        while date_courante < date_fin:
            jours_delta = 30  # Commence avec une plage de 30 jours
            
            # Boucle interne: réduit la plage si elle contient trop de données
            while jours_delta > 0:
                # Calcule la fin de la plage
                date_courante_fin = date_courante + timedelta(days=jours_delta)
                if date_courante_fin > date_fin:
                    date_courante_fin = date_fin
                
                # Compte le nombre d'enregistrements pour cette plage
                comptage = obtenirComptageEntreDates(
                    date_courante.strftime("%Y-%m-%dT00:00:00Z"),
                    date_courante_fin.strftime("%Y-%m-%dT00:00:00Z")
                )
                
                # Si le nombre d'enregistrements est acceptable, les récupère
                if comptage <= max_enregistrements:
                    print(f"Plage retenue {date_courante.date()} - {date_courante_fin.date()} avec {comptage} enregistrements", file=sys.stderr)
                    donnees_lot = recupererDonneesEntreDates(
                        date_courante.strftime("%Y-%m-%dT00:00:00Z"),
                        date_courante_fin.strftime("%Y-%m-%dT00:00:00Z")
                    )
                    # extend() ajoute tous les éléments du lot à la liste
                    toutes_donnees.extend(donnees_lot)
                    # Avance à la fin de la plage pour la prochaine itération
                    date_courante = date_courante_fin
                    break  # Sort de la boucle interne
                else:
                    # Trop de données, divise la plage par 2 (binary search)
                    jours_delta //= 2
            else:
                # Si jours_delta devient 0, avance d'un seul jour
                date_courante += timedelta(days=1)
        
        print(f"Récupération totale d'enregistrements: {len(toutes_donnees)}", file=sys.stderr)
        
        if not toutes_donnees:
            print("Aucune donnée récupérée pour cette période.", file=sys.stderr)
            return False
        
        # Traite et nettoie les données
        df_final = traiterDonnees(toutes_donnees)
        
        # Sauvegarder les CSV dans le même répertoire que ce script
        repertoire_script = os.path.dirname(os.path.abspath(__file__))
        chemin_csv = os.path.join(repertoire_script, 'dataFinal.csv')
        # to_csv() exporte le dataframe en CSV avec UTF-8
        df_final.to_csv(chemin_csv, index=False, encoding='utf-8-sig')

        # Générer un CSV pour les données situées dans un rayon de 100 km autour de Poitiers
        if {'Latitude', 'Longitude'}.issubset(df_final.columns):
            df_geo = df_final.dropna(subset=['Latitude', 'Longitude']).copy()
            df_geo['distance_km_poitiers'] = df_geo.apply(
                lambda r: calculer_distance_km(float(r['Latitude']), float(r['Longitude']), POITIERS_LAT, POITIERS_LON),
                axis=1
            )
            df_poitiers = df_geo[df_geo['distance_km_poitiers'] <= 100]
            chemin_csv_poitiers = os.path.join(repertoire_script, 'dataPoitiers.csv')
            df_poitiers.to_csv(chemin_csv_poitiers, index=False, encoding='utf-8-sig')
        else:
            print("Colonnes Latitude/Longitude absentes : dataPoitiers.csv non généré", file=sys.stderr)
        
        print("Export CSV terminé avec succès", file=sys.stderr)
        return True
        
    except Exception as e:
        print(f"Erreur : {str(e)}", file=sys.stderr)
        return False

if __name__ == "__main__":
    if len(sys.argv) == 3:
        date_debut = sys.argv[1]
        date_fin = sys.argv[2]
        executerAvecDates(date_debut, date_fin)
    else:
        print("Utilisation : python API.py YYYY-MM-DD YYYY-MM-DD", file=sys.stderr)
        executerAvecDates("2025-01-01", "2025-01-31")
