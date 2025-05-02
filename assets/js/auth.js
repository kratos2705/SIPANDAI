const loginBtn = document.getElementById('loginBtn');
const loginModal = document.getElementById('loginModal');
const closeModal = document.getElementById('closeModal');

// Tab switching
const loginTab = document.getElementById('loginTab');
const registerTab = document.getElementById('registerTab');
const loginForm = document.getElementById('loginForm');
const registerForm = document.getElementById('registerForm');

// Forgot password
const forgotPassword = document.getElementById('forgotPassword');

// Open modal
loginBtn.addEventListener('click', function() {
    loginModal.style.display = 'flex';
});

// Close modal
closeModal.addEventListener('click', function() {
    loginModal.style.display = 'none';
});

// Close modal when clicking outside
window.addEventListener('click', function(event) {
    if (event.target === loginModal) {
        loginModal.style.display = 'none';
    }
});

// Switch to login tab
loginTab.addEventListener('click', function() {
    loginTab.classList.add('active');
    registerTab.classList.remove('active');
    loginForm.classList.add('active');
    registerForm.classList.remove('active');
});

// Switch to register tab
registerTab.addEventListener('click', function() {
    registerTab.classList.add('active');
    loginTab.classList.remove('active');
    registerForm.classList.add('active');
    loginForm.classList.remove('active');
});

// Forgot password functionality
forgotPassword.addEventListener('click', function(e) {
    e.preventDefault();
    alert('Fitur reset password akan segera tersedia.');
});