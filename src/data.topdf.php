<?php 
require('../data/session.php');
/*require('../data/connectdb.php');*/
require('../data/backdb.php');
require('../init/head.noTem.php');
$ptype = $_GET['ptype'];
$h = (date('H')+3);
$date = 'heure_'.date($h.'-i-s').' date_'.date('d-m-Y');
?>
<title>Exportation</title>

<body class="bg-slate-600 h-screen" style="background-color: #475469;">

    <div class="bg-slate-400 flex h-10 p-1">

        <div class="sm:w-5/12 sm:text-left lg:w-3/12 lg:text-right p-1">
        <a href="./accueil.php" class="px-2 py-1 rounded-md border border-black"><span class="bi-house-door-fill"></span> Accueil</a>
        </div>
        
        <div class="sm:w-2/12 lg:w-6/12 text-center">
            <p class="b-title center"><b><?=$ptype?></b></p>
        </div>
        <div class="sm:w-5/12 sm:text-right lg:text-left lg:w-3/12">
            
            <form action="data.toxlsx.php" method="post" id="exportForm">
                
                <input type="hidden" name="htmlContent" id="htmlContent" value="">
                
                <button type="submit" class="px-2 py-1 rounded-md text-white bg-green-700 opacity-0" id="btnToExcel"><span class="bi-file-earmark-spreadsheet"></span> Excel</button>
                
                <a href="#" onclick="printThisContent()" class="px-2 py-[5.5px] rounded-md text-white bg-red-800"><span class="bi-filetype-pdf"></span> Pdf</a>
                
                <a class="ml-2 text-sm"><b><i class="bi-arrow-repeat"></i></b> portrait</a>
            </form>
            
        </div>
    </div>
    <div style="overflow: auto; width: 100%; height: calc(100% - 43px);">

        <div style="border: 1px solid #475460; box-shadow: 0px 0px 20px #475469; padding: 35px;background: white; width: 800px;margin: auto; margin-top: 20px; margin-bottom: 20px;">
            
<!-- ::::::::::::::::::::::::::::: CONTENTS PRINT ::::::::::::::::::::::::::::::::: -->            

    <div id="printThisContent" style="min-height: 1020px; width: 100%; position: relative;">
        
    <?php

        if($ptype != "Badge" AND $ptype != "Abonnement Caf" AND $ptype !="ticketMail" AND $ptype !="workedSlip") { 
        
            require('../init/.forPrint/top.forPrint.php'); 
        
        }
        
        if($ptype == "Statistique") {
            require ('./extenssionPrint/statistic-sexe.php');
            require ('./extenssionPrint/statistic-religion.php');
            
        }elseif($ptype == "Badge"){
            require ('./extenssionPrint/badge.php');
        }elseif($ptype == "Bulletin"){
            require ('./extenssionPrint/bulletin.php');
        }elseif($ptype == "Transcript"){
            require ('./extenssionPrint/transcript.php');
        }elseif($ptype == "Diplôme"){
            require ('./extenssionPrint/diplome.php');
        }elseif($ptype == "Checklist"){
            require ('./extenssionPrint/vrai.cheklist.php');
        }elseif($ptype == "Certificat de scolarité"){
            require ('./extenssionPrint/certScolarity.php');
        }elseif($ptype == "Abonnement Caf"){
            require ('./extenssionPrint/abonnement.caf.php');
        }elseif($ptype == "TranscriptSS"){
            require ('./extenssionPrint/transcriptSS.php');
        }elseif($ptype == "Worked_point"){
            require ('./extenssionPrint/worked_point.php');
        }elseif($ptype == "listeStd"){
            require ('./extenssionPrint/std.list.php');
        }elseif($ptype == "listeCours"){
            require ('./extenssionPrint/cours.list.php');
        }elseif($ptype == "ticketMail"){
            require ('./extenssionPrint/ticket.mail.php');
        }elseif($ptype == "workedSlip"){
            require ('./extenssionPrint/worked.Slip.php');
        }elseif($ptype == "Fiche_inscription"){
            require ('./extenssionPrint/fiche_inscription.php');
        }


        if($ptype != "Badge" AND $ptype != "Fiche_inscription" AND $ptype != "Abonnement Caf" AND $ptype != "Worked_point" AND $ptype !="ticketMail" AND $ptype !="workedSlip") {
        
            require('../init/.forPrint/foot.forPrint.php');

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

<script type="text/javascript">

    var printConge = document.getElementById('printThisContent');

    function printThisContent(){

        alert('Download PDF processing !');

        var opt = {
            margin:     0.5,
            filename:   '<?=$printName?> <?=$date?>.pdf',
            image:      { type: 'jpeg', quality: 1 },
            html2canvas:{ scale: 4, logging: true, useCORS: true },
            jsPDF:      { unit: 'in', format: 'a4', orientation: 'portrait' }
        };

         // New Promise-based usage:
         html2pdf().set(opt).from(printConge).save();

         // Old monolithic-style usage:
         html2pdf(printConge, opt);

        }

        // Ajouter le contenu HTML au champ caché et soumettre le formulaire
        document.getElementById('btnToExcel').addEventListener('click', function() {
        document.getElementById('htmlContent').value = document.getElementById('printThisContent').outerHTML;
        document.getElementById('exportForm').submit();
    });
</script>