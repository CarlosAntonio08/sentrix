@echo off
setlocal
title Sentrix - Simulador de eventos
cd /d "%~dp0"

call "scripts\encontrar-php.bat"
if not defined PHP_EXE (
    echo [ERRO] PHP nao encontrado. Rode o INICIAR.bat primeiro.
    pause
    exit /b 1
)

echo ==================================================
echo    SIMULADOR DE EVENTOS - modo demonstracao
echo ==================================================
echo  Gera posicoes de GPS e alertas ^(cameras, sensores, IA, cercas^)
echo  a cada 5 segundos. Deixe o Painel ou a pagina GPS aberta.
echo  O INICIAR.bat precisa estar rodando.
echo  Para PARAR, feche esta janela.
echo ==================================================
echo.
"%PHP_EXE%" scripts\simular.php 720 5
pause
