<!DOCTYPE html>
<html>
<head>
    <?php require('../init/head.php');?>
    <title>Autorisation Réinscription - Caisse</title>
    <style>
        :root {
            --background: 222.2 84% 4.9%;
            --foreground: 210 40% 98%;
            --card: 222.2 84% 4.9%;
            --card-foreground: 210 40% 98%;
            --primary: 199 89% 48%;
            --primary-foreground: 222.2 47.4% 11.2%;
            --secondary: 217.2 32.6% 17.5%;
            --muted: 217.2 32.6% 17.5%;
            --muted-foreground: 215 20.2% 65.1%;
            --border: 217.2 32.6% 17.5%;
            --ring: 199 89% 48%;
            --radius: 0.5rem;
        }

        .permission-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            border-radius: 0.5rem;
            overflow: hidden;
            border: 1px solid rgba(51, 65, 85, 0.4);
        }

        .permission-table thead {
            background: rgba(14, 165, 233, 0.1);
        }

        .permission-table thead tr th {
            background: rgba(14, 165, 233, 0.1);
            border-bottom: 1px solid rgba(14, 165, 233, 0.2);
            color: #38bdf8;
            font-weight: 600;
            font-size: 0.8rem;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            padding: 0.6rem 0.5rem;
        }

        .permission-table tbody td {
            border-bottom: 1px solid rgba(51, 65, 85, 0.25);
            padding: 0.5rem 0.6rem;
            background: transparent;
            color: #cbd5e1;
            font-size: 0.75rem;
        }

        .permission-table tbody tr:hover {
            background: rgba(51, 65, 85, 0.2);
        }

        .checkbox-wrapper {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .checkbox-wrapper input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: #0ea5e9;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-authorized {
            background: rgba(34, 197, 94, 0.2);
            color: #86efac;
            border: 1px solid rgba(34, 197, 94, 0.3);
        }

        .status-unauthorized {
            background: rgba(239, 68, 68, 0.2);
            color: #fca5a5;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .search-box {
            background: rgba(15, 23, 42, 0.4);
            padding: 0.6rem 0.8rem;
            width: 100%;
            max-width: 300px;
            font-size: 0.8rem;
            border: 1px solid rgba(51, 65, 85, 0.5);
            border-radius: 0.375rem;
            color: #e2e8f0;
            margin-bottom: 1rem;
        }

        .search-box:focus {
            outline: none;
            border-color: #0ea5e9;
            background: rgba(30, 41, 59, 0.8);
        }

        .page-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1.25rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid rgba(51, 65, 85, 0.4);
        }

        .page-header-icon {
            width: 2.5rem;
            height: 2.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 0.5rem;
            background: linear-gradient(135deg, rgba(14, 165, 233, 0.2), rgba(14, 165, 233, 0.1));
            color: #0ea5e9;
            font-size: 1.1rem;
        }

        .page-header-title {
            font-size: 1.35rem;
            font-weight: 700;
            color: #f1f5f9;
            letter-spacing: -0.025em;
        }

        .page-header-desc {
            font-size: 0.8rem;
            color: #64748b;
        }

        .success-message {
            display: none;
            position: fixed;
            top: 80px;
            right: 20px;
            background: rgba(34, 197, 94, 0.2);
            border: 1px solid rgba(34, 197, 94, 0.5);
            color: #86efac;
            padding: 1rem;
            border-radius: 0.375rem;
            z-index: 1000;
            max-width: 300px;
        }

        .custom-scroll::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        .custom-scroll::-webkit-scrollbar-track {
            background: rgba(15, 23, 42, 0.5);
            border-radius: 3px;
        }

        .custom-scroll::-webkit-scrollbar-thumb {
            background: rgba(51, 65, 85, 0.8);
            border-radius: 3px;
        }

        .custom-scroll::-webkit-scrollbar-thumb:hover {
            background: rgba(71, 85, 105, 0.8);
        }

        .dossier-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .dossier-ok {
            background: rgba(34, 197, 94, 0.2);
            color: #86efac;
            border: 1px solid rgba(34, 197, 94, 0.3);
        }

        .dossier-nok {
            background: rgba(249, 115, 22, 0.2);
            color: #fdba74;
            border: 1px solid rgba(249, 115, 22, 0.3);
        }
    </style>
</head>

<body class="<?=$bg_three_color?> sm:text-xs lg:text-sm">
<div class="h-screen w-full <?=$bg_three_color?>">
    
    <!-- TOP BAR --><?php require('../init/topbar.php');?>
