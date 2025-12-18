<?php
/**
 * Projet Atmosphere - Interopérabilité des données
 * 
 */

// Configuration du proxy pour webetu
$opts = array(
    'http' => array(
        'proxy' => 'tcp://127.0.0.1:8080',
        'request_fulluri' => true
    ),
    'ssl' => array(
        'verify_peer' => false,
        'verify_peer_name' => false
    )
);
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
        <p>Vos conditions de circulation à <strong><?php echo htmlspecialchars($city); ?></strong></p>
    </header>

    <main>
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
    </main>

    <footer>
        <p>Projet Interopérabilité - IUT Charlemagne</p>
    </footer>
</body>

</html>