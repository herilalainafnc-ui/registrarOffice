<?php
/*
 * Presences aux cours (data/presence.php) : les regles du Bulletin
 * academique, ASSIDUITE §474 et §476. Sans base de donnees.
 *
 *   php tests/test_presence.php
 */
$base = dirname(__DIR__);
require "$base/data/presence.php";

$ok = 0; $ko = 0;
function verifier(string $nom, bool $cond, string $detail = ''): void
{
	global $ok, $ko;
	if ($cond) { $ok++; echo "  ok  $nom\n"; }
	else { $ko++; echo "  KO  $nom" . ($detail !== '' ? "  ($detail)" : '') . "\n"; }
}
/** Un pointage, pour ecrire les cas lisiblement. */
function p(?string $statut, $minutes = null, bool $sansPenalite = false): array
{
	return ['statut' => $statut, 'minutes_retard' => $minutes, 'sans_penalite' => $sansPenalite];
}

echo "Retards (§476)\n";
verifier('4 min de retard : a l\'heure', presenceCategorie('retard', 4) === 'present');
verifier('5 min : un retard', presenceCategorie('retard', 5) === 'retard');
verifier('9 min : un retard', presenceCategorie('retard', 9) === 'retard');
verifier('10 min : une absence', presenceCategorie('retard', 10) === 'absence');
verifier('retard sans duree : compte 5 min', presenceCategorie('retard', null) === 'retard');
verifier('non pointe : ne compte pas', presenceCategorie(null) === null);

$b = presenceBilan([p('retard', 6), p('retard', 7), p('retard', 8), p('present')], 30);
verifier('deux retards font une absence', $b['retards'] === 3 && $b['comptees'] === 1, json_encode($b));
verifier('un troisieme retard reste en attente', $b['retard_en_attente'] === true);

echo "Seuil des 15 %\n";
verifier('30 seances : 4 absences permises', presenceAbsencesPermises(30) === 4);
verifier('20 seances : 3 permises (15 % pile ne depasse pas)', presenceAbsencesPermises(20) === 3);
verifier('6 seances : aucune permise', presenceAbsencesPermises(6) === 0);
verifier('seances prevues inconnues : pas de seuil', presenceAbsencesPermises(null) === null);

$quatre = presenceBilan(array_fill(0, 4, p('absent')), 30);
verifier('4 absences sur 30 : a la limite', $quatre['etat'] === 'limite', $quatre['etat']);
$cinq = presenceBilan(array_fill(0, 5, p('absent')), 30);
verifier('5 absences sur 30 : depasse', $cinq['etat'] === 'depasse' && $cinq['taux'] === 16.7, json_encode($cinq));
$retards = presenceBilan(array_merge(array_fill(0, 4, p('absent')), [p('retard', 5), p('retard', 5)]), 30);
verifier('4 absences et 2 retards sur 30 : depasse', $retards['comptees'] === 5 && $retards['etat'] === 'depasse');
$tard = presenceBilan([p('retard', 12), p('retard', 15)], 20);
verifier('les retards de 10 min et plus comptent en absences', $tard['absences'] === 2 && $tard['retards'] === 0);
verifier('sans seances prevues : etat inconnu', presenceBilan([p('absent')], null)['etat'] === 'inconnu');
verifier('aucune absence : ok', presenceBilan([p('present'), p('present')], 30)['etat'] === 'ok');

echo "Retard de l'enseignant (§474)\n";
$seance = ['heure_prevue' => '08:00:00', 'arrivee_enseignant' => '08:15:00', 'message_retard' => 0];
verifier('15 min de retard de l\'enseignant', presenceRetardEnseignant($seance) === 15);
verifier('sans message : seance sans penalite', presenceSeanceSansPenalite($seance) === true);
verifier('avec message : la seance compte', presenceSeanceSansPenalite(['message_retard' => 1] + $seance) === false);
verifier('10 min pile : la seance compte', presenceSeanceSansPenalite(['arrivee_enseignant' => '08:10:00'] + $seance) === false);
verifier('heure d\'arrivee inconnue : la seance compte', presenceSeanceSansPenalite(['arrivee_enseignant' => null] + $seance) === false);
$neutre = presenceBilan([p('absent', null, true), p('retard', 7, true), p('absent')], 30);
verifier('absences d\'une seance sans penalite non comptees', $neutre['comptees'] === 1 && $neutre['sans_penalite'] === 2, json_encode($neutre));

echo "Libelles\n";
verifier('retard compte absence', presenceLibellePointage('retard', 12) === 'Retard 12 min (compte absence)');
verifier('retard tolere', presenceLibellePointage('retard', 3) === 'Retard 3 min (toléré)');

echo "\n$ok reussite(s), $ko echec(s)\n";
exit($ko === 0 ? 0 : 1);
