// SIGoIB WEB2 - map helpers (Leaflet). Data diinjeksi PHP sebagai JSON / diperbarui via live.js.
// JS hanya untuk rendering peta, bukan application layer.

function web2MarkerColor(status) {
  var c = { TRACKING: '#2e7d32', ONLINE: '#2e7d32', TERLAMBAT: '#c5a100', ALERT: '#c62828', OFFLINE: '#c62828' };
  return c[status] || '#9aa094';
}

function web2GmapsLink(lat, lng) {
  return 'https://www.google.com/maps/search/?api=1&query=' + lat + ',' + lng;
}

function web2MakeMap(elId, center, zoom) {
  var map = L.map(elId).setView(center || [-2.5, 118.0], zoom || 5);
  L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19, attribution: '&copy; OpenStreetMap contributors'
  }).addTo(map);
  return map;
}

function web2MarkerPopup(m) {
  var lat = m.latitude != null ? m.latitude : m.lat;
  var lng = m.longitude != null ? m.longitude : m.lng;
  var html = '<b>' + (m.name || '') + '</b><br>NRP: ' + (m.nrp || '-') +
    '<br>' + (m.company_name || m.company || '-') + ' / ' + (m.platoon_name || m.platoon || '-') +
    '<br>Status: ' + (m.status || '-');
  if (m.monitored !== undefined) {
    html += '<br>Monitoring: ' + (m.monitored
      ? 'DIMONITOR' + (m.session_name ? ' (' + m.session_name + ')' : '')
      : 'TIDAK DIMONITOR');
  }
  html += '<br>Battery: ' + (m.battery != null ? m.battery + '%' : '-') +
    '<br>Akurasi: ' + (m.accuracy != null ? Math.round(m.accuracy) + ' m' : '-') +
    '<br>Last seen: ' + (m.last_seen || m.last_seen_at || '-');
  if (lat != null && lng != null) {
    html += '<br><a target="_blank" rel="noopener" href="' + web2GmapsLink(lat, lng) + '"><b>BUKA DI GOOGLE MAPS</b></a>';
  }
  return html;
}

// markers statis (initial SSR). markers: [{lat,lng,status,name,nrp,...}]
function web2RenderMarkers(map, markers) {
  var bounds = [];
  (markers || []).forEach(function (m) {
    if (m.lat === null || m.lng === null) return;
    var mk = L.circleMarker([m.lat, m.lng], {
      radius: 9, color: '#fff', weight: 2, fillColor: web2MarkerColor(m.status), fillOpacity: 1
    }).addTo(map);
    mk.bindPopup(web2MarkerPopup(m));
    bounds.push([m.lat, m.lng]);
  });
  if (bounds.length) map.fitBounds(bounds, { padding: [34, 34] });
  return bounds.length;
}

// LIVE map: dibuat SEKALI. Marker di-UPSERT (bukan recreate) tiap polling.
function web2LiveMap(elId) {
  return { map: web2MakeMap(elId), reg: {}, fitted: false };
}

// markers dari API buildMarkers: [{personnel_id,latitude,longitude,status,name,nrp,...}]
function web2UpsertMarkers(state, markers) {
  var seen = {}, bounds = [];
  (markers || []).forEach(function (m) {
    if (m.latitude == null || m.longitude == null) return;
    var id = m.personnel_id != null ? m.personnel_id : (m.nrp || m.name);
    seen[id] = true;
    var ll = [m.latitude, m.longitude];
    bounds.push(ll);
    var color = web2MarkerColor(m.status);
    var e = state.reg[id];
    if (e) {
      e.marker.setLatLng(ll);
      e.marker.setStyle({ fillColor: color });
      e.marker.setPopupContent(web2MarkerPopup(m));
      e.data = m;
    } else {
      var mk = L.circleMarker(ll, { radius: 9, color: '#fff', weight: 2, fillColor: color, fillOpacity: 1 }).addTo(state.map);
      mk.bindPopup(web2MarkerPopup(m));
      (function (pid) {
        mk.on('click', function () { if (typeof state.onMarkerClick === 'function') { state.onMarkerClick(pid); } });
      })(id);
      state.reg[id] = { marker: mk, data: m };
    }
  });
  Object.keys(state.reg).forEach(function (id) {
    if (!seen[id]) { state.map.removeLayer(state.reg[id].marker); delete state.reg[id]; }
  });
  if (!state.fitted && bounds.length) { state.map.fitBounds(bounds, { padding: [34, 34] }); state.fitted = true; }
  return state;
}

function web2FocusMarker(state, personnelId) {
  var e = state.reg[personnelId];
  if (!e) return false;
  state.map.setView(e.marker.getLatLng(), 16, { animate: true });
  e.marker.openPopup();
  return true;
}

// geofences: [{lat,lng,radius,name,category}]
function web2RenderGeofences(map, fences) {
  (fences || []).forEach(function (g) {
    L.circle([g.lat, g.lng], { radius: g.radius, color: '#c62828', weight: 2, fillOpacity: .08 })
      .addTo(map).bindPopup('<b>' + g.name + '</b><br>' + (g.category || '') + ' &middot; ' + g.radius + ' m');
  });
}

