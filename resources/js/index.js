import { initMercadoPagoStatusPolling } from './payments/status-modal';

        // Create animated particles
        function createParticles() {
            const particlesContainer = document.getElementById('particles');
            if (!particlesContainer) return; // Page without particles container
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
            const pricingWrappers = document.querySelectorAll('.pricing-wrapper');

            pricingWrappers.forEach(wrapper => {
                const toggleBtns = wrapper.querySelectorAll('.toggle-btn');
                const planGroups = wrapper.querySelectorAll('[data-plan-group]');
                if (!toggleBtns.length || !planGroups.length) {
                    return;
                }

                const showGroup = (target) => {
                    planGroups.forEach(group => {
                        const isTarget = group.dataset.planGroup === target;
                        // Toggle class for styling frameworks
                        group.classList.toggle('hidden', !isTarget);
                        // Also force display to avoid CSS specificity issues overriding .hidden
                        group.style.display = isTarget ? '' : 'none';
                    });
                };

                toggleBtns.forEach(btn => {
                    const target = btn.dataset.target;
                    if (!target) return;

                    btn.addEventListener('click', function() {
                        toggleBtns.forEach(b => b.classList.remove('active'));
                        this.classList.add('active');
                        showGroup(target);
                    });
                });

                const activeBtn = wrapper.querySelector('.toggle-btn.active') || toggleBtns[0];
                if (activeBtn && activeBtn.dataset.target) {
                    showGroup(activeBtn.dataset.target);
                }
            });
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            createParticles();
            handleScrollAnimations();
            setupPricingToggle();
            initMercadoPagoStatusPolling();
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
                const href = this.getAttribute('href');
                // Guard against bare '#', which is not a valid selector and causes errors
                if (!href || href.trim() === '#' ) {
                    return; // do nothing
                }
                const target = document.querySelector(href);
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
            if (!sphereContainer) return; // Page without sphere container

            sphereContainer.addEventListener('mouseenter', function() {
                this.style.transform = 'scale(1.05)';
                this.style.transition = 'transform 0.3s ease';
            });

            sphereContainer.addEventListener('mouseleave', function() {
                this.style.transform = 'scale(1)';
            });
        });

