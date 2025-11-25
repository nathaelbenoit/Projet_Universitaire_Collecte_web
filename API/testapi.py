#%%
import requests
from datetime import datetime, timedelta
import csv

url_base = "https://public.opendatasoft.com/api/explore/v2.1/catalog/datasets/donnees-synop-essentielles-omm/records"

def get_count_between_dates(start_date, end_date):
    params = {
        "limit": 0,  # On ne récupère pas les données, juste le comptage
        "where": f"date >= '{start_date}' AND date < '{end_date}'"
    }
    response = requests.get(url_base, params=params)
    if response.status_code != 200:
        print("Erreur API lors du comptage")
        return 0
    data = response.json()
    return data.get("total_count", 0)  # total_count retourne le nombre total d’enregistrements

def fetch_data_between_dates(start_date, end_date):
    results = []
    offset = 0
    limit = 100
    while True:
        params = {
            "limit": limit,
            "offset": offset,
            "where": f"date >= '{start_date}' AND date < '{end_date}'"
        }
        response = requests.get(url_base, params=params)
        if response.status_code != 200:
            print(f"Erreur API pour {start_date} - {end_date}, offset {offset}")
            break
        batch = response.json().get("results", [])
        if not batch:
            break
        results.extend(batch)
        offset += limit
        if len(batch) < limit:
            break
    return results

# Exemple d’ajustement dynamique de la plage temporelle
start_date_obj = datetime(2025, 1, 1)
end_total_obj = datetime(2025, 1, 31)
max_records = 10000
current_start = start_date_obj

all_data = []

while current_start < end_total_obj:
    # Commencer avec une plage maximale initiale (ex: 30 jours)
    delta_days = 30
    while delta_days > 0:
        current_end = current_start + timedelta(days=delta_days)
        if current_end > end_total_obj:
            current_end = end_total_obj

        count = get_count_between_dates(current_start.strftime("%Y-%m-%dT00:00:00Z"),
                                        current_end.strftime("%Y-%m-%dT00:00:00Z"))

        if count <= max_records:
            print(f"Plage retenue {current_start.date()} - {current_end.date()} avec {count} enregistrements")
            batch_data = fetch_data_between_dates(current_start.strftime("%Y-%m-%dT00:00:00Z"),
                                                  current_end.strftime("%Y-%m-%dT00:00:00Z"))
            all_data.extend(batch_data)
            current_start = current_end
            break
        else:
            delta_days //= 2  # Réduire la plage pour ne pas dépasser la limite
    else:
        # Si delta_days tombe à 0, on avance d’un jour pour éviter boucle infinie
        current_start += timedelta(days=1)

print(f"Récupération totale d'enregistrements: {len(all_data)}")

with open("data.csv", "w", newline="") as f:
    writer = csv.writer(f)
    writer.writerow(all_data)




# %%
