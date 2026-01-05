<?php
$proxy = 'tcp://127.0.0.1:8080';
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

$test_context = @stream_context_create($opts);
if (@file_get_contents("http://google.com", false, $test_context, 0, 1) === false) {
    $opts['http']['proxy'] = null;
}

$context = stream_context_create($opts);
libxml_set_streams_context($context);

function safe_get(string $url, $context): ?string
{
    $content = @file_get_contents($url, false, $context);
    return $content === false ? null : $content;
}

function get_client_ip(): string
{
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    }
    return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
}

function geocode_address(string $address, array $base_opts): array
{
    $address_opts = $base_opts;
    $headers = array("User-Agent: AtmosphereProject/1.0");
    if (!empty($address_opts['http']['header'])) {
        $headers[] = $address_opts['http']['header'];
    }
    $address_opts['http']['header'] = implode("\r\n", $headers);
    $address_context = stream_context_create($address_opts);
    $url = "https://nominatim.openstreetmap.org/search?q=" . urlencode($address) . "&format=json&limit=1";
    $json = safe_get($url, $address_context);
    $lat = 48.6815;
    $lon = 6.1737;
    if ($json) {
        $data = json_decode($json, true);
        if (!empty($data[0]['lat']) && !empty($data[0]['lon'])) {
            $lat = (float) $data[0]['lat'];
            $lon = (float) $data[0]['lon'];
        }
    }
    return array('lat' => $lat, 'lon' => $lon, 'url' => $url);
}

function build_weather_xml(float $lat, float $lon, $context): ?string
{
    $url = "https://api.open-meteo.com/v1/forecast?latitude={$lat}&longitude={$lon}&hourly=temperature_2m,precipitation,snowfall,windspeed_10m&timezone=auto";
    $json = safe_get($url, $context);
    if (!$json) {
        return null;
    }
    $data = json_decode($json, true);
    if (empty($data['hourly']['time'])) {
        return null;
    }
    $periods = array(
        'Matin' => range(6, 11),
        'Midi' => range(12, 16),
        'Soir' => range(17, 22)
    );
    $aggregated = array();
    foreach ($periods as $label => $hours) {
        $aggregated[$label] = array('temp' => array(), 'pluie' => array(), 'neige' => array(), 'vent' => array());
    }
    foreach ($data['hourly']['time'] as $idx => $time) {
        $hour = (int) substr($time, 11, 2);
        foreach ($periods as $label => $hours) {
            if (in_array($hour, $hours, true)) {
                $aggregated[$label]['temp'][] = $data['hourly']['temperature_2m'][$idx] ?? 0;
                $aggregated[$label]['pluie'][] = $data['hourly']['precipitation'][$idx] ?? 0;
                $aggregated[$label]['neige'][] = $data['hourly']['snowfall'][$idx] ?? 0;
                $aggregated[$label]['vent'][] = $data['hourly']['windspeed_10m'][$idx] ?? 0;
            }
        }
    }
    $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><meteo></meteo>');
    foreach ($aggregated as $label => $values) {
        if (empty($values['temp'])) {
            continue;
        }
        $temp = array_sum($values['temp']) / count($values['temp']);
        $pluie = max($values['pluie']);
        $neige = max($values['neige']);
        $vent = max($values['vent']);
        $description = 'Ciel dégagé';
        if ($pluie >= 5) {
            $description = 'Pluie marquée';
        } elseif ($pluie > 0) {
            $description = 'Averses possibles';
        } elseif ($neige > 0) {
            $description = 'Risque de neige';
        }
        if ($vent > 50) {
            $description = trim($description . ' vent fort');
        }
        $node = $xml->addChild('prevision');
        $node->addChild('moment', $label);
        $node->addChild('temp', round($temp, 1));
        $node->addChild('pluie', round($pluie, 1));
        $node->addChild('neige', round($neige, 1));
        $node->addChild('vitesse_vent', round($vent, 1));
        $node->addChild('description', $description);
    }
    if (count($xml->prevision) === 0) {
        return null;
    }
    return $xml->asXML();
}

function label_air_quality(int $index): string
{
    $labels = array(
        1 => 'Très bon',
        2 => 'Bon',
        3 => 'Moyen',
        4 => 'Dégradé',
        5 => 'Mauvais',
        6 => 'Très mauvais',
        7 => 'Extrême',
        8 => 'Extrême',
        9 => 'Extrême',
        10 => 'Extrême'
    );
    return $labels[$index] ?? 'Indisponible';
}

