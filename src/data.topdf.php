<?php 
require('../data/session.php');
require('../data/backdb.php');
require('../init/head.noTem.php');
$ptype = $_GET['ptype'];
$h = (date('H')+1);
$date = 'h_'.date($h.'-i-s').' date_'.date('d-m-Y');
?>
<title>Exportation (portrait)</title>

<body class="h-screen" style="background-color:#63748b;">

    <div class="bg-slate-800 flex h-10 p-1">

        <div class="sm:w-5/12 py-1 text-left lg:w-4/12">
        <a href="./" class="flex text-white">
            <img src="../file/logo-coldbloud.png" class="w-6 h-6 mx-3 mx-2">
            <b> Infinit Registrar</b>
        </a>

        </div>
        
        <div class="sm:w-2/12 lg:w-4/12 text-center">
            <p class="b-title center"><b><?=$ptype?></b></p>
        </div>
        <div class="sm:w-5/12 text-right lg:w-4/12 pr-4">
            
            <form action="data.toxlsx.php" method="post" id="exportForm">
                
                <input type="hidden" name="htmlContent" id="htmlContent" value="">
                
                <button type="submit" class="px-2 py-1 rounded-md text-white bg-green-700 opacity-0" id="btnToExcel"><span class="bi-file-earmark-spreadsheet"></span> Excel</button>
                
                <!-- <a href="./gen.pdf.php?ptype=<?=$ptype?>" target="_blank" class="px-2 py-[5.5px] rounded-md text-white bg-red-800"><span class="bi-filetype-pdf"></span><i class="text-[10px]">(Text)</i>.Pdf</a> -->

                <a href="#" onclick="printThisContent()" class="px-2 py-[5.5px] rounded-md text-white bg-blue-800"><span class="bi-file-earmark-image-fill"></span><i class="text-[10px]">(Image)</i>.Pdf</a>
                
                <!-- <a class="ml-2 text-sm"><b><i class="bi-arrow-repeat"></i></b> portrait</a> -->
            </form>
            
        </div>
    </div>
    <div style="overflow: auto; width: 100%; height: calc(100% - 43px);">

        <div style="border: 1px solid #475460; box-shadow: 0px 0px 20px #475469; padding: 35px;background: white; width: 800px;margin: auto; margin-top: 20px; margin-bottom: 20px;">
            
<!-- ::::::::::::::::::::::::::::: CONTENTS PRINT ::::::::::::::::::::::::::::::::: -->            

    <div id="printThisContent" style="min-height: 1020px; width: 100%; position: relative;">
        
    <?php
        
        if($ptype == "Statistique") {
            $printName = "STATISTIQUE";
            require('../init/.forPrint/top.forPrint.php');

            echo "<br>";
            require ('./extenssionPrint/statistic-general.php');
            echo "<br>";
            require ('./extenssionPrint/statistic-internat.php');
            echo "<br>";
            require ('./extenssionPrint/statistic-abonment.php');
            echo "<br>";
            require ('./extenssionPrint/statistic-sexe.php');
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

        var scl = parseFloat('<?=$scale?>');
        var qlt = parseFloat('<?=$quality?>');

        alert('Download PDF processing ! quality '+qlt+', scale '+scl);

        

        var opt = {
            margin:     0.5,
            filename:   '<?=$printName?>-image_<?=$date?>.pdf',
            image:      { type: 'jpeg', quality: qlt },
            html2canvas:{ scale: scl, logging: true, useCORS: true },
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

<script>
        // Connexion au serveur WebSocket
        const socket = new WebSocket('ws://localhost:8080');

        // Gérer la connexion
        socket.onopen = function() {
            console.log('Connecté au serveur WebSocket PHP');
        };

        // Afficher les messages reçus
        socket.onmessage = function(event) {
            const messagesDiv = document.getElementById('messages');
            messagesDiv.innerHTML += `<p>${event.data}</p>`;
        };

        // Envoyer un message au serveur
        function sendMessage() {
            const input = document.getElementById('messageInput');
            const message = input.value;
            socket.send(message);
            input.value = '';
        }
</script>