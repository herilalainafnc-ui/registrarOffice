<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Bouton activé / désactivé</title>
  <style>
    :root{
      --toggle-width: 88px;
      --toggle-height: 44px;
      --padding: 6px;             /* espace autour du bouton */
      --off-color: #c55;          /* fond quand off */
      --on-color:  #46c86a;       /* fond quand on */
      --thumb-size: calc(var(--toggle-height) - (var(--padding) * 2));
      --transition-speed: 300ms;
      --thumb-shadow: 0 4px 10px rgba(0,0,0,0.18);
      --track-inner-shadow: inset 0 -4px 8px rgba(0,0,0,0.06);
    }

    /* Conteneur centré (uniquement pour la démo) */
    body{
      display:flex;
      align-items:center;
      justify-content:center;
      min-height:100vh;
      margin:0;
      font-family:system-ui,Segoe UI,Roboto,"Helvetica Neue",Arial;
      background: #f7f7f8;
    }

    /* cache la checkbox réelle mais la laisse accessible */
    .toggle-checkbox{
      position: absolute;
      opacity: 0;
      width: 0;
      height: 0;
      pointer-events: none;
    }

    /* le label fait office d'UI */
    .toggle {
      display:inline-block;
      position:relative;
      width: var(--toggle-width);
      height: var(--toggle-height);
      border-radius: calc(var(--toggle-height) / 2);
      background: var(--off-color);
      padding: var(--padding);
      box-sizing: border-box;
      cursor: pointer;
      transition: background var(--transition-speed) ease, transform 120ms ease;
      box-shadow: 0 6px 14px rgba(0,0,0,0.06);
      -webkit-tap-highlight-color: transparent;
      user-select: none;
      /* petit relief interne */
      background-image: linear-gradient(rgba(255,255,255,0.03), rgba(0,0,0,0.03));
    }

    /* l'aspect interne plus sombre/clair pour relief */
    .toggle::before{
      content:"";
      position:absolute;
      inset:0;
      border-radius:inherit;
      box-shadow: var(--track-inner-shadow);
      pointer-events:none;
    }

    /* la pastille (thumb) */
    .toggle .thumb{
      position:absolute;
      top:50%;
      left: calc(var(--padding));
      transform: translateY(-50%);
      width: var(--thumb-size);
      height: var(--thumb-size);
      border-radius:50%;
      background: linear-gradient(#ffffff, #f3f3f3);
      box-shadow: var(--thumb-shadow);
      transition:
        left var(--transition-speed) cubic-bezier(.2,.9,.25,1),
        transform 160ms ease,
        box-shadow var(--transition-speed) ease;
    }

    /* texte d'état (optionnel) */
    .toggle .state{
      position:absolute;
      top:50%;
      transform: translateY(-50%);
      font-size: 13px;
      font-weight:600;
      left: calc(var(--thumb-size) + var(--padding) + 10px);
      color: rgba(255,255,255,0.95);
      pointer-events: none;
      transition: opacity var(--transition-speed);
      text-shadow: 0 1px 0 rgba(0,0,0,0.08);
    }

    /* état activé : déplace la pastille et change la couleur */
    .toggle-checkbox:checked + .toggle {
      background: var(--on-color);
    }

    .toggle-checkbox:checked + .toggle .thumb{
      left: calc(var(--toggle-width) - var(--thumb-size) - var(--padding));
      transform: translateY(-50%) scale(1.02);
      box-shadow: 0 8px 18px rgba(70,200,120,0.22);
    }

    /* texte On/Off : on visible quand checked */
    .toggle .state.off { opacity: 1; left: calc(var(--padding) + 8px); color: rgba(255,255,255,0.95);}
    .toggle-checkbox:checked + .toggle .state.off { opacity: 0; }

    .toggle .state.on { opacity: 0; left: calc(var(--thumb-size) + var(--padding) + 10px); }
    .toggle-checkbox:checked + .toggle .state.on { opacity: 1; }

    /* focus pour accessibilité */
    .toggle-checkbox:focus + .toggle {
      box-shadow: 0 6px 14px rgba(0,0,0,0.06), 0 0 0 6px rgba(70,200,120,0.12);
      outline: none;
    }

    /* interaction tactile : shrink lors du click */
    .toggle:active .thumb{
      transform: translateY(-50%) scale(0.96);
      transition: transform 70ms;
    }

    /* respect des préférences utilisateur */
    @media (prefers-reduced-motion: reduce) {
      .toggle, .toggle .thumb, .toggle .state { transition: none !important; }
    }
  </style>
</head>
<body>

  <!-- accessible toggle : la checkbox est le vrai contrôle -->
  <input id="myToggle" class="toggle-checkbox" type="checkbox" aria-label="Activer les notifications" />
  <label class="toggle" for="myToggle" role="switch" aria-checked="false">
    <span class="thumb" aria-hidden="true"></span>
    <span class="state off" aria-hidden="true">Off</span>
    <span class="state on" aria-hidden="true">On</span>
  </label>

  <script>
    // maintient aria-checked sur le label pour compatibilité assistive tech
    const checkbox = document.getElementById('myToggle');
    const label = document.querySelector('label[for="myToggle"]');

    function syncAria(){
      label.setAttribute('aria-checked', checkbox.checked ? 'true' : 'false');
    }

    // sync initial
    syncAria();

    // sync à chaque changement
    checkbox.addEventListener('change', syncAria);

    // exemple d'utilisation : log dans la console (à remplacer par votre logique)
    checkbox.addEventListener('change', () => {
      console.log('État du toggle :', checkbox.checked ? 'ON' : 'OFF');
      // ici vous pouvez appeler une API, stocker la valeur, etc.
    });
  </script>

</body>
</html>