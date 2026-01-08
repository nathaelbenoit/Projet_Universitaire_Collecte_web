# -*- coding: utf-8 -*-
"""
Scraping des établissements Onisep
HTML complet avec :
- Tableau récapitulatif par ville (DataTables)
- Tableau complet des établissements (DataTables)
- Carte interactive Folium
- Filtre par type de formation
- Nombre total d'établissements
- Design moderne bleu avec cartes élargies
"""

import requests
from bs4 import BeautifulSoup
import pandas as pd
import folium
from geopy.geocoders import Nominatim
from geopy.exc import GeocoderTimedOut
import time

# ----------------------------
# URLs Onisep
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

formation_names = {
    urls[0]: "BUT R&T - Réseaux, opérateurs et multimédia",
    urls[1]: "BUT R&T - Cybersécurité",
    urls[2]: "BUT SD - Exploration et modélisation",
    urls[3]: "BUT SD - Visualisation décisionnelle",
    urls[4]: "BUT MMI - Création numérique",
    urls[5]: "BUT MMI - Développement web",
    urls[6]: "BUT Informatique - Administration données",
    urls[7]: "BUT Informatique - Réalisation applications"
}

# ----------------------------
# Scraping
# ----------------------------
def scrape_page(url):
    response = requests.get(url, timeout=10)
    soup = BeautifulSoup(response.text, "html.parser")

    for h2 in soup.find_all(["h2", "h3"]):
        if "Où se former" in h2.text:
            table = h2.find_next("table")
            if table:
                rows = []
                for tr in table.find_all("tr"):
                    cols = tr.find_all("td")
                    if len(cols) >= 3:
                        rows.append({
                            "Nom": cols[0].get_text(strip=True),
                            "Ville": cols[1].get_text(strip=True),
                            "Code postal": cols[2].get_text(strip=True)
                        })
                return pd.DataFrame(rows)
    return pd.DataFrame(columns=["Nom", "Ville", "Code postal"])

dfs = []
for url in urls:
    df = scrape_page(url)
    if not df.empty:
        df["TYPE FORMATION"] = formation_names[url]
        dfs.append(df)

df_final = pd.concat(dfs, ignore_index=True)

# ----------------------------
# Géocodage optimisé avec cache
# ----------------------------
geolocator = Nominatim(user_agent="onisep_map")
geo_cache = {}

def geocode_with_retry(adresse, retries=3):
    for _ in range(retries):
        try:
            return geolocator.geocode(adresse, timeout=10)
        except GeocoderTimedOut:
            time.sleep(1)
    return None

df_final["Latitude"] = None
df_final["Longitude"] = None

for i, row in df_final.iterrows():  # <-- colon important !
    ville = row["Ville"]
    cp = row["Code postal"]

    if ville not in geo_cache:
        loc = geocode_with_retry(f"{cp} {ville}, France")
        geo_cache[ville] = loc
        time.sleep(1)
    else:
        loc = geo_cache[ville]

    if loc:
        df_final.at[i, "Latitude"] = loc.latitude
        df_final.at[i, "Longitude"] = loc.longitude

# ----------------------------
# Statistiques
# ----------------------------
total_etablissements = len(df_final)

ville_counts = (
    df_final.groupby("Ville")
    .size()
    .reset_index(name="Nombre de formations")
    .sort_values(by="Nombre de formations", ascending=False)
)

formations_uniques = sorted(df_final["TYPE FORMATION"].unique())

# ----------------------------
# Carte Folium
# ----------------------------
m = folium.Map(location=[46.6, 2.5], zoom_start=6)

for _, row in df_final.iterrows():
    if pd.notnull(row["Latitude"]):
        folium.Marker(
            [row["Latitude"], row["Longitude"]],
            popup=f"{row['Nom']}<br>{row['TYPE FORMATION']}"
        ).add_to(m)

map_html = m._repr_html_()

# ----------------------------
# Tableaux HTML
# ----------------------------
ville_table_html = ville_counts.to_html(
    index=False, classes="display", table_id="table_villes"
)

etab_table_html = df_final.to_html(
    index=False, classes="display", table_id="table_etablissements"
)

# ----------------------------
# Options du filtre formation
# ----------------------------
options_formations = '<option value="">Toutes les formations</option>'
for f in formations_uniques:
    options_formations += f'<option value="{f}">{f}</option>'

# ----------------------------
# Page HTML finale
# ----------------------------
html_content = """
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Établissements Onisep</title>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

<style>
body {
    font-family: "Segoe UI", Roboto, Arial, sans-serif;
    background: #e6f0fa;
    margin: 0;
    color: #1a1a1a;
}

.container {
    max-width: 1400px; /* élargi les cartes */
    margin: auto;
    padding: 30px;
}

h1 {
    text-align: center;
    color: #0d3b66;
    margin-bottom: 10px;
}

.subtitle {
    text-align: center;
    color: #1e5f99;
    margin-bottom: 30px;
}

.card {
    background: #ffffff;
    padding: 30px;
    margin-bottom: 30px;
    border-radius: 12px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.08);
    border-left: 8px solid #1e5f99;
}

.filter {
    text-align: center;
    margin-bottom: 20px;
}

.filter select {
    padding: 8px 14px;
    font-size: 14px;
    border: 1px solid #1e5f99;
    border-radius: 5px;
    background: #f0f6ff;
    color: #0d3b66;
}

.stats {
    text-align: center;
    font-size: 18px;
    margin-bottom: 20px;
    color: #0d3b66;
}

table.dataTable {
    width: 100% !important;
}

h2 {
    color: #1e5f99;
    margin-bottom: 15px;
}
</style>
</head>

<body>

<div class="container">

<h1>Établissements Onisep</h1>
<div class="subtitle">
BUT Informatique • R&T • Science des données • MMI
</div>

<div class="card stats">
<strong>Nombre total d’établissements :</strong> __TOTAL__
</div>

<div class="card filter">
<label for="formation_filter"><strong>Filtrer par type de formation :</strong></label>
<select id="formation_filter">
__OPTIONS_FORMATIONS__
</select>
</div>

<div class="card">
<h2>Nombre de formations par ville</h2>
__VILLE_TABLE__
</div>

<div class="card">
<h2>Tableau complet des établissements</h2>
__ETAB_TABLE__
</div>

<div class="card">
<h2>Carte interactive</h2>
__MAP__
</div>

</div>

<script>
$(document).ready(function() {
    var table = $('#table_etablissements').DataTable({
        pageLength: 25,
        language: {
            url: "https://cdn.datatables.net/plug-ins/1.13.6/i18n/fr-FR.json"
        }
    });

    $('#table_villes').DataTable({
        order: [[1, 'desc']],
        language: {
            url: "https://cdn.datatables.net/plug-ins/1.13.6/i18n/fr-FR.json"
        }
    });

    $('#formation_filter').on('change', function () {
        table.column(3).search(this.value).draw();
    });
});
</script>

</body>
</html>
"""

# Remplacement des variables
html_content = (
    html_content
    .replace("__VILLE_TABLE__", ville_table_html)
    .replace("__ETAB_TABLE__", etab_table_html)
    .replace("__MAP__", map_html)
    .replace("__OPTIONS_FORMATIONS__", options_formations)
    .replace("__TOTAL__", str(total_etablissements))
)

with open("etablissements_onisep_complet.html", "w", encoding="utf-8") as f:
    f.write(html_content)

print("✅ Page HTML générée")
