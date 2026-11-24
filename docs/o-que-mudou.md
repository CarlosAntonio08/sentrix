# O que mudou nesta revisão

Resumo das melhorias feitas no projeto original, para orientar a apresentação
e as respostas à banca.

## 1. Controle de acesso por cargo (antes: qualquer conta fazia tudo)

- Antes, qualquer pessoa que se cadastrasse podia resolver alertas e ligar ou
  desligar câmeras. Agora existem permissões reais, checadas **no servidor**
  em cada ação (`PERMISSOES` em `includes/helpers.php`):
  administrador, operador e visualizador.
- Contas novas entram como visualizador (somente leitura).
- Mudanças de cargo e desativação valem na hora, porque o usuário é relido
  do banco a cada página (`includes/auth.php`).

## 2. Telas novas (antes: dados só existiam no `schema.sql`)

| Tela | O que faz |
|---|---|
| `dispositivos.php` | CRUD completo de câmeras, sensores, GPS e alarmes, com validação (IP, RTSP, coordenadas) |
| `cercas.php` | Cria cercas clicando no mapa; ativa, desativa e exclui |
| `usuarios.php` | Admin altera cargo, ativa/desativa e redefine senha |
| `logs.php` | Log de auditoria com filtros e paginação (a tabela já existia, mas não tinha tela) |
| `privacidade.php` | Política de privacidade (LGPD) |
| `exportar-alertas.php` | Exportação dos alertas em CSV |

## 3. O site agora só promete o que o sistema faz

- As funcionalidades da página inicial mostram **✓ disponível** ou
  **◷ planejado** (antes anunciavam SMS, PDF, PTZ, "latência < 500 ms"…).
- O celular da página inicial é identificado como ilustração, sem números falsos.
- A tela de monitoramento avisa que o vídeo ao vivo é trabalho futuro.

## 4. "Tempo real" e "IA" de verdade, de forma demonstrável

- **Simulador de eventos** (`includes/simulador.php`): move os rastreadores,
  gera detecções de IA com % de confiança e alertas de sensores. Funciona por
  botão ou pelo terminal (`php scripts/simular.php 60 5`).
- **Cercas virtuais funcionando:** a fórmula de Haversine calcula a distância
  e gera o alerta "Saída de cerca virtual".
- **Mapa e painel se atualizam sozinhos** (a cada 10 e 15 s) por meio de
  endpoints JSON (`gps-dados.php`, `painel-dados.php`).

## 5. Segurança

- Token **CSRF** em todos os formulários e no logout (que passou a ser POST).
- `session_regenerate_id` no login; cookie `HttpOnly` e `SameSite`;
  expiração após 30 min sem uso.
- **Limite de tentativas de login** (5 por e-mail/IP em 15 min).
- Senha mínima de 8 caracteres, com letras e números.
- Erros do banco não aparecem mais na tela (só com `APP_DEBUG`).
- Mensagens agora passam pela sessão ("flash"), e não mais por `?msg=` na URL,
  que deixava qualquer pessoa montar um link com o texto que quisesse.
- Cabeçalhos de segurança (`X-Frame-Options` e outros); `.htaccess` bloqueia
  `config/`, `includes/`, `scripts/`, `tests/` e arquivos `.sql`/`.md`.
- Lista de espera com consentimento, campo-armadilha anti-robô e limite de envios.
- Proteção contra "injeção de fórmula" no CSV.

## 6. Correções de bugs

- `</div>` sobrando na seção "Sobre" da página inicial.
- Os cards de funcionalidades usavam `all: unset`, que apagava o próprio
  visual (borda, fundo e espaçamento).
- `BASE_URL` fixo em `/sentrix` (o README dizia que era vazio) quebrava o CSS
  fora dessa pasta. Agora os caminhos são relativos e funcionam em qualquer pasta.
- Acentos quebrados ao importar o `schema.sql` pelo terminal: foi adicionado
  `SET NAMES utf8mb4`.
