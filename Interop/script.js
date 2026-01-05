document.addEventListener('DOMContentLoaded', () => {
    const map = L.map('map').setView(userPos, 13);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);

    L.marker(userPos).addTo(map)
        .bindPopup('<b>Position utilisée (IUT Charlemagne)</b>')
        .openPopup();

    L.circleMarker(fixedPos, { color: 'red' }).addTo(map)
        .bindPopup('<b>IUT Charlemagne / Destination</b>');

    if (ipPos && ipPos.length === 2) {
        L.circleMarker(ipPos, { color: 'blue' }).addTo(map)
            .bindPopup('<b>Localisation IP détectée</b>');
    }

    if (trafficData && trafficData.incidents) {
        trafficData.incidents.forEach(incident => {
            if (incident.location) {
                let lat, lon;
                if (incident.location.polyline) {
                    const coords = incident.location.polyline.split(' ');
                    const latlon = coords[0].split(',');
                    lat = parseFloat(latlon[0]);
                    lon = parseFloat(latlon[1]);
                }

                if (lat && lon) {
                    L.marker([lat, lon], {
                        icon: L.divIcon({
                            className: 'traffic-marker',
                            html: '<div style="background:red; width:10px; height:10px; border-radius:50%; border:2px solid white; box-shadow: 0 0 5px rgba(0,0,0,0.3);"></div>'
                        })
                    }).addTo(map)
                        .bindPopup(`<b>${incident.type || 'Incident'}</b><br>${incident.description || 'Difficulté de circulation'}<br><i>${incident.starttime || ''} - ${incident.endtime || ''}</i>`);
                }
            }
        });
    }

    if (covidDataPoints && covidDataPoints.length > 0) {
        const labels = covidDataPoints.map(p => p.date);
        const values = covidDataPoints.map(p => p.value);

        const ctx = document.getElementById('covidChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Indicateur SARS-CoV-2 (Maxeville / Nancy)',
                    data: values,
                    borderColor: '#2563eb',
                    backgroundColor: 'rgba(37, 99, 235, 0.1)',
                    fill: true,
                    tension: 0.3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top' }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: { display: true, text: 'Concentration' }
                    },
                    x: {
                        title: { display: true, text: 'Date de prélèvement' }
                    }
                }
            }
        });
    } else {
        document.getElementById('covidChart').parentElement.innerHTML += "<p>Données Covid non disponibles pour le moment.</p>";
    }

    console.log("Atmosphere JS chargé avec succès");
});