// Click-to-pick untuk form geofence. onPick(lat, lng)
function web2PickPoint(map, onPick) {
  var circle = null;
  map.on('click', function (e) {
    if (circle) map.removeLayer(circle);
    circle = L.circle(e.latlng, { radius: 100, color: '#c62828' }).addTo(map);
    onPick(e.latlng.lat.toFixed(7), e.latlng.lng.toFixed(7), circle);
  });
}

// Route history: points [{lat,lng,recorded_at}]. Mengembalikan {map, markers[]} agarbisa difokus dari daftar.
function web2RenderRoute(map, points) {
  if (!points || !points.length) return null;
  var latlngs = points.map(function (p) { return [p.lat, p.lng]; });
  L.polyline(latlngs, { color: '#3f5233', weight: 4 }).addTo(map);
  var start = L.circleMarker(latlngs[0], { radius: 8, fillColor: '#2e7d32', color: '#fff', weight: 2, fillOpacity: 1 })
    .addTo(map).bindPopup('TITIK AWAL<br>' + (points[0].recorded_at || '') +
      '<br><a target="_blank" rel="noopener" href="' + web2GmapsLink(points[0].lat, points[0].lng) + '">BUKA DI GOOGLE MAPS</a>');
  var last = points[points.length - 1];
  L.circleMarker(latlngs[latlngs.length - 1], { radius: 8, fillColor: '#c62828', color: '#fff', weight: 2, fillOpacity: 1 })
    .addTo(map).bindPopup('POSISI TERAKHIR<br>' + (last.recorded_at || '') +
      '<br><a target="_blank" rel="noopener" href="' + web2GmapsLink(last.lat, last.lng) + '">BUKA DI GOOGLE MAPS</a>');
  map.fitBounds(latlngs, { padding: [34, 34] });
  return { start: start };
}

// ── ROUTE OVERLAY untuk LIVE map (dipakai monitoring.php inline) ──
// Digambar di atas map yang SAMA (tidak recreate map). LayerGroup lama dibersihkan
// sebelum menggambar yang baru sehingga hanya route personel terpilih yang tampil.
function web2RoutePointPopup(p, label) {
  return (label ? '<b>' + label + '</b><br>' : '') +
    (p.recorded_at || '') +
    '<br>' + Number(p.lat).toFixed(6) + ', ' + Number(p.lng).toFixed(6) +
    (p.accuracy != null ? '<br>Akurasi: ' + Math.round(p.accuracy) + ' m' : '') +
    (p.battery != null ? '<br>Baterai: ' + p.battery + '%' : '') +
    '<br><a target="_blank" rel="noopener" href="' + web2GmapsLink(p.lat, p.lng) + '"><b>BUKA DI GOOGLE MAPS</b></a>';
}

// points: [{lat,lng,recorded_at,accuracy,battery}]. opts.live=true → titik akhir = POSISI SEKARANG (hijau); false → TITIK AKHIR (merah).
function web2ShowRoute(state, points, opts) {
  web2ClearRoute(state);
  if (!points || !points.length) { return null; }
  opts = opts || {};
  var live = opts.live !== false;
  var latlngs = points.map(function (p) { return [p.lat, p.lng]; });
  var grp = L.layerGroup();
  if (latlngs.length > 1) {
    L.polyline(latlngs, { color: '#3f5233', weight: 4, opacity: 0.9 }).addTo(grp);
  }
  for (var i = 1; i < points.length - 1; i++) {
    L.circleMarker(latlngs[i], { radius: 4, color: '#3f5233', weight: 1, fillColor: '#6d8a54', fillOpacity: 1 })
      .addTo(grp).bindPopup(web2RoutePointPopup(points[i]));
  }
  L.circleMarker(latlngs[0], { radius: 8, color: '#fff', weight: 2, fillColor: '#2d5f8a', fillOpacity: 1 })
    .addTo(grp).bindPopup(web2RoutePointPopup(points[0], 'TITIK AWAL'));
  if (latlngs.length > 1) {
    var last = points[points.length - 1];
    L.circleMarker(latlngs[latlngs.length - 1], { radius: 9, color: '#fff', weight: 2, fillColor: live ? '#2e7d32' : '#c62828', fillOpacity: 1 })
      .addTo(grp).bindPopup(web2RoutePointPopup(last, live ? 'POSISI SEKARANG' : 'TITIK AKHIR'));
  }
  grp.addTo(state.map);
  state.routeLayer = grp;
  if (opts.fit !== false) { state.map.fitBounds(latlngs, { padding: [45, 45], maxZoom: 17 }); }
  return grp;
}

function web2ClearRoute(state) {
  if (state && state.routeLayer) { state.map.removeLayer(state.routeLayer); state.routeLayer = null; }
}

function web2FocusLatLng(state, lat, lng, zoom) {
  state.map.setView([lat, lng], zoom || 17, { animate: true });
}
