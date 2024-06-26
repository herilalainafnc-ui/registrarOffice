<div class="bottom-0 w-full text-xs mt-10">
	<div class="text-sm">
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

		<br><br><br>

		<em>La registraire - Mme. Daniella MALALANIRINA</em>

	</div>
	<div class="border-t border-black flex">
		<div class="w-4/12">Université Adventiste Zurcher</div>
		<div class="w-4/12 text-center">--<?=$ptype?>--</div>
		<div class="w-4/12"></div>
	</div>
</div>