$client_ip = get_client_ip();
if ($client_ip === '127.0.0.1' || $client_ip === '::1') {
    $client_ip = '78.125.143.125';
}

$address = "2 ter Boulevard Charlemagne, 54000 Nancy";
$address_info = geocode_address($address, $opts);
$fixed_lat = $address_info['lat'];
$fixed_lon = $address_info['lon'];
$address_url = $address_info['url'];

$geo_url = "http://ip-api.com/xml/" . $client_ip;
$geo_xml_str = safe_get($geo_url, $context);
$geo_data = $geo_xml_str ? simplexml_load_string($geo_xml_str) : null;

$ip_lat = null;
$ip_lon = null;
$ip_city = null;
$ip_zip = null;
if ($geo_data && $geo_data->status == 'success') {
    $ip_lat = (float) $geo_data->lat;
    $ip_lon = (float) $geo_data->lon;
    $ip_city = (string) $geo_data->city;
    $ip_zip = (string) $geo_data->zip;
}

$lat = 48.6815;
$lon = 6.1737;
$city = "Nancy (IUT Charlemagne)";
$zip = "54000";
$lat = $fixed_lat;
$lon = $fixed_lon;
$city = "Nancy (IUT Charlemagne)";
$zip = "54000";

$meteo_xml_str = build_weather_xml($lat, $lon, $context);
if (!$meteo_xml_str) {
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
}

$xml = new DOMDocument();
$xml->loadXML($meteo_xml_str);

$xsl = new DOMDocument();
$xsl->load('meteo.xsl');

$proc = new XSLTProcessor();
$proc->importStyleSheet($xsl);
$meteo_html = $proc->transformToXML($xml);

$traffic_url = "https://carto.g-ny.eu/data/cifs/cifs_waze_v2.json";
$traffic_json = safe_get($traffic_url, $context);
$traffic_data = $traffic_json ? (json_decode($traffic_json, true) ?: array()) : array();

$covid_dataset_url = "https://www.data.gouv.fr/api/1/datasets/651a82516edc589b4f6a0354/";
$covid_json = safe_get($covid_dataset_url, $context);
$covid_info = $covid_json ? json_decode($covid_json, true) : null;
$covid_resource_url = "";
if ($covid_info && isset($covid_info['resources'])) {
    foreach ($covid_info['resources'] as $res) {
        if (stripos($res['title'], 'indicateurs') !== false && $res['format'] == 'csv') {
            $covid_resource_url = $res['latest'] ?? ($res['url'] ?? "");
            break;
        }
    }
}

$covid_data_points = array();
if ($covid_resource_url) {
    $csv_content = safe_get($covid_resource_url, $context);
    if ($csv_content) {
        $rows = explode("\n", $csv_content);
        $header_line = array_shift($rows);
        $header = str_getcsv($header_line, ";");
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
                if (empty(trim($row))) {
                    continue;
                }
                $data = str_getcsv($row, ";");
                if (count($data) > $col_index) {
                    $raw_val = trim($data[$col_index], '" ');
                    if ($raw_val !== "NA" && $raw_val !== "") {
                        $covid_data_points[] = array(
                            'date' => trim($data[0], '" '),
                            'value' => (float) str_replace(',', '.', $raw_val)
                        );
                    }
                }
            }
        }
        $covid_data_points = array_slice($covid_data_points, -8);
    }
}
$covid_last_date = $covid_data_points ? end($covid_data_points)['date'] : null;

$air_quality_url = "https://admindata.atmo-france.org/api/data/112/?code_zone=54395";
$air_quality_index = 2;
$air_quality_label = label_air_quality($air_quality_index);
$air_quality_json = safe_get($air_quality_url, $context);
if ($air_quality_json) {
    $air_quality_data = json_decode($air_quality_json, true);
    $stack = array($air_quality_data);
    $found_index = null;
    while ($stack) {
        $current = array_pop($stack);
        if (is_array($current)) {
            foreach ($current as $value) {
                if (is_array($value)) {
                    $stack[] = $value;
                } elseif (is_numeric($value)) {
                    $num = (int) $value;
                    if ($num >= 1 && $num <= 10) {
                        $found_index = $num;
                        break 2;
                    }
                }
            }
        }
    }
    if ($found_index !== null) {
        $air_quality_index = $found_index;
        $air_quality_label = label_air_quality($air_quality_index);
    }
}

