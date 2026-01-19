# =====================================================
# Installation de la tâche planifiée pour la sauvegarde
# Exécuter en tant qu'Administrateur
# =====================================================

Write-Host "======================================" -ForegroundColor Cyan
Write-Host "  Installation de la tâche planifiée" -ForegroundColor Cyan
Write-Host "  Sauvegarde quotidienne à 17:15" -ForegroundColor Cyan
Write-Host "======================================" -ForegroundColor Cyan
Write-Host ""

# Vérifier les droits administrateur
$isAdmin = ([Security.Principal.WindowsPrincipal] [Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)

if (-not $isAdmin) {
    Write-Host "Ce script nécessite les droits Administrateur!" -ForegroundColor Red
    Write-Host "Relancement en tant qu'Administrateur..." -ForegroundColor Yellow
    Start-Process powershell -Verb RunAs -ArgumentList "-ExecutionPolicy Bypass -File `"$PSCommandPath`""
    exit
}

$taskName = "Backup_Registrar_DB"
$batPath = "C:\xampp\htdocs\a.registrar\data\backup_database.bat"

# Supprimer l'ancienne tâche si elle existe
$existingTask = Get-ScheduledTask -TaskName $taskName -ErrorAction SilentlyContinue
if ($existingTask) {
    Write-Host "Suppression de l'ancienne tâche..." -ForegroundColor Yellow
    Unregister-ScheduledTask -TaskName $taskName -Confirm:$false
}

# Créer l'action
$action = New-ScheduledTaskAction -Execute $batPath

# Créer le déclencheur (tous les jours à 17:15)
$trigger = New-ScheduledTaskTrigger -Daily -At "17:15"

# Créer les paramètres
$settings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -StartWhenAvailable

# Créer le principal (utilisateur actuel)
$principal = New-ScheduledTaskPrincipal -UserId $env:USERNAME -LogonType Interactive -RunLevel Highest

# Enregistrer la tâche
try {
    Register-ScheduledTask -TaskName $taskName -Action $action -Trigger $trigger -Settings $settings -Principal $principal -Force
    
    Write-Host ""
    Write-Host "✓ Tâche planifiée créée avec succès!" -ForegroundColor Green
    Write-Host ""
    Write-Host "Détails de la tâche:" -ForegroundColor White
    Write-Host "  - Nom: $taskName" -ForegroundColor Gray
    Write-Host "  - Heure: 17:15 tous les jours" -ForegroundColor Gray
    Write-Host "  - Script: $batPath" -ForegroundColor Gray
    Write-Host "  - Destination: C:\Users\REGISTRAR\Desktop\Backup" -ForegroundColor Gray
    Write-Host ""
    Write-Host "Pour vérifier, ouvrez le Planificateur de tâches Windows" -ForegroundColor Cyan
    Write-Host "ou exécutez: Get-ScheduledTask -TaskName '$taskName'" -ForegroundColor Cyan
}
catch {
    Write-Host "✗ Erreur lors de la création de la tâche!" -ForegroundColor Red
    Write-Host $_.Exception.Message -ForegroundColor Red
}

Write-Host ""
Read-Host "Appuyez sur Entrée pour fermer"
