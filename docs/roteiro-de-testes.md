# Roteiro de testes manuais — Sentrix Guard Flow

Cenários para validar o sistema manualmente (e para anexar à monografia como
evidência de testes). Antes de começar, reimporte o `schema.sql` para ter os
dados iniciais. Usuários de teste: veja o README.

Os mesmos fluxos também são verificados automaticamente por
`php tests/teste_funcional.php <url-do-site>`.

Login nos cenários: depois de digitar e-mail e senha, conclua a etapa do e-mail.
No modo demonstração, clique em "Abrir a caixa de saída" e depois em
"Entrar no Sentrix"; com o Gmail configurado, abra o link recebido.

---

Scenario 1 — Access to internal pages requires login

Steps (Unauthenticated access):
1. Make sure you are logged out.
2. Open `painel.php` directly in the browser address bar.
3. Repeat with `gps.php`, `dispositivos.php` and `usuarios.php`.

Result: Every page redirects to the login screen with the message "Faça login para continuar."

---

Scenario 2 — Login with valid and invalid credentials

Steps (Login):
1. Open `login.php`.
2. Enter `admin@sentrixguard.com` with the password `wrong-password` and click "Entrar".
3. Enter `admin@sentrixguard.com` with the password `admin123` and click "Entrar".

Result: Step 2 shows "E-mail ou senha incorretos." without revealing whether the e-mail exists. Step 3 opens the "Confirme pelo e-mail" screen (step 2 of 2) and does not open the dashboard yet. After the link is confirmed, the dashboard shows "OLÁ, Administrador" and the role "Administrador".

---

Scenario 3 — Login is blocked after repeated failures

Steps (Brute-force protection):
1. Open `login.php`.
2. Enter `teste@exemplo.com` with a wrong password and submit 5 times.
3. Submit a 6th attempt with the same e-mail.
4. Log in with `operador@sentrixguard.com` / `operador123`.

Result: Step 3 shows "Muitas tentativas sem sucesso. Aguarde 15 minutos…". Step 4 succeeds, because the block applies only to the attacked e-mail. Admin > Log de auditoria lists the `login_falha` events.

---

Scenario 4 — New account starts as Viewer (read-only)

Steps (Sign-up):
1. Open `cadastro.php`.
2. Fill in the name, a new e-mail and the password `123`, then submit.
3. Change the password to `senhaForte1` (both fields), leave the privacy checkbox unchecked and submit.
4. Check the privacy checkbox and submit.
5. Log in with the new account.

Result: Step 2 shows the password rule (8+ characters, letters and numbers). Step 3 asks for acceptance of the Privacy Policy. Step 4 redirects to login with "Conta criada!". In step 5 the dashboard shows the role "Visualizador", without the "Gerar eventos simulados" box.

---

Scenario 5 — Viewer cannot change data

Steps (Role permissions — Viewer):
1. Log in as `visitante@sentrixguard.com` / `visitante123`.
2. Open Monitoramento, Alertas and Dispositivos.
3. Open `usuarios.php` directly in the address bar.

Result: No recording, resolve, edit or delete buttons are shown, and Dispositivos shows a read-only notice. Step 3 redirects to the dashboard with "Você não tem permissão para esta ação."

---

Scenario 6 — Operator handles an alert

Steps (Alert workflow):
1. Log in as `operador@sentrixguard.com` / `operador123`.
2. Open Alertas and filter by "Novos".
3. On alert "#4 · Detecção por IA", click "Marcar visualizado".
4. On the same alert, click "Resolver".
5. Filter by "Resolvidos" and click "Reabrir" on that alert.

Result: After each action a green confirmation message appears and the status pill changes (Novo → Visualizado → Resolvido → Novo). The IA line shows "Rosto desconhecido (96,4%)". The admin's audit log records each change with the operator's name.

---

Scenario 7 — Export alerts to CSV

Steps (Export):
1. Logged in with any role, open Alertas.
2. Click "Exportar CSV".
3. Open the downloaded file in Excel, LibreOffice or Google Sheets.

