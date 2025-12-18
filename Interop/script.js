/**
 * Atmosphere - Logique Client-Side (JS)
 * Initialisation de la carte Leaflet et de Chart.js
 */

document.addEventListener('DOMContentLoaded', () => {
    // 1. Initialisation de la Carte Leaflet
    const map = L.map('map').setView(userPos, 13);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);

    // Marqueur pour la position de l'utilisateur
    L.marker(userPos).addTo(map)
        .bindPopup('<b>Votre position (IP)</b>')
        .openPopup();

    // Marqueur pour l'adresse fixe (IUT Charlemagne)
    L.circleMarker(fixedPos, { color: 'red' }).addTo(map)
        .bindPopup('<b>IUT Charlemagne / Destination</b>');

    // Ajout des incidents de trafic
    if (trafficData && trafficData.incidents) {
        trafficData.incidents.forEach(incident => {
            if (incident.location && incident.location.polyline) {
                // Simplification : on prend le premier point de la polyline
                const coords = incident.location.polyline.split(' ');
                const latlon = coords[0].split(',');
                
                L.marker([parseFloat(latlon[0]), parseFloat(latlon[1])], {
                    icon: L.divIcon({
                        className: 'traffic-marker',
                        html: '<div style="background:orange; width:12px; height:12px; border-radius:50%; border:2px solid white;"></div>'
                    })
                }).addTo(map)
                    .bindPopup(`<b>${incident.type}</b><br>${incident.description || 'Difficulté de circulation'}<br>Début: ${incident.starttime || 'N/A'}`);
            }
        });
    }

    // 2. Initialisation du Graphique Covid (Chart.js)
    // Données simulées pour l'exemple (à remplacer par le parsing de l'URL covid_resource_url)
    const ctx = document.getElementById('covidChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: ['S-4', 'S-3', 'S-2', 'S-1', 'Semaine Actuelle'],
            datasets: [{
                label: 'Concentration SARS-CoV-2 (Maxeville)',
                data: [12, 19, 3, 5, 2],
                borderColor: '#2563eb',
                backgroundColor: 'rgba(37, 99, 235, 0.1)',
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'bottom' }
            },
            scales: {
                y: { beginAtZero: true }
            }
        }
    });

    console.log("Atmosphere JS chargé avec succès");
});
