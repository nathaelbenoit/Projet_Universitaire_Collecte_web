# %%
import requests
import pandas as pd

url_base = "https://public.opendatasoft.com/api/explore/v2.1/catalog/datasets/donnees-synop-essentielles-omm/records"
    
contenu = requests.get(url_base).json()
print(contenu["total_count"])
resultat_final = []

for i in range(0, 10000, 100):
    temp = requests.get(url_base + "?limit=100&offset=" + str(i)).json()
    resultat_final += temp["results"]
print(len(resultat_final))

data = pd.DataFrame(resultat_final)


# %%