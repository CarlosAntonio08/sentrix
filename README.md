# Sentrix Guard Flow

Plataforma web de monitoramento e segurança: câmeras, sensores, rastreamento GPS
com cercas virtuais e alertas classificados por inteligência artificial, com
controle de acesso por cargo e registro de auditoria.

Projeto de Trabalho de Conclusão de Curso (TCC).

## Tecnologias

| Camada | Tecnologia |
|---|---|
| Back-end | PHP 8.0+ (sem framework), PDO |
| Banco de dados | MySQL 5.7+ / MariaDB 10.4+ |
| Front-end | HTML5, CSS3, JavaScript (sem framework) |
| Mapas | Leaflet 1.9.4 + OpenStreetMap |
| E-mail | PHPMailer 6.9 (SMTP do Gmail), incluído em `lib/` |
| Aplicativo | PWA (Progressive Web App): manifest + service worker |
| Servidor | Apache (XAMPP, Laragon, WAMP, MAMP) ou servidor embutido do PHP |

## Funcionalidades

**Disponíveis nesta versão**

- Site institucional que apresenta o serviço
- **Login em duas etapas: e-mail e senha + link de acesso enviado por e-mail**
- **Aplicativo instalável** no celular e no computador (PWA), sem loja de apps
- Cadastro e logout seguros; sessão expira após 30 min sem uso
- Três cargos: **administrador**, **operador** e **visualizador** (ver tabela abaixo)
- Painel com indicadores que se atualizam sozinhos a cada 15 s
- Cadastro, edição e exclusão de dispositivos (câmeras, sensores, GPS, alarmes)
- Monitoramento: status das câmeras e controle de gravação
- Alertas com nível, status (novo → visualizado → resolvido), filtros e exportação CSV
- Detecções de IA com tipo e % de confiança
- Mapa GPS ao vivo (atualiza a cada 10 s), trilha recente e histórico
- Cercas virtuais: alerta automático quando um rastreador sai da área
- Administração de usuários (cargo, ativar/desativar, redefinir senha)
- Log de auditoria com filtros e paginação
- **Simulador de eventos** para demonstrar o sistema sem equipamentos físicos
- Testes funcionais automatizados (79 verificações)

**Planejadas (trabalhos futuros)**

- Vídeo ao vivo no navegador (RTSP → HLS/WebRTC) e controle PTZ/ONVIF
- Modelo real de visão computacional (ex.: YOLO) alimentando a tabela `deteccoes_ia`
- Integração com rastreadores GPS físicos via API
- Notificações push e SMS de alertas; publicação nas lojas (Play Store/App Store); relatórios em PDF

## Permissões por cargo

| Ação | Administrador | Operador | Visualizador |
|---|:---:|:---:|:---:|
| Ver painel, câmeras, alertas, GPS, dispositivos | ✔ | ✔ | ✔ |
| Exportar alertas (CSV) | ✔ | ✔ | ✔ |
| Resolver alertas, ligar/desligar gravação, simulador | ✔ | ✔ | — |
| Cadastrar/editar dispositivos e cercas | ✔ | ✔ | — |
| Excluir dispositivos e cercas | ✔ | — | — |
| Gerenciar usuários e ver log de auditoria | ✔ | — | — |

Contas criadas pela página de cadastro começam como **visualizador**.
Um administrador pode promovê-las em **Admin → Usuários**.

## Como rodar

### Jeito mais fácil (Windows, com XAMPP)

