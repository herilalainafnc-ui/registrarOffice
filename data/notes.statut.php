<?php
/* =============================================================================
   Statut d'une note (t_2023_notes.grade)

   La colonne grade est un VARCHAR qui mêle notes chiffrées et codes :
     -2   validé sans note          → V
     -3   échec sans note           → E
     -4   pas encore noté           → NN
     -1   ancien code hérité        → NN
     vide, NULL, non numérique      → NN
   Un 0 saisi est une vraie note de 0/20 : c'est un échec et il compte dans la
   moyenne (voir data/migration_note_non_notee.sql).

   Les cours de validation (catégorie 5, RELP 291, formation spirituelle) ne
   s'expriment qu'en V ou E et ne comptent jamais dans la moyenne.

   Une seule règle pour les pages et les exports : chaque écran refaisait son
   propre test, et plusieurs affichaient « -4 » tel quel ou le comptaient
   dans la moyenne (crédit × -4).
   ========================================================================== */

if (!function_exists('noteStatut')) {

    /** Cours noté seulement en V / E. Accepte une ligne de t_2023_notes ou de t_2023_cours. */
    function noteEstCoursValidation(array $ligne): bool
    {
        $categorie = $ligne['cours_category'] ?? ($ligne['category'] ?? null);
        $sigle     = (string) ($ligne['Sigle'] ?? '');
        $titre     = (string) ($ligne['title_cours'] ?? ($ligne['title'] ?? ''));

        return (int) $categorie === 5
            || stripos($sigle, 'RELP 291') !== false
            || stripos($titre, 'formation spirituelle') !== false;
    }

    /**
     * @return array{code:string, libelle:string, titre:string, chiffree:bool, compte:bool, valeur:?float}
     *   code      S (succès), E (échec), V (validé) ou NN (pas encore noté)
     *   libelle   ce qu'affiche la colonne note : la note, ou le code
     *   chiffree  vraie note numérique, 0 compris
     *   compte    entre dans la moyenne
     *   valeur    la note en nombre, null pour un code
     */
    function noteStatut($grade, bool $coursValidation = false): array
    {
        $brut   = trim(str_replace(',', '.', (string) ($grade ?? '')));
        $valeur = is_numeric($brut) ? (float) $brut : null;

        if ($valeur === -2.0) {
            return noteStatutCode('V', 'Validé');
        }
        if ($valeur === -3.0) {
            return noteStatutCode('E', 'Échec');
        }
        if ($valeur === null || $valeur < 0) {
            return noteStatutCode('NN', 'Pas encore noté');
        }
        if ($coursValidation) {
            return $valeur >= 10 ? noteStatutCode('V', 'Validé') : noteStatutCode('E', 'Échec');
        }

        return [
            'code'     => $valeur >= 10 ? 'S' : 'E',
            'libelle'  => $brut,
            'titre'    => $valeur >= 10 ? 'Succès' : 'Échec',
            'chiffree' => true,
            'compte'   => true,
            'valeur'   => $valeur,
        ];
    }

    function noteStatutCode(string $code, string $titre): array
    {
        return ['code' => $code, 'libelle' => $code, 'titre' => $titre,
                'chiffree' => false, 'compte' => false, 'valeur' => null];
    }

    /** Ce qu'on affiche dans une colonne « Note » : 12.5, V, E ou NN. */
    function noteLibelle($grade, bool $coursValidation = false): string
    {
        return noteStatut($grade, $coursValidation)['libelle'];
    }

    function noteCompteDansMoyenne($grade, bool $coursValidation = false): bool
    {
        return noteStatut($grade, $coursValidation)['compte'];
    }

    /** Classe Tailwind de la pastille d'état. */
    function noteClasseEtat(string $code): string
    {
        switch ($code) {
            case 'S':
            case 'V':
                return 'bg-green-500';
            case 'E':
                return 'bg-red-500';
            case 'NN':
                return 'bg-slate-500';
            default:
                return '';
        }
    }
}
