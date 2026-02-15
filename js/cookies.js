document.addEventListener('DOMContentLoaded', function() {
    const cookieConsent = document.getElementById('cookie-consent');
    const acceptBtn = document.getElementById('cookie-accept');

    // Check if user has already accepted cookies
    if (!localStorage.getItem('cookieConsent')) {
        // Show the banner with a slight delay
        setTimeout(() => {
            cookieConsent.classList.add('visible');
        }, 1000);
    }

    // Handle acceptance
    if (acceptBtn) {
        acceptBtn.addEventListener('click', function() {
            localStorage.setItem('cookieConsent', 'true');
            cookieConsent.classList.remove('visible');
        });
    }
});
