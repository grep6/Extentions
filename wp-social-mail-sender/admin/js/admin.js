/**
 * WP Social Mail Sender Admin JavaScript
 */

jQuery(document).ready(function($) {
    'use strict';
    
    // Test SMTP Connection for specific email
    $(document).on('click', '.test-connection-btn', function(e) {
        e.preventDefault();
        
        var $btn = $(this);
        var email = $btn.data('email');
        var $row = $btn.closest('tr');
        
        $btn.prop('disabled', true).text('Testing...');
        
        $.ajax({
            url: wpSocialMailSenderAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'test_smtp_connection',
                email: email,
                nonce: wpSocialMailSenderAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    alert('✓ Test successful!\n\n' + response.data.message);
                    $row.css('background-color', '#d4edda');
                } else {
                    alert('✗ Test failed!\n\n' + response.data.message);
                    $row.css('background-color', '#f8d7da');
                }
            },
            error: function(xhr, status, error) {
                alert('✗ AJAX Error: ' + error);
            },
            complete: function() {
                $btn.prop('disabled', false).text('Test');
                setTimeout(function() {
                    $row.css('background-color', '');
                }, 3000);
            }
        });
    });
});
