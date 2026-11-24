@echo off
setlocal
title Sentrix - Restaurar dados de demonstracao
cd /d "%~dp0"

call "scripts\encontrar-php.bat"
if not defined PHP_EXE (
    echo [ERRO] PHP nao encontrado. Rode o INICIAR.bat primeiro.
    pause
    exit /b 1
)

echo Isto APAGA tudo o que foi cadastrado e volta aos dados iniciais
echo ^(util antes da apresentacao^). O MySQL precisa estar rodando.
echo.
set "RESP="
set /p "RESP=Digite S e tecle Enter para continuar: "
if defined RESP set "RESP=%RESP:~0,1%"
if /I "%RESP%"=="S" goto restaurar
echo Cancelado. Nada foi alterado.
pause
exit /b 0

:restaurar

"%PHP_EXE%" scripts\instalar.php --restaurar
echo.
pause
