
<?php $printStamp = preg_replace('/\s+/', ' ', trim((string)($date ?? ''))); ?>

<div class="bottom-0 w-full text-xs mt-10">
    <div class="text-xs" style="page-break-inside: avoid;">
        <p>Sambaina, <?php
                     echo date('d')." ";
                     $volana = date('m');
                     if($volana == '01'){echo('Janvier ');}
                     else if($volana == '02'){echo('Fevrier ');}
                     else if($volana == '03'){echo('Mars ');}
                     else if($volana == '04'){echo('Avril ');}
                     else if($volana == '05'){echo('Mai ');}
                     else if($volana == '06'){echo('Juin ');}
                     else if($volana == '07'){echo('Juillet ');}
                     else if($volana == '08'){echo('Aout ');}
                     else if($volana == '09'){echo('Septembre ');}
                     else if($volana == '10'){echo('Octobre ');}
                     else if($volana == '11'){echo('Novembre ');}
                     else if($volana == '12'){echo('Decembre ');}
                     echo date('Y')
                     ?></p>

        <br><br>

        <div style="height: 60px; margin-bottom: 4px;">
            <img src="<?=isset($app_base) ? $app_base : ''?>/file/signature.png" alt="Signature Daniella Malalanirina" style="height: 60px; max-width: 220px; object-fit: contain;">
        </div>
        <em>La registraire : Madame Daniella MALALANIRINA</em>

    </div>
    <div class="border-t border-black flex" style="page-break-inside: avoid; align-items: center;">
        <div class="w-4/12">Université Adventiste Zurcher</div>
        <div class="w-4/12 text-center">
            <span style="display: inline-block; padding: 2px 8px; border: 1px solid #111827; border-radius: 999px; font-family: monospace; font-size: 10px; letter-spacing: .2px;">[ <?=$printStamp?> ]</span>
        </div>
        <div class="w-4/12"></div>
    </div>
</div>