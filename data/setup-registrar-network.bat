@echo off
REM ============================================================================
REM  Configuration reseau - Infinit Registrar
REM  Universite Adventiste Zurcher
REM ============================================================================
REM
REM  Ce script ajoute l'entree DNS locale pour acceder a l'application
REM  via https://registrar.mg au lieu de https://192.168.88.247
REM
REM  IMPORTANT: Executez ce script en tant qu'Administrateur !
REM  Clic droit > "Executer en tant qu'administrateur"
REM
REM ============================================================================

echo.
echo ============================================================
echo   Configuration reseau - Infinit Registrar
echo   Universite Adventiste Zurcher
echo ============================================================
echo.

REM Check admin rights
net session >nul 2>&1
if %errorlevel% neq 0 (
    echo [ERREUR] Ce script doit etre execute en tant qu'Administrateur !
    echo.
    echo Clic droit sur le fichier ^> "Executer en tant qu'administrateur"
    echo.
    pause
    exit /b 1
)

REM Check if entry already exists
findstr /C:"registrar.mg" "%SystemRoot%\System32\drivers\etc\hosts" >nul 2>&1
if %errorlevel% equ 0 (
    echo [INFO] L'entree registrar.mg existe deja dans le fichier hosts.
    echo.
    echo Vous pouvez acceder a l'application via:
    echo   https://registrar.mg
    echo.
    pause
    exit /b 0
)

REM Add the entry
echo.>> "%SystemRoot%\System32\drivers\etc\hosts"
echo # Infinit Registrar - Universite Adventiste Zurcher>> "%SystemRoot%\System32\drivers\etc\hosts"
echo 192.168.88.247    registrar.mg>> "%SystemRoot%\System32\drivers\etc\hosts"

if %errorlevel% equ 0 (
    echo [OK] Configuration terminee avec succes !
    echo.
    echo Vous pouvez maintenant acceder a l'application via:
    echo.
    echo   https://registrar.mg
    echo.
    echo Note: Le navigateur affichera un avertissement de securite
    echo pour le certificat SSL. Cliquez sur "Avance" puis
    echo "Continuer vers registrar.mg" pour acceder au site.
) else (
    echo [ERREUR] Impossible de modifier le fichier hosts.
    echo Verifiez que vous executez ce script en tant qu'administrateur.
)

echo.
pause
