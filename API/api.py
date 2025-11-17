# %%
import requests
import pandas as pd

url_base = "https://public.opendatasoft.com/api/explore/v2.1/catalog/datasets/donnees-synop-essentielles-omm/records"

contenu = requests.get(url_base).json()

df = pd.json_normalize(contenu['results'])

# Carte Folium interactive
# Afficher la température moyenne par commune avec des marqueurs Folium :
# ▪ couleur = en fonction de classe de température
# ▪ info-bulle : nom de la commune et température moyenne.



# %%
