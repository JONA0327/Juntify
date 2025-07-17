        // Create animated particles
        function createParticles() {
            const particlesContainer = document.getElementById('particles');
            const particleCount = 100;

            for (let i = 0; i < particleCount; i++) {
                const particle = document.createElement('div');
                particle.className = 'particle';
                particle.style.left = Math.random() * 100 + '%';
                particle.style.top = Math.random() * 100 + '%';
                particle.style.animationDelay = Math.random() * 8 + 's';
                particle.style.animationDuration = (Math.random() * 4 + 4) + 's';
                particlesContainer.appendChild(particle);
            }
        }

        // Enhanced parallax effect
        function parallaxEffect() {
            const scrolled = window.pageYOffset;
            const rate = scrolled * -0.2;
            const rate2 = scrolled * -0.1;
            const rate3 = scrolled * -0.05;

            // Parallax only for specific elements, not all layers
            const hero = document.querySelector('.hero');
            if (hero) {
                hero.style.transform = `translateY(${rate3}px)`;
            }

            // Sphere parallax
            const sphere = document.querySelector('.sphere-container');
            if (sphere) {
                sphere.style.transform = `translateY(${rate2}px)`;
            }

            // Particles parallax
            const particles = document.querySelectorAll('.particle');
            particles.forEach((particle, index) => {
                const speed = (index % 3 + 1) * 0.02;
                particle.style.transform = `translateY(${scrolled * speed}px)`;
            });
        }

        // Scroll animations
        function handleScrollAnimations() {
            const elements = document.querySelectorAll('.fade-in');

            elements.forEach(element => {
                const elementTop = element.getBoundingClientRect().top;
                const elementVisible = 150;

                if (elementTop < window.innerHeight - elementVisible) {
                    element.classList.add('visible');
                }
            });
        }

        // Enhanced mouse movement effect for sphere
        function handleMouseMove(e) {
            const sphere = document.querySelector('.sphere');
            if (sphere) {
                const x = (e.clientX / window.innerWidth) * 2 - 1;
                const y = (e.clientY / window.innerHeight) * 2 - 1;

                sphere.style.transform = `rotateX(${y * 5}deg) rotateY(${x * 5}deg)`;
            }
        }

        // Toggle mobile navbar
        function toggleMobileNavbar() {
            const navLinks = document.getElementById('nav-links');
            const hamburger = document.querySelector('.hamburger-navbar');
            if (navLinks) {
                navLinks.classList.toggle('show');
            }
            if (hamburger) {
                hamburger.classList.toggle('active');
            }
        }

        // Make function global

        // Pricing toggle functionality
        function setupPricingToggle() {
            const toggleBtns = document.querySelectorAll('.toggle-btn');

            toggleBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    toggleBtns.forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                });
            });
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            createParticles();
            handleScrollAnimations();
            setupPricingToggle();
        });

        window.addEventListener('scroll', function() {
            parallaxEffect();
            handleScrollAnimations();
        });

        document.addEventListener('mousemove', handleMouseMove);

        // Smooth scrolling for navigation links
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

        // Enhanced sphere interaction
        document.addEventListener('DOMContentLoaded', function() {
            const sphereContainer = document.querySelector('.sphere-container');

            sphereContainer.addEventListener('mouseenter', function() {
                this.style.transform = 'scale(1.05)';
                this.style.transition = 'transform 0.3s ease';
            });

            sphereContainer.addEventListener('mouseleave', function() {
                this.style.transform = 'scale(1)';
            });
        });

