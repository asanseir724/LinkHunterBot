// Wait for the DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    // Handle link copying
    setupCopyButtons();
    
    // Setup link search functionality
    setupLinkSearch();
    
    // Check if we're on the index page with the status element
    const statusElement = document.getElementById('background-status');
    if (statusElement) {
        // Check immediately and then every 3 seconds
        checkBackgroundStatus();
        statusCheckInterval = setInterval(checkBackgroundStatus, 3000);
    }
});

/**
 * Sets up the copy functionality for links
 */
function setupCopyButtons() {
    const copyButtons = document.querySelectorAll('.copy-btn');
    
    copyButtons.forEach(button => {
        button.addEventListener('click', function() {
            const link = this.getAttribute('data-link');
            
            // Create a temporary input element
            const tempInput = document.createElement('input');
            tempInput.style.position = 'absolute';
            tempInput.style.left = '-1000px';
            tempInput.value = link;
            document.body.appendChild(tempInput);
            
            // Select and copy the text
            tempInput.select();
            document.execCommand('copy');
            
            // Remove the temporary input
            document.body.removeChild(tempInput);
            
            // Visual feedback that the copy was successful
            const originalText = this.innerHTML;
            this.innerHTML = '<i class="fas fa-check"></i> Copied!';
            this.classList.add('copied');
            
            // Reset the button after a short delay
            setTimeout(() => {
                this.innerHTML = originalText;
                this.classList.remove('copied');
            }, 2000);
        });
    });
}

/**
 * Sets up link search functionality
 */
function setupLinkSearch() {
    const searchInput = document.getElementById('linkSearch');
    if (!searchInput) return;
    
    searchInput.addEventListener('keyup', function() {
        const searchTerm = this.value.toLowerCase();
        const table = document.getElementById('linksTable');
        const rows = table.querySelectorAll('tbody tr');
        
        rows.forEach(row => {
            const linkCell = row.querySelector('.link-cell');
            if (!linkCell) return;
            
            const linkText = linkCell.textContent.toLowerCase();
            
            if (linkText.includes(searchTerm)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });
}

// Variable to store the interval ID for status checking
let statusCheckInterval;

/**
 * Check status of background link checking process
 */
function checkBackgroundStatus() {
    const statusElement = document.getElementById('background-status');
    if (!statusElement) return;
    
    // Only run this if we're on the home page
    fetch('/api/check_status')
        .then(response => response.json())
        .then(data => {
            if (data.status === 'completed') {
                // Update the status display with results
                statusElement.innerHTML = `
                    <div class="alert alert-success">
                        <strong>عملیات موفق!</strong> ${data.new_links} لینک جدید در تاریخ ${data.timestamp} استخراج شد.
                        از مجموع ${data.total_channels} کانال، ${data.channels_checked} کانال بررسی شد.
                    </div>
                `;
                
                // Stop polling once completed
                clearInterval(statusCheckInterval);
            } else if (data.status === 'error') {
                // Show error
                statusElement.innerHTML = `
                    <div class="alert alert-danger">
                        <strong>خطا!</strong> در استخراج لینک‌ها خطا رخ داد: ${data.error}
                    </div>
                `;
                
                // Stop polling on error
                clearInterval(statusCheckInterval);
            } else if (data.status === 'not_run') {
                // Do nothing for not run status
            }
        })
        .catch(error => {
            console.error('Error checking status:', error);
        });
}
