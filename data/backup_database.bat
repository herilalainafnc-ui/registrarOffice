@echo off
REM =====================================================
REM Script de sauvegarde automatique - registrar_db
REM Exécuter tous les jours à 17:15
REM =====================================================

echo ======================================
echo   Sauvegarde automatique registrar_db
echo   %date% %time%
echo ======================================

REM Chemin vers PHP de XAMPP
set PHP_PATH=C:\xampp\php\php.exe

REM Chemin vers le script de backup
set BACKUP_SCRIPT=C:\xampp\htdocs\a.registrar\data\backup_database.php

REM Exécuter le script PHP
"%PHP_PATH%" "%BACKUP_SCRIPT%"

echo.
echo Sauvegarde terminée.
