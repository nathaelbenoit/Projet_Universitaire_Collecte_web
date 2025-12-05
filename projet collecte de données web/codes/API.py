import requests
from datetime import datetime, timedelta
import pandas as pd
import sys

url_base = "https://public.opendatasoft.com/api/explore/v2.1/catalog/datasets/donnees-synop-essentielles-omm/records"

def obtenirComptageEntreDates(date_debut, date_fin):
    params = {
        "limit": 0,
        "where": f"date >= '{date_debut}' AND date < '{date_fin}'"
    }
    response = requests.get(url_base, params=params)
    if response.status_code != 200:
        print("Erreur API lors du comptage")
        return 0
    data = response.json()
    return data.get("total_count", 0)

def recupererDonneesEntreDates(date_debut, date_fin):
    resultats = []
    decalage = 0
    limite = 100
    while True:
        params = {
            "limit": limite,
            "offset": decalage,
            "where": f"date >= '{date_debut}' AND date < '{date_fin}'"
        }
        response = requests.get(url_base, params=params)
        if response.status_code != 200:
            print(f"Erreur API pour {date_debut} - {date_fin}, décalage {decalage}")
            break
        lot = response.json().get("results", [])
        if not lot:
            break
        resultats.extend(lot)
        decalage += limite
        if len(lot) < limite:
            break
    return resultats

def traiterDonnees(donnees):
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

    colonnes_existantes = [col for col in colonnes_a_supprimer if col in df_propre.columns]
    df_propre = df_propre.drop(columns=colonnes_existantes)

    return df_propre

def executerAvecDates(date_debut_str, date_fin_str):
    try:
        date_debut = datetime.strptime(date_debut_str, "%Y-%m-%d")
        date_fin = datetime.strptime(date_fin_str, "%Y-%m-%d")
        date_fin = date_fin + timedelta(days=1)
        
        print(f"Récupération des données du {date_debut.date()} au {date_fin.date()}", file=sys.stderr)
        
        max_enregistrements = 10000
        date_courante = date_debut
        toutes_donnees = []
        
        while date_courante < date_fin:
            jours_delta = 30
            while jours_delta > 0:
                date_courante_fin = date_courante + timedelta(days=jours_delta)
                if date_courante_fin > date_fin:
                    date_courante_fin = date_fin
                
                comptage = obtenirComptageEntreDates(
                    date_courante.strftime("%Y-%m-%dT00:00:00Z"),
                    date_courante_fin.strftime("%Y-%m-%dT00:00:00Z")
                )
                
                if comptage <= max_enregistrements:
                    print(f"Plage retenue {date_courante.date()} - {date_courante_fin.date()} avec {comptage} enregistrements", file=sys.stderr)
                    donnees_lot = recupererDonneesEntreDates(
                        date_courante.strftime("%Y-%m-%dT00:00:00Z"),
                        date_courante_fin.strftime("%Y-%m-%dT00:00:00Z")
                    )
                    toutes_donnees.extend(donnees_lot)
                    date_courante = date_courante_fin
                    break
                else:
                    jours_delta //= 2
            else:
                date_courante += timedelta(days=1)
        
        print(f"Récupération totale d'enregistrements: {len(toutes_donnees)}", file=sys.stderr)
        
        if not toutes_donnees:
            print("Aucune donnée récupérée pour cette période.", file=sys.stderr)
            return False
        
        df_final = traiterDonnees(toutes_donnees)
        df_final.to_csv("dataFinal.csv", index=False, encoding='utf-8-sig')
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