Result: The file opens with correct accents and the columns ID, Data/hora, Nível, Tipo, Status, Dispositivo, Descrição, Detecção IA and Confiança (%).

---

Scenario 8 — Create, edit and delete a camera

Steps (Device CRUD):
1. Log in as operator and open Dispositivos → "+ Novo dispositivo".
2. Name "Câmera Portão Lateral", type Câmera, IP `999.1.1.1`, then submit.
3. Fix the IP to `192.168.0.120`, set the RTSP URL `rtsp://192.168.0.120/stream1` and submit.
4. Open Monitoramento.
5. Back in Dispositivos, click "Editar", change the status to Manutenção and save.
6. Check that the operator has no "Excluir" button, then log in as admin and delete the camera.

Result: Step 2 shows "Endereço IP inválido." Step 3 saves and lists the camera. Step 4 shows its card. Step 5 updates the status. In step 6 only the admin sees "Excluir", a confirmation is requested, and the camera disappears from the list.

---

Scenario 9 — Start and stop camera recording

Steps (Recording control):
1. Log in as operator and open Monitoramento.
2. On "Câmera Estacionamento" (online), click "Iniciar gravação".
3. Click "Parar gravação".
4. On "Câmera Doca de Carga" (manutenção), click "Iniciar gravação".

Result: Step 2 shows the "● REC" badge and a confirmation message. Step 3 removes the badge. Step 4 is refused with "não está online".

---

Scenario 10 — Live GPS map and simulator

Steps (GPS tracking):
1. Log in as operator and open GPS.
2. Note the position of the markers and the "Ao vivo — atualizado às …" time.
3. Click "Simular movimento" (or run `php scripts/simular.php 10 5` in a terminal).
4. Wait about 10 seconds without reloading the page.

Result: The map shows 2 trackers, the red trail and the "Perímetro da Sede" geofence. After the simulation the markers move, the trail grows, the update time changes and new rows appear in the position history.

---

Scenario 11 — Geofence exit generates an alert

Steps (Geofence):
1. Log in as operator and open GPS → "Cercas virtuais".
2. Click on the map exactly on one tracker, set a 40 m radius, name it "Cerca Teste" and create it.
3. Click "Gerar eventos simulados" on the dashboard several times.
4. Open Alertas.

Result: When the tracker leaves the circle, an alert "Saída de cerca virtual" (level Médio) appears with the text "<tracker> saiu da cerca virtual "Cerca Teste"". The alert is not repeated while the tracker stays outside.

---

Scenario 12 — Administrator manages users

Steps (User administration):
1. Log in as admin and open Admin → Usuários.
2. Change the role of the account created in Scenario 4 to Operador.
3. In another browser (or private window) logged in as that account, reload the dashboard.
4. As admin, click "Desativar" on that account.
5. In the other browser, reload any page.
6. Try to change your own role or deactivate yourself.

Result: In step 3 the role changes immediately to Operador. In step 5 the user is logged out. In step 6 the controls are not shown for your own account. All actions are listed in "Log de auditoria".

---

Scenario 13 — Waiting list with consent (LGPD)

Steps (Lead capture):
1. Open `download.php` while logged out.
2. Type an e-mail and click "Avisar-me" without checking the consent box.
3. Check the box and click "Avisar-me".
4. Submit the same e-mail again.

Result: Step 2 asks for consent. Step 3 shows "E-mail registrado!". Step 4 shows "Você já está na nossa lista!". The `leads` table stores the consent date and IP.

---

Scenario 14 — Malicious input is displayed as text (XSS)

Steps (Output escaping):
1. Log in as operator and create a sensor named `<script>alert(1)</script>`.
2. Open Dispositivos, Monitoramento and the dashboard.

Result: No pop-up appears. The name is displayed literally as text on every page.

---

Scenario 15 — Logout and session expiry

Steps (Session):
1. While logged in, click "Sair".
2. Use the browser "Back" button and reload the page.
3. Log in again and leave the tab idle for more than 30 minutes, then click any link.

Result: Step 2 redirects to the login page. In step 3 the system shows "Sua sessão expirou por inatividade."

