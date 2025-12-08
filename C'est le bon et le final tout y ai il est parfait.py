# -*- coding: utf-8 -*-
"""
Created on Mon Dec  8 15:11:42 2025

@author: ccarraco
"""

# -*- coding: utf-8 -*-
"""
Scraping des établissements Onisep
Combine texte brut et tableau HTML selon la structure de la page
Supprime la première ligne de chaque DataFrame
Ajoute une colonne TYPE FORMATION
"""

import requests
from bs4 import BeautifulSoup
import pandas as pd

# ----------------------------
# Liste des URLs Onisep dans l'ordre demandé
# ----------------------------
urls = [
    "https://www.onisep.fr/ressources/univers-formation/formations/post-bac/but-reseaux-et-telecommunications-parcours-reseaux-operateurs-et-multimedia",
    "https://www.onisep.fr/ressources/univers-formation/formations/post-bac/but-reseaux-et-telecommunications-parcours-cybersecurite",
    "https://www.onisep.fr/ressources/univers-formation/formations/post-bac/but-science-des-donnees-parcours-exploration-et-modelisation-statistique",
    "https://www.onisep.fr/ressources/univers-formation/formations/post-bac/but-science-des-donnees-parcours-visualisation-conception-d-outils-decisionnels",
    "https://www.onisep.fr/ressources/univers-formation/formations/post-bac/but-metiers-du-multimedia-et-de-l-internet-parcours-creation-numerique",
    "https://www.onisep.fr/ressources/univers-formation/formations/post-bac/but-metiers-du-multimedia-et-de-l-internet-parcours-developpement-web-et-dispositifs-interactifs",
    "https://www.onisep.fr/ressources/univers-formation/formations/post-bac/but-informatique-parcours-administration-gestion-et-exploitation-des-donnees",
    "https://www.onisep.fr/ressources/univers-formation/formations/post-bac/but-informatique-parcours-realisation-d-applications-conception-developpement-validation"
]

# ----------------------------
# Dictionnaire url -> nom de formation
# ----------------------------
formation_names = {
    urls[0]: "BUT Réseaux et Télécommunications - Réseaux, opérateurs et multimédia",
    urls[1]: "BUT Réseaux et Télécommunications - Cybersécurité",
    urls[2]: "BUT Science des données - Exploration et modélisation statistique",
    urls[3]: "BUT Science des données - Visualisation et conception d’outils décisionnels",
    urls[4]: "BUT Métiers du multimédia et de l’internet - Création numérique",
    urls[5]: "BUT Métiers du multimédia et de l’internet - Développement web et dispositifs interactifs",
    urls[6]: "BUT Informatique - Administration, gestion et exploitation des données",
    urls[7]: "BUT Informatique - Réalisation d’applications : conception, développement et validation"
}

# ----------------------------
# Fonction pour le texte brut
# ----------------------------
def scrape_page_grouped_clean(url):
    response = requests.get(url)
    soup = BeautifulSoup(response.text, "html.parser")

    ou_section = None
    for h2 in soup.find_all(["h2", "h3"]):
        if "Où se former" in h2.text:
            ou_section = h2.find_next_sibling()
            break

    noms, villes, codes_postaux = [], [], []

    if ou_section:
        lines = [line.strip() for line in ou_section.get_text("\n", strip=True).split("\n") if line.strip()]
        start_index = 0
        for i in range(start_index, len(lines) - 2, 3):
            noms.append(lines[i])
            villes.append(lines[i+1])
            codes_postaux.append(lines[i+2])

    return pd.DataFrame({
        "Nom": noms,
        "Ville": villes,
        "Code postal": codes_postaux
    })

# ----------------------------
# Fonction pour le tableau HTML
# ----------------------------
def scrape_table_formation(url):
    resp = requests.get(url)
    soup = BeautifulSoup(resp.text, "html.parser")

    table = None
    for h2 in soup.find_all(["h2","h3"]):
        if "Où se former" in h2.text:
            table = h2.find_next("table")
            break

    if table is None:
        return pd.DataFrame(columns=["Nom", "Ville", "Code postal"])

    rows = []
    for tr in table.find_all("tr"):
        cols = tr.find_all(["td","th"])
        if len(cols) < 3:
            continue
        nom = cols[0].get_text(strip=True)
        ville = cols[1].get_text(strip=True)
        cp = cols[2].get_text(strip=True)
        rows.append({"Nom": nom, "Ville": ville, "Code postal": cp})

    return pd.DataFrame(rows)

# ----------------------------
# Fonction qui détecte la structure
# ----------------------------
def scrape_page(url):
    response = requests.get(url)
    soup = BeautifulSoup(response.text, "html.parser")

    table = None
    for h2 in soup.find_all(["h2","h3"]):
        if "Où se former" in h2.text:
            table = h2.find_next("table")
            break

    if table:
        return scrape_table_formation(url)
    else:
        return scrape_page_grouped_clean(url)

# ----------------------------
# Scraper toutes les pages
# ----------------------------
dfs = []
for url in urls:
    print(f"Scraping : {url}")
    df_page = scrape_page(url)

    # Supprimer la première ligne si le DataFrame n'est pas vide
    if not df_page.empty:
        df_page = df_page.drop(index=0).reset_index(drop=True)

    # Ajouter la colonne TYPE FORMATION
    df_page["TYPE FORMATION"] = formation_names[url]

    dfs.append(df_page)
    print(df_page)
    print("-" * 50)

# ----------------------------
# Combiner tous les DataFrames
# ----------------------------
df_final = pd.concat(dfs, ignore_index=True)

# Afficher le résultat final
print("=== DataFrame final combiné ===")
print(df_final)
