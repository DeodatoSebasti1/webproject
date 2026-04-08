function drawRouteOnMap(route) {

    if (!route.segments || route.segments.length === 0) return;

    const coords = [];

    // pontos intermédios
    route.segments.forEach(s => {
        if (s.from.latitude && s.from.longitude) {
            coords.push([s.from.latitude, s.from.longitude]);
        }
    });

    // último destino
    const last = route.segments[route.segments.length - 1];

    if (last.to.latitude && last.to.longitude) {
        coords.push([last.to.latitude, last.to.longitude]);
    }

    if (window.currentPolyline) {
        map.removeLayer(window.currentPolyline);
    }

    window.currentPolyline = L.polyline(coords, {
        color: "orange",
        weight: 6
    }).addTo(map);

    map.fitBounds(coords);
}