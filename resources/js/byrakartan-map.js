/*
 * Kartan i hero-blocket på /byrakartan: byråerna i sökresultatet som pins, och byråer
 * som ligger nära varandra slås ihop till en markör med antal (klick zoomar in).
 * Samma upplägg som på Byråkartans startsida: Leaflet med Mapbox-rutor (eller
 * OpenStreetMap om ingen publik token finns).
 *
 * Byråerna kommer färdiga från servern i #byraer-data (bara publik byrådata).
 * Popupen byggs med textNode:er, så inget i datan tolkas som HTML.
 */

import L from 'leaflet';
import 'leaflet.markercluster';
import 'leaflet/dist/leaflet.css';
import 'leaflet.markercluster/dist/MarkerCluster.css';

const SWEDEN = [[55.3, 10.9], [69.1, 24.2]];

const PIN = '<svg class="byraer-pin" width="24" height="32" viewBox="0 0 9 12" fill="currentColor" aria-hidden="true"><path d="M4.5 0A4.5 4.5 0 0 0 0 4.5C0 7.9 4.5 12 4.5 12S9 7.9 9 4.5A4.5 4.5 0 0 0 4.5 0Zm0 6.2a1.7 1.7 0 1 1 0-3.4 1.7 1.7 0 0 1 0 3.4Z"/></svg>';

function popup(agency) {
    const box = document.createElement('div');
    box.className = 'byraer-popup';

    // Namnet leder till byråns egen sida (på byråns egen sida finns ingen länk, kartan visar bara platsen).
    if (agency.url) {
        const link = document.createElement('a');
        link.href = agency.url;
        link.className = 'byraer-popup-name';
        link.textContent = agency.name;
        box.append(link);
    } else {
        const name = document.createElement('strong');
        name.textContent = agency.name;
        box.append(name);
    }

    const meta = [agency.city, agency.rating ? `★ ${agency.rating}` : ''].filter(Boolean).join(' · ');

    if (meta) {
        const line = document.createElement('span');
        line.textContent = meta;
        box.append(line);
    }

    return box;
}

export default function initAgencyMap(el) {

    let agencies = [];

    try {
        agencies = JSON.parse(document.getElementById('byraer-data')?.textContent || '[]');
    } catch (e) { /* ingen data: kartan visar hela Sverige */ }

    const map = L.map(el, { zoomControl: false, scrollWheelZoom: false });

    L.control.zoom({ position: 'topright' }).addTo(map);

    // Leaflets egen flagga tas bort; leverantörens attribution (Mapbox/OSM) ska ligga kvar.
    map.attributionControl.setPrefix(false);

    const token = el.dataset.mapboxToken;

    if (token) {
        L.tileLayer(`https://api.mapbox.com/styles/v1/${el.dataset.mapboxStyle || 'mapbox/light-v11'}/tiles/512/{z}/{x}/{y}@2x?access_token=${token}`, {
            tileSize: 512,
            zoomOffset: -1,
            maxZoom: 18,
            attribution: '© <a href="https://www.mapbox.com/about/maps/">Mapbox</a> © <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        }).addTo(map);
    } else {
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 18,
            attribution: '© OpenStreetMap-bidragsgivare',
        }).addTo(map);
    }

    const cluster = L.markerClusterGroup({
        maxClusterRadius: 45,
        showCoverageOnHover: false,
        iconCreateFunction: (c) => L.divIcon({
            className: '',
            html: `<div class="byraer-cluster">${c.getChildCount()}</div>`,
            iconSize: [38, 38],
        }),
    });

    map.addLayer(cluster);

    const bounds = [];

    agencies.forEach((agency) => {
        if (agency.lat === null || agency.lng === null) return;

        const marker = L.marker([agency.lat, agency.lng], {
            icon: L.divIcon({ className: '', html: PIN, iconSize: [24, 32], iconAnchor: [12, 32], popupAnchor: [0, -30] }),
            title: agency.name,
        });

        marker.bindPopup(popup(agency));
        cluster.addLayer(marker);
        bounds.push([agency.lat, agency.lng]);
    });

    // På bred skärm ligger texten och sökformuläret till vänster, så kartans mitt flyttas åt höger.
    const wide = window.matchMedia('(min-width: 1024px)').matches;
    const padding = { paddingTopLeft: [wide ? Math.round(el.clientWidth * 0.5) : 30, 40], paddingBottomRight: [30, 40] };

    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // Hela landet först, så att man ser inzoomningen när platsen är vald.
    if (bounds.length > 1) map.fitBounds(bounds, { ...padding, maxZoom: 11 });
    else map.fitBounds(SWEDEN, padding);

    if (el.dataset.origin) {
        // Sökt nära en plats (Nära mig eller postnummer): markera platsen och zooma in på den
        // tillsammans med närmaste byrån och de som ligger inom 50 km.
        const origin = el.dataset.origin.split(',').map(Number);

        L.circleMarker(origin, { radius: 7, color: '#ffffff', weight: 3, fillColor: '#2e251f', fillOpacity: 1, interactive: false }).addTo(map);

        const byDistance = agencies.filter((a) => a.km !== null && a.km !== undefined).sort((a, b) => a.km - b.km);
        const near = byDistance.filter((a, i) => i === 0 || a.km <= 50).map((a) => [a.lat, a.lng]);
        const target = L.latLngBounds([origin, ...near]);
        const options = { ...padding, maxZoom: 12 };

        if (reduceMotion) map.fitBounds(target, { ...options, animate: false });
        else setTimeout(() => map.flyToBounds(target, { ...options, duration: 1.4 }), 500);
    } else if (bounds.length === 1) {
        map.setView(bounds[0], Number(el.dataset.zoom) || 11);
    }

    // Hjulet scrollar sidan; zooma med knapparna, dubbelklick eller knip.
    return map;
}
