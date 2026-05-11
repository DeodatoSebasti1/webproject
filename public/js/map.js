// Shared Leaflet helpers for UrbanTraffic maps.
(function () {
    const tileLayers = {
        default: {
            url: 'https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png',
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> &copy; CartoDB',
            maxZoom: 19
        },
        satellite: {
            url: 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
            attribution: '&copy; <a href="https://www.esri.com/">Esri</a>',
            maxZoom: 19
        },
        mapbox: {
            url: null,
            attribution: '&copy; <a href="https://www.mapbox.com/">Mapbox</a> &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
            maxZoom: 19
        }
    };

    function createMap(elementId, options = {}) {
        if (typeof L === 'undefined' || !document.getElementById(elementId)) {
            return null;
        }

        const center = options.center || [38.7223, -9.1393];
        const zoom = options.zoom || 13;

        const map = L.map(elementId, options.leafletOptions || {}).setView(center, zoom);

        setTileLayer(map, options.layer || 'mapbox');

        return map;
    }

    function setTileLayer(map, type = 'default') {
        if (!map) return null;

        if (map._urbanTileLayer) {
            map.removeLayer(map._urbanTileLayer);
        }

        const layerConfig = tileLayers[type] || tileLayers.default;
        const mapboxToken = typeof window !== 'undefined' && typeof window.URBAN_MAPBOX_TOKEN === 'string'
            ? window.URBAN_MAPBOX_TOKEN.trim()
            : '';
        const tileUrl = type === 'mapbox' && mapboxToken
            ? `https://api.mapbox.com/styles/v1/mapbox/light-v11/tiles/256/{z}/{x}/{y}?access_token=${encodeURIComponent(mapboxToken)}`
            : layerConfig.url || tileLayers.default.url;

        map._urbanTileLayer = L.tileLayer(tileUrl, {
            attribution: layerConfig.attribution,
            maxZoom: layerConfig.maxZoom || 19
        }).addTo(map);

        map._urbanLayerType = type;

        return map._urbanTileLayer;
    }

    function drawPolyline(map, coordinates, options = {}) {
        if (!map || !coordinates || coordinates.length < 2) {
            return null;
        }

        const line = L.polyline(coordinates, {
            color: options.color || '#4CAF50',
            weight: options.weight || 5,
            opacity: options.opacity || 0.9
        }).addTo(map);

        if (options.fit !== false) {
            map.fitBounds(line.getBounds(), { padding: options.padding || [35, 35] });
        }

        return line;
    }

    window.UrbanMap = {
        tileLayers,
        createMap,
        setTileLayer,
        drawPolyline
    };
})();

function drawRouteOnMap(route) {
    if (!route || !route.segments || route.segments.length === 0) return;

    const coords = [];

    route.segments.forEach(segment => {
        if (segment.from && segment.from.latitude && segment.from.longitude) {
            coords.push([
                parseFloat(segment.from.latitude),
                parseFloat(segment.from.longitude)
            ]);
        }
    });

    const last = route.segments[route.segments.length - 1];

    if (last.to && last.to.latitude && last.to.longitude) {
        coords.push([
            parseFloat(last.to.latitude),
            parseFloat(last.to.longitude)
        ]);
    }

    if (!window.map) {
        console.warn('Mapa global "map" não encontrado.');
        return;
    }

    if (window.currentPolyline) {
        window.map.removeLayer(window.currentPolyline);
    }

    window.currentPolyline = L.polyline(coords, {
        color: 'orange',
        weight: 6
    }).addTo(window.map);

    window.map.fitBounds(window.currentPolyline.getBounds(), {
        padding: [35, 35]
    });
}