- Centenas de linhas de CSS duplicadas ou escritas dentro das páginas foram
  centralizadas em `style.css`; o JavaScript de cada página foi para `assets/js/`.

## 7. Qualidade, acessibilidade e documentação

- Layout revisado no celular: os alertas se empilham e não há rolagem horizontal.
- Acessibilidade: link "pular para o conteúdo", foco preso no modal, `aria-*`
  no menu e respeito a "reduzir movimento".
- Banco: novos índices, tipo de alerta `saida_cerca`, campos de consentimento
  e mais dados de exemplo (3º usuário, 2º rastreador, histórico).
- **Testes automatizados**: `tests/teste_funcional.php`, com mais de 60 verificações.
- **Documentação**: `README.md`, `docs/documentacao-tecnica.md` (requisitos,
  casos de uso, modelo de dados e arquitetura em diagramas) e
  `docs/roteiro-de-testes.md`.

## 8. Pronto para rodar no Windows

- **`INICIAR.bat`**: encontra o PHP do XAMPP (ou do Laragon), liga o MySQL,
  cria o banco na primeira vez e abre o site no navegador. Não precisa copiar
  a pasta para o `htdocs` nem usar o phpMyAdmin.
- **Instalador automático** (`instalar.php`): abre sozinho na primeira vez em
  qualquer servidor e cria o banco com um clique. Aceita usuário e senha de um
  MySQL fora do padrão.
- **`RESTAURAR-DADOS.bat`** (ou Admin → Dados de demonstração): volta aos
  dados iniciais antes da apresentação.
- **`SIMULAR-EVENTOS.bat`**: gera eventos a cada 5 s durante a demonstração.
- **`COMO-RODAR.txt`**: passo a passo para quem não é da área.

## 9. Login com link por e-mail e aplicativo

Implementa a ideia original do grupo: *"a pessoa coloca e-mail e senha e recebe
no e-mail um link que a direciona para o aplicativo"*.

- **Login em duas etapas:** depois da senha, o sistema envia um e-mail com um
  link de acesso (`includes/login_email.php`). O link vale 15 minutos e só pode
  ser usado uma vez. No banco fica guardado apenas o *hash* do token, nunca o
  token em si.
- **Envio pelo Gmail** com a biblioteca PHPMailer (já incluída em `lib/`),
  configurado pelo admin em **Admin → E-mail**, sem editar código. Essa tela tem
  o passo a passo da senha de app, um botão de e-mail de teste e o histórico de
  envios.
- **Modo demonstração:** sem Gmail configurado, o e-mail aparece numa caixa de
  saída na tela. Assim dá para apresentar o fluxo até sem internet.
- **Aplicativo instalável (PWA):** o painel ganhou ícone, *manifest*,
  *service worker* e tela offline, e pode ser instalado no celular ou no
  computador sem loja de aplicativos. A página **Aplicativo** explica como
  instalar em Android, iPhone e computador.
- **"Direcionar para o aplicativo":** o link do e-mail abre o Sentrix no
  aparelho onde foi tocado. No celular com o app instalado, abre o próprio app.

**Por que PWA e não um app de loja?** Um app nativo exigiria outra linguagem
(Kotlin/Swift/Flutter), conta paga na Play Store e, para iPhone, um Mac e conta
paga da Apple. O PWA reaproveita todo o sistema web, instala com um toque e é
uma tecnologia usada por empresas como Twitter/X, Starbucks e Uber. É uma
escolha fácil de justificar para a banca.

Bugs encontrados e corrigidos durante os testes desta etapa:

- Logo depois de salvar as configurações de e-mail, o cache do PHP (OPcache,
  ligado por padrão no XAMPP) continuava usando os valores antigos por alguns
  segundos. Agora o cache é limpo ao salvar.
- Abrir uma página interna no meio do login apagava a etapa do e-mail.
- Um servidor de e-mail que não responde poderia travar o site por até 5
  minutos. Agora o limite é de 12 segundos.
