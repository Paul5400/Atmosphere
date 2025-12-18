<?php
/**
 * Projet Atmosphere - Interopérabilité des données
 * 
 */

// Configuration du proxy pour webetu (www-cache:3128)
// On teste si le proxy est nécessaire (pour le local)
$proxy = 'tcp://127.0.0.1:8080'; // Valeur par défaut IUT (ou www-cache:3128)
// Si on est en local sans proxy, on peut désactiver ou changer
$opts = array(
    'http' => array(
        'proxy' => $proxy,
        'request_fulluri' => true,
        'timeout' => 5
    ),
    'ssl' => array(
        'verify_peer' => false,
        'verify_peer_name' => false
    )
);

// On tente une petite requête pour voir si le proxy répond, sinon on désactive
$test_context = @stream_context_create($opts);
if (@file_get_contents("http://google.com", false, $test_context, 0, 1) === false) {
    // Si échec, on tente sans proxy
    $opts['http']['proxy'] = null;
}

$context = stream_context_create($opts);
libxml_set_streams_context($context); // Pour simplexml_load_file et XSLT

/**
 * Récupère l'adresse IP du client
 */
function get_client_ip()
{
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}

$client_ip = get_client_ip();
// Pour le développement local, si l'IP est locale, on utilise une IP de test à Nancy
if ($client_ip == '127.0.0.1' || $client_ip == '::1') {
    $client_ip = '78.125.143.125'; // IP Nancy (exemple)
}

// Géolocalisation via IP-API (XML)
$geo_url = "http://ip-api.com/xml/" . $client_ip;
$geo_xml_str = file_get_contents($geo_url, false, $context);
$geo_data = simplexml_load_string($geo_xml_str);

// Coordonnées par défaut (IUT Charlemagne, Nancy) si la géo échoue ou n'est pas à Nancy
$lat = 48.6815;
$lon = 6.1737;
$city = "Nancy (IUT Charlemagne)";
$zip = "54000";

if ($geo_data && $geo_data->status == 'success') {
    // On vérifie si on est dans le 54 (Meurthe-et-Moselle) ou proche de Nancy
    if (strpos((string) $geo_data->zip, '54') === 0 || (string) $geo_data->city == 'Nancy') {
        $lat = (float) $geo_data->lat;
        $lon = (float) $geo_data->lon;
        $city = (string) $geo_data->city;
        $zip = (string) $geo_data->zip;
    }
}

// Simulation de données Météo XML (en attendant une API réelle conforme au TD)
$meteo_xml_str = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<meteo>
    <prevision>
        <moment>Matin</moment>
        <temp>2</temp>
        <pluie>0</pluie>
        <neige>5</neige>
        <vitesse_vent>15</vitesse_vent>
        <description>Neige faible</description>
    </prevision>
    <prevision>
        <moment>Midi</moment>
        <temp>4</temp>
        <pluie>10</pluie>
        <neige>0</neige>
        <vitesse_vent>20</vitesse_vent>
        <description>Averses de pluie</description>
    </prevision>
    <prevision>
        <moment>Soir</moment>
        <temp>1</temp>
        <pluie>0</pluie>
        <neige>0</neige>
        <vitesse_vent>55</vitesse_vent>
        <description>Ciel degage, vent fort</description>
    </prevision>
</meteo>
XML;

// Transformation XSLT
$xml = new DOMDocument();
$xml->loadXML($meteo_xml_str);

$xsl = new DOMDocument();
$xsl->load('meteo.xsl');

$proc = new XSLTProcessor();
$proc->importStyleSheet($xsl);
$meteo_html = $proc->transformToXML($xml);

// 1. Récupération du trafic (Grand Nancy)
// On utilise l'URL officielle du Grand Nancy (.eu au lieu de .org pour éviter les redirections)
$traffic_url = "https://carto.g-ny.eu/data/cifs/cifs_waze_v2.json";
$traffic_json = file_get_contents($traffic_url, false, $context);
$traffic_data = json_decode($traffic_json, true);

