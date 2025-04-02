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
            // Update the dashboard statistics regardless of status
            updateDashboardStats(data);
            
            if (data.status === 'completed') {
                // Update the status display with results
                statusElement.innerHTML = `
                    <div class="alert alert-success">
                        <strong>عملیات موفق!</strong> ${data.new_links} لینک جدید در تاریخ ${data.timestamp} استخراج شد.
                        تمام ${data.total_channels} کانال و ${data.total_websites || 0} وب‌سایت بررسی شدند.
                        ${getWebsitesCheckedMessage(data)}
                        ${getGroupsCheckedMessage(data)}
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

/**
 * Update dashboard statistics with the latest check data
 */
function updateDashboardStats(data) {
    // Update the last check time
    const lastCheckTimeElement = document.getElementById('last-check-time');
    if (lastCheckTimeElement && data.timestamp) {
        lastCheckTimeElement.textContent = data.timestamp;
    }
    
    // Update channels stats
    const channelsCheckedElement = document.getElementById('channels-checked');
    const totalChannelsElement = document.getElementById('total-channels');
    if (channelsCheckedElement && data.channels_checked) {
        channelsCheckedElement.textContent = data.channels_checked;
    }
    if (totalChannelsElement && data.total_channels) {
        totalChannelsElement.textContent = data.total_channels;
    }
    
    // Update websites stats
    const websitesCheckedElement = document.getElementById('websites-checked');
    const totalWebsitesElement = document.getElementById('total-websites');
    const websitesLinksElement = document.getElementById('websites-links');
    
    if (websitesCheckedElement && data.websites_checked !== undefined) {
        websitesCheckedElement.textContent = data.websites_checked;
    }
    if (totalWebsitesElement && data.total_websites !== undefined) {
        totalWebsitesElement.textContent = data.total_websites;
    }
    if (websitesLinksElement && data.websites_links !== undefined) {
        websitesLinksElement.textContent = data.websites_links;
    }
    
    // Update groups stats
    const groupsCheckedElement = document.getElementById('groups-checked');
    const accountsCheckedElement = document.getElementById('accounts-checked');
    if (groupsCheckedElement && data.user_groups_checked !== undefined) {
        groupsCheckedElement.textContent = data.user_groups_checked;
    }
    if (accountsCheckedElement && data.user_accounts_checked !== undefined) {
        accountsCheckedElement.textContent = data.user_accounts_checked;
    }
    
    // Update new links count
    const newLinksCountElement = document.getElementById('new-links-count');
    if (newLinksCountElement && data.new_links !== undefined) {
        newLinksCountElement.textContent = data.new_links;
    }
}

/**
 * Generate a message about groups checked by user accounts
 */
function getGroupsCheckedMessage(data) {
    if (data.user_groups_checked && data.user_accounts_checked) {
        return `همچنین ${data.user_groups_checked} گروه توسط ${data.user_accounts_checked} اکانت کاربر بررسی شد.`;
    }
    return '';
}

/**
 * Generate a message about websites checked 
 */
function getWebsitesCheckedMessage(data) {
    if (data.websites_links && data.websites_checked) {
        return `از وب‌سایت‌ها ${data.websites_links} لینک جدید پیدا شد.`;
    }
    return '';
}
