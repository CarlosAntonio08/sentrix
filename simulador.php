<?php
/**
 * Simulador de eventos para demonstração.
 *
 * Como o projeto não está ligado a câmeras, sensores e rastreadores físicos,
 * este módulo gera dados realistas para mostrar o sistema funcionando:
 *  - move cada rastreador GPS ativo alguns metros e grava a nova posição;
 *  - verifica as cercas virtuais e cria um alerta quando um rastreador sai delas;
 *  - de forma aleatória, gera alertas de câmeras (detecção por IA) e sensores.
 *
 * Pode ser chamado pelo botão "Gerar eventos" no painel ou pelo terminal:
 *     php scripts/simular.php
 *
 * IMPORTANTE (para a banca): as detecções de IA aqui são simuladas. A
 * estrutura de dados (tabela deteccoes_ia) está pronta para receber
 * resultados de um modelo real de visão computacional no futuro.
 */

require_once __DIR__ . '/helpers.php';

function simular_eventos(PDO $pdo, bool $forcarAlerta = false): array
{
    $resumo = ['posicoes' => 0, 'alertas' => 0, 'saidas_cerca' => 0];

    $pdo->beginTransaction();
    try {
        /* ---------- 1) Movimento dos rastreadores GPS ---------- */
        $rastreadores = $pdo->query(
            "SELECT g.id, g.nome, g.dispositivo_id, l.latitude, l.longitude
             FROM gps_dispositivos g
             JOIN gps_localizacoes l ON l.id = (
                 SELECT id FROM gps_localizacoes WHERE gps_dispositivo_id = g.id
                 ORDER BY registrado_em DESC, id DESC LIMIT 1)
             WHERE g.ativo = 1"
        )->fetchAll();

        $insPos = $pdo->prepare(
            'INSERT INTO gps_localizacoes (gps_dispositivo_id, latitude, longitude, velocidade_kmh) VALUES (?, ?, ?, ?)'
        );
        $cercasStmt = $pdo->prepare(
            'SELECT id, nome, centro_lat, centro_lng, raio_metros FROM cercas_virtuais
             WHERE ativa = 1 AND (gps_dispositivo_id = ? OR gps_dispositivo_id IS NULL)'
        );

        foreach ($rastreadores as $r) {
            $latAnt = (float) $r['latitude'];
            $lngAnt = (float) $r['longitude'];

            // Deslocamento aleatório de até ~150 m (1 grau de latitude ≈ 111 km)
            $parado = mt_rand(1, 100) <= 20;
            $passo  = $parado ? 0 : mt_rand(30, 150) / 111000;
            $angulo = deg2rad(mt_rand(0, 359));
            $latNova = $latAnt + $passo * cos($angulo);
            $lngNova = $lngAnt + $passo * sin($angulo) / max(cos(deg2rad($latAnt)), 0.01);
            $velocidade = $parado ? 0 : mt_rand(150, 600) / 10; // 15 a 60 km/h

            $insPos->execute([$r['id'], round($latNova, 7), round($lngNova, 7), $velocidade]);
            $resumo['posicoes']++;

            // Cercas virtuais: alerta quando passa de dentro para fora
            $cercasStmt->execute([$r['id']]);
            foreach ($cercasStmt->fetchAll() as $c) {
                $raio = (int) $c['raio_metros'];
                $antesDentro = distancia_metros($latAnt, $lngAnt, (float) $c['centro_lat'], (float) $c['centro_lng']) <= $raio;
                $agoraFora   = distancia_metros($latNova, $lngNova, (float) $c['centro_lat'], (float) $c['centro_lng']) > $raio;
                if ($antesDentro && $agoraFora) {
                    criar_alerta($pdo, $r['dispositivo_id'] ? (int) $r['dispositivo_id'] : null, 'saida_cerca', 'medio',
                        "{$r['nome']} saiu da cerca virtual \"{$c['nome']}\"");
                    $resumo['saidas_cerca']++;
                    $resumo['alertas']++;
                }
            }
        }

        /* ---------- 2) Alertas aleatórios de câmeras e sensores ---------- */
        if ($forcarAlerta || mt_rand(1, 100) <= 60) {
            $disp = $pdo->query(
                "SELECT id, nome, tipo, localizacao FROM dispositivos
                 WHERE status = 'online' AND tipo IN ('camera','sensor') ORDER BY RAND() LIMIT 1"
            )->fetch();

            if ($disp) {
                $local = $disp['localizacao'] ?: $disp['nome'];
                if ($disp['tipo'] === 'camera') {
                    $deteccoes = ['pessoa', 'veiculo', 'rosto_desconhecido', 'animal', 'objeto_abandonado', 'comportamento_suspeito'];
                    $tipoDet   = $deteccoes[array_rand($deteccoes)];
                    $confianca = mt_rand(6500, 9900) / 100;
                    $nivel     = in_array($tipoDet, ['rosto_desconhecido', 'comportamento_suspeito'], true) ? 'critico'
                               : ($tipoDet === 'animal' ? 'baixo' : 'medio');
                    $alertaId  = criar_alerta($pdo, (int) $disp['id'], 'deteccao_ia', $nivel,
                        rotulo('deteccao', $tipoDet) . " detectado(a) em {$local}");
                    $pdo->prepare('INSERT INTO deteccoes_ia (alerta_id, tipo_deteccao, confianca) VALUES (?, ?, ?)')
                        ->execute([$alertaId, $tipoDet, $confianca]);
                } else {
                    criar_alerta($pdo, (int) $disp['id'], 'movimento', mt_rand(0, 1) ? 'baixo' : 'medio',
                        "Movimento detectado em {$local}");
                }
                $resumo['alertas']++;
            }
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    return $resumo;
}

function criar_alerta(PDO $pdo, ?int $dispositivoId, string $tipo, string $nivel, string $descricao): int
{
    $pdo->prepare('INSERT INTO alertas (dispositivo_id, tipo, nivel, descricao) VALUES (?, ?, ?, ?)')
        ->execute([$dispositivoId, $tipo, $nivel, mb_substr($descricao, 0, 255)]);
    return (int) $pdo->lastInsertId();
}