// 2. Récupération des données Covid (SARS-CoV-2 dans les égouts - SUM'Eau)
$covid_dataset_url = "https://www.data.gouv.fr/api/1/datasets/651a82516edc589b4f6a0354/";
$covid_json = file_get_contents($covid_dataset_url, false, $context);
$covid_info = json_decode($covid_json, true);
$covid_resource_url = "";
if (isset($covid_info['resources'])) {
    foreach ($covid_info['resources'] as $res) {
        if (stripos($res['title'], 'indicateurs') !== false && $res['format'] == 'csv') {
            $covid_resource_url = $res['latest'];
            break;
        }
    }
}

// Parsing du CSV Covid (Format LARGE : semaine;Station1;Station2;...)
$covid_data_points = [];
if ($covid_resource_url) {
    $csv_content = file_get_contents($covid_resource_url, false, $context);
    $rows = explode("\n", $csv_content);
    $header_line = array_shift($rows);
    $header = str_getcsv($header_line, ";");

    // On cherche l'index de la colonne correspondant à Maxéville ou Nancy
    $col_index = -1;
    foreach ($header as $idx => $col_name) {
        $clean_col = trim($col_name, '" ');
        if (stripos($clean_col, 'MAXEVILLE') !== false || stripos($clean_col, 'NANCY') !== false) {
            $col_index = $idx;
            break;
        }
    }

    if ($col_index !== -1) {
        foreach ($rows as $row) {
            if (empty(trim($row)))
                continue;
            $data = str_getcsv($row, ";");
            if (count($data) > $col_index) {
                $raw_val = trim($data[$col_index], '" ');
                if ($raw_val !== "NA" && $raw_val !== "") {
                    $covid_data_points[] = [
                        'date' => trim($data[0], '" '),
                        'value' => (float) str_replace(',', '.', $raw_val)
                    ];
                }
            }
        }
    }
    // On garde les 8 derniers points pour une meilleure lisibilité
    $covid_data_points = array_slice($covid_data_points, -8);
}
// 3. Récupération de la qualité de l'air (Atmo Grand Est)
// Nancy code INSEE: 54395. On utilise une URL simplifiée si possible ou un mock réaliste.
$air_quality_url = "https://admindata.atmo-france.org/api/data/112/?code_zone=54395"; // URL théorique
// Pour le projet, on va simuler un indice ATMO (1: Bon, 6: Mauvais)
$air_quality_index = 2; // Bon
$air_quality_label = "Bon";

// 4. Géolocalisation d'une adresse fixe (IUT Charlemagne)
$address = "2 ter Boulevard Charlemagne, 54000 Nancy";
$address_url = "https://nominatim.openstreetmap.org/search?q=" . urlencode($address) . "&format=json&limit=1";
$address_opts = [
    "http" => [
        "method" => "GET",
        "header" => [
            "User-Agent: AtmosphereProject/1.0",
            "proxy" => "tcp://127.0.0.1:8080"
        ]
    ]
];
$address_context = stream_context_create($address_opts);
$address_json = file_get_contents($address_url, false, $address_context);
$address_data = json_decode($address_json, true);
$fixed_lat = 48.6815;
$fixed_lon = 6.1737;
if (!empty($address_data)) {
    $fixed_lat = $address_data[0]['lat'];
    $fixed_lon = $address_data[0]['lon'];
}

// 5. Logique de décision (Prendre la voiture ou non ?)
$reasons = [];
$should_drive = true;

// Condition Météo (basée sur le XML simulé ou réel)
$xml_obj = simplexml_load_string($meteo_xml_str);
foreach ($xml_obj->prevision as $prev) {
    if ((float) $prev->temp < 3)
        $reasons[] = "Températures très froides prévues.";
    if ((float) $prev->pluie > 5)
        $reasons[] = "Pluie forte prévue.";
    if ((float) $prev->neige > 0)
        $reasons[] = "Risque de neige.";
    if ((float) $prev->vitesse_vent > 50)
        $reasons[] = "Vent violent prévu.";
}

