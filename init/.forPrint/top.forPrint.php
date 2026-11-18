<?php require_once(__DIR__ . '/../../data/guard.ajax.php'); exigerConnexion();
/* Exception unique : la fiche d'inscription de l'etudiant connecte, ouverte
   par src/student.fiche-inscription.php apres avoir verifie son matricule et
   sa session. La constante est posee par ce code, jamais par la requete. */
if (!defined('IMPRESSION_ETUDIANT')) { refuserEtudiant(); } ?>
<table style="width: 100%; color : black; border-bottom: 1px solid #8e9bb2;">
	<tr>
		<td style="width: 20%;"><img src="../file/UAZ Official Black Logo.jpg" style="height: 85px;"></td>
		<td style="width: 60%; text-align: center;">
			<b style="font-size: 13px;">UNIVERSITÉ ADVENTISTE ZURCHER</b><br>
			<b style="font-size: 11px;">BUREAU DE LA REGISTRAIRE</b>
			<p class="text-xs">BP 325, Antsirabe 110, MADAGASCAR <br>Tél : 034 38 180 81</p>
			<?php /* La devise, comme sur le certificat de scolarite (src/extenssionPrint/certScolarity.php) */ ?>
			<p style="margin: 4px 0 0; font-size: 16px; font-style: italic; font-weight: bold; font-family: 'Matura MT Script Capitals', 'Monotype Corsiva', cursive;">Préparer aujourd'hui les leaders de demain</p>
		</td>
		<td style="width: 20%;"></td>
	</tr>
</table>