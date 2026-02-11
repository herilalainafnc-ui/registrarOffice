<!--
    Student Page Transition System
    GSAP-style slide curtain transitions between student pages
    Creates a seamless SPA-like experience with zero visible latency
-->

<!-- TRANSITION OVERLAY — rendered inline so it's visible BEFORE any CSS/JS loads -->
<div id="pageTransition" style="
    position: fixed;
    top: 0; left: 0;
    width: 100vw; height: 100vh;
    z-index: 99999;
    pointer-events: none;
    overflow: hidden;
">
    <!-- Main curtain panel -->
    <div id="curtainPanel" style="
        position: absolute;
        top: 0; left: 0;
        width: 100%; height: 100%;
        background: #0a1628;
        transform: translateY(-101%);
        will-change: transform;
    ">
        <!-- Subtle accent line at bottom of curtain -->
        <div style="
            position: absolute;
            bottom: 0; left: 0;
            width: 100%; height: 2px;
            background: linear-gradient(90deg, transparent, rgba(14, 165, 233, 0.5), transparent);
        "></div>
        <!-- Center loader (visible only during slow connections) -->
        <div id="curtainLoader" style="
            position: absolute;
            top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1rem;
            opacity: 0;
            transition: opacity 0.3s ease;
        ">
            <div style="
                width: 36px; height: 36px;
                border: 2px solid rgba(14, 165, 233, 0.15);
                border-top-color: #0ea5e9;
                border-radius: 50%;
                animation: curtainSpin 0.8s linear infinite;
            "></div>
            <span style="
                font-family: 'Inter', 'Segoe UI', sans-serif;
                font-size: 0.75rem;
                color: rgba(142, 184, 212, 0.6);
                letter-spacing: 0.08em;
            ">Chargement...</span>
        </div>
    </div>
</div>

<style>
    @keyframes curtainSpin {
        to { transform: rotate(360deg); }
    }

    /* Page content starts hidden when arriving via transition */
    body.page-entering {
        opacity: 0;
    }
</style>

<script>
(function() {
    'use strict';

    const TRANSITION_DURATION = 520;  // ms — curtain slide duration
    const LOADER_DELAY = 600;         // ms — show loader only if page hasn't loaded yet
    const EASING = 'cubic-bezier(0.76, 0, 0.24, 1)'; // GSAP Power4.inOut equivalent

    const curtain = document.getElementById('curtainPanel');
    const loader = document.getElementById('curtainLoader');
    const overlay = document.getElementById('pageTransition');

    // ─── ENTRY: Reveal page on load ───────────────────────────
    // If we arrived here via a transition (flag in sessionStorage),
    // the curtain starts covering the page, then slides UP to reveal.
    if (sessionStorage.getItem('__st_transitioning') === '1') {
        sessionStorage.removeItem('__st_transitioning');

        // Curtain starts DOWN (covering the page)
        curtain.style.transition = 'none';
        curtain.style.transform = 'translateY(0%)';
        overlay.style.pointerEvents = 'all';

        // Wait for page to be fully rendered
        const revealPage = () => {
            requestAnimationFrame(() => {
                requestAnimationFrame(() => {
                    // Slide curtain UP to reveal
                    curtain.style.transition = `transform ${TRANSITION_DURATION}ms ${EASING}`;
                    curtain.style.transform = 'translateY(-101%)';
                    overlay.style.pointerEvents = 'none';

                    // Cleanup
                    setTimeout(() => {
                        curtain.style.transition = 'none';
                    }, TRANSITION_DURATION + 50);
                });
            });
        };

        if (document.readyState === 'complete') {
            revealPage();
        } else {
            window.addEventListener('load', revealPage);
        }
    }

    // ─── EXIT: Intercept links and slide curtain DOWN ─────────
    // Student pages we want transitions for
    const STUDENT_PATHS = [
        'student.home', 'student.dashboard', 'student.home.php', 'student.dashboard.php',
        'student.game', 'student.info', 'student.actus', 'student.quiz',
        'student.game.php', 'student.info.php', 'student.actus.php', 'student.quiz.php',
        'student/bulletin', 'student/transcript', 'student/information',
        'student/historique-notes', 'student/diplome', 'student/transcriptSS',
        'student/newCours', 'student/courssupprim', 'student/histNotes', 'student/histInfos',
        'student?page=', 'student.php?page='
    ];

    function isStudentLink(href) {
        if (!href) return false;
        // Exclude logout, external links, anchors, and javascript:
        if (href.includes('logout') || href.includes('mailto:') || href.startsWith('#') || href.startsWith('javascript:')) {
            return false;
        }
        if (href.startsWith('http') && !href.includes(location.hostname)) {
            return false;
        }
        return STUDENT_PATHS.some(path => href.includes(path));
    }

    function navigateWithTransition(href) {
        // Block further clicks
        overlay.style.pointerEvents = 'all';

        // Set flag so the destination page knows to play reveal animation
        sessionStorage.setItem('__st_transitioning', '1');

        // Slide curtain DOWN over current page
        curtain.style.transition = `transform ${TRANSITION_DURATION}ms ${EASING}`;
        curtain.style.transform = 'translateY(0%)';

        // Show loader if navigation takes too long (slow connection)
        const loaderTimeout = setTimeout(() => {
            if (loader) loader.style.opacity = '1';
        }, LOADER_DELAY);

        // Preload the destination page
        const preloadPromise = fetch(href, { credentials: 'same-origin' })
            .then(r => r.text())
            .catch(() => null); // On error, just navigate normally

        // Wait for BOTH animation and preload to complete, then navigate
        const animationDone = new Promise(resolve => {
            setTimeout(resolve, TRANSITION_DURATION);
        });

        Promise.all([animationDone, preloadPromise]).then(([_, html]) => {
            clearTimeout(loaderTimeout);

            if (html !== null) {
                // Prefetch succeeded → page is cached by browser,
                // navigating will be instant from cache
                window.location.href = href;
            } else {
                // Fetch failed → navigate anyway
                window.location.href = href;
            }
        });
    }

    // Intercept all clicks on student links (delegation)
    document.addEventListener('click', function(e) {
        const link = e.target.closest('a[href]');
        if (!link) return;

        const href = link.getAttribute('href');
        if (isStudentLink(href)) {
            e.preventDefault();
            navigateWithTransition(link.href);
        }
    });

    // Also handle back/forward navigation
    window.addEventListener('pageshow', function(e) {
        if (e.persisted) {
            // Page was restored from bfcache — reset curtain
            curtain.style.transition = 'none';
            curtain.style.transform = 'translateY(-101%)';
            overlay.style.pointerEvents = 'none';
            sessionStorage.removeItem('__st_transitioning');
        }
    });

})();
</script>
