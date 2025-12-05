# Autre site avec des infos grace a l'insep: 

# https://www.onisep.fr/ressources/univers-formation/formations/post-bac/but-reseaux-et-telecommunications-parcours-reseaux-operateurs-et-multimedia (RT)
# https://www.onisep.fr/ressources/univers-formation/formations/post-bac/but-science-des-donnees-parcours-exploration-et-modelisation-statistique (SD EMS)
# https://www.onisep.fr/ressources/univers-formation/formations/post-bac/but-science-des-donnees-parcours-visualisation-conception-d-outils-decisionnels (SD VCOD)
# https://www.onisep.fr/ressources/univers-formation/formations/post-bac/but-metiers-du-multimedia-et-de-l-internet-parcours-creation-numerique (MMI)
# https://www.onisep.fr/ressources/univers-formation/formations/post-bac/but-informatique-parcours-administration-gestion-et-exploitation-des-donnees (INFO)

import time
import re
import requests
from bs4 import BeautifulSoup
import pandas as pd
import matplotlib.pyplot as plt
from urllib.parse import quote_plus

# Liste d'exemples d'URLs Onisep (à adapter / étendre)
PROGRAM_URLS = [
    "https://www.onisep.fr/ressources/univers-formation/formations/post-bac/but-reseaux-et-telecommunications-parcours-reseaux-operateurs-et-multimedia",
    "https://www.onisep.fr/ressources/univers-formation/formations/post-bac/but-science-des-donnees-parcours-exploration-et-modelisation-statistique",
    "https://www.onisep.fr/ressources/univers-formation/formations/post-bac/but-science-des-donnees-parcours-visualisation-conception-d-outils-decisionnels",
    "https://www.onisep.fr/ressources/univers-formation/formations/post-bac/but-metiers-du-multimedia-et-de-l-internet-parcours-creation-numerique",
    "https://www.onisep.fr/ressources/univers-formation/formations/post-bac/but-informatique-parcours-administration-gestion-et-exploitation-des-donnees"
]

HEADERS = {"User-Agent": "Mozilla/5.0 (compatible; ProjetCollecteWeb/1.0)"}

def fetch_soup(url):
    r = requests.get(url, headers=HEADERS, timeout=15)
    r.raise_for_status()
    return BeautifulSoup(r.text, "html.parser")

def extract_locations_from_soup(soup):
    # Tentatives multiples pour trouver la zone contenant "Lieu(s) de formation" ou "Lieux"
    texts = []
    # 1) chercher tous les <p> ou <li> contenant 'Lieu' ou 'Lieu(x)'
    candidates = soup.find_all(lambda tag: tag.name in ["p", "li", "div"] and tag.get_text(strip=True) and re.search(r"\bLieu", tag.get_text(), re.I))
    for c in candidates:
        texts.append(c.get_text(" ", strip=True))
    # 2) chercher les titres puis le paragraphe suivant (ex: <h3>Lieu</h3>)
    for header in soup.find_all(re.compile("^h[1-6]$")):
        if re.search(r"\blieu", header.get_text(), re.I):
            nxt = header.find_next_sibling()
            if nxt:
                texts.append(nxt.get_text(" ", strip=True))
    # 3) extraction heuristique : rechercher phrases contenant 'Formation' et 'Lieu' sur la page entière
    fulltext = soup.get_text(" ", strip=True)
    matches = re.findall(r"(?:(?:Lieu[s]?)[:\s].{5,200})", fulltext, re.I)
    for m in matches:
        texts.append(m)
    # Nettoyage et retour unique
    cleaned = []
    for t in texts:
        tt = re.sub(r"\s+", " ", t).strip()
        if tt and tt not in cleaned:
            cleaned.append(tt)
    return cleaned

def normalize_city_text(text):
    # heuristique simple : garder la portion après 'Lieu' ou ':' et couper si longue
    m = re.search(r"(Lieu[s]?\s*[:\-]?\s*)(.+)", text, re.I)
    if m:
        candidate = m.group(2)
    else:
        candidate = text
    # couper sur phrases longues
    candidate = candidate.split(".")[0]
    candidate = candidate.split(";")[0]
    candidate = candidate.strip()
    return candidate

def build_dataframe(results_per_program):
    rows = []
    for prog, extracted in results_per_program.items():
        for raw in extracted:
            rows.append({
                "programme": prog,
                "lieu_text": raw,
                # pas de géocodage -> on conserve le texte normalisé comme 'city'
                "city": raw
            })
    return pd.DataFrame(rows)

def make_map_html(df, output_html="programs_map.html"):
    # Génère un HTML simple avec des liens Google Maps (pas de folium)
    dfc = df.dropna(subset=["city"])
    if dfc.empty:
        return None
    lines = [
        "<!doctype html>",
        "<html><head><meta charset='utf-8'><title>Carte (liens) des formations</title></head><body>",
        "<h1>Liens Google Maps pour les lieux extraits</h1>",
        "<ul>"
    ]
    for _, r in dfc.iterrows():
        query = quote_plus(r["city"] + ", France")
        url = f"https://www.google.com/maps/search/?api=1&query={query}"
        lines.append(f"<li><strong>{r['programme']}</strong> — {r['city']} — <a href='{url}' target='_blank'>Voir sur Google Maps</a></li>")
    lines.extend(["</ul>", "</body></html>"])
    with open(output_html, "w", encoding="utf-8") as f:
        f.write("\n".join(lines))
    return output_html

def make_barplot(df, output_png="plot_programs_per_city.png"):
    dfc = df.copy()
    dfc["city_display"] = dfc["city"].fillna("Inconnu")
    counts = dfc["city_display"].value_counts().head(20)
    plt.figure(figsize=(10,6))
    plt.barh(range(len(counts)), counts.values, color="tab:blue")
    plt.yticks(range(len(counts)), counts.index)
    plt.xlabel("Nombre de formations")
    plt.title("Top lieux / villes pour les formations scrappées")
    plt.gca().invert_yaxis()
    plt.tight_layout()
    plt.savefig(output_png)
    plt.close()
    return output_png

def main():
    results_per_program = {}
    for url in PROGRAM_URLS:
        try:
            soup = fetch_soup(url)
        except Exception as e:
            print(f"Erreur fetch {url}: {e}")
            continue
        title_tag = soup.find("h1")
        programme_title = title_tag.get_text(strip=True) if title_tag else url
        extracted_texts = extract_locations_from_soup(soup)
        normalized = [normalize_city_text(t) for t in extracted_texts] if extracted_texts else []
        normalized = list(dict.fromkeys(normalized))
        results_per_program[programme_title] = normalized
        time.sleep(1)
    df = build_dataframe(results_per_program)
    df.to_csv("programs_locations.csv", index=False, encoding="utf-8")
    map_file = make_map_html(df, "programs_map.html")
    plot_file = make_barplot(df, "programs_per_city.png")
    print("Terminé. Fichiers :", "programs_locations.csv,", map_file, ",", plot_file)

if __name__ == "__main__":
    main()


