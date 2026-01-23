/**
 * Infinit Registrar - Landing Page Script
 * Animated slider with GSAP
 */

// Data for the UAZ mentions (departments/programs)
const data = [
    {
        place: 'Mention Théologie',
        title: 'THÉOLOGIE',
        title2: '',
        description: 'Approfondissez votre compréhension des textes sacrés et de la théologie adventiste. Une formation spirituelle et académique pour ceux qui souhaitent servir l\'Église et enseigner la Parole.',
        image: 'https://actualites.adventiste.org/wp-content/uploads/sites/3/2019/09/Bible-etudde.jpg'
    },
    {
        place: 'Mention Gestion',
        title: 'GESTION',
        title2: '',
        description: 'Développez vos compétences en leadership et en gestion d\'entreprise. Notre programme forme les futurs dirigeants capables de relever les défis économiques contemporains avec intégrité.',
        image: 'https://zurcher.edu.mg/wp-content/uploads/2026/01/b.jpg'
    },
    {
        place: 'Mention Informatique',
        title: 'INFORMATIQUE',
        title2: '',
        description: 'Plongez dans le monde du numérique avec nos programmes en informatique. Développement logiciel, systèmes d\'information, réseaux - préparez-vous aux métiers de demain.',
        image: 'https://images.unsplash.com/photo-1517694712202-14dd9538aa97?q=80&w=2070&auto=format&fit=crop'
    },
    {
        place: 'Mention Sciences Infirmières',
        title: 'SCIENCES',
        title2: 'INFIRMIÈRES',
        description: 'Formez-vous aux métiers de la santé avec notre programme d\'excellence en sciences infirmières. Un cursus complet alliant théorie et pratique clinique pour devenir des professionnels de santé qualifiés.',
        image: 'https://latribune.cyber-diego.com/images/stories/2014/aout/formation-paramedicaux-antsiranana.jpg'
    },
    {
        place: 'Mention Éducation',
        title: 'ÉDUCATION',
        title2: '',
        description: 'Devenez un acteur du changement dans l\'éducation. Notre programme forme des enseignants innovants et des pédagogues passionnés par la transmission du savoir.',
        image: 'https://zurcher.edu.mg/wp-content/uploads/2026/01/Design-sans-titre-6.jpg'
    },
    {
        place: 'Mention Communication',
        title: 'COMMUNICATION',
        title2: '',
        description: 'Maîtrisez l\'art de la communication dans un monde connecté. Journalisme, relations publiques, médias numériques - exprimez-vous avec impact et professionnalisme.',
        image: 'https://zurcher.edu.mg/wp-content/uploads/2026/01/Design-sans-titre-1.jpg'
    },
    {
        place: 'Mention Études Anglophones',
        title: 'ÉTUDES',
        title2: 'ANGLOPHONES',
        description: 'Explorez la richesse de la langue et de la culture anglophone. Littérature, linguistique et civilisation pour une ouverture internationale et des opportunités professionnelles élargies.',
        image: 'https://zurcher.edu.mg/wp-content/uploads/2026/01/EA.jpg'
    },
    {
        place: 'Mention Droit',
        title: 'DROIT',
        title2: '',
        description: 'Formez-vous aux fondements du droit et de la justice. Notre programme prépare les futurs juristes à exercer avec éthique et compétence dans un monde en constante évolution.',
        image: 'https://images.unsplash.com/photo-1589829545856-d10d557cf95f?q=80&w=2070&auto=format&fit=crop'
    },
];

// Helper function to get element by ID
const _ = (id) => document.getElementById(id);

// Generate card HTML
const cards = data.map((i, index) => `<div class="card" id="card${index}" style="background-image:url(${i.image})"></div>`).join('');

// Generate card content HTML
const cardContents = data.map((i, index) => `<div class="card-content" id="card-content-${index}">
<div class="content-start"></div>
<div class="content-place">${i.place}</div>
<div class="content-title-1">${i.title}</div>
${i.title2 ? `<div class="content-title-2">${i.title2}</div>` : ''}
</div>`).join('');

// Generate slide numbers HTML
const slideNumbers = data.map((_, index) => `<div class="item" id="slide-item-${index}">${index + 1}</div>`).join('');

// Inject HTML into the DOM
_('demo').innerHTML = cards + cardContents;
_('slide-numbers').innerHTML = slideNumbers;

// Helper function to create range array
const range = (n) => Array(n).fill(0).map((i, j) => i + j);
const set = gsap.set;

// Helper functions to get element selectors
function getCard(index) {
    return `#card${index}`;
}

function getCardContent(index) {
    return `#card-content-${index}`;
}

function getSliderItem(index) {
    return `#slide-item-${index}`;
}

// Animate function wrapper
function animate(target, duration, properties) {
    return new Promise((resolve) => {
        gsap.to(target, {
            ...properties,
            duration: duration,
            onComplete: resolve,
        });
    });
}

// State variables
let order = [0, 1, 2, 3, 4, 5, 6, 7];
let detailsEven = true;
let isPaused = false;

// Layout variables
let offsetTop = 200;
let offsetLeft = 700;
let cardWidth = 200;
let cardHeight = 300;
let gap = 40;
let numberSize = 50;
const ease = "sine.inOut";

// Get proper viewport height (handles mobile address bar)
function getViewportHeight() {
    return window.visualViewport?.height || document.documentElement.clientHeight || window.innerHeight;
}

// Responsive layout adjustments
function updateLayoutVariables() {
    const width = window.innerWidth;
    const height = getViewportHeight();
    
    if (width <= 600) {
        // Mobile
        cardWidth = 0; // Hide mini cards
        cardHeight = 0;
        gap = 0;
        offsetTop = height - 180;
        offsetLeft = 16;
    } else if (width <= 900) {
        // Tablet
        cardWidth = 0; // Hide mini cards
        cardHeight = 0;
        gap = 0;
        offsetTop = height - 250;
        offsetLeft = 30;
    } else if (width <= 1200) {
        // Small desktop
        cardWidth = 160;
        cardHeight = 240;
        gap = 30;
        offsetTop = height - 380;
        offsetLeft = width - 680;
    } else {
        // Desktop
        cardWidth = 200;
        cardHeight = 300;
        gap = 40;
        offsetTop = height - 430;
        offsetLeft = width - 830;
    }
}

// Initialize the slider
function init() {
    updateLayoutVariables();
    
    const [active, ...rest] = order;
    const detailsActive = detailsEven ? "#details-even" : "#details-odd";
    const detailsInactive = detailsEven ? "#details-odd" : "#details-even";
    const height = getViewportHeight();
    const width = window.innerWidth;

    gsap.set("#pagination", {
        top: offsetTop + 330,
        left: offsetLeft,
        y: 200,
        opacity: 0,
        zIndex: 60,
    });
    gsap.set("nav", { y: -200, opacity: 0 });
    gsap.set("#stats", { y: 30, opacity: 0 });

    gsap.set(getCard(active), {
        x: 0,
        y: 0,
        width: window.innerWidth,
        height: getViewportHeight(),
    });
    gsap.set(getCardContent(active), { x: 0, y: 0, opacity: 0 });
    gsap.set(detailsActive, { opacity: 0, zIndex: 22, x: -200 });
    gsap.set(detailsInactive, { opacity: 0, zIndex: 12 });
    gsap.set(`${detailsInactive} .text`, { y: 100 });
    gsap.set(`${detailsInactive} .title-1`, { y: 100 });
    gsap.set(`${detailsInactive} .title-2`, { y: 100 });
    gsap.set(`${detailsInactive} .desc`, { y: 50 });
    gsap.set(`${detailsInactive} .cta`, { y: 60 });

    gsap.set(".progress-sub-foreground", {
        width: 500 * (1 / order.length) * (active + 1),
    });

    rest.forEach((i, index) => {
        gsap.set(getCard(i), {
            x: offsetLeft + 400 + index * (cardWidth + gap),
            y: offsetTop,
            width: cardWidth,
            height: cardHeight,
            zIndex: 30,
            borderRadius: 10,
        });
        gsap.set(getCardContent(i), {
            x: offsetLeft + 400 + index * (cardWidth + gap),
            zIndex: 40,
            y: offsetTop + cardHeight - 100,
        });
        gsap.set(getSliderItem(i), { x: (index + 1) * numberSize });
    });

    gsap.set(".indicator", { x: -window.innerWidth });

    const startDelay = 0.6;

    gsap.to(".cover", {
        x: width + 400,
        delay: 0.5,
        ease,
        onComplete: () => {
            setTimeout(() => {
                loop();
            }, 500);
        },
    });

    rest.forEach((i, index) => {
        gsap.to(getCard(i), {
            x: offsetLeft + index * (cardWidth + gap),
            zIndex: 30,
            delay: startDelay + 0.05 * index,
            ease,
        });
        gsap.to(getCardContent(i), {
            x: offsetLeft + index * (cardWidth + gap),
            zIndex: 40,
            delay: startDelay + 0.05 * index,
            ease,
        });
    });

    gsap.to("#pagination", { y: 0, opacity: 1, ease, delay: startDelay });
    gsap.to("nav", { y: 0, opacity: 1, ease, delay: startDelay });
    gsap.to(detailsActive, { opacity: 1, x: 0, ease, delay: startDelay });
    gsap.to("#stats", { y: 0, opacity: 1, ease, delay: startDelay + 0.3 });
    
    // Animate stats numbers
    setTimeout(() => {
        animateStats();
    }, startDelay * 1000 + 500);
}

// Animate stat numbers
function animateStats() {
    const statNumbers = document.querySelectorAll('.stat-number');
    statNumbers.forEach(stat => {
        const target = parseInt(stat.dataset.count);
        const duration = 2000;
        const start = 0;
        const startTime = performance.now();
        
        function updateNumber(currentTime) {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);
            const easeProgress = 1 - Math.pow(1 - progress, 3);
            const current = Math.floor(start + (target - start) * easeProgress);
            stat.textContent = current.toLocaleString();
            
            if (progress < 1) {
                requestAnimationFrame(updateNumber);
            }
        }
        
        requestAnimationFrame(updateNumber);
    });
}

let clicks = 0;

// Step function for slide transition
function step() {
    return new Promise((resolve) => {
        order.push(order.shift());
        detailsEven = !detailsEven;

        const detailsActive = detailsEven ? "#details-even" : "#details-odd";
        const detailsInactive = detailsEven ? "#details-odd" : "#details-even";

        document.querySelector(`${detailsActive} .place-box .text`).textContent = data[order[0]].place;
        document.querySelector(`${detailsActive} .title-1`).textContent = data[order[0]].title;
        document.querySelector(`${detailsActive} .title-2`).textContent = data[order[0]].title2;
        document.querySelector(`${detailsActive} .desc`).textContent = data[order[0]].description;
        
        // Handle single title mentions
        const titleBox2 = document.querySelector(`${detailsActive} .title-box-2`);
        if (data[order[0]].title2) {
            titleBox2.style.display = 'block';
        } else {
            titleBox2.style.display = 'none';
        }

        gsap.set(detailsActive, { zIndex: 22 });
        gsap.to(detailsActive, { opacity: 1, delay: 0.4, ease });
        gsap.to(`${detailsActive} .text`, { y: 0, delay: 0.1, duration: 0.7, ease });
        gsap.to(`${detailsActive} .title-1`, { y: 0, delay: 0.15, duration: 0.7, ease });
        gsap.to(`${detailsActive} .title-2`, { y: 0, delay: 0.15, duration: 0.7, ease });
        gsap.to(`${detailsActive} .desc`, { y: 0, delay: 0.3, duration: 0.4, ease });
        gsap.to(`${detailsActive} .cta`, { y: 0, delay: 0.35, duration: 0.4, onComplete: resolve, ease });
        gsap.set(detailsInactive, { zIndex: 12 });

        const [active, ...rest] = order;
        const prv = rest[rest.length - 1];

        gsap.set(getCard(prv), { zIndex: 10 });
        gsap.set(getCard(active), { zIndex: 20 });
        gsap.to(getCard(prv), { scale: 1.5, ease });

        gsap.to(getCardContent(active), {
            y: offsetTop + cardHeight - 10,
            opacity: 0,
            duration: 0.3,
            ease,
        });
        gsap.to(getSliderItem(active), { x: 0, ease });
        gsap.to(getSliderItem(prv), { x: -numberSize, ease });
        gsap.to(".progress-sub-foreground", {
            width: 500 * (1 / order.length) * (active + 1),
            ease,
        });

        gsap.to(getCard(active), {
            x: 0,
            y: 0,
            ease,
            width: window.innerWidth,
            height: getViewportHeight(),
            borderRadius: 0,
            onComplete: () => {
                const xNew = offsetLeft + (rest.length - 1) * (cardWidth + gap);
                gsap.set(getCard(prv), {
                    x: xNew,
                    y: offsetTop,
                    width: cardWidth,
                    height: cardHeight,
                    zIndex: 30,
                    borderRadius: 10,
                    scale: 1,
                });

                gsap.set(getCardContent(prv), {
                    x: xNew,
                    y: offsetTop + cardHeight - 100,
                    opacity: 1,
                    zIndex: 40,
                });
                gsap.set(getSliderItem(prv), { x: rest.length * numberSize });

                gsap.set(detailsInactive, { opacity: 0 });
                gsap.set(`${detailsInactive} .text`, { y: 100 });
                gsap.set(`${detailsInactive} .title-1`, { y: 100 });
                gsap.set(`${detailsInactive} .title-2`, { y: 100 });
                gsap.set(`${detailsInactive} .desc`, { y: 50 });
                gsap.set(`${detailsInactive} .cta`, { y: 60 });
                clicks -= 1;
                if (clicks > 0) {
                    step();
                }
            },
        });

        rest.forEach((i, index) => {
            if (i !== prv) {
                const xNew = offsetLeft + index * (cardWidth + gap);
                gsap.set(getCard(i), { zIndex: 30 });
                gsap.to(getCard(i), {
                    x: xNew,
                    y: offsetTop,
                    width: cardWidth,
                    height: cardHeight,
                    ease,
                    delay: 0.1 * (index + 1),
                });

                gsap.to(getCardContent(i), {
                    x: xNew,
                    y: offsetTop + cardHeight - 100,
                    opacity: 1,
                    zIndex: 40,
                    ease,
                    delay: 0.1 * (index + 1),
                });
                gsap.to(getSliderItem(i), { x: (index + 1) * numberSize, ease });
            }
        });
    });
}

// Main animation loop
async function loop() {
    if (isPaused) {
        // Check again after a short delay
        setTimeout(loop, 500);
        return;
    }
    await animate(".indicator", 2, { x: 0 });
    if (isPaused) {
        set(".indicator", { x: -window.innerWidth });
        setTimeout(loop, 500);
        return;
    }
    await animate(".indicator", 0.8, { x: window.innerWidth, delay: 0.3 });
    set(".indicator", { x: -window.innerWidth });
    if (!isPaused) {
        await step();
    }
    loop();
}

// Load image helper
async function loadImage(src) {
    return new Promise((resolve, reject) => {
        let img = new Image();
        img.onload = () => resolve(img);
        img.onerror = reject;
        img.src = src;
    });
}

// Load all images
async function loadImages() {
    const promises = data.map(({ image }) => loadImage(image));
    return Promise.all(promises);
}

// Start the application
async function start() {
    try {
        await loadImages();
        init();
    } catch (error) {
        console.error("One or more images failed to load", error);
        // Start anyway with fallback
        init();
    }
}

// Arrow click handlers
document.querySelector('.arrow-right').addEventListener('click', () => {
    clicks++;
    step();
});

document.querySelector('.arrow-left').addEventListener('click', () => {
    // Reverse the order for previous slide
    order.unshift(order.pop());
    order.unshift(order.pop());
    clicks++;
    step();
});

// Pause button handler
document.getElementById('pause-btn').addEventListener('click', () => {
    isPaused = !isPaused;
    const pauseBtn = document.getElementById('pause-btn');
    
    if (isPaused) {
        pauseBtn.classList.add('paused');
        // Stop the indicator animation
        gsap.set(".indicator", { x: -window.innerWidth });
    } else {
        pauseBtn.classList.remove('paused');
    }
});

// Handle window resize
function handleResize() {
    updateLayoutVariables();
    const height = getViewportHeight();
    
    // Update active card height
    const activeIndex = order[0];
    gsap.set(getCard(activeIndex), {
        height: height,
    });
    
    // Update pagination position only on larger screens
    if (window.innerWidth > 600) {
        gsap.set("#pagination", {
            top: offsetTop + 330,
            left: offsetLeft,
        });
    }
}

window.addEventListener('resize', handleResize);

// Handle visual viewport resize (for mobile address bar)
if (window.visualViewport) {
    window.visualViewport.addEventListener('resize', handleResize);
}

// Start the application
start();
