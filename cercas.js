/* Cercas virtuais: mapa com as cercas e escolha do centro por clique */
(() => {
    const { esc, dados } = window.Sentrix;
    const cfg = dados('dadosMapa');
    const elMapa = document.getElementById('mapaCercas');
    if (!cfg || !elMapa || typeof L === 'undefined') return;

    const map = L.map(elMapa, { scrollWheelZoom: false }).setView([cfg.centro.lat, cfg.centro.lng], 15);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors', maxZoom: 19,
    }).addTo(map);

    cfg.cercas.forEach(c => {
        L.circle([c.lat, c.lng], {
            radius: c.raio, weight: 1.5, fillOpacity: 0.08,
            color: c.ativa ? '#dc2626' : '#52525b', fillColor: c.ativa ? '#dc2626' : '#52525b',
        }).addTo(map).bindPopup(`<strong>${esc(c.nome)}</strong><br>Raio: ${c.raio} m`);
    });

    if (!cfg.editavel) return;

    const inLat  = document.getElementById('centro_lat');
    const inLng  = document.getElementById('centro_lng');
    const inRaio = document.getElementById('raio_metros');
    let previa = null;

    function desenharPrevia() {
        const lat = parseFloat(inLat.value), lng = parseFloat(inLng.value), raio = parseInt(inRaio.value, 10);
        if (isNaN(lat) || isNaN(lng) || isNaN(raio)) return;
        if (previa) previa.setLatLng([lat, lng]).setRadius(raio);
        else previa = L.circle([lat, lng], { radius: raio, color: '#facc15', fillColor: '#facc15', fillOpacity: 0.12, dashArray: '6 6' }).addTo(map);
    }

    map.on('click', e => {
        inLat.value = e.latlng.lat.toFixed(7);
        inLng.value = e.latlng.lng.toFixed(7);
        desenharPrevia();
        document.getElementById('nome').focus();
    });
    [inLat, inLng, inRaio].forEach(el => el.addEventListener('input', desenharPrevia));
    desenharPrevia();
})();
