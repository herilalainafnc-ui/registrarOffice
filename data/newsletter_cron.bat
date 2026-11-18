@echo off
setlocal
set PHP_EXE=C:\xampp\php\php.exe
set APP_DIR=C:\xampp\htdocs\a.registrar
"%PHP_EXE%" "%APP_DIR%\data\newsletter_cron.php" 10
endlocal