<?php
// Contrôle d'accès : superadmin (1), registraire (3) et comptabilité/caissier (4) peuvent accéder
if (!isset($rg_user) || !in_array($rg_user['level'], [1, 3, 4])) {
    header('Location: ' . $app_base . '/dashboard');
    exit;
}
?>
    <!-- NOTIFICATION MANAGER --><?php require('../src/main/notificationGeneral.php');?>

    <div class="w-full flex flex-col lg:flex-row">
        
        <!-- BARRE DE MENU --><?php require('../init/menubar.php');?>

        <div class="w-full lg:w-10/12 flex flex-col" style="height: calc(100vh - 56px);">
        
            <div class="back flex-1 overflow-hidden">
                
                <div class="p-4 overflow-auto custom-scroll" style="height: calc(100vh - 75px);">

                    <!-- Page Header -->
                    <div class="page-header">
                        <div class="page-header-icon">
                            <i class="bi bi-check-circle"></i>
                        </div>
                        <div>
                            <div class="page-header-title">Autorisation de Réinscription</div>
                            <div class="page-header-desc">Autorisez ou refusez les réinscriptions des étudiants</div>
                        </div>
                    </div>

                    <!-- Success Message -->
                    <div class="success-message" id="successMsg">
                        <i class="bi bi-check-circle"></i> Autorisation mise à jour avec succès
                    </div>

                    <!-- Search Box -->
                    <input type="text" 
                           id="searchInput" 
                           class="search-box" 
                           placeholder="Rechercher par matricule, nom ou filière...">

                    <!-- Students Table -->
                    <div style="border-radius: 0.5rem; overflow: hidden; border: 1px solid rgba(51, 65, 85, 0.4);">
                        <div style="overflow-x: auto;">
                            <table class="permission-table custom-scroll">
                                <thead>
                                    <tr>
                                        <th style="width: 8%;">Matricule</th>
                                        <th style="width: 19%;">Nom & Prénom</th>
                                        <th style="width: 12%;">Filière</th>
                                        <th style="width: 10%;">Niveau</th>
                                        <th style="width: 10%;">Statut</th>
                                        <th style="width: 8%; text-align: center;">Dossier</th>
                                        <th style="width: 8%; text-align: center;">Autorisé</th>
                                        <th style="width: 15%;">Modifié par</th>
                                        <th style="width: 15%;">Date Autorisation</th>
                                        <th style="width: 3%;">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="studentList">
                                    <?php
                                    // Récupérer tous les étudiants actifs
                                    $stmt = $dtb->prepare("
                                        SELECT 
                                            e.id,
                                            e.student_id,
                                            e.student_nom,
                                            e.student_prenom,
                                            e.etude_envisage,
                                            e.etude_option,
                                            e.annee_etude,
                                            e.status,
                                            e.authorized_reinscription,
                                            e.authorized_date,
                                            e.authorized_by,
                                            e.dossier_ok,
                                            e.dossier_checked_by,
                                            e.dossier_checked_date,
                                            u.prenom as authorized_by_prenom,
                                            u.nom as authorized_by_nom,
                                            ud.prenom as dossier_by_prenom,
                                            ud.nom as dossier_by_nom
                                        FROM tbl_2024_etudiant e
                                        LEFT JOIN compt_utilisateur u ON e.authorized_by = u.id
                                        LEFT JOIN compt_utilisateur ud ON e.dossier_checked_by = ud.id
                                        WHERE e.remove != 1
                                        ORDER BY e.student_id DESC
                                    ");
                                    $stmt->execute();
                                    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                    // Vérifier si l'utilisateur peut modifier le dossier (superadmin=1 ou registraire=3)
                                    $canEditDossier = isset($rg_user) && in_array($rg_user['level'], [1, 3]);
                                    // Vérifier si l'utilisateur peut modifier l'autorisation (superadmin=1 ou comptabilité=4)
                                    $canEditAuth = isset($rg_user) && in_array($rg_user['level'], [1, 4]);

                                    foreach ($students as $student) {
                                        $authorized = $student['authorized_reinscription'] == 1;
                                        $dossierOk = $student['dossier_ok'] == 1;
                                        $level_text = ($student['annee_etude'] == 0) 
                                            ? 'Rem. Niv.' 
                                            : (($student['annee_etude'] <= 3) 
                                                ? 'L' . $student['annee_etude'] 
                                                : 'M' . ($student['annee_etude'] - 3));

                                        $authorized_by = '';
                                        if (!empty($student['authorized_by_prenom'])) {
                                            $authorized_by = $student['authorized_by_prenom'] . ' ' . $student['authorized_by_nom'];
                                        }

                                        $authorized_date = '';
                                        if (!empty($student['authorized_date'])) {
                                            $authorized_date = date('d/m/Y', strtotime($student['authorized_date']));
                                        }

                                        $dossier_by = '';
                                        if (!empty($student['dossier_by_prenom'])) {
                                            $dossier_by = $student['dossier_by_prenom'] . ' ' . $student['dossier_by_nom'];
                                        }

                                        $dossier_date = '';
                                        if (!empty($student['dossier_checked_date'])) {
                                            $dossier_date = date('d/m/Y', strtotime($student['dossier_checked_date']));
                                        }
                                    ?>
                                    <tr class="student-row" data-student-id="<?=$student['student_id']?>" data-authorized="<?=$authorized ? '1' : '0'?>">
                                        <td><?=$student['student_id']?></td>
                                        <td><?=strtoupper($student['student_nom']) . ' ' . $student['student_prenom']?></td>
                                        <td><?=$student['etude_envisage']?></td>
                                        <td><?=$level_text?></td>
                                        <td><?=$student['status']?></td>
                                        <td>
                                            <div class="checkbox-wrapper" style="gap: 0.5rem;">
                                                <?php if ($canEditDossier) { ?>
                                                <input type="checkbox" 
                                                       class="dossier-checkbox" 
                                                       data-student-id="<?=$student['id']?>"
                                                       <?=$dossierOk ? 'checked' : ''?>
                                                       title="<?=$dossier_by ? 'Vérifié par ' . htmlspecialchars($dossier_by) . ' le ' . $dossier_date : 'Dossier non vérifié'?>">
                                                <?php } ?>
                                                <span class="dossier-badge <?=$dossierOk ? 'dossier-ok' : 'dossier-nok'?>" id="dossier-badge-<?=$student['id']?>">
                                                    <?=$dossierOk ? 'OK' : 'Non OK'?>
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="checkbox-wrapper" style="gap: 0.5rem;">
                                                <?php if ($canEditAuth) { ?>
                                                <input type="checkbox" 
                                                       class="auth-checkbox" 
                                                       data-student-id="<?=$student['id']?>"
                                                       <?=$authorized ? 'checked' : ''?>>
                                                <?php } ?>
                                                <span class="status-badge <?=$authorized ? 'status-authorized' : 'status-unauthorized'?>" id="badge-<?=$student['id']?>">
                                                    <?=$authorized ? 'Oui' : 'Non'?>
                                                </span>
                                            </div>
                                        </td>
                                        <td class="cell-modifier" id="modifier-<?=$student['id']?>"><?=$authorized_by?></td>
                                        <td class="cell-date" id="date-<?=$student['id']?>"><?=$authorized_date?></td>
                                        <td>
                                            <button class="update-btn text-blue-400 hover:text-blue-300" 
                                                    data-student-id="<?=$student['id']?>"
                                                    style="background: none; border: none; cursor: pointer; font-size: 0.8rem;">
                                                <i class="bi bi-arrow-repeat"></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>
                
                <?php require('../init/footer.php'); ?>
            </div>

        </div>

    </div>

</div>

<script>
    // Search functionality
    document.getElementById('searchInput').addEventListener('keyup', function() {
        const searchTerm = this.value.toLowerCase();
        const rows = document.querySelectorAll('.student-row');

        rows.forEach(row => {
            const studentId = row.getAttribute('data-student-id').toLowerCase();
            const nom = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
            const filiere = row.querySelector('td:nth-child(3)').textContent.toLowerCase();

            if (studentId.includes(searchTerm) || nom.includes(searchTerm) || filiere.includes(searchTerm)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });

    // Checkbox change and update button handlers
    document.querySelectorAll('.auth-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            updateAuthorization(this.getAttribute('data-student-id'), this.checked);
        });
    });

    // Dossier checkbox handler
    document.querySelectorAll('.dossier-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            updateDossier(this.getAttribute('data-student-id'), this.checked);
        });
    });

    document.querySelectorAll('.update-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const studentId = this.getAttribute('data-student-id');
            const checkbox = document.querySelector(`.auth-checkbox[data-student-id="${studentId}"]`);
            const newState = !checkbox.checked;
            checkbox.checked = newState;
            updateAuthorization(studentId, newState);
        });
    });

    // Function to update authorization
    function updateAuthorization(studentId, authorized) {
        const formData = new FormData();
        formData.append('student_id', studentId);
        formData.append('authorized_reinscription', authorized ? 1 : 0);

        // Désactiver la checkbox pendant la requête
        const checkbox = document.querySelector(`.auth-checkbox[data-student-id="${studentId}"]`);
        if (checkbox) checkbox.disabled = true;

        fetch('<?=$app_base?>/api/update-authorization', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                return response.text().then(text => {
                    throw new Error('HTTP ' + response.status + ': ' + text.substring(0, 200));
                });
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // Mettre à jour l'UI avec les données retournées
                updateRowUI(studentId, data);
                showSuccessMessage(data.message);
            } else {
                // Rétablir l'état de la checkbox en cas d'erreur
                if (checkbox) checkbox.checked = !authorized;
                alert('Erreur: ' + (data.message || 'Une erreur est survenue'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            // Rétablir l'état de la checkbox en cas d'erreur réseau
            if (checkbox) checkbox.checked = !authorized;
            alert('Erreur: ' + error.message);
        })
        .finally(() => {
            if (checkbox) checkbox.disabled = false;
        });
    }

    // Mettre à jour la ligne du tableau après modification
    function updateRowUI(studentId, data) {
        const isAuthorized = data.authorized == 1;

        // Mettre à jour le badge
        const badge = document.getElementById('badge-' + studentId);
        if (badge) {
            badge.textContent = isAuthorized ? 'Oui' : 'Non';
            badge.className = 'status-badge ' + (isAuthorized ? 'status-authorized' : 'status-unauthorized');
        }

        // Mettre à jour "Modifié par"
        const modifier = document.getElementById('modifier-' + studentId);
        if (modifier && data.authorized_by_name) {
            modifier.textContent = data.authorized_by_name;
        }

        // Mettre à jour "Date Autorisation"
        const dateCell = document.getElementById('date-' + studentId);
        if (dateCell && data.authorized_date) {
            dateCell.textContent = data.authorized_date;
        }

        // Mettre à jour le data-attribute de la ligne
        const row = document.querySelector(`tr[data-student-id]`);
        const rows = document.querySelectorAll('.student-row');
        rows.forEach(r => {
            const cb = r.querySelector(`input[data-student-id="${studentId}"]`);
            if (cb) r.setAttribute('data-authorized', isAuthorized ? '1' : '0');
        });

        // Animation flash sur la ligne modifiée
        rows.forEach(r => {
            const cb = r.querySelector(`input[data-student-id="${studentId}"]`);
            if (cb) {
                r.style.transition = 'background 0.3s ease';
                r.style.background = isAuthorized ? 'rgba(34, 197, 94, 0.15)' : 'rgba(239, 68, 68, 0.15)';
                setTimeout(() => { r.style.background = ''; }, 1500);
            }
        });
    }

    // Show success message
    function showSuccessMessage(message) {
        const msg = document.getElementById('successMsg');
        if (message) msg.innerHTML = '<i class="bi bi-check-circle"></i> ' + message;
        msg.style.display = 'block';
        setTimeout(() => {
            msg.style.display = 'none';
        }, 3000);
    }

    // Function to update dossier status
    function updateDossier(studentId, dossierOk) {
        const formData = new FormData();
        formData.append('student_id', studentId);
        formData.append('dossier_ok', dossierOk ? 1 : 0);

        const checkbox = document.querySelector(`.dossier-checkbox[data-student-id="${studentId}"]`);
        if (checkbox) checkbox.disabled = true;

        fetch('<?=$app_base?>/api/update-dossier', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                return response.text().then(text => {
                    throw new Error('HTTP ' + response.status + ': ' + text.substring(0, 200));
                });
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                updateDossierUI(studentId, data);
                showSuccessMessage(data.message);
            } else {
                if (checkbox) checkbox.checked = !dossierOk;
                alert('Erreur: ' + (data.message || 'Une erreur est survenue'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            if (checkbox) checkbox.checked = !dossierOk;
            alert('Erreur: ' + error.message);
        })
        .finally(() => {
            if (checkbox) checkbox.disabled = false;
        });
    }

    // Update dossier UI after modification
    function updateDossierUI(studentId, data) {
        const isDossierOk = data.dossier_ok == 1;

        const badge = document.getElementById('dossier-badge-' + studentId);
        if (badge) {
            badge.textContent = isDossierOk ? 'OK' : 'Non OK';
            badge.className = 'dossier-badge ' + (isDossierOk ? 'dossier-ok' : 'dossier-nok');
        }

        // Update tooltip
        const checkbox = document.querySelector(`.dossier-checkbox[data-student-id="${studentId}"]`);
        if (checkbox && data.dossier_checked_by_name) {
            checkbox.title = 'Vérifié par ' + data.dossier_checked_by_name + ' le ' + (data.dossier_checked_date || '');
        }

        // Flash animation
        const rows = document.querySelectorAll('.student-row');
        rows.forEach(r => {
            const cb = r.querySelector(`.dossier-checkbox[data-student-id="${studentId}"]`);
            if (cb) {
                r.style.transition = 'background 0.3s ease';
                r.style.background = isDossierOk ? 'rgba(34, 197, 94, 0.15)' : 'rgba(249, 115, 22, 0.15)';
                setTimeout(() => { r.style.background = ''; }, 1500);
            }
        });
    }
</script>

</body>
</html>
