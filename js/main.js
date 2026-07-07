/**
 * Klimax — Main JavaScript
 * ========================
 * Clean, modular vanilla ES6+ with zero dependencies.
 * Uses IntersectionObserver for scroll-triggered animations and
 * passive event listeners for optimal performance.
 */

document.addEventListener('DOMContentLoaded', () => {
  /* ============================================================
   * 1. DOM References (with null-safety)
   * ============================================================ */
  const nav        = document.querySelector('.nav');
  const navToggle  = document.querySelector('.nav__toggle');
  const navMenu    = document.querySelector('.nav__menu');
  const navLinks   = document.querySelectorAll('.nav__menu-link');
  const trustBar   = document.querySelector('.trust-bar');
  const sections   = document.querySelectorAll('section[id]');

  /** Utility — safe class toggle that silently skips null elements */
  const toggleClass = (el, className, force) => {
    if (el) el.classList.toggle(className, force);
  };

  /* ============================================================
   * 2. Sticky Navigation with Backdrop Blur
   * ============================================================
   * Adds .nav--scrolled once the user scrolls past 50 px.
   * The corresponding CSS should apply backdrop-filter: blur()
   * and any background / shadow changes.
   * Passive listener keeps the main thread unblocked.
   */
  const SCROLL_THRESHOLD = 50;

  const handleNavScroll = () => {
    if (!nav) return;
    toggleClass(nav, 'nav--scrolled', window.scrollY > SCROLL_THRESHOLD);
  };

  window.addEventListener('scroll', handleNavScroll, { passive: true });
  // Run once on load in case the page is already scrolled (e.g. refresh)
  handleNavScroll();

  /* ============================================================
   * 3. Mobile Hamburger Menu
   * ============================================================
   * • Toggles .nav__menu--open on the menu container
   * • Toggles .active on the hamburger button (for ✕ animation)
   * • Locks body scroll while the menu is open
   * • Closes when a nav link is clicked or the user taps outside
   */

  /** Open / close helpers */
  const openMenu = () => {
    if (!navMenu || !navToggle) return;
    navMenu.classList.add('nav__menu--open');
    navToggle.classList.add('active');
    navToggle.setAttribute('aria-expanded', 'true');
    document.body.style.overflow = 'hidden';
  };

  const closeMenu = () => {
    if (!navMenu || !navToggle) return;
    navMenu.classList.remove('nav__menu--open');
    navToggle.classList.remove('active');
    navToggle.setAttribute('aria-expanded', 'false');
    document.body.style.overflow = '';
  };

  const isMenuOpen = () => navMenu?.classList.contains('nav__menu--open');

  // Toggle button
  if (navToggle) {
    navToggle.addEventListener('click', (e) => {
      e.stopPropagation();
      isMenuOpen() ? closeMenu() : openMenu();
    });
  }

  // Close on nav-link click (after a tiny delay so users see the active state)
  navLinks.forEach((link) => {
    link.addEventListener('click', () => {
      if (isMenuOpen()) closeMenu();
    });
  });

  // Close on outside click
  document.addEventListener('click', (e) => {
    if (isMenuOpen() && navMenu && !navMenu.contains(e.target) && !navToggle?.contains(e.target)) {
      closeMenu();
    }
  });

  // Close on Escape key for accessibility
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && isMenuOpen()) closeMenu();
  });

  /* ============================================================
   * 4. Scroll Fade-in Animations (IntersectionObserver)
   * ============================================================
   * Every element with class .fade-in is observed.
   * When it enters the viewport it receives .fade-in--visible.
   * Elements inside a shared parent get staggered transitionDelay
   * based on their index (0.1 s × index).
   *
   * Each element is unobserved after it becomes visible so the
   * callback fires only once (performance).
   */
  const fadeElements = document.querySelectorAll('.fade-in');

  if (fadeElements.length) {
    // Pre-compute stagger delays per parent
    applyStaggerDelays(fadeElements);

    const fadeObserver = new IntersectionObserver(
      (entries, observer) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            entry.target.classList.add('fade-in--visible');
            observer.unobserve(entry.target);
          }
        });
      },
      {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px',
      }
    );

    fadeElements.forEach((el) => fadeObserver.observe(el));
  }

  /**
   * Apply staggered transition delays to .fade-in children that
   * share the same parent. Groups elements by parentNode and sets
   * transitionDelay = index × 0.1 s for each group.
   */
  function applyStaggerDelays(elements) {
    const groups = new Map();

    elements.forEach((el) => {
      const parent = el.parentNode;
      if (!groups.has(parent)) groups.set(parent, []);
      groups.get(parent).push(el);
    });

    groups.forEach((children) => {
      // Only stagger when there are multiple siblings
      if (children.length <= 1) return;
      children.forEach((child, i) => {
        child.style.transitionDelay = `${i * 0.1}s`;
      });
    });
  }

  /* ============================================================
   * 5. Smooth Scroll for Anchor Links
   * ============================================================
   * Intercepts clicks on all a[href^="#"] links and scrolls to
   * the target section with a top offset equal to the nav height.
   */
  const NAV_HEIGHT = 72; // px — matches the fixed nav height in CSS

  document.querySelectorAll('a[href^="#"]').forEach((anchor) => {
    anchor.addEventListener('click', (e) => {
      const href = anchor.getAttribute('href');
      // Skip bare "#" links
      if (!href || href === '#') return;

      const target = document.querySelector(href);
      if (!target) return;

      e.preventDefault();

      const topOffset =
        target.getBoundingClientRect().top + window.scrollY - NAV_HEIGHT;

      window.scrollTo({
        top: topOffset,
        behavior: 'smooth',
      });

      // Update the URL hash without jumping
      if (history.pushState) {
        history.pushState(null, '', href);
      }
    });
  });

  /* ============================================================
   * 6. Trust Bar Subtle Parallax
   * ============================================================
   * When the trust bar is in view, translate its inner logos
   * wrapper slightly on scroll for a subtle depth effect.
   * Runs inside a requestAnimationFrame for smoothness.
   */
  if (trustBar) {
    const logosWrapper = trustBar.querySelector('.trust-bar__track, .trust-bar__logos');
    let ticking = false;

    const updateParallax = () => {
      const rect = trustBar.getBoundingClientRect();
      const windowH = window.innerHeight;

      // Only run while the trust bar is in the viewport
      if (rect.bottom > 0 && rect.top < windowH) {
        // Progress: 0 when element enters bottom → 1 when it leaves top
        const progress = 1 - (rect.bottom / (windowH + rect.height));
        const translate = (progress - 0.5) * 20; // ±10 px max

        if (logosWrapper) {
          logosWrapper.style.transform = `translateX(${translate}px)`;
        }
      }

      ticking = false;
    };

    window.addEventListener(
      'scroll',
      () => {
        if (!ticking) {
          requestAnimationFrame(updateParallax);
          ticking = true;
        }
      },
      { passive: true }
    );
  }

  /* ============================================================
   * 7. Active Nav Link Highlighting
   * ============================================================
   * Uses IntersectionObserver on each <section id="…"> to toggle
   * an .active class on the corresponding .nav__menu-link whose
   * href matches "#sectionId".
   *
   * rootMargin is set so the section is considered "active" once
   * its top crosses the nav bar.
   */
  if (sections.length && navLinks.length) {
    const activateLink = (id) => {
      navLinks.forEach((link) => {
        const isMatch = link.getAttribute('href') === `#${id}`;
        toggleClass(link, 'active', isMatch);
      });
    };

    const sectionObserver = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            activateLink(entry.target.id);
          }
        });
      },
      {
        // Top offset = negative nav height so the trigger line sits
        // just below the sticky nav. Bottom offset clips to 40 %
        // of viewport so the section is "active" while mostly visible.
        rootMargin: `-${NAV_HEIGHT}px 0px -40% 0px`,
        threshold: 0,
      }
    );

    sections.forEach((section) => sectionObserver.observe(section));
  }

  /* ============================================================
   * 8. Reduced-motion preference
   * ============================================================
   * Respect the user's OS-level "prefers-reduced-motion" setting
   * by skipping transition delays and parallax transforms.
   */
  const prefersReducedMotion = window.matchMedia(
    '(prefers-reduced-motion: reduce)'
  );

  const handleReducedMotion = (mq) => {
    if (mq.matches) {
      // Immediately reveal all fade-in elements without animation
      document.querySelectorAll('.fade-in').forEach((el) => {
        el.classList.add('fade-in--visible');
        el.style.transitionDelay = '0s';
      });
    }
  };

  // Check on load
  handleReducedMotion(prefersReducedMotion);
  // Listen for changes (user toggles setting while page is open)
  prefersReducedMotion.addEventListener('change', handleReducedMotion);
});
