@echo off
rem Localiza o PHP (e o XAMPP, se houver). Define PHP_EXE e XAMPP_DIR.
rem Usado por INICIAR.bat, SIMULAR-EVENTOS.bat e RESTAURAR-DADOS.bat.
set "PHP_EXE="
set "XAMPP_DIR="

for %%D in ("C:\xampp" "D:\xampp" "E:\xampp" "%ProgramFiles%\xampp" "%USERPROFILE%\xampp") do (
    if not defined XAMPP_DIR if exist "%%~D\php\php.exe" set "XAMPP_DIR=%%~D"
)
if defined XAMPP_DIR set "PHP_EXE=%XAMPP_DIR%\php\php.exe"

rem Laragon
if not defined PHP_EXE (
    for /d %%V in ("C:\laragon\bin\php\php-8*") do if exist "%%~V\php.exe" set "PHP_EXE=%%~V\php.exe"
)

rem PHP instalado no PATH do Windows
if not defined PHP_EXE (
    for /f "delims=" %%P in ('where php 2^>nul') do if not defined PHP_EXE set "PHP_EXE=%%P"
)
exit /b 0
