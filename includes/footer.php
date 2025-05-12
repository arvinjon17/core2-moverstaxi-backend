<footer class="footer mt-auto py-3 bg-light">
    <div class="container text-center">
        <span class="text-muted">Made by the Core 1 & 2 TEAM &copy; <?php echo date('Y'); ?> All rights reserved</span>
        <div class="mt-2">
            <small class="text-muted">Movers Taxi System v1.0.0</small>
        </div>
    </div>
</footer>

<!-- Firebase Configuration -->
<script>
// Firebase configuration will be added here
const firebaseConfig = {
    // Firebase credentials will be filled in by system administrators
};

// Initialize Firebase if available
if (typeof firebase !== 'undefined') {
    try {
        firebase.initializeApp(firebaseConfig);
        console.log('Firebase initialized successfully');
        
        // Set up real-time listeners for notifications here when Firebase is configured
    } catch (error) {
        console.error('Error initializing Firebase:', error);
    }
}

// AJAX fallback for real-time updates
function setupAjaxUpdates() {
    // Set up periodic AJAX polling for updates when Firebase is unavailable
    const checkForUpdates = () => {
        // This will be implemented to fetch updates via AJAX
    };
    
    // Check for updates every 30 seconds
    setInterval(checkForUpdates, 30000);
}

// Initialize SPA-like behavior
document.addEventListener('DOMContentLoaded', function() {
    // Handle link clicks for SPA navigation
    document.querySelectorAll('a[href^="index.php"]').forEach(link => {
        link.addEventListener('click', function(e) {
            // Only if not targeting a new window/tab
            if (!this.target || this.target === '_self') {
                e.preventDefault();
                const url = new URL(this.href);
                // Update browser history
                history.pushState({}, '', url);
                // Load content
                loadContent(url.searchParams.get('page') || 'dashboard');
            }
        });
    });
    
    // Handle browser back/forward buttons
    window.addEventListener('popstate', function() {
        const url = new URL(window.location.href);
        loadContent(url.searchParams.get('page') || 'dashboard');
    });
    
    // Function to load content without full page refresh
    function loadContent(page) {
        const contentDiv = document.getElementById('content');
        const loadingHTML = '<div class="text-center p-5"><i class="fas fa-circle-notch fa-spin fa-3x"></i><p class="mt-3">Loading...</p></div>';
        
        if (contentDiv) {
            contentDiv.innerHTML = loadingHTML;
            
            fetch(`pages/${page}.php`)
                .then(response => response.text())
                .then(html => {
                    contentDiv.innerHTML = html;
                    // Update active state in sidebar
                    updateSidebarActiveState(page);
                })
                .catch(error => {
                    contentDiv.innerHTML = '<div class="alert alert-danger">Error loading content. Please try again.</div>';
                    console.error('Error loading content:', error);
                });
        }
    }
    
    // Update active state in sidebar
    function updateSidebarActiveState(page) {
        // Remove active class from all links
        document.querySelectorAll('#sidebarMenu .nav-link').forEach(link => {
            link.classList.remove('active');
            link.setAttribute('aria-current', 'false');
        });
        
        // Add active class to current page link
        const activeLink = document.querySelector(`#sidebarMenu .nav-link[href="index.php?page=${page}"]`);
        if (activeLink) {
            activeLink.classList.add('active');
            activeLink.setAttribute('aria-current', 'page');
        }
    }
});
</script> 