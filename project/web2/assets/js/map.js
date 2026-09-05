// SIGoIB WEB2 - map helpers (Leaflet). Data diinjeksi PHP sebagai JSON.
// JS hanya untuk rendering peta, bukan application layer.

function web2MarkerColor(status) {
  var c = { TRACKING: '#2e7d32', ONLINE: '#2e7d32', TERLAMBAT: '#c5a100', ALERT: '#c62828', OFFLINE: '#c62828' };
  return c[status] || '#9aa094';
}

function web2MakeMap(elId, center, zoom) {
  var map = L.map(elId).setView(center || [-2.5, 118.0], zoom || 5);
  L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19, attribution: '&copy; OpenStreetMap contributors'
  }).addTo(map);
  return map;
}

// markers: [{lat,lng,status,name,nrp,rank,company,platoon,battery,last_seen,detail_url}]
function web2RenderMarkers(map, markers) {
  var bounds = [];
  (markers || []).forEach(function (m) {
    if (m.lat === null || m.lng === null) return;
    var mk = L.circleMarker([m.lat, m.lng], {
      radius: 9, color: '#fff', weight: 2, fillColor: web2MarkerColor(m.status), fillOpacity: 1
    }).addTo(map);
    var html = '<b>' + m.name + '</b><br>NRP: ' + m.nrp +
      '<br>' + (m.company || '-') + ' / ' + (m.platoon || '-') +
      '<br>Status: ' + m.status +
      '<br>Battery: ' + (m.battery !== null && m.battery !== undefined ? m.battery + '%' : '-') +
      '<br>Last seen: ' + (m.last_seen || '-');
    if (m.detail_url) html += '<br><a href="' + m.detail_url + '"><b>DETAIL</b></a>';
    mk.bindPopup(html);
    bounds.push([m.lat, m.lng]);
  });
  if (bounds.length) map.fitBounds(bounds, { padding: [34, 34] });
  return bounds.length;
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

// Route history: points [{lat,lng,recorded_at}]
function web2RenderRoute(map, points) {
  if (!points || !points.length) return false;
  var latlngs = points.map(function (p) { return [p.lat, p.lng]; });
  L.polyline(latlngs, { color: '#3f5233', weight: 4 }).addTo(map);
  L.circleMarker(latlngs[0], { radius: 8, fillColor: '#2e7d32', color: '#fff', weight: 2, fillOpacity: 1 })
    .addTo(map).bindPopup('Awal: ' + (points[0].recorded_at || ''));
  L.circleMarker(latlngs[latlngs.length - 1], { radius: 8, fillColor: '#c62828', color: '#fff', weight: 2, fillOpacity: 1 })
    .addTo(map).bindPopup('Akhir: ' + (points[points.length - 1].recorded_at || ''));
  map.fitBounds(latlngs, { padding: [34, 34] });
  return true;
}
