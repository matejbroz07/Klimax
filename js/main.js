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

  /* ============================================================
   * 9. Trusted Clients Carousel (Drag, Touch, Arrows, Edge-Fade)
   * ============================================================
   * Full carousel controller:
   * - Mouse drag-to-scroll (desktop)
   * - Touch swipe (mobile/tablet, passive)
   * - Prev / Next arrow buttons (smooth scroll by one card width)
   * - Edge-fade gradient classes on the wrapper
   * - Disabled state on arrows at scroll boundaries
   */
  const clientsGrid = document.querySelector('.trusted-clients__grid');

  if (clientsGrid) {
    const wrapper  = clientsGrid.closest('.trusted-clients__grid-wrapper');
    const prevBtn  = wrapper?.querySelector('.trusted-clients__nav--prev');
    const nextBtn  = wrapper?.querySelector('.trusted-clients__nav--next');

    /* --- Edge-fade & arrow state ----------------------------------------- */
    const updateScrollUI = () => {
      if (!wrapper) return;
      const sl = clientsGrid.scrollLeft;
      const maxScroll = clientsGrid.scrollWidth - clientsGrid.clientWidth;
      const threshold = 4; // px tolerance for rounding

      const atStart = sl <= threshold;
      const atEnd   = sl >= maxScroll - threshold;

      wrapper.classList.toggle('trusted-clients__grid-wrapper--fade-left', !atStart);
      wrapper.classList.toggle('trusted-clients__grid-wrapper--fade-right', !atEnd);

      if (prevBtn) prevBtn.disabled = atStart;
      if (nextBtn) nextBtn.disabled = atEnd;
    };

    // Run once + on every scroll
    updateScrollUI();
    clientsGrid.addEventListener('scroll', updateScrollUI, { passive: true });
    // Re-evaluate on resize (card widths may change)
    window.addEventListener('resize', updateScrollUI, { passive: true });

    /* --- Arrow-button click → scroll by one card width ------------------- */
    const scrollByCard = (direction) => {
      const firstCard = clientsGrid.querySelector('.client-card');
      if (!firstCard) return;
      const cardWidth = firstCard.offsetWidth +
        parseFloat(getComputedStyle(clientsGrid).gap || '0');
      clientsGrid.scrollBy({ left: direction * cardWidth, behavior: 'smooth' });
    };

    if (prevBtn) prevBtn.addEventListener('click', () => scrollByCard(-1));
    if (nextBtn) nextBtn.addEventListener('click', () => scrollByCard(1));

    /* --- Mouse drag-to-scroll -------------------------------------------- */
    let isDragging = false;
    let startX     = 0;
    let scrollLeft = 0;
    let hasDragged = false;

    clientsGrid.addEventListener('mousedown', (e) => {
      isDragging = true;
      hasDragged = false;
      startX = e.pageX - clientsGrid.offsetLeft;
      scrollLeft = clientsGrid.scrollLeft;
      clientsGrid.style.scrollSnapType = 'none';
      clientsGrid.style.scrollBehavior = 'auto';
    });

    const stopDrag = () => {
      if (!isDragging) return;
      isDragging = false;
      clientsGrid.style.scrollSnapType = '';
      clientsGrid.style.scrollBehavior = '';
    };

    clientsGrid.addEventListener('mouseleave', stopDrag);
    clientsGrid.addEventListener('mouseup', stopDrag);

    clientsGrid.addEventListener('mousemove', (e) => {
      if (!isDragging) return;
      e.preventDefault();
      const x = e.pageX - clientsGrid.offsetLeft;
      const walk = (x - startX) * 1.5;
      if (Math.abs(walk) > 5) hasDragged = true;
      clientsGrid.scrollLeft = scrollLeft - walk;
    });

    // Prevent accidental link clicks after drag
    clientsGrid.addEventListener('click', (e) => {
      if (hasDragged) {
        e.preventDefault();
        e.stopPropagation();
        hasDragged = false;
      }
    }, true);

    /* --- Touch swipe (passive, iOS-safe) --------------------------------- */
    let touchStartX = 0;
    let touchScrollLeft = 0;

    clientsGrid.addEventListener('touchstart', (e) => {
      touchStartX = e.touches[0].pageX;
      touchScrollLeft = clientsGrid.scrollLeft;
      clientsGrid.style.scrollSnapType = 'none';
    }, { passive: true });

    clientsGrid.addEventListener('touchmove', (e) => {
      const dx = e.touches[0].pageX - touchStartX;
      clientsGrid.scrollLeft = touchScrollLeft - dx;
    }, { passive: true });

    clientsGrid.addEventListener('touchend', () => {
      clientsGrid.style.scrollSnapType = '';
    }, { passive: true });
  }

  /* ============================================================
   * 10. Accordion Toggle (FAQ / Glossary)
   * ============================================================
   * Toggles .accordion__item--active on click to reveal panel.
   * Only one item is open at a time within each accordion group.
   */
  const accordions = document.querySelectorAll('.accordion');

  accordions.forEach((accordion) => {
    const triggers = accordion.querySelectorAll('.accordion__trigger');

    triggers.forEach((trigger) => {
      trigger.addEventListener('click', () => {
        const item = trigger.closest('.accordion__item');
        const isActive = item.classList.contains('accordion__item--active');

        // Close all items in this accordion
        accordion.querySelectorAll('.accordion__item--active').forEach((openItem) => {
          openItem.classList.remove('accordion__item--active');
        });

        // Toggle the clicked item (open if it was closed)
        if (!isActive) {
          item.classList.add('accordion__item--active');
        }
      });
    });
  });

  /* ============================================================
   * 11. Animated Stat Counters
   * ============================================================
   * Animates numbers from 0 to their final value when they
   * enter the viewport. Uses data-target attribute for the value.
   */
  const statValues = document.querySelectorAll('.stat-card__value[data-target]');

  if (statValues.length) {
    const animateCounter = (el) => {
      const target = parseInt(el.getAttribute('data-target'), 10);
      const suffix = el.getAttribute('data-suffix') || '';
      const prefix = el.getAttribute('data-prefix') || '';
      const duration = 2000; // ms
      const startTime = performance.now();

      const update = (currentTime) => {
        const elapsed = currentTime - startTime;
        const progress = Math.min(elapsed / duration, 1);

        // Ease-out cubic for smooth deceleration
        const eased = 1 - Math.pow(1 - progress, 3);
        const current = Math.round(eased * target);

        el.textContent = prefix + current.toLocaleString('cs-CZ') + suffix;

        if (progress < 1) {
          requestAnimationFrame(update);
        }
      };

      requestAnimationFrame(update);
    };

    const counterObserver = new IntersectionObserver(
      (entries, observer) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            animateCounter(entry.target);
            observer.unobserve(entry.target);
          }
        });
      },
      { threshold: 0.3 }
    );

    statValues.forEach((el) => counterObserver.observe(el));
  }

  /* ============================================================
   * 12. Gallery Filter (Reference Page)
   * ============================================================
   * Filters .client-card elements by data-brand attribute.
   * Toggles .gallery-card--hidden class for smooth show/hide.
   */
  const filterBtns = document.querySelectorAll('.gallery-filter__btn');
  const galleryCards = document.querySelectorAll('.gallery-grid .client-card');

  if (filterBtns.length && galleryCards.length) {
    filterBtns.forEach((btn) => {
      btn.addEventListener('click', () => {
        const filter = btn.getAttribute('data-filter');

        // Update active button
        filterBtns.forEach((b) => b.classList.remove('gallery-filter__btn--active'));
        btn.classList.add('gallery-filter__btn--active');

        // Filter cards
        galleryCards.forEach((card) => {
          if (filter === 'all' || card.getAttribute('data-brand') === filter) {
            card.classList.remove('gallery-card--hidden');
          } else {
            card.classList.add('gallery-card--hidden');
          }
        });
      });
    });
  }

  /* ============================================================
   * 13. Active Nav Link for Multi-Page Navigation
   * ============================================================
   * Detects the current page URL and adds .nav__menu-link--active
   * to the corresponding navigation link.
   */
  const currentPage = window.location.pathname.split('/').pop() || 'index.html';

  navLinks.forEach((link) => {
    const href = link.getAttribute('href');
    if (href === currentPage) {
      link.classList.add('nav__menu-link--active');
    }
  });

  /* ============================================================
   * 14. KORE Slideshow
   * ============================================================
   */
  const slideshow = document.getElementById('kore-slideshow');
  if (slideshow) {
    const slides = slideshow.querySelectorAll('.kore-slideshow__slide');
    const dots = slideshow.querySelectorAll('.kore-slideshow__dot');
    const prevBtn = slideshow.querySelector('.kore-slideshow__btn--prev');
    const nextBtn = slideshow.querySelector('.kore-slideshow__btn--next');
    let currentIndex = 0;
    let intervalId;
    const intervalTime = 5000;

    const goToSlide = (index) => {
      slides[currentIndex].classList.remove('kore-slideshow__slide--active');
      dots[currentIndex].classList.remove('kore-slideshow__dot--active');
      
      currentIndex = (index + slides.length) % slides.length;
      
      slides[currentIndex].classList.add('kore-slideshow__slide--active');
      dots[currentIndex].classList.add('kore-slideshow__dot--active');
    };

    const nextSlide = () => goToSlide(currentIndex + 1);
    const prevSlide = () => goToSlide(currentIndex - 1);

    const startSlideshow = () => {
      intervalId = setInterval(nextSlide, intervalTime);
    };

    const stopSlideshow = () => {
      clearInterval(intervalId);
    };

    // Event Listeners
    if (prevBtn) prevBtn.addEventListener('click', () => {
      prevSlide();
      stopSlideshow();
      startSlideshow();
    });
    
    if (nextBtn) nextBtn.addEventListener('click', () => {
      nextSlide();
      stopSlideshow();
      startSlideshow();
    });

    dots.forEach((dot, index) => {
      dot.addEventListener('click', () => {
        goToSlide(index);
        stopSlideshow();
        startSlideshow();
      });
    });

    // Pause on hover
    slideshow.addEventListener('mouseenter', stopSlideshow);
    slideshow.addEventListener('mouseleave', startSlideshow);

    // Start
    startSlideshow();
  }

  /* ============================================================
   * 15. Journey Step Scroll Observer
   * ============================================================
   * Highlights exactly ONE .journey-step that is closest to the 
   * center of the viewport.
   */
  const journeySteps = document.querySelectorAll('.journey-step');
  const journeySection = document.querySelector('.customer-journey');
  
  if (journeySteps.length > 0 && journeySection) {
    const updateActiveStep = () => {
      const sectionRect = journeySection.getBoundingClientRect();
      
      // If the section is completely off-screen, remove all highlights
      if (sectionRect.bottom < 0 || sectionRect.top > window.innerHeight) {
        journeySteps.forEach(step => step.classList.remove('is-active'));
        return;
      }

      let closestStep = null;
      let minDistance = Infinity;
      const viewportCenter = window.innerHeight / 2;

      // Find the step whose center is closest to the viewport center
      journeySteps.forEach((step) => {
        const rect = step.getBoundingClientRect();
        const stepCenter = rect.top + rect.height / 2;
        const distance = Math.abs(viewportCenter - stepCenter);

        if (distance < minDistance) {
          minDistance = distance;
          closestStep = step;
        }
      });

      // Apply the active class ONLY to the closest step
      journeySteps.forEach((step) => {
        if (step === closestStep) {
          step.classList.add('is-active');
        } else {
          step.classList.remove('is-active');
        }
      });
    };

    window.addEventListener('scroll', updateActiveStep, { passive: true });
    window.addEventListener('resize', updateActiveStep, { passive: true });
    
    // Initial check
    updateActiveStep();
  }
});
