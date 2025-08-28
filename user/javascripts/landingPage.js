
        // Navbar scroll effect
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar');
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });

        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Intersection Observer for animations
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver(function(entries) {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                    entry.target.classList.remove('animate');
                }
            });
        }, observerOptions);

        // Observe elements for animation
        document.addEventListener('DOMContentLoaded', function() {
            // Remove loading class
            document.body.classList.remove('loading');
            
            // Add animate class initially and observe stat items
            document.querySelectorAll('.stat-item').forEach(item => {
                observer.observe(item);
            });

            // Add animate class and observe section titles
            document.querySelectorAll('.section-title').forEach(title => {
                title.classList.add('animate');
                observer.observe(title);
            });

            // Add animate class and observe service cards with staggered animation
            document.querySelectorAll('.service-card').forEach((card, index) => {
                card.classList.add('animate');
                card.style.animationDelay = `${index * 0.1}s`;
                observer.observe(card);
            });

            // Add animate class and observe step cards
            document.querySelectorAll('.step-card').forEach((card, index) => {
                card.classList.add('animate');
                card.style.animationDelay = `${index * 0.2}s`;
                observer.observe(card);
            });

            // Add animate class and observe confirmation process
            const confirmationProcess = document.querySelector('.confirmation-process');
            if (confirmationProcess) {
                confirmationProcess.classList.add('animate');
                observer.observe(confirmationProcess);
            }
        });

        // Counter animation for stats
        function animateCounter(element, target) {
            const increment = target / 100;
            let current = 0;
            
            const timer = setInterval(() => {
                current += increment;
                element.textContent = Math.floor(current);
                
                if (current >= target) {
                    element.textContent = target;
                    clearInterval(timer);
                }
            }, 20);
        }

        // Enhanced intersection observer for stats
        const statsObserver = new IntersectionObserver(function(entries) {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const statNumber = entry.target.querySelector('.stat-number');
                    const target = parseInt(statNumber.dataset.count);
                    animateCounter(statNumber, target);
                    statsObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.5 });

        // Observe stats section
        document.addEventListener('DOMContentLoaded', function() {
            const statsSection = document.querySelector('.stats');
            if (statsSection) {
                statsObserver.observe(statsSection);
            }
        });

        // Mobile menu enhancement
        const navbarToggler = document.querySelector('.navbar-toggler');
        const navbarCollapse = document.querySelector('.navbar-collapse');

        if (navbarToggler && navbarCollapse) {
            navbarToggler.addEventListener('click', function() {
                this.classList.toggle('active');
            });

            // Close mobile menu when clicking on a link
            document.querySelectorAll('.navbar-nav .nav-link').forEach(link => {
                link.addEventListener('click', function() {
                    if (window.innerWidth < 992) {
                        const bsCollapse = new bootstrap.Collapse(navbarCollapse, {
                            toggle: false
                        });
                        bsCollapse.hide();
                        navbarToggler.classList.remove('active');
                    }
                });
            });
        }

        // Preload critical images
        function preloadImages() {
            // No images to preload now since we're using CSS-based logo
        }

        // Initialize preloading
        document.addEventListener('DOMContentLoaded', preloadImages);

        // Optimize scroll performance
        let ticking = false;

        function updateNavbar() {
            const navbar = document.querySelector('.navbar');
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
            ticking = false;
        }

        window.addEventListener('scroll', function() {
            if (!ticking) {
                requestAnimationFrame(updateNavbar);
                ticking = true;
            }
        });

        // Add loading states for buttons
        document.querySelectorAll('.btn-modern, .btn-outline-modern').forEach(btn => {
            btn.addEventListener('click', function(e) {
                // Only add loading state for actual form submissions or navigation
                if (this.href && !this.href.includes('#')) {
                    const originalText = this.innerHTML;
                    this.innerHTML = '<i class="bi bi-arrow-repeat me-2" style="animation: spin 1s linear infinite;"></i>Loading...';
                    this.style.pointerEvents = 'none';
                    
                    // Reset after a short delay (in case navigation is slow)
                    setTimeout(() => {
                        this.innerHTML = originalText;
                        this.style.pointerEvents = 'auto';
                    }, 3000);
                }
            });
        });

        // Add CSS animation for loading spinner
        const style = document.createElement('style');
        style.textContent = `
            @keyframes spin {
                from { transform: rotate(0deg); }
                to { transform: rotate(360deg); }
            }
        `;
        document.head.appendChild(style);