1. Instale o [XAMPP](https://www.apachefriends.org/pt_br/download.html) com PHP 8, na pasta padrão `C:\xampp`.
2. Extraia o `sentrix.zip` em qualquer pasta.
3. Dê dois cliques em **`INICIAR.bat`**.

O script liga o MySQL do XAMPP, cria o banco na primeira vez e abre o site no
navegador. Não é preciso copiar nada para o `htdocs` nem usar o phpMyAdmin.
Para desligar, feche a janela preta. O passo a passo para leigos está em
`COMO-RODAR.txt`.

| Atalho | Para que serve |
|---|---|
| `INICIAR.bat` | Liga tudo e abre o sistema |
| `SIMULAR-EVENTOS.bat` | Gera GPS e alertas a cada 5 s (apresentação) |
| `RESTAURAR-DADOS.bat` | Volta aos dados iniciais (antes da banca) |

### Instalador automático

Em qualquer servidor, na primeira vez que o site é aberto aparece a página
**Instalação** (`instalar.php`), que cria o banco com um clique. Se o MySQL não
for o padrão (`root` sem senha), informe usuário e senha nela: o instalador
grava em `config/config.local.php`. Depois de instalado, só o administrador
pode reabrir essa página, em **Admin → Dados de demonstração**, para restaurar
os dados iniciais.

### Jeito tradicional (XAMPP, Laragon, WAMP, MAMP)

1. Copie a pasta `sentrix` para `htdocs` (XAMPP) ou `www` (Laragon/WAMP).
2. Inicie o Apache e o MySQL.
3. Acesse `http://localhost/sentrix/`. O instalador abre sozinho.

(Se preferir, também dá para importar o `schema.sql` pelo phpMyAdmin.)

### Pelo terminal (qualquer sistema)

```bash
cd sentrix
php scripts/instalar.php            # cria o banco (se ainda não existir)
php -S localhost:8000               # sobe o site em http://localhost:8000
php scripts/instalar.php --restaurar   # volta aos dados iniciais
```

## Login com link por e-mail

1. A pessoa digita **e-mail e senha** em `login.php`.
2. Se a senha estiver certa, o sistema envia um **e-mail com um link de acesso**
   (vale 15 minutos e só pode ser usado uma vez).
3. Ao tocar no link, ela entra no Sentrix **no aparelho onde abriu o e-mail**:
   no celular, abre o app instalado ou o navegador.

**Modo demonstração (padrão):** enquanto o Gmail não é configurado, o e-mail é
gerado mas não sai. Ele aparece numa **caixa de saída** na própria tela, e assim
o fluxo funciona até sem internet.

**Para enviar e-mails de verdade:** entre como admin → **Admin → E-mail**,
informe um Gmail do projeto e uma **senha de app** (o passo a passo está na
própria tela) e clique em **Enviar e-mail de teste**. Os dados ficam em
`config/email.local.php`. Na mesma tela dá para desligar a exigência do link
(o login volta a ser só com senha).

> Se o envio falhar durante a apresentação (ex.: sem internet), o e-mail gerado
> pode ser aberto na caixa de saída **só no computador que roda o sistema**.

## Aplicativo (PWA)

O painel pode ser instalado como aplicativo: ícone na tela inicial, abre em tela
cheia e mostra uma tela própria quando está sem conexão. Instruções em
**Aplicativo** (`download.php`) no menu do site.

- **No computador que roda o sistema** (`INICIAR.bat`): Chrome ou Edge → ícone
  *Instalar* na barra de endereço. Funciona direto.
- **No celular:** o navegador só permite instalar sites com **https://**. Para
  isso, o site precisa estar publicado na internet (hospedagem com HTTPS) e o
  endereço dele informado em **Admin → E-mail → Endereço do site nos links**.

## Usuários de teste

| E-mail | Senha | Cargo |
|---|---|---|
| admin@sentrixguard.com | admin123 | Administrador |
| operador@sentrixguard.com | operador123 | Operador |
| visitante@sentrixguard.com | visitante123 | Visualizador |

> Troque essas senhas se o sistema for publicado na internet.

## Demonstração (dica para a apresentação)

O sistema não está ligado a câmeras e rastreadores reais, então há um
**simulador** que gera posições de GPS e alertas (inclusive detecções de IA e
saídas de cerca virtual):

- pelo botão **⚡ Gerar eventos simulados** no painel (admin/operador); ou
- pelo terminal, deixando o sistema "vivo" durante a apresentação:

```bash
php scripts/simular.php 60 5     # 60 rodadas, uma a cada 5 segundos
```

Deixe a página **GPS** aberta: os marcadores se movem sozinhos.

## Testes automatizados

Com o site rodando em **modo demonstração de e-mail** e os dados recém-restaurados (`RESTAURAR-DADOS.bat`):

```bash
php tests/teste_funcional.php http://localhost/sentrix
# ou
php tests/teste_funcional.php http://localhost:8000
```

Os testes simulam usuários reais e verificam login (inclusive o link por e-mail), permissões, CSRF, XSS,
CRUD, alertas, simulador, GPS, cercas, cadastro, lista de espera, auditoria
e logout. Adicione `--forca-bruta` para testar o bloqueio de tentativas de
login. Os testes criam registros: restaure os dados depois.

O roteiro de testes manuais está em `docs/roteiro-de-testes.md`.

## Segurança implementada

| Ameaça | Proteção |
|---|---|
| SQL Injection | Consultas preparadas (PDO) em todas as consultas |
| XSS | Toda saída passa por `htmlspecialchars` (função `e()`); dados para JS via JSON |
| CSRF | Token por sessão em todos os formulários e no logout |
| Força bruta | 5 tentativas por e-mail/IP a cada 15 min (e teto de 20 por IP) |
| Senhas | `password_hash` (bcrypt) + regra mínima (8 caracteres, letras e números) |
| Sequestro de sessão | `session_regenerate_id` no login; cookie `HttpOnly` e `SameSite`; expiração por inatividade |
| Acesso indevido | Permissões por cargo checadas no servidor em cada ação |
| Vazamento de erros | Detalhes técnicos só aparecem com `APP_DEBUG` ligado |
| Clickjacking | Cabeçalho `X-Frame-Options: DENY` |
| Injeção de fórmula (CSV) | Células iniciadas com `= + - @` são neutralizadas |
| Spam na lista de espera | Campo-armadilha anti-robô + limite por sessão |
| Senha vazada | Segunda etapa: sem acesso ao e-mail, a senha sozinha não entra |
| Link de acesso | Token aleatório de 256 bits, guardado só como hash SHA-256, vale 15 min, uso único; o link novo invalida o anterior |
| Robôs que "abrem" links de e-mail | O link só é gasto ao clicar em **Entrar** (POST), não ao ser visualizado |

## Estrutura de pastas

```
sentrix/
├── index.php, login.php, cadastro.php, logout.php, privacidade.php
├── verificar-email.php, confirmar-login.php, caixa-saida.php   login por e-mail
├── download.php          página do aplicativo (como instalar)
├── manifest.webmanifest, sw.js, offline.html   aplicativo instalável (PWA)
├── configuracoes-email.php   Admin → E-mail (Gmail)
├── painel.php            dashboard
├── monitoramento.php     câmeras e sensores
├── alertas-ia.php        alertas (+ exportar-alertas.php)
├── gps.php, cercas.php   mapa, histórico e cercas virtuais
├── dispositivos.php      CRUD de dispositivos
├── usuarios.php, logs.php   administração
├── simulador.php         ação do botão de simulação
├── gps-dados.php, painel-dados.php, leads.php   endpoints JSON
├── config/config.php     configurações (banco, debug, sessão)
├── includes/             bootstrap, conexão, autenticação, funções, layout, simulador
├── assets/css, assets/js, assets/icons   estilos, scripts e ícones do app
├── lib/PHPMailer/        biblioteca de envio de e-mail
├── instalar.php          instalador automático / restauração dos dados
├── INICIAR.bat, SIMULAR-EVENTOS.bat, RESTAURAR-DADOS.bat   atalhos Windows
├── scripts/              instalar.php, simular.php (terminal), encontrar-php.bat
├── tests/                testes funcionais automatizados
├── docs/                 documentação técnica e roteiro de testes
└── schema.sql            criação do banco + dados de exemplo
```

Mais detalhes (requisitos, casos de uso, modelo de dados, arquitetura) em
`docs/documentacao-tecnica.md`.
