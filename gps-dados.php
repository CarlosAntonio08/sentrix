<?php
/**
 * Endpoint JSON usado pelo mapa (gps.php) para se atualizar sozinho.
 * Retorna a última posição de cada rastreador e a trilha recente de cada um.
 */
require_once 'includes/auth.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

echo json_encode(dados_gps($pdo), JSON_UNESCAPED_UNICODE);

function dados_gps(PDO $pdo): array
{
    $rastreadores = $pdo->query(
        "SELECT g.id, g.nome, g.tipo, g.ativo,
                l.latitude, l.longitude, l.velocidade_kmh, l.registrado_em
         FROM gps_dispositivos g
         LEFT JOIN gps_localizacoes l ON l.id = (
             SELECT id FROM gps_localizacoes WHERE gps_dispositivo_id = g.id
             ORDER BY registrado_em DESC, id DESC LIMIT 1)
         ORDER BY g.nome"
    )->fetchAll();

    $trilha = $pdo->prepare(
        'SELECT latitude, longitude FROM gps_localizacoes WHERE gps_dispositivo_id = ?
         ORDER BY registrado_em DESC, id DESC LIMIT 15'
    );

    $saida = [];
    foreach ($rastreadores as $r) {
        if ($r['latitude'] === null) continue;
        $trilha->execute([$r['id']]);
        $pontos = array_reverse(array_map(fn ($p) => [(float) $p['latitude'], (float) $p['longitude']], $trilha->fetchAll()));
        $saida[] = [
            'id'     => (int) $r['id'],
            'nome'   => $r['nome'],
            'tipo'   => rotulo('tipo_gps', $r['tipo']),
            'ativo'  => (bool) $r['ativo'],
            'lat'    => (float) $r['latitude'],
            'lng'    => (float) $r['longitude'],
            'vel'    => (float) $r['velocidade_kmh'],
            'quando' => date('d/m H:i:s', strtotime($r['registrado_em'])),
            'trilha' => $pontos,
        ];
    }
    return ['ok' => true, 'atualizado_em' => date('H:i:s'), 'rastreadores' => $saida];
}
