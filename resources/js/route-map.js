import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

/*
    A route drawn on OpenStreetMap tiles.

    Leaflet and OSM rather than a commercial provider: no API key, no billing
    account, no per-load quota. The tiles are fetched straight from
    openstreetmap.org, whose usage policy this stays well inside for an
    internal system.

    Click to place the origin, click again for the destination; drag either pin
    to correct it. The inputs Filament renders are the source of truth — this
    writes into them and dispatches an event so Livewire notices, rather than
    holding coordinates of its own.
*/

const TILES = 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
const ATTRIBUTION = '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors';

// Leaflet ships its marker icons as separate files; bundling them by URL keeps
// them working after a build, which the default relative paths do not.
const icon = L.icon({
    iconUrl: new URL('leaflet/dist/images/marker-icon.png', import.meta.url).href,
    iconRetinaUrl: new URL('leaflet/dist/images/marker-icon-2x.png', import.meta.url).href,
    shadowUrl: new URL('leaflet/dist/images/marker-shadow.png', import.meta.url).href,
    iconSize: [25, 41],
    iconAnchor: [12, 41],
});

window.sapRouteMap = (config) => ({
    map: null,
    origin: null,
    destination: null,
    line: null,

    init() {
        this.map = L.map(this.$refs.canvas, { scrollWheelZoom: false })
            .setView(config.center ?? [-1.2921, 36.8219], config.zoom ?? 6);

        L.tileLayer(TILES, { attribution: ATTRIBUTION, maxZoom: 18 }).addTo(this.map);

        if (config.origin) {
            this.place('origin', config.origin);
        }

        if (config.destination) {
            this.place('destination', config.destination);
        }

        this.fit();

        this.map.on('click', (event) => {
            const point = [event.latlng.lat, event.latlng.lng];

            // First click sets the origin, the next the destination, then it
            // starts over — the same way you would describe the run out loud.
            if (!this.origin || (this.origin && this.destination)) {
                this.clear();
                this.place('origin', point);
            } else {
                this.place('destination', point);
            }

            this.draw();
        });
    },

    place(which, point) {
        const marker = L.marker(point, { icon, draggable: true }).addTo(this.map);

        marker.on('dragend', () => {
            const position = marker.getLatLng();
            this.write(which, [position.lat, position.lng]);
            this.draw();
        });

        marker.bindTooltip(which === 'origin' ? 'Origin' : 'Destination', { permanent: false });

        this[which] = marker;
        this.write(which, point);
    },

    clear() {
        ['origin', 'destination'].forEach((which) => {
            if (this[which]) {
                this.map.removeLayer(this[which]);
                this[which] = null;
            }
        });

        if (this.line) {
            this.map.removeLayer(this.line);
            this.line = null;
        }
    },

    draw() {
        if (this.line) {
            this.map.removeLayer(this.line);
            this.line = null;
        }

        if (this.origin && this.destination) {
            this.line = L.polyline([this.origin.getLatLng(), this.destination.getLatLng()], {
                weight: 3,
                opacity: 0.8,
            }).addTo(this.map);
        }
    },

    fit() {
        if (this.origin && this.destination) {
            this.map.fitBounds(
                L.latLngBounds([this.origin.getLatLng(), this.destination.getLatLng()]),
                { padding: [30, 30] },
            );
        } else if (this.origin) {
            this.map.setView(this.origin.getLatLng(), 11);
        }
    },

    /**
     * Write straight into the form inputs, then tell Livewire they changed.
     * Setting .value alone leaves the component's state stale.
     */
    write(which, [lat, lng]) {
        const fields = which === 'origin'
            ? [config.fields.originLatitude, config.fields.originLongitude]
            : [config.fields.destinationLatitude, config.fields.destinationLongitude];

        [lat, lng].forEach((value, index) => {
            const input = document.getElementById(fields[index]);

            if (!input) {
                return;
            }

            input.value = value.toFixed(7);
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('change', { bubbles: true }));
        });
    },
});
