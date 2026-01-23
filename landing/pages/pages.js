/**
 * UAZ Pages - Shared JavaScript
 * Page transitions and common functionality
 */

// ========== PAGE TRANSITIONS ==========

// Check for View Transition API support
const supportsViewTransitions = 'startViewTransition' in document;

// Handle page transitions
function initPageTransitions() {
    // Get all internal navigation links
    const navLinks = document.querySelectorAll('a[href^="./"], a[href^="../"]');
    
    navLinks.forEach(link => {
        // Skip external links, anchors, and special links
        if (link.href.includes('#') || link.href.includes('mailto:') || link.href.includes('tel:')) {
            return;
        }
        
        link.addEventListener('click', async (e) => {
            const href = link.href;
            
            // Use View Transition API if supported
            if (supportsViewTransitions) {
                e.preventDefault();
                
                try {
                    await document.startViewTransition(async () => {
                        window.location.href = href;
                    }).finished;
                } catch {
                    // Fallback if transition fails
                    window.location.href = href;
                }
            } else {
                // Fallback animation for unsupported browsers
                e.preventDefault();
                
                // Add exit animation class
                document.body.classList.add('page-exit');
                
                // Wait for animation to complete then navigate
                setTimeout(() => {
                    window.location.href = href;
                }, 300);
            }
        });
    });
}

// ========== NAVBAR SCROLL EFFECT ==========

function initNavbarScroll() {
    const navbar = document.querySelector('.navbar');
    if (!navbar) return;
    
    const handleScroll = () => {
        if (window.scrollY > 50) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }
    };
    
    window.addEventListener('scroll', handleScroll, { passive: true });
    handleScroll(); // Check initial state
}

// ========== SCROLL ANIMATIONS ==========

function initScrollAnimations() {
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
                observer.unobserve(entry.target); // Only animate once
            }
        });
    }, observerOptions);

    // Select all animatable elements
    const animatableElements = document.querySelectorAll(
        '.program-card, .feature-card, .timeline-item, .contact-item, .gallery-item, .stat-box'
    );
    
    animatableElements.forEach((el, index) => {
        el.style.opacity = '0';
        el.style.transform = 'translateY(30px)';
        el.style.transition = `all 0.6s ease ${index * 0.05}s`; // Stagger effect
        observer.observe(el);
    });
}

// ========== PAGE LOAD ANIMATION ==========

function initPageLoadAnimation() {
    // Remove any loading states
    document.body.classList.remove('page-exit');
    
    // Trigger animations after a small delay to ensure CSS is loaded
    requestAnimationFrame(() => {
        document.body.style.opacity = '1';
    });
}

// ========== SMOOTH SCROLL ==========

function initSmoothScroll() {
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            const targetId = this.getAttribute('href');
            if (targetId === '#') return;
            
            const targetElement = document.querySelector(targetId);
            if (targetElement) {
                e.preventDefault();
                targetElement.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
}

// ========== FORM HANDLING ==========

function initFormHandling() {
    const forms = document.querySelectorAll('form');
    
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Show success message
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            submitBtn.innerHTML = '<span>✓ Envoyé avec succès !</span>';
            submitBtn.style.background = 'linear-gradient(135deg, #22c55e, #16a34a)';
            
            // Reset form
            setTimeout(() => {
                form.reset();
                submitBtn.innerHTML = originalText;
                submitBtn.style.background = '';
            }, 3000);
        });
    });
}

// ========== INITIALIZE ALL ==========

document.addEventListener('DOMContentLoaded', () => {
    initPageLoadAnimation();
    initPageTransitions();
    initNavbarScroll();
    initScrollAnimations();
    initSmoothScroll();
    initFormHandling();
});

// Handle page show event (for back/forward navigation)
window.addEventListener('pageshow', (event) => {
    if (event.persisted) {
        // Page was restored from cache
        document.body.classList.remove('page-exit');
        document.body.style.opacity = '1';
    }
});
