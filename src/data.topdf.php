<?php 
require('../data/session.php');
require('../data/backdb.php');
require('../init/head.noTem.php');

// Pages d'export (sortie destinée à l'impression / PDF) : on masque les
// avertissements "clé de tableau indéfinie" (PHP 8+) qui polluent le rendu
// quand un champ POST est absent (accès direct en GET, rechargement...).
// Les erreurs réelles (fatales / exceptions) restent signalées.
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);

$ptype = $_GET['ptype'] ?? $_POST['ptype'] ?? '';

// Un export a besoin de paramètres : soit un formulaire POST (boutons "en
// masse"), soit des paramètres GET (liens individuels : Badge, liste d'un
// cours...). On ne bloque que les accès réellement vides (aucune donnée hormis
// ptype/export_format), qui ne peuvent rien générer — plutôt que de planter.
$getParams = $_GET;
unset($getParams['ptype'], $getParams['export_format']);
if (empty($_POST) && empty($getParams)) {
    echo '<div style="max-width:520px;margin:12vh auto;padding:28px;border-radius:14px;background:#0f172a;border:1px solid #334155;color:#e2e8f0;font-family:Inter,Arial,sans-serif;text-align:center;box-shadow:0 24px 60px -12px rgba(0,0,0,.5);">'
        . '<div style="font-size:42px;margin-bottom:10px;">&#128196;</div>'
        . '<h2 style="margin:0 0 8px;font-size:18px;font-weight:600;">Export indisponible en acc&egrave;s direct</h2>'
        . '<p style="color:#94a3b8;font-size:14px;line-height:1.5;margin:0 0 20px;">Cet export doit &ecirc;tre lanc&eacute; depuis son bouton dans l\'application&nbsp;: les param&egrave;tres sont transmis par le formulaire.</p>'
        . '<a href="' . $app_base . '/tableau-de-bord" style="display:inline-block;padding:9px 20px;border-radius:8px;background:#0891b2;color:#fff;text-decoration:none;font-size:14px;font-weight:500;">Retour au tableau de bord</a>'
        . '</div>';
    exit;
}
$exportFormat = $_GET['export_format'] ?? $_POST['export_format'] ?? '';
$h = (date('H')+1);
$date = 'h_'.date($h.'-i-s').' date_'.date('d-m-Y');
$pdfOrientation = 'portrait';
$pdfFormat = 'a4';
$sheetWidth = '800px';
$printMinHeight = '1020px';

if (
    $ptype === 'listeStd'
    && !empty($_POST['presence_sheet'])
    && in_array(($_POST['presence_type'] ?? ''), ['semaine_priere', 'chapelle_lundi'], true)
) {
    $pdfOrientation = 'landscape';
    // Force a real A4 landscape canvas area for html2pdf rendering.
    $pdfFormat = array(11.69, 8.27);
    $sheetWidth = '1120px';
    $printMinHeight = '760px';
}

/* Le titre de l'onglet : le document affiche, avec le libelle du bouton qui
   l'ouvre, et le matricule pour un document individuel. Le navigateur le
   propose aussi comme nom de fichier a l'impression en PDF. */
$ongletDocuments = [
    'Statistique' => 'Statistique générale', 'Badge' => 'Badge', 'BadgeGr' => 'Badges en groupe',
    'Bulletin' => 'Bulletin', 'Transcript' => 'Transcript', 'Diplôme' => 'Diplôme',
    'Checklist' => 'Check list', 'Certificat de scolarité' => 'Certificat de scolarité',
    'Abonnement Caf' => 'Abonnement cafétéria', 'TranscriptSS' => 'Relevé semestriel des notes',
    'TranscriptSSBulk' => 'Relevés semestriels des notes', 'Worked_point' => 'Work Education',
    'listeStd' => 'Liste des étudiants', 'listeCours' => 'Liste de cours', 'ticketMail' => 'Tickets',
    'workedSlip' => 'Slips Work Education', 'filesSlip' => 'Slips de vérification des dossiers',
    'worked' => 'Liste Work Education', 'Fiche_inscription' => "Fiche d'inscription",
    'ListStdInThisCours' => 'Liste des étudiants du cours', 'RemiseNotes' => 'Remise de notes du cours',
];
$ongletTitre = $ongletDocuments[$ptype] ?? 'Exportation';
if ($ptype === 'listeStd' && !empty($_POST['presence_sheet'])) { $ongletTitre = 'Feuille de présence'; }
$ongletMatricule = trim((string) ($_GET['student_id'] ?? ''));
if ($ongletMatricule !== '') { $ongletTitre .= ' — ' . $ongletMatricule; }

// Badges : page d'aperçu dédiée, fidèle au PDF (pdf-lib + pdf.js). badge.php /
// badge.gr.php ne servent qu'à préparer les données : leur sortie HTML est écartée.
if ($ptype === 'Badge' || $ptype === 'BadgeGr') {
    ob_start();
    require ($ptype === 'Badge') ? './extenssionPrint/badge.php' : './extenssionPrint/badge.gr.php';
    ob_end_clean();
    require './extenssionPrint/badge.apercu.php';
    exit;
}
?>
<title><?= htmlspecialchars($ongletTitre, ENT_QUOTES, 'UTF-8') ?></title>

<body class="h-screen" style="background-color:#63748b;">

    <div class="bg-slate-800 flex h-10 p-1">

        <div class="sm:w-5/12 py-1 text-left lg:w-4/12">
        <a href="<?=$app_base?>/tableau-de-bord" class="flex text-white">
            <img src="../file/logo-coldbloud.png" class="w-6 h-6 mx-3 mx-2">
            <b> Infinit Registrar</b>
        </a>

        </div>
        
        <div class="sm:w-2/12 lg:w-4/12 text-center">
            <p class="b-title center"><b><?=$ptype?></b></p>
        </div>
        <div class="sm:w-5/12 text-right lg:w-4/12 pr-4">
            
            <form action="<?=$app_base?>/src/data.toxlsx" method="post" id="exportForm">
                
                <input type="hidden" name="htmlContent" id="htmlContent" value="">
                <input type="hidden" name="fileName" id="fileName" value="<?= htmlspecialchars((($printName ?? 'export').'_'.$date), ENT_QUOTES, 'UTF-8') ?>">
                
                <button type="submit" class="px-2 py-1 rounded-md text-white bg-green-700 opacity-0" id="btnToExcel"><span class="bi-file-earmark-spreadsheet"></span> Excel</button>
                
                <!-- <a href="./gen.pdf.php?ptype=<?=$ptype?>" target="_blank" class="px-2 py-[5.5px] rounded-md text-white bg-red-800"><span class="bi-filetype-pdf"></span><i class="text-[10px]">(Text)</i>.Pdf</a> -->

                <a href="#" onclick="printThisContent()" class="px-2 py-[5.5px] rounded-md text-white bg-blue-800"><span class="bi-file-earmark-image-fill"></span><i class="text-[10px]">(Image)</i>.Pdf</a>
                
                <!-- <a class="ml-2 text-sm"><b><i class="bi-arrow-repeat"></i></b> portrait</a> -->
            </form>
            
        </div>
    </div>
    <div style="overflow: auto; width: 100%; height: calc(100% - 43px);">

        <div style="border: 1px solid #475460; box-shadow: 0px 0px 20px #475469; padding: 35px;background: white; width: <?=$sheetWidth?>;margin: auto; margin-top: 20px; margin-bottom: 20px;">
            
<!-- ::::::::::::::::::::::::::::: CONTENTS PRINT ::::::::::::::::::::::::::::::::: -->            

    <div id="printThisContent" style="min-height: <?=$printMinHeight?>; width: 100%; position: relative;">
        
    <?php
        
        if($ptype == "Statistique") {
            $printName = "STATISTIQUE";
            require('../init/.forPrint/top.forPrint.php');

            echo "<br>";
            require ('./extenssionPrint/statistic-general.php');
            echo "<br>";
            require ('./extenssionPrint/statistic-sexe.php');
            echo "<br>";
            require ('./extenssionPrint/statistic-internat.php');
            echo "<br>";
            require ('./extenssionPrint/statistic-abonment.php');
            echo "<br>";
            require ('./extenssionPrint/statistic-religion.php');
            echo "<br>";
            require('../init/.forPrint/foot.forPrint.php');

            $scale = 3;
            $quality = 3;
            
        }elseif($ptype == "Badge"){
            require ('./extenssionPrint/badge.php');
            $scale = 4;
            $quality = 4;
        }elseif($ptype == "BadgeGr"){
            require ('./extenssionPrint/badge.gr.php');
            $scale = 4;
            $quality = 5;
        }elseif($ptype == "Bulletin"){
            require ('./extenssionPrint/bulletin.php');
            $scale = 3;
            $quality = 3;
        }elseif($ptype == "Transcript"){
            require ('./extenssionPrint/transcript.php');
            $scale = 3;
            $quality = 3;
        }elseif($ptype == "Diplôme"){
            require ('./extenssionPrint/diplome.php');
            $scale = 4;
            $quality = 4;
        }elseif($ptype == "Checklist"){
            require ('./extenssionPrint/vrai.cheklist.php');
            $scale = 3;
            $quality = 3;
        }elseif($ptype == "Certificat de scolarité"){
            require ('./extenssionPrint/certScolarity.php');
            $scale = 3;
            $quality = 3;
        }elseif($ptype == "Abonnement Caf"){
            require ('./extenssionPrint/abonnement.caf.php');
            $scale = 3;
            $quality = 3;
        }elseif($ptype == "TranscriptSS"){
            require ('./extenssionPrint/transcriptSS.php');
            $scale = 3;
            $quality = 3;
        }elseif($ptype == "Worked_point"){
            require ('./extenssionPrint/worked_point.php');
            $scale = 3;
            $quality = 3;
        }elseif($ptype == "listeStd"){
            require ('./extenssionPrint/std.list.php');
            $scale = 3;
            $quality = 3;
        }elseif($ptype == "listeCours"){
            require ('./extenssionPrint/cours.list.php');
            $scale = 3;
            $quality = 3;
        }elseif($ptype == "ticketMail"){
            require ('./extenssionPrint/ticket.mail.php');
            $scale = 3;
            $quality = 3;
        }elseif($ptype == "workedSlip"){
            require ('./extenssionPrint/worked.Slip.php');
            $scale = 3;
            $quality = 3;
        }elseif($ptype == "filesSlip"){
            require ('./extenssionPrint/files.checking.slip.php');
            $scale = 3;
            $quality = 3;
        }elseif($ptype == "worked"){
            require ('./extenssionPrint/worked.php');
            $scale = 1;
            $quality = 1;
        }elseif($ptype == "Fiche_inscription"){
            require ('./extenssionPrint/fiche_inscription.php');
            $scale = 3;
            $quality = 3;
        }elseif($ptype == "ListStdInThisCours"){
            require ('./extenssionPrint/listStdInThisCours.php');
            $scale = 3;
            $quality = 3;
        }elseif($ptype == "RemiseNotes"){
            require ('./extenssionPrint/remiseNotes.php');
            $scale = 3;
            $quality = 3;
        }elseif($ptype == "TranscriptSSBulk"){
            require ('./extenssionPrint/transcriptSS.bulk.php');
            $scale = 3;
            $quality = 3;
        }

    ?>

    </div>

<!-- ::::::::::::::::::::::::::::: --- ::::::::::::::::::::::::::::::::: -->

        </div>
    </div>
</body>

<!-- /////////////////////////////////////////////////////////////////////////////////////////////////////////// -->

<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js" integrity="sha512-GsLlZN/3F2ErC5ifS5QtgpiJtWd43JWSuIgh7mbzZ8zBps+dvLusV+eNQATqgA/HdeKFVgA5v3S/cIrLF7QnIg==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/exceljs/4.4.0/exceljs.min.js"></script>

<script type="text/javascript">

    var printConge = document.getElementById('printThisContent');
   

    function printThisContent(){

        var scl = parseFloat(<?= json_encode((float)($scale ?? 3)) ?>);
        var qlt = parseFloat(<?= json_encode((float)($quality ?? 3)) ?>);

        if (window.Toast) { Toast.info('Génération du PDF en cours…'); }

        

        var opt = {
            margin:     0.5,
            filename:   <?= json_encode((($printName ?? 'export').'-image_'.$date.'.pdf')) ?>,
            image:      { type: 'jpeg', quality: qlt },
            html2canvas:{ scale: scl, logging: true, useCORS: true },
            jsPDF:      { unit: 'in', format: <?= json_encode($pdfFormat) ?>, orientation: <?= json_encode($pdfOrientation) ?> }
        };

         // New Promise-based usage:
         html2pdf().set(opt).from(printConge).save();

        }

        // Ajouter le contenu HTML au champ caché et soumettre le formulaire
        document.getElementById('btnToExcel').addEventListener('click', function() {
        document.getElementById('htmlContent').value = document.getElementById('printThisContent').outerHTML;
        document.getElementById('exportForm').submit();
    });
</script>

