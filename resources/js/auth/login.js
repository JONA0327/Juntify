        // Create animated particles
        function createParticles() {
            const particlesContainer = document.getElementById('particles');
            const particleCount = 80;

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

        // Form validation
        function validateForm() {
            const login = document.getElementById('login');
            const password = document.getElementById('password');
            let isValid = true;

            // Clear previous errors
            clearErrors();

            // Validate login
            if (!login.value.trim()) {
                showError('login', 'Este campo es requerido');
                isValid = false;
            }

            // Validate password
            if (!password.value) {
                showError('password', 'La contraseña es requerida');
                isValid = false;
            }

            return isValid;
        }

        function showError(fieldId, message) {
            const field = document.getElementById(fieldId);
            const errorDiv = document.getElementById(fieldId + 'Error');
            
            field.classList.add('error');
            errorDiv.textContent = message;
            errorDiv.style.display = 'block';
        }

        function clearErrors() {
            const inputs = document.querySelectorAll('.form-input');
            const errors = document.querySelectorAll('.error-message');
            
            inputs.forEach(input => input.classList.remove('error'));
            errors.forEach(error => {
                error.style.display = 'none';
                error.textContent = '';
            });
        }

        // Form submission
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            if (validateForm()) {
                const submitBtn = document.getElementById('submitBtn');
                submitBtn.classList.add('loading');
                submitBtn.textContent = 'Iniciando sesión...';
                
                // Simulate API call
                setTimeout(() => {
                    this.submit();
                }, 1000);
            }
        });

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            createParticles();
        });

        // Clear errors on input
        document.querySelectorAll('.form-input').forEach(input => {
            input.addEventListener('input', function() {
                if (this.classList.contains('error')) {
                    this.classList.remove('error');
                    const errorDiv = document.getElementById(this.id + 'Error');
                    errorDiv.style.display = 'none';
                }
            });
        });