$reasons = array();
$should_drive = true;
$xml_obj = simplexml_load_string($meteo_xml_str);
foreach ($xml_obj->prevision as $prev) {
    if ((float) $prev->temp < 3) {
        $reasons[] = "Températures très froides prévues.";
    }
    if ((float) $prev->pluie > 5) {
        $reasons[] = "Pluie forte prévue.";
    }
    if ((float) $prev->neige > 0) {
        $reasons[] = "Risque de neige.";
    }
    if ((float) $prev->vitesse_vent > 50) {
        $reasons[] = "Vent violent prévu.";
    }
}

$incident_count = isset($traffic_data['incidents']) ? count($traffic_data['incidents']) : 0;
if ($incident_count > 5) {
    $reasons[] = "Trafic dense avec $incident_count incidents signalés.";
}

if ($air_quality_index > 4) {
    $reasons[] = "Qualité de l'air médiocre (Indice $air_quality_index).";
    $should_drive = false;
}

if (count($reasons) > 2) {
    $should_drive = false;
}

$weather_api_url = "https://api.open-meteo.com/v1/forecast?latitude={$lat}&longitude={$lon}&hourly=temperature_2m,precipitation,snowfall,windspeed_10m&timezone=auto";
$git_repo_url = "https://github.com/Paul5400/Atmosphere";
$api_links = array(
    "Géo IP" => $geo_url,
    "Météo" => $weather_api_url,
    "Trafic" => $traffic_url,
    "Covid" => $covid_dataset_url,
    "Qualité de l'air" => $air_quality_url,
    "Adresse Géocodage" => $address_url,
    "Dépôt Git" => $git_repo_url
);
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
            <p>Localisation IP détectée :
                <?php if ($ip_lat !== null && $ip_lon !== null): ?>
                    <?php echo htmlspecialchars($ip_city ?? 'Inconnue'); ?> (<?php echo htmlspecialchars($ip_zip ?? ''); ?>)
                    - <?php echo $ip_lat; ?>, <?php echo $ip_lon; ?>
                <?php else: ?>
                    Indisponible
                <?php endif; ?>
            </p>
            <p>Coordonnées utilisées (IUT Charlemagne) : <?php echo $lat; ?>, <?php echo $lon; ?> (<?php echo htmlspecialchars($zip); ?>)</p>
            <p>Source API Géo : <a href="<?php echo htmlspecialchars($geo_url); ?>"><?php echo htmlspecialchars($geo_url); ?></a></p>
        </section>
        <section id="air-quality">
            <h2>Qualité de l'air</h2>
            <p>Indice ATMO à Nancy : <strong><?php echo $air_quality_index; ?> (<?php echo $air_quality_label; ?>)</strong></p>
        </section>
        <section id="map-container">
            <h2>Difficultés de circulation</h2>
            <div id="map" style="height: 300px; border-radius: 12px;"></div>
        </section>
        <section id="covid-chart">
            <h2>Évolution Covid (Sras dans les égouts)</h2>
            <div class="chart-wrapper">
                <?php if ($covid_last_date): ?>
                    <p>Dernière mesure : <?php echo htmlspecialchars($covid_last_date); ?></p>
                <?php endif; ?>
                <canvas id="covidChart"></canvas>
            </div>
        </section>
        <section id="sources">
            <h2>Sources des données</h2>
            <ul>
                <?php foreach ($api_links as $name => $url): ?>
                    <li><?php echo htmlspecialchars($name); ?> : <a href="<?php echo htmlspecialchars($url); ?>"><?php echo htmlspecialchars($url); ?></a></li>
                <?php endforeach; ?>
            </ul>
        </section>
    </main>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const trafficData = <?php echo json_encode($traffic_data); ?>;
        const covidDataPoints = <?php echo json_encode($covid_data_points); ?>;
        const userPos = [<?php echo $lat; ?>, <?php echo $lon; ?>];
        const fixedPos = [<?php echo $fixed_lat; ?>, <?php echo $fixed_lon; ?>];
        const ipPos = <?php echo ($ip_lat !== null && $ip_lon !== null) ? '[' . $ip_lat . ', ' . $ip_lon . ']' : 'null'; ?>;
    </script>
    <script src="script.js"></script>
    <footer>
        <p>Projet Interopérabilité - IUT Charlemagne</p>
    </footer>
</body>
</html>