<script>
    (function autoExcelExportFromPdfLayout() {
        var exportFormat = <?= json_encode((string)$exportFormat) ?>;
        if (exportFormat !== 'excel') {
            return;
        }

        var content = document.getElementById('printThisContent');

            if (!content || typeof ExcelJS === 'undefined') {
            return;
        }

            var filename = <?= json_encode((($printName ?? 'export').'_'.$date.'.xlsx')) ?>;

            function getText(node) {
                return (node && node.textContent ? node.textContent : '').replace(/\s+/g, ' ').trim();
            }

            function styleCell(cell, options) {
                options = options || {};
                cell.alignment = {
                    vertical: 'middle',
                    horizontal: options.align || 'left',
                    wrapText: options.wrapText !== false
                };
                cell.font = options.font || { name: 'Calibri', size: 11, color: { argb: '000000' } };
                if (options.fill) {
                    cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: options.fill } };
                }
                cell.border = {
                    top: { style: 'thin', color: { argb: '9CA3AF' } },
                    left: { style: 'thin', color: { argb: '9CA3AF' } },
                    bottom: { style: 'thin', color: { argb: '9CA3AF' } },
                    right: { style: 'thin', color: { argb: '9CA3AF' } }
                };
            }

            function setEditableCell(cell) {
                cell.protection = { locked: false };
            }

            function buildRemiseNotesWorkbook() {
                var workbook = new ExcelJS.Workbook();
                workbook.creator = 'Infinit Registrar';
                workbook.created = new Date();
                workbook.modified = new Date();

                var sheet = workbook.addWorksheet('Remise notes', {
                    pageSetup: { orientation: 'landscape', fitToPage: true, fitToWidth: 1, fitToHeight: 0 },
                    properties: { defaultRowHeight: 18 },
                    views: [{ state: 'frozen', ySplit: 10 }]
                });

                sheet.columns = [
                    { width: 6 },
                    { width: 14 },
                    { width: 60 },
                    { width: 14 },
                    { width: 10 },
                    { width: 10 },
                    { width: 10 },
                    { width: 12 },
                    { width: 12 },
                    { width: 12 }
                ];

                var title = content.querySelector('.bg-blue-200 b');
                var titleText = title ? title.textContent.trim() : 'Remise de notes';
                sheet.mergeCells('A1:J1');
                var titleCell = sheet.getCell('A1');
                titleCell.value = titleText;
                titleCell.font = { bold: true, size: 14 };
                titleCell.alignment = { horizontal: 'center', vertical: 'middle' };
                sheet.getRow(1).height = 24;

                var infoLabels = ['Sigle', 'Catégorie', 'Enseignant(e)', 'Année', 'Crédit', 'Niveau', 'Semestre'];
                var infoValues = [];
                var infoBlock = content.querySelector('div[style*="display: flex"]');
                if (infoBlock) {
                    var cells = infoBlock.querySelectorAll('div');
                    if (cells.length >= 4) {
                        var leftLabels = cells[0].querySelectorAll('label');
                        var leftValues = cells[1].querySelectorAll('b');
                        var rightLabels = cells[2].querySelectorAll('label');
                        var rightValues = cells[3].querySelectorAll('b');

                        infoValues = [
                            getText(leftValues[0]),
                            getText(leftValues[1]),
                            getText(leftValues[2]),
                            getText(leftValues[3]),
                            getText(rightValues[0]),
                            getText(rightValues[1]),
                            getText(rightValues[2])
                        ];
                    }
                }

                sheet.mergeCells('A3:C3');
                sheet.mergeCells('D3:F3');
                sheet.mergeCells('G3:J3');
                sheet.getCell('A3').value = 'Sigle';
                sheet.getCell('D3').value = 'Catégorie';
                sheet.getCell('G3').value = 'Enseignant(e)';
                styleCell(sheet.getCell('A3'), { fill: 'E5E7EB', align: 'center', font: { bold: true, size: 11 } });
                styleCell(sheet.getCell('D3'), { fill: 'E5E7EB', align: 'center', font: { bold: true, size: 11 } });
                styleCell(sheet.getCell('G3'), { fill: 'E5E7EB', align: 'center', font: { bold: true, size: 11 } });

                sheet.mergeCells('A4:C4');
                sheet.mergeCells('D4:F4');
                sheet.mergeCells('G4:J4');
                sheet.getCell('A4').value = infoValues[0] || '';
                sheet.getCell('D4').value = infoValues[1] || '';
                sheet.getCell('G4').value = infoValues[2] || '';
                styleCell(sheet.getCell('A4'));
                styleCell(sheet.getCell('D4'));
                styleCell(sheet.getCell('G4'));

                sheet.mergeCells('A5:C5');
                sheet.mergeCells('D5:F5');
                sheet.mergeCells('G5:J5');
                sheet.getCell('A5').value = 'Année';
                sheet.getCell('D5').value = 'Crédit';
                sheet.getCell('G5').value = 'Niveau';
                styleCell(sheet.getCell('A5'), { fill: 'E5E7EB', align: 'center', font: { bold: true, size: 11 } });
                styleCell(sheet.getCell('D5'), { fill: 'E5E7EB', align: 'center', font: { bold: true, size: 11 } });
                styleCell(sheet.getCell('G5'), { fill: 'E5E7EB', align: 'center', font: { bold: true, size: 11 } });

                sheet.mergeCells('A6:C6');
                sheet.mergeCells('D6:F6');
                sheet.mergeCells('G6:J6');
                sheet.getCell('A6').value = infoValues[3] || '';
                sheet.getCell('D6').value = infoValues[4] || '';
                sheet.getCell('G6').value = infoValues[5] || '';
                styleCell(sheet.getCell('A6'));
                styleCell(sheet.getCell('D6'));
                styleCell(sheet.getCell('G6'));

                sheet.mergeCells('A7:J7');
                sheet.getCell('A7').value = 'Semestre: ' + (infoValues[6] || '');
                styleCell(sheet.getCell('A7'), { fill: 'DBEAFE', align: 'center', font: { bold: true, size: 11 } });

                var table = content.querySelector('table.tbl');
                if (!table) {
                    return workbook;
                }

                var startRow = 9;
                var headerRow = sheet.getRow(startRow);
                var cleanHeaders = [
                    'No',
                    'Matricule',
                    'Noms',
                    'Niveau',
                    'Quiz',
                    'Recherche',
                    'Autres',
                    'Examen Mi-sem',
                    'ExamenFinal',
                    'Note Finale /20'
                ];

                cleanHeaders.forEach(function(headerTitle, index) {
                    var cell = headerRow.getCell(index + 1);
                    cell.value = headerTitle;
                    styleCell(cell, { fill: '6B7280', align: 'center', font: { bold: true, size: 10, color: { argb: 'FFFFFF' } } });
                });
                headerRow.height = 28;

                // Ligne de ponderation editable: le professeur renseigne les % pour chaque composante.
                var weightRowIndex = startRow + 1;
                var weightRow = sheet.getRow(weightRowIndex);
                weightRow.getCell(4).value = 'Ponderation (%)';
                styleCell(weightRow.getCell(4), { fill: 'FEF3C7', align: 'right', font: { bold: true, size: 10, color: { argb: '92400E' } } });

                [5, 6, 7, 8, 9].forEach(function(colIndex) {
                    var wCell = weightRow.getCell(colIndex);
                    wCell.value = 0;
                    wCell.numFmt = '0.00';
                    styleCell(wCell, { fill: 'FEF3C7', align: 'center', font: { bold: true, size: 10, color: { argb: '92400E' } } });
                    setEditableCell(wCell);
                });

                weightRow.getCell(10).value = 'Total %';
                weightRow.getCell(10).alignment = { horizontal: 'center', vertical: 'middle', wrapText: true };
                weightRow.getCell(10).font = { bold: true, size: 10, color: { argb: '92400E' } };
                weightRow.getCell(10).fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FEF3C7' } };
                weightRow.getCell(10).border = {
                    top: { style: 'thin', color: { argb: '9CA3AF' } },
                    left: { style: 'thin', color: { argb: '9CA3AF' } },
                    bottom: { style: 'thin', color: { argb: '9CA3AF' } },
                    right: { style: 'thin', color: { argb: '9CA3AF' } }
                };
                weightRow.height = 22;

                var rows = table.querySelectorAll('tbody tr');
                var dataRowIndex = startRow + 2;
                rows.forEach(function(tr) {
                    var row = sheet.getRow(dataRowIndex);
                    var cells = tr.querySelectorAll('td');
                    cells.forEach(function(td, index) {
                        var cell = row.getCell(index + 1);
                        var textValue = getText(td);

                        if (index >= 4 && index <= 8) {
                            var note = parseFloat(String(textValue).replace(',', '.'));
                            if (!isNaN(note)) {
                                cell.value = note;
                                cell.numFmt = '0.00';
                            } else {
                                cell.value = '';
                            }
                            setEditableCell(cell);
                        } else if (index === 9) {
                            var currentGrade = parseFloat(String(textValue).replace(',', '.'));
                            cell.value = {
                                formula: 'ROUND((E' + dataRowIndex + '*$E$' + weightRowIndex + ' + F' + dataRowIndex + '*$F$' + weightRowIndex + ' + G' + dataRowIndex + '*$G$' + weightRowIndex + ' + H' + dataRowIndex + '*$H$' + weightRowIndex + ' + I' + dataRowIndex + '*$I$' + weightRowIndex + ')/100,2)',
                                result: !isNaN(currentGrade) ? currentGrade : 0
                            };
                            cell.numFmt = '0.00';
                        } else {
                            cell.value = textValue;
                        }

                        styleCell(cell, { fill: dataRowIndex % 2 === 0 ? 'F9FAFB' : 'FFFFFF' });
                        if (index === 9) {
                            cell.font = { bold: true, color: { argb: '0000FF' } };
                            cell.alignment = { horizontal: 'center', vertical: 'middle' };
                        }
                    });
                    row.height = 18;
                    dataRowIndex++;
                });

                var countText = getText(content.querySelector('b:last-of-type'));
                if (countText) {
                    sheet.mergeCells('A' + (dataRowIndex + 1) + ':C' + (dataRowIndex + 1));
                    sheet.getCell('A' + (dataRowIndex + 1)).value = countText;
                    sheet.getCell('A' + (dataRowIndex + 1)).font = { bold: true };
                    dataRowIndex += 2;
                } else {
                    dataRowIndex += 1;
                }

                // ----------------------------------------------------------------
                // Lignes additionnelles, totalement detachees du tableau HTML.
                // Le professeur peut y inscrire a la main des etudiants manquants
                // (matricule, nom, niveau, notes), avec la meme formule de calcul
                // de note finale que le reste du tableau.
                // ----------------------------------------------------------------
                var EXTRA_ROWS_COUNT = 10;

                sheet.mergeCells('A' + dataRowIndex + ':J' + dataRowIndex);
                var extraTitleCell = sheet.getCell('A' + dataRowIndex);
                extraTitleCell.value = 'Étudiants additionnels';
                styleCell(extraTitleCell, { fill: 'D1FAE5', align: 'center', font: { bold: true, size: 11, color: { argb: '065F46' } } });
                sheet.getRow(dataRowIndex).height = 20;
                dataRowIndex++;

                var extraRowNumberBase = rows.length; // continue la numérotation No après le dernier étudiant

                for (var i = 0; i < EXTRA_ROWS_COUNT; i++) {
                    var row = sheet.getRow(dataRowIndex);

                    // Colonne No : numero auto, continue la sequence
                    var noCell = row.getCell(1);
                    noCell.value = extraRowNumberBase + i + 1;
                    styleCell(noCell, { align: 'center', fill: 'ECFDF5' });

                    // Matricule, Noms, Niveau : libres, modifiables a la main
                    [2, 3, 4].forEach(function(colIndex) {
                        var freeCell = row.getCell(colIndex);
                        freeCell.value = '';
                        styleCell(freeCell, { fill: 'ECFDF5' });
                        setEditableCell(freeCell);
                    });

                    // Notes (Quiz, Recherche, Autres, Examen Mi-sem, ExamenFinal) : libres
                    [5, 6, 7, 8, 9].forEach(function(colIndex) {
                        var noteCell = row.getCell(colIndex);
                        noteCell.value = null;
                        noteCell.numFmt = '0.00';
                        styleCell(noteCell, { align: 'center', fill: 'ECFDF5' });
                        setEditableCell(noteCell);
                    });

                    // Note Finale : meme formule que les lignes generees, reference a la meme ligne de ponderation
                    var finalCell = row.getCell(10);
                    finalCell.value = {
                        formula: 'IF(COUNT(E' + dataRowIndex + ':I' + dataRowIndex + ')=0,"",ROUND((E' + dataRowIndex + '*$E$' + weightRowIndex + ' + F' + dataRowIndex + '*$F$' + weightRowIndex + ' + G' + dataRowIndex + '*$G$' + weightRowIndex + ' + H' + dataRowIndex + '*$H$' + weightRowIndex + ' + I' + dataRowIndex + '*$I$' + weightRowIndex + ')/100,2))',
                        result: ''
                    };
                    finalCell.numFmt = '0.00';
                    styleCell(finalCell, { align: 'center', fill: 'ECFDF5' });
                    finalCell.font = { bold: true, color: { argb: '0000FF' } };

                    row.height = 18;
                    dataRowIndex++;
                }

                // Lignes "tampon" supplementaires si le professeur a besoin de plus
                // de place que les EXTRA_ROWS_COUNT prevues : meme mise en forme et
                // meme formule, mais vides et sans numero pre-rempli.
                var BUFFER_ROWS_COUNT = 30;
                for (var b = 0; b < BUFFER_ROWS_COUNT; b++) {
                    var bufferRow = sheet.getRow(dataRowIndex);

                    [1, 2, 3, 4].forEach(function(colIndex) {
                        var cell = bufferRow.getCell(colIndex);
                        cell.value = '';
                        styleCell(cell, { fill: 'FFFFFF', align: colIndex === 1 ? 'center' : 'left' });
                        setEditableCell(cell);
                    });

                    [5, 6, 7, 8, 9].forEach(function(colIndex) {
                        var cell = bufferRow.getCell(colIndex);
                        cell.value = null;
                        cell.numFmt = '0.00';
                        styleCell(cell, { align: 'center', fill: 'FFFFFF' });
                        setEditableCell(cell);
                    });

                    var bufferFinalCell = bufferRow.getCell(10);
                    bufferFinalCell.value = {
                        formula: 'IF(COUNT(E' + dataRowIndex + ':I' + dataRowIndex + ')=0,"",ROUND((E' + dataRowIndex + '*$E$' + weightRowIndex + ' + F' + dataRowIndex + '*$F$' + weightRowIndex + ' + G' + dataRowIndex + '*$G$' + weightRowIndex + ' + H' + dataRowIndex + '*$H$' + weightRowIndex + ' + I' + dataRowIndex + '*$I$' + weightRowIndex + ')/100,2))',
                        result: ''
                    };
                    bufferFinalCell.numFmt = '0.00';
                    styleCell(bufferFinalCell, { align: 'center', fill: 'FFFFFF' });
                    bufferFinalCell.font = { bold: true, color: { argb: '0000FF' } };

                    bufferRow.height = 18;
                    dataRowIndex++;
                }

                sheet.protect('uaz-remise-notes', {
                    selectLockedCells: false,
                    selectUnlockedCells: true,
                    formatCells: false,
                    formatColumns: false,
                    formatRows: false,
                    insertColumns: false,
                    insertRows: false,
                    insertHyperlinks: false,
                    deleteColumns: false,
                    deleteRows: false,
                    sort: false,
                    autoFilter: false,
                    pivotTables: false
                });

                return workbook;
            }

            buildRemiseNotesWorkbook().xlsx.writeBuffer().then(function(buffer) {
                var blob = new Blob([buffer], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
                var link = document.createElement('a');
                link.href = URL.createObjectURL(blob);
                link.download = filename;
                document.body.appendChild(link);
                link.click();
                setTimeout(function() {
                    URL.revokeObjectURL(link.href);
                    link.remove();
                    if (window.opener == null) {
                        return;
                    }
                }, 500);
            }).catch(function(error) {
                console.error('Erreur export Excel:', error);
                if (window.Toast) { Toast.error('Erreur lors de la création du fichier Excel : ' + error.message); } else if (window.console) { console.error(error); }
            });
    })();
</script>

<script>
        // Initialize WebSocket only when related UI exists.
        (function initOptionalWebSocket() {
            const messagesDiv = document.getElementById('messages');
            const input = document.getElementById('messageInput');

            if (!messagesDiv || !input) {
                return;
            }

            const wsProtocol = window.location.protocol === 'https:' ? 'wss' : 'ws';
            const socket = new WebSocket(`${wsProtocol}://localhost:8080`);

            socket.onopen = function() {
                console.log('Connected to WebSocket server');
            };

            socket.onmessage = function(event) {
                messagesDiv.innerHTML += `<p>${event.data}</p>`;
            };

            window.sendMessage = function() {
                const message = input.value;
                if (socket.readyState === WebSocket.OPEN && message.trim() !== '') {
                    socket.send(message);
                    input.value = '';
                }
            };
        })();
</script>
<?php require('../init/toast.php'); ?>
