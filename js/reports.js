document.addEventListener('DOMContentLoaded', () => {
    const map = L.map('map').setView([4.2105, 101.9758], 6);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap'
    }).addTo(map);

    // Define marker icons
    const greenIcon = new L.Icon({
        iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-green.png',
        shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png',
        iconSize: [25, 41],
        iconAnchor: [12, 41],
        popupAnchor: [1, -34],
        shadowSize: [41, 41]
    });

    const yellowIcon = new L.Icon({
        iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-yellow.png',
        shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png',
        iconSize: [25, 41],
        iconAnchor: [12, 41],
        popupAnchor: [1, -34],
        shadowSize: [41, 41]
    });

    const orangeIcon = new L.Icon({
        iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-orange.png',
        shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png',
        iconSize: [25, 41],
        iconAnchor: [12, 41],
        popupAnchor: [1, -34],
        shadowSize: [41, 41]
    });

    const redIcon = new L.Icon({
        iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-red.png',
        shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png',
        iconSize: [25, 41],
        iconAnchor: [12, 41],
        popupAnchor: [1, -34],
        shadowSize: [41, 41]
    });

    // Fetch reports
    fetch('get_reports.php')
        .then(res => res.json())
        .then(reports => {
            reports.forEach(r => {
                const icon = getIconByUrgency(r.level);

                L.marker([r.lat, r.lng], { icon })
                    .addTo(map)
                    .bindPopup(`
                        <strong>${r.type}</strong><br>
                        Type: ${r.reportType.toUpperCase()}<br>
                        Urgency: ${r.level}<br>
                        Status: ${r.status}<br>
                        ${r.description}
                    `);
            });
        })
        .catch(console.error);

    // Map urgency level to marker icon
    function getIconByUrgency(level) {
        switch(level) {
            case 'low': return greenIcon;
            case 'medium': return yellowIcon;
            case 'high': return orangeIcon;
            case 'critical': return redIcon;
            default: return greenIcon; // fallback
        }
    }

});
