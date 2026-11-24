-- =========================================================
-- SENTRIX GUARD FLOW — BANCO DE DADOS
-- MySQL / MariaDB
-- =========================================================
-- Como usar:
--   1) Importe este arquivo no phpMyAdmin, HeidiSQL, DBeaver
--      ou via linha de comando:
--        mysql -u root -p < schema.sql
--   2) Ajuste as credenciais em config/config.php
--
-- ATENÇÃO: este script APAGA e recria o banco sentrix_guard_flow.
-- =========================================================

SET NAMES utf8mb4;  -- garante acentos corretos ao importar pelo terminal

SET NAMES utf8mb4;  -- garante os acentos corretos ao importar pelo terminal

DROP DATABASE IF EXISTS sentrix_guard_flow;
CREATE DATABASE sentrix_guard_flow
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE sentrix_guard_flow;

-- =========================================================
-- 1) USUÁRIOS (login do painel administrativo)
-- =========================================================
CREATE TABLE usuarios (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome            VARCHAR(120)        NOT NULL,
    email           VARCHAR(160)        NOT NULL UNIQUE,
    senha_hash      VARCHAR(255)        NOT NULL,
    cargo           ENUM('admin','operador','visualizador') NOT NULL DEFAULT 'operador',
    ativo           TINYINT(1)          NOT NULL DEFAULT 1,
    ultimo_login    DATETIME            NULL,
    criado_em       DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =========================================================
-- 2) DISPOSITIVOS (qualquer equipamento conectado: câmera,
--    sensor, rastreador GPS, alarme, etc.)
-- =========================================================
CREATE TABLE dispositivos (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome            VARCHAR(120)        NOT NULL,
    tipo            ENUM('camera','sensor','gps','alarme','outro') NOT NULL,
    status          ENUM('online','offline','manutencao') NOT NULL DEFAULT 'offline',
    localizacao     VARCHAR(160)        NULL,
    usuario_id      INT UNSIGNED        NULL,           -- responsável/dono
    criado_em       DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_dispositivo_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        ON DELETE SET NULL
) ENGINE=InnoDB;

-- =========================================================
-- 3) CÂMERAS (detalhes específicos de dispositivos tipo câmera)
-- =========================================================
CREATE TABLE cameras (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    dispositivo_id  INT UNSIGNED        NOT NULL,
    modelo          VARCHAR(120)        NULL,
    tipo            ENUM('ptz','fixa')  NOT NULL DEFAULT 'fixa',
    resolucao       VARCHAR(20)         DEFAULT 'Full HD',
    ip_address      VARCHAR(45)         NULL,
    rtsp_url        VARCHAR(255)        NULL,
    gravando        TINYINT(1)          NOT NULL DEFAULT 0,
    CONSTRAINT fk_camera_dispositivo
        FOREIGN KEY (dispositivo_id) REFERENCES dispositivos(id)
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- =========================================================
-- 4) ALERTAS (eventos gerados por sensores, câmeras ou IA)
-- =========================================================
CREATE TABLE alertas (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    dispositivo_id  INT UNSIGNED        NULL,
    tipo            ENUM('movimento','intrusao','falha_equipamento',
                         'acesso_nao_autorizado','queda_conexao',
                         'deteccao_ia','saida_cerca','outro') NOT NULL,
    nivel           ENUM('baixo','medio','critico') NOT NULL DEFAULT 'medio',
    descricao       VARCHAR(255)        NULL,
    imagem_url      VARCHAR(255)        NULL,
    status          ENUM('novo','visualizado','resolvido') NOT NULL DEFAULT 'novo',
    criado_em       DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_alertas_status (status, nivel),
    INDEX idx_alertas_data (criado_em),
    CONSTRAINT fk_alerta_dispositivo
        FOREIGN KEY (dispositivo_id) REFERENCES dispositivos(id)
        ON DELETE SET NULL
) ENGINE=InnoDB;

-- =========================================================
-- 5) DETECÇÕES DE IA (detalhe técnico de alertas gerados por IA)
-- =========================================================
CREATE TABLE deteccoes_ia (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    alerta_id       INT UNSIGNED        NOT NULL,
    tipo_deteccao   ENUM('rosto_desconhecido','veiculo','pessoa',
                         'animal','objeto_abandonado',
                         'comportamento_suspeito','aglomeracao') NOT NULL,
    confianca       DECIMAL(5,2)        NOT NULL DEFAULT 0.00, -- % de precisão
    criado_em       DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_deteccao_alerta
        FOREIGN KEY (alerta_id) REFERENCES alertas(id)
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- =========================================================
-- 6) DISPOSITIVOS GPS (veículos, pessoas ou ativos rastreados)
-- =========================================================
CREATE TABLE gps_dispositivos (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    dispositivo_id  INT UNSIGNED        NULL,
    nome            VARCHAR(120)        NOT NULL,
    tipo            ENUM('veiculo','pessoa','ativo') NOT NULL DEFAULT 'veiculo',
    ativo           TINYINT(1)          NOT NULL DEFAULT 1,
    CONSTRAINT fk_gps_dispositivo
        FOREIGN KEY (dispositivo_id) REFERENCES dispositivos(id)
        ON DELETE SET NULL
) ENGINE=InnoDB;

