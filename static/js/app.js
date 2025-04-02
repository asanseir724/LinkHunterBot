// Wait for the DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    // Handle link copying
    setupCopyButtons();
    
    // Setup link search functionality
    setupLinkSearch();
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
