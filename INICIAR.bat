@echo off
setlocal
title Sentrix Guard Flow
cd /d "%~dp0"

echo ==================================================
echo    SENTRIX GUARD FLOW - iniciando...
echo ==================================================
echo.

if not exist "schema.sql" (
    echo [ERRO] Extraia o arquivo .zip antes de rodar este programa.
    echo        Clique com o botao direito no .zip e escolha "Extrair tudo".
    echo.
    pause
    exit /b 1
)

call "scripts\encontrar-php.bat"
if not defined PHP_EXE (
    echo [ERRO] O PHP nao foi encontrado neste computador.
    echo.
    echo  1. Instale o XAMPP ^(gratuito^), versao com PHP 8, na pasta padrao C:\xampp
    echo     https://www.apachefriends.org/pt_br/download.html
    echo  2. Depois, de dois cliques neste arquivo INICIAR.bat de novo.
    echo.
    start "" "https://www.apachefriends.org/pt_br/download.html"
    pause
    exit /b 1
)
echo PHP encontrado: "%PHP_EXE%"

rem ---- Inicia o MySQL do XAMPP, se ainda nao estiver rodando ----
set "MYSQL_RODANDO="
tasklist /FI "IMAGENAME eq mysqld.exe" 2>nul | find /I "mysqld.exe" >nul
if not errorlevel 1 set "MYSQL_RODANDO=1"
tasklist /FI "IMAGENAME eq mariadbd.exe" 2>nul | find /I "mariadbd.exe" >nul
if not errorlevel 1 set "MYSQL_RODANDO=1"

if defined MYSQL_RODANDO goto mysql_ok
if not defined XAMPP_DIR goto mysql_manual
if not exist "%XAMPP_DIR%\mysql\bin\mysqld.exe" goto mysql_manual
echo Iniciando o MySQL do XAMPP ^(uma janela minimizada vai abrir - nao feche^)...
start "MySQL - Sentrix (nao feche)" /min /D "%XAMPP_DIR%" "%XAMPP_DIR%\mysql\bin\mysqld.exe" --defaults-file="%XAMPP_DIR%\mysql\bin\my.ini" --standalone
goto mysql_fim

:mysql_manual
echo [AVISO] Inicie o MySQL manualmente ^(painel do XAMPP/Laragon, botao Start do MySQL^).
goto mysql_fim

:mysql_ok
echo MySQL ja esta rodando.

:mysql_fim
echo.
echo Preparando o banco de dados...
set "PAGINA="
"%PHP_EXE%" scripts\instalar.php
if errorlevel 1 (
    echo.
    echo [AVISO] O banco nao foi preparado automaticamente.
    echo         O navegador vai abrir o instalador para voce concluir por la.
    set "PAGINA=instalar.php"
)

rem ---- Escolhe uma porta livre ----
set "PORTA=8000"
netstat -ano | find ":8000 " | find "LISTENING" >nul
if not errorlevel 1 set "PORTA=8080"

set "URL=http://127.0.0.1:%PORTA%/%PAGINA%"
start "" cmd /c "timeout /t 2 /nobreak >nul & start %URL%"

echo.
echo ==================================================
echo    Sistema rodando em: http://127.0.0.1:%PORTA%/
echo.
echo    Login admin:    admin@sentrixguard.com  /  admin123
echo    Login operador: operador@sentrixguard.com  /  operador123
echo    Depois da senha, confirme pelo e-mail ^(no modo demonstracao:
echo    botao "Abrir a caixa de saida"^).
echo.
echo    Para DESLIGAR o sistema, feche esta janela.
echo ==================================================
echo.
"%PHP_EXE%" -S 127.0.0.1:%PORTA%
pause
