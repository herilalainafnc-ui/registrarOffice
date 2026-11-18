<?php
    $host = 'localhost';
    $user = 'herilalaina';
    $pass = 'J8wFF(FOy1KI(nay';
    $dbname = 'registrar_db';

    $backupDir = 'C:/xampp/htdocs/registraireoffice/myphp/a.registrar2024/data/.BACKUP/';
    $date = date("Y-m-d_H-i-s");
    $backupFile = $backupDir . $dbname . '_back_' . $date . '.sql';

    $command = "C:/xampp/mysql/bin/mysqldump --host=$host --user=$user --password=$pass $dbname > $backupFile && sed -i 's/DEFINER=`[^`]*`@`[^`]*`//g' $backupFile";

    exec($command . ' 2>&1', $output, $return_var);

    if ($return_var === 0) {
        echo "Sauvegarde réussie. Le fichier est enregistré sous : " . $backupFile;
    } else {
        echo "Erreur lors de la sauvegarde : " . implode("\n", $output);
    }
?>
