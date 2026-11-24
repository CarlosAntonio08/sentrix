/* GPS: mapa Leaflet que se atualiza sozinho (consulta gps-dados.php) */
(() => {
    const { esc, dados } = window.Sentrix;
    const cfg = dados('dadosMapa') || { cercas: [], intervalo: 10000 };
    const elMapa   = document.getElementById('gpsMap');
    const elStatus = document.getElementById('mapaStatus');
    const elVazio  = document.getElementById('mapaVazio');
    if (!elMapa || typeof L === 'undefined') {
        if (elStatus) elStatus.textContent = 'Não foi possível carregar o mapa (verifique a conexão com a internet).';
        return;
    }

    const map = L.map(elMapa, { scrollWheelZoom: false }).setView([-22.9068, -43.1729], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors', maxZoom: 19,
    }).addTo(map);

    cfg.cercas.forEach(c => {
        L.circle([c.lat, c.lng], {
            radius: c.raio, weight: 1.5, fillOpacity: 0.08,
            color: c.ativa ? '#dc2626' : '#52525b', fillColor: c.ativa ? '#dc2626' : '#52525b',
            dashArray: c.ativa ? null : '6 6',
        }).addTo(map).bindPopup(`<strong>${esc(c.nome)}</strong><br>Raio: ${c.raio} m${c.ativa ? '' : ' (inativa)'}`);
    });

    const icone = ativo => L.divIcon({
        className: 'gps-marker',
        html: `<div class="gps-marker-dot ${ativo ? '' : 'gps-marker-off'}"></div>`,
        iconSize: [18, 18], iconAnchor: [9, 9],
    });

    const marcadores = new Map(); // id -> { marker, linha }
    let primeiraVez = true;

    async function atualizar() {
        if (document.hidden && !primeiraVez) return;
        try {
            const resp = await fetch('gps-dados.php', { headers: { Accept: 'application/json' } });
            if (resp.status === 401) { window.location.href = 'login.php'; return; }
            const d = await resp.json();
            const lista = d.rastreadores || [];

            elVazio.hidden = lista.length > 0;
            elMapa.hidden  = lista.length === 0;

            const vistos = new Set();
            lista.forEach(r => {
                vistos.add(r.id);
                const popup = `<strong>${esc(r.nome)}</strong><br>${esc(r.tipo)} · ${r.vel.toFixed(1)} km/h<br><small>${esc(r.quando)}</small>`;
                let item = marcadores.get(r.id);
                if (!item) {
                    item = {
                        marker: L.marker([r.lat, r.lng], { icon: icone(r.ativo) }).addTo(map),
                        linha: L.polyline(r.trilha, { color: '#dc2626', weight: 2, opacity: 0.5 }).addTo(map),
                    };
                    marcadores.set(r.id, item);
                } else {
                    item.marker.setLatLng([r.lat, r.lng]);
                    item.marker.setIcon(icone(r.ativo));
                    item.linha.setLatLngs(r.trilha);
                }
                item.marker.bindPopup(popup);
            });
            // remove rastreadores excluídos
            marcadores.forEach((item, id) => {
                if (!vistos.has(id)) { map.removeLayer(item.marker); map.removeLayer(item.linha); marcadores.delete(id); }
            });

            if (primeiraVez && lista.length) {
                const pontos = lista.map(r => [r.lat, r.lng]);
                cfg.cercas.forEach(c => pontos.push([c.lat, c.lng]));
                if (pontos.length > 1) map.fitBounds(pontos, { padding: [40, 40], maxZoom: 16 });
                else map.setView(pontos[0], 15);
                map.invalidateSize();
            }
            primeiraVez = false;
            elStatus.textContent = `● Ao vivo — ${lista.length} rastreador(es) · atualizado às ${d.atualizado_em}`;
        } catch (e) {
            elStatus.textContent = 'Sem conexão com o servidor. Tentando novamente…';
        }
    }

    atualizar();
    setInterval(atualizar, cfg.intervalo || 10000);
})();
