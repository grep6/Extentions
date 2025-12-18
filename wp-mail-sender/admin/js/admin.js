(function($) {
    'use strict';
    
    $(document).ready(function() {
        console.log('[WP Mail Sender] Admin JS loaded');
        
        // Confirmation avant suppression
        $('.delete-item').on('click', function(e) {
            if (!confirm('Êtes-vous sûr de vouloir supprimer cet élément ?')) {
                e.preventDefault();
                return false;
            }
        });
    });
    
})(jQuery);
