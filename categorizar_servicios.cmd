@echo off
chcp 65001 > nul
cd /d "%~dp0"

echo Aplicando categoria en BD y en la pagina de Servicios...
"C:\xampp\php\php.exe" aplicar_categoria_servicios.php

echo.
pause