-- =========================================================
-- 7) HISTÓRICO DE LOCALIZAÇÕES GPS
-- =========================================================
CREATE TABLE gps_localizacoes (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    gps_dispositivo_id  INT UNSIGNED    NOT NULL,
    latitude            DECIMAL(10,7)   NOT NULL,
    longitude           DECIMAL(10,7)   NOT NULL,
    velocidade_kmh      DECIMAL(6,2)    DEFAULT 0.00,
    registrado_em       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_localizacao_gps
        FOREIGN KEY (gps_dispositivo_id) REFERENCES gps_dispositivos(id)
        ON DELETE CASCADE,
    INDEX idx_gps_tempo (gps_dispositivo_id, registrado_em)
) ENGINE=InnoDB;

-- =========================================================
-- 8) CERCAS VIRTUAIS (geofencing)
-- =========================================================
CREATE TABLE cercas_virtuais (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome                VARCHAR(120)    NOT NULL,
    gps_dispositivo_id  INT UNSIGNED    NULL,          -- NULL = aplica a todos
    centro_lat          DECIMAL(10,7)   NOT NULL,
    centro_lng          DECIMAL(10,7)   NOT NULL,
    raio_metros         INT UNSIGNED    NOT NULL DEFAULT 100,
    ativa               TINYINT(1)      NOT NULL DEFAULT 1,
    criado_em           DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_cerca_gps
        FOREIGN KEY (gps_dispositivo_id) REFERENCES gps_dispositivos(id)
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- =========================================================
-- 9) LOG DE EVENTOS / AUDITORIA (ações no sistema)
-- =========================================================
CREATE TABLE logs_eventos (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id      INT UNSIGNED        NULL,
    tipo_evento     VARCHAR(80)         NOT NULL,   -- ex: 'login', 'alerta_resolvido'
    descricao       VARCHAR(255)        NULL,
    ip_origem       VARCHAR(45)         NULL,
    criado_em       DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_logs_data (criado_em),
    INDEX idx_logs_tentativas (tipo_evento, ip_origem, criado_em),   -- limite de tentativas de login
    CONSTRAINT fk_log_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        ON DELETE SET NULL
) ENGINE=InnoDB;

-- =========================================================
-- 10) LEADS (captação de e-mail da página download.php)
-- =========================================================
CREATE TABLE leads (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email           VARCHAR(160)        NOT NULL UNIQUE,
    origem          VARCHAR(80)         DEFAULT 'download.php',
    consentimento_em DATETIME           NULL,           -- LGPD: quando a pessoa concordou
    ip_origem       VARCHAR(45)         NULL,
    criado_em       DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- =========================================================
-- 11) TOKENS DE LOGIN (link de confirmação enviado por e-mail)
--     Guarda só o hash SHA-256 do token: quem vir o banco não
--     consegue usar o link.
-- =========================================================
CREATE TABLE login_tokens (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id      INT UNSIGNED        NOT NULL,
    token_hash      CHAR(64)            NOT NULL UNIQUE,
    expira_em       DATETIME            NOT NULL,
    usado_em        DATETIME            NULL,
    ip_origem       VARCHAR(45)         NULL,
    criado_em       DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tokens_usuario (usuario_id, criado_em),
    CONSTRAINT fk_token_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- =========================================================
-- 12) E-MAILS ENVIADOS (histórico e caixa de saída de demonstração)
-- =========================================================
CREATE TABLE emails_enviados (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id      INT UNSIGNED        NULL,
    destinatario    VARCHAR(160)        NOT NULL,
    assunto         VARCHAR(200)        NOT NULL,
    corpo_html      MEDIUMTEXT          NOT NULL,
    status          ENUM('enviado','falhou','demonstracao') NOT NULL,
    erro            VARCHAR(255)        NULL,
    criado_em       DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_emails_usuario (usuario_id, criado_em),
    CONSTRAINT fk_email_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        ON DELETE SET NULL
) ENGINE=InnoDB;

-- =========================================================
-- DADOS DE EXEMPLO
-- =========================================================

-- Usuários de teste (TROQUE as senhas fora do ambiente de testes!)
--   admin@sentrixguard.com      senha: admin123     (administrador)
--   operador@sentrixguard.com   senha: operador123  (operador)
--   visitante@sentrixguard.com  senha: visitante123 (visualizador)
-- Hashes gerados com password_hash(..., PASSWORD_DEFAULT)
INSERT INTO usuarios (nome, email, senha_hash, cargo) VALUES
('Administrador', 'admin@sentrixguard.com',
 '$2y$10$0Ch..kyK0IBuA2/CdyXDDeOoumqKa2jC8m045Sz7nYpTXOQ77H5oq', 'admin'),
