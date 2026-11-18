"""Studio UAZ — les instances fixes d'Archivo (src/studio/polices/).

La police de la charte, Archivo (OFL), est variable (largeur 62-125, graisse
100-900). Chromium incorpore une police variable dans un PDF en « Type 3 »
(des dessins de glyphes) : le texte reste selectionnable, mais ce n'est pas
une vraie police, et les imprimeurs la refusent parfois. On fige donc les
seules combinaisons de la charte en polices fixes (TrueType), que Chromium
incorpore telles quelles (CIDFontType2) :

    largeur 100 % : graisses 400, 500, 600, 700, 800
    largeur  92 % : graisse 800 (les titres)

pour chacun des deux jeux de caracteres servis (latin, latin etendu).
Sources : source/archivo-latin.woff2 et source/archivo-latin-ext.woff2 (Google
Fonts, police variable). Les fichiers produits vont dans src/studio/polices/,
declares par src/studio/charte.css.

    python -m venv venv && venv/bin/pip install fonttools==4.55.3 brotli==1.1.0
    venv/bin/python outils/studio-polices/instancier.py
"""
from pathlib import Path

from fontTools.ttLib import TTFont
from fontTools.varLib import instancer

ICI = Path(__file__).resolve().parent
SORTIE = ICI.parent.parent / 'src' / 'studio' / 'polices'
INSTANCES = [(100, 400), (100, 500), (100, 600), (100, 700), (100, 800), (92, 800)]
JEUX = ['latin', 'latin-ext']


def main() -> None:
    for jeu in JEUX:
        source = ICI / 'source' / f'archivo-{jeu}.woff2'
        for largeur, graisse in INSTANCES:
            police = TTFont(source)
            fixe = instancer.instantiateVariableFont(police, {'wdth': largeur, 'wght': graisse}, inplace=False)
            for table in ('fvar', 'gvar', 'HVAR', 'cvar'):  # STAT, descriptive, peut rester
                assert table not in fixe, f'{table} reste dans {jeu} {largeur} {graisse}'
            fixe.flavor = 'woff2'
            cible = SORTIE / f'archivo-{largeur}-{graisse}-{jeu}.woff2'
            fixe.save(cible)
            print(f'{cible.name} : {cible.stat().st_size} octets')


if __name__ == '__main__':
    main()
