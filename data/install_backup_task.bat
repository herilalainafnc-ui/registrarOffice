@echo off
REM =====================================================
REM Installation de la tâche planifiée pour la sauvegarde
REM Exécute backup_database.bat tous les jours à 17:15
REM =====================================================

echo ======================================
echo   Installation de la tâche planifiée
echo   Sauvegarde quotidienne à 17:15
echo ======================================
echo.

REM Supprimer l'ancienne tâche si elle existe
schtasks /delete /tn "Backup_Registrar_DB" /f >nul 2>&1

REM Créer la nouvelle tâche planifiée
schtasks /create /tn "Backup_Registrar_DB" /tr "\"C:\xampp\htdocs\a.registrar\data\backup_database.bat\"" /sc daily /st 17:15 /ru SYSTEM /rl HIGHEST /f

if %errorlevel% equ 0 (
    echo.
    echo ✓ Tâche planifiée créée avec succès!
    echo.
    echo Détails de la tâche:
    echo   - Nom: Backup_Registrar_DB
    echo   - Heure: 17:15 tous les jours
    echo   - Script: C:\xampp\htdocs\a.registrar\data\backup_database.bat
    echo   - Destination: C:\Users\REGISTRAR\Desktop\Backup
    echo.
    echo Pour vérifier la tâche, ouvrez le Planificateur de tâches Windows
    echo ou exécutez: schtasks /query /tn "Backup_Registrar_DB"
) else (
    echo.
    echo ✗ Erreur lors de la création de la tâche!
    echo Essayez d'exécuter ce script en tant qu'Administrateur.
)

echo.
pause