('Operador Teste', 'operador@sentrixguard.com',
 '$2y$10$m37mLoPbfZdIaM80udwjuu1eC8CI5s/vMez5WM0hXDZ8v8o0ZMdhG', 'operador'),
('Visitante Teste', 'visitante@sentrixguard.com',
 '$2y$10$BtOOVC2G98E2VzkCI4I2XO.2Jrt4.NTMSW1I.Mw.8J3vGi0.uIdXy', 'visualizador');

-- Dispositivos
INSERT INTO dispositivos (nome, tipo, status, localizacao, usuario_id) VALUES
('Câmera Entrada Principal', 'camera', 'online', 'Portão Principal', 1),
('Câmera Estacionamento',    'camera', 'online', 'Estacionamento', 1),
('Sensor de Movimento - Corredor', 'sensor', 'online', 'Corredor A', 1),
('Rastreador Veículo 01',    'gps', 'online', 'Frota', 1),
('Alarme Central',           'alarme', 'offline', 'Sala de Segurança', 1),
('Câmera Doca de Carga',     'camera', 'manutencao', 'Doca 2', 1),
('Rastreador Moto 02',       'gps', 'online', 'Frota', 1);

-- Câmeras (detalhe)
INSERT INTO cameras (dispositivo_id, modelo, tipo, resolucao, ip_address, rtsp_url, gravando) VALUES
(1, 'Sentrix Cam PTZ 4K', 'ptz',  '4K',      '192.168.0.101', 'rtsp://192.168.0.101/stream1', 1),
(2, 'Sentrix Cam Fixa HD', 'fixa', 'Full HD', '192.168.0.102', 'rtsp://192.168.0.102/stream1', 0),
(6, 'Sentrix Cam Fixa HD', 'fixa', 'Full HD', '192.168.0.106', 'rtsp://192.168.0.106/stream1', 0);

-- Alertas
INSERT INTO alertas (dispositivo_id, tipo, nivel, descricao, status, criado_em) VALUES
(2, 'deteccao_ia', 'medio',   'Veículo detectado no estacionamento fora do horário', 'resolvido', NOW() - INTERVAL 2 DAY),
(3, 'movimento',   'medio',   'Movimento detectado no corredor A fora do horário', 'visualizado', NOW() - INTERVAL 5 HOUR),
(5, 'falha_equipamento', 'critico', 'Alarme central perdeu conexão', 'novo', NOW() - INTERVAL 2 HOUR),
(1, 'deteccao_ia', 'critico', 'Rosto desconhecido detectado na entrada principal', 'novo', NOW() - INTERVAL 20 MINUTE);

-- Detecções de IA vinculadas ao primeiro alerta
INSERT INTO deteccoes_ia (alerta_id, tipo_deteccao, confianca) VALUES
(1, 'veiculo', 91.20),
(4, 'rosto_desconhecido', 96.40);

-- Dispositivo GPS
INSERT INTO gps_dispositivos (dispositivo_id, nome, tipo) VALUES
(4, 'Van de Ronda 01', 'veiculo'),
(7, 'Moto de Ronda 02', 'veiculo');

-- Histórico de localização
INSERT INTO gps_localizacoes (gps_dispositivo_id, latitude, longitude, velocidade_kmh, registrado_em) VALUES
(1, -22.9068, -43.1729, 42.50, NOW() - INTERVAL 30 MINUTE),
(1, -22.9070, -43.1731, 38.10, NOW() - INTERVAL 20 MINUTE),
(1, -22.9075, -43.1740,  0.00, NOW() - INTERVAL 10 MINUTE),
(2, -22.9052, -43.1765, 35.00, NOW() - INTERVAL 25 MINUTE),
(2, -22.9046, -43.1752, 28.40, NOW() - INTERVAL 15 MINUTE),
(2, -22.9041, -43.1738, 31.20, NOW() - INTERVAL 5 MINUTE);

-- Cerca virtual de exemplo
INSERT INTO cercas_virtuais (nome, gps_dispositivo_id, centro_lat, centro_lng, raio_metros) VALUES
('Perímetro da Sede', 1, -22.9068, -43.1729, 300);

-- Log de eventos
INSERT INTO logs_eventos (usuario_id, tipo_evento, descricao, ip_origem, criado_em) VALUES
(1, 'login', 'Login realizado com sucesso', '127.0.0.1', NOW() - INTERVAL 1 DAY),
(1, 'alerta_resolvido', 'Alerta #1 marcado como resolvido', '127.0.0.1', NOW() - INTERVAL 1 DAY),
(2, 'login', 'Login realizado com sucesso', '127.0.0.1', NOW() - INTERVAL 5 HOUR),
(2, 'alerta_visualizado', 'Alerta #2 marcado como visualizado', '127.0.0.1', NOW() - INTERVAL 4 HOUR);
