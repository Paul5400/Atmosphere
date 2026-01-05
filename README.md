# Atmosphere – Interopérabilité (PHP/XSL/JS)

Page PHP dynamique qui croise plusieurs APIs ouvertes pour décider s’il faut prendre la voiture (météo, trafic, Covid eaux usées, qualité de l’air, géoloc IP) et affiche tout dans une page unique avec XSLT, Leaflet et Chart.js.

## Prérequis
- PHP 8.x avec extension DOM/XSL activée.
- Accès réseau sortant. Sur webetu : proxy `tcp://127.0.0.1:8080` déjà pris en compte dans le code.

## Lancer en local
```bash
cd Interop
php -S localhost:8000
```
Puis ouvrir http://localhost:8000/atmosphere.php

## Fonctionnement par exigence du sujet
- **Géolocalisation IP (XML)** : `http://ip-api.com/xml/{ip}`. On lit `X-Forwarded-For`/`REMOTE_ADDR`, et on force une IP de test à Nancy en local. Si l’IP n’est pas dans le 54, on se replie sur l’IUT (géocodée via Nominatim).
- **Météo** : appel Open-Meteo (`temperature_2m`, `precipitation`, `snowfall`, `windspeed_10m`), agrégation matin/midi/soir en XML, transformation via `Interop/meteo.xsl` en fragment HTML.
- **Trafic Grand Nancy** : JSON Waze CIFS `https://carto.g-ny.eu/data/cifs/cifs_waze_v2.json`, rendu Leaflet centré sur la géoloc.
- **Covid (eaux usées)** : dataset data.gouv `651a82516edc589b4f6a0354`, ressource CSV “indicateurs”, extraction Maxéville/Nancy, affichage des 8 derniers points avec Chart.js.
- **Qualité de l’air** : URL Atmo Grand Est `https://admindata.atmo-france.org/api/data/112/?code_zone=54395`, tentative de lecture de l’indice ATMO sinon valeur par défaut.
- **Adresse fixe sur la carte** : géocodage Nominatim de l’IUT Charlemagne.
- **Liens sources** : toutes les URLs d’API + dépôt Git listés en bas de page.

## Arborescence utile
- `Interop/atmosphere.php` : logique PHP (requêtes API, fallback, décision voiture).
- `Interop/meteo.xsl` : XSL pour transformer le XML météo en bloc HTML.
- `Interop/style.css` : styles, media-queries.
- `Interop/script.js` : Leaflet + Chart.js côté client.

## Déploiement webetu
- Mettre le projet dans `Interop/` avec `atmosphere.php` à la racine.
- Vérifier que le proxy webetu est accessible (`tcp://127.0.0.1:8080`). Sinon, retirer le proxy dans le code.

## Notes de robustesse
- Chaque appel réseau est protégé (fallbacks locaux météo/air/Covid, repli géoloc sur l’IUT).
- Dates affichées (dernière mesure Covid) pour vérifier la fraîcheur des données.

## Auteurs
ANDRIEU Paul  
LAMBERT Valentino  
FRANOUX Noé  
CARETTE Robin  
DWM-2