---

Scenario 16 — Responsive layout

Steps (Mobile):
1. Open the site on a phone (or browser DevTools at 390 px width).
2. Open the menu (☰), navigate to Painel, Alertas and GPS.
3. Open a feature on the home page and close it with the "X" and with Esc.

Result: There is no horizontal scrolling on the pages (wide tables scroll inside their own box). The menu opens and closes. Cards and alerts stack in a single column. The feature modal opens and closes correctly.

---

Scenario 17 — E-mail confirmation link in demo mode

Steps (E-mail login — demo mode):
1. Make sure Admin > E-mail shows "Modo demonstração".
2. Log out, open `login.php` and log in as `operador@sentrixguard.com` / `operador123`.
3. In another browser tab, open `painel.php`.
4. Back on the "Confirme pelo e-mail" screen, click "Abrir a caixa de saída".
5. In the e-mail shown, click "Entrar no Sentrix", then click "Entrar no Sentrix" again on the confirmation page.

Result: Step 3 redirects to login, because the password alone does not grant access. Step 4 shows the e-mail "Seu link de acesso ao Sentrix" with the user's first name, the 15-minute validity and the link. Step 5 opens the dashboard as Operador, and Admin > Log de auditoria lists `login_link_enviado` and `login` ("Login confirmado pelo link enviado por e-mail").

---

Scenario 18 — Link is single-use and replaced by a newer one

Steps (E-mail login — link security):
1. Log in as operator and open the e-mail in the outbox. Copy the link address (right click > copy link) without opening it.
2. Wait 60 seconds and click "Reenviar link".
3. Paste the copied (old) link in the address bar.
4. Open the newest e-mail in the outbox and complete the login.
5. Log out and paste the newest link in the address bar again.

Result: Step 2 shows "Enviamos um novo link. O anterior deixou de valer." Step 3 shows "Link inválido ou expirado". Step 4 logs in. Step 5 shows "Link inválido ou expirado", because each link works only once.

---

Scenario 19 — Real e-mail delivery through Gmail

Steps (E-mail configuration):
1. Create a Gmail app password, following the instructions on the Admin > E-mail page.
2. Log in as admin, open Admin > E-mail, enter the Gmail address and the app password, and click "Salvar".
3. Click "Enviar e-mail de teste".
4. Create an account in "Cadastre-se" using a real e-mail address you can open, log out and log in with it.
5. Open the e-mail on a phone and tap "Entrar no Sentrix".

Result: Step 2 changes the status card to "Envio ativo". Step 3 shows "E-mail de teste enviado" and the message arrives (check Spam). In step 4 the screen shows the masked address, and the "Abrir a caixa de saída" button is not offered. In step 5 the confirmation page opens on the phone and logs in there. Note: on a phone, the link only reaches the system if the site is published on the internet or "Endereço do site nos links" points to an address the phone can reach.

---

Scenario 20 — Wrong Gmail password is explained to the user

Steps (E-mail configuration — error handling):
1. In Admin > E-mail, save the normal Gmail password (not an app password).
2. Click "Enviar e-mail de teste".
3. Open the "Últimos e-mails" table.

Result: After a few seconds the page shows "O Gmail recusou o login. Confira o e-mail e use uma 'senha de app' de 16 letras…". The table lists the attempt with status "Falhou", and the technical error appears when hovering over it.

---

Scenario 21 — Install the system as an app

Steps (PWA — desktop):
1. Start the system with INICIAR.bat and open it in Chrome or Edge.
2. Log in and check the "Sentrix como aplicativo" box on the dashboard.
3. Click "Instalar" (or the install icon in the address bar) and confirm.
4. Open the installed app from the Start menu or desktop.
5. Close the INICIAR.bat window and reload the app.

Result: The install prompt shows the name "Sentrix Guard Flow" and the shield icon. The app opens in its own window, without the address bar, on the dashboard, and the "Sentrix como aplicativo" box no longer appears inside it. In step 5 the app shows the "Sem conexão" screen with a "Tentar novamente" button instead of a browser error.