// Condition Trafic
$incident_count = isset($traffic_data['incidents']) ? count($traffic_data['incidents']) : 0;
if ($incident_count > 5) {
    $reasons[] = "Trafic dense avec $incident_count incidents signalés.";
}

// Condition Air
if ($air_quality_index > 4) {
    $reasons[] = "Qualité de l'air médiocre (Indice $air_quality_index).";
    $should_drive = false; // On déconseille en cas de pollution pour ne pas aggraver
}

if (count($reasons) > 2)
    $should_drive = false;

// 6. Liens API pour affichage
$api_links = [
    "Géo IP" => $geo_url,
    "Trafic" => $traffic_url,
    "Covid" => $covid_dataset_url,
    "Qualité de l'air" => "https://www.atmo-grandest.eu/",
    "Adresse Géocodage" => $address_url
];
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Atmosphere - Décider de prendre sa voiture</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <header>
        <h1>Atmosphere</h1>
        <p>Conditions en temps réel à <strong><?php echo htmlspecialchars($city); ?></strong></p>
        <p class="date-header">Mis à jour le <?php echo date('d/m/Y à H:i'); ?></p>
    </header>

    <main>
        <section id="verdict" class="<?php echo $should_drive ? 'drive-yes' : 'drive-no'; ?>">
            <h2>Faut-il utiliser sa voiture ?</h2>
            <div class="decision-box">
                <p class="main-decision">
                    <?php echo $should_drive ? "OUI, les conditions sont favorables." : "NON, privilégiez les transports en commun."; ?>
                </p>
                <?php if (!empty($reasons)): ?>
                    <ul class="reasons-list">
                        <?php foreach (array_unique($reasons) as $reason): ?>
                            <li><?php echo htmlspecialchars($reason); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </section>

        <section id="meteo">
            <?php echo $meteo_html; ?>
        </section>

        <section id="geo-info">
            <h2>Géolocalisation</h2>
            <p>Votre IP : <?php echo htmlspecialchars($client_ip); ?></p>
            <p>Position : <?php echo $lat; ?>, <?php echo $lon; ?> (<?php echo htmlspecialchars($zip); ?>)</p>
            <p>Source API Géo : <a
                    href="<?php echo htmlspecialchars($geo_url); ?>"><?php echo htmlspecialchars($geo_url); ?></a></p>
        </section>

        <section id="air-quality">
            <h2>Qualité de l'air</h2>
            <p>Indice ATMO à Nancy : <strong><?php echo $air_quality_index; ?>
                    (<?php echo $air_quality_label; ?>)</strong></p>
        </section>

        <section id="map-container">
            <h2>Difficultés de circulation</h2>
            <div id="map" style="height: 300px; border-radius: 12px;"></div>
        </section>

        <section id="covid-chart">
            <h2>Évolution Covid (Sras dans les égouts)</h2>
            <div class="chart-wrapper">
                <canvas id="covidChart"></canvas>
            </div>
        </section>

        <section id="sources">
            <h2>Sources des données</h2>
            <ul>
                <?php foreach ($api_links as $name => $url): ?>
                    <li><?php echo htmlspecialchars($name); ?> : <a
                            href="<?php echo htmlspecialchars($url); ?>"><?php echo htmlspecialchars($url); ?></a></li>
                <?php endforeach; ?>
            </ul>
        </section>
    </main>

    <!-- Inclusion des bibliothèques externes -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // On passe les données PHP au JavaScript
        const trafficData = <?php echo json_encode($traffic_data); ?>;
        const covidDataPoints = <?php echo json_encode($covid_data_points); ?>;
        const userPos = [<?php echo $lat; ?>, <?php echo $lon; ?>];
        const fixedPos = [<?php echo $fixed_lat; ?>, <?php echo $fixed_lon; ?>];
    </script>
    <script src="script.js"></script>

    <footer>
        <p>Projet Interopérabilité - IUT Charlemagne</p>
    </footer>
</body>

</html>