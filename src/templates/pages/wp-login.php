<?php
/**
 * Custom WP Login Overrides
 *
 * This file is loaded conditionally based on the plugin setting
 * (Settings > authentication > custom_login_ui).
 */

// Customize the login logo using the site’s main logo for desktop and the favicon for mobile
add_action('login_header', function() {
    // Get the site icon URL (favicon for mobile)
    $favicon_url = get_site_icon_url();

    // Get the site logo URL (for desktop)
    $custom_logo_id = get_theme_mod('custom_logo');
    $logo_url = $custom_logo_id ? wp_get_attachment_image_url($custom_logo_id, 'full') : $favicon_url;


    echo '
        <style>
            /* General Page Styling */
            body.login {
                background: #f4f6f8;
                font-family: "Inter", "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
            }

            /* Center login container and language switcher */
            #login {
                flex-direction: column;
                align-items: center;
                justify-content: center;
            }

            .login #backtoblog, .login #nav {
                text-align: center;
            }

            /* Logo Styling */
            .login h1 a {
                background-size: contain !important;
                background-repeat: no-repeat !important;
                background-position: center center !important;
                width: 250px; /* Increased size for desktop logo */
                height: 120px;
                margin: 0 auto 20px;
                display: block;
            }

            /* Default: Set the site logo for desktop */
            .login h1 a {
                background-image: url("' . esc_url($logo_url) . '");
            }

            /* Responsive: Mobile-specific adjustments */
            @media (max-width: 540px) {
                /* On mobile, switch to the favicon */
                .login h1 a {
                    background-image: url("' . esc_url($favicon_url) . '");
                    width: 100px; /* Larger favicon size on mobile */
                    height: 100px;
                }
            }

            /* Login Form Styling */
            .login form {
                background: #ffffff;
                border-radius: 12px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.05);
                max-width: 400px;
                border: 1px solid #ddd;
            }

            /* Form Label Styling */
            .login label {
                display: block;
                margin-bottom: 6px;
                font-size: 14px;
                color: #333;
                font-weight: 500;
            }

            /* Input Fields */
            .login .input {
                width: 100%;
                padding: 12px 14px;
                margin-bottom: 20px;
                border: 1px solid #ccc;
                border-radius: 6px;
                font-size: 15px;
                transition: border-color 0.3s;
                box-sizing: border-box;
                background: #fff;
            }

            .login .input:focus {
                border-color: #667eea;
                outline: none;
            }

            /* Login Button */
            .login .button-primary {
                background-color:rgb(0, 38, 255); /* Fresh, modern green for a positive look */
                border: none;
                border-radius: 6px;
                padding: 12px;
                margin-top: 10px;
                width: 100%;
                color: #fff;
                font-size: 16px;
                cursor: pointer;
                font-weight: 600; /* Slightly bolder text for prominence */
                box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1); /* Subtle shadow for depth */
                transition: background-color 0.3s ease, box-shadow 0.3s ease; /* Smooth color and shadow transitions */
            }

            .login .button-primary:hover {
                background-color:rgb(0, 89, 255); /* Slightly darker green on hover */
                box-shadow: 0 6px 15px rgba(0, 0, 0, 0.15); /* Enhanced shadow on hover */
            }


            /* Target the <p> container of the Generate Password button */
            .login .reset-pass-submit {
                display: flex;
                justify-content: center; /* Center the button horizontally */
                align-items: center;     /* Optionally center vertically, if needed */
            }

            .login .pw-weak {
                display: flex;
                justify-content: center;
                align-items: center;  /* Centers checkbox vertically */
                text-align: center;
                gap: 5px;  /* Adds a little more space between the checkbox and the label */
                margin-bottom: 15px;
            }

            #pw-weak {
                width: 18px;  /* Larger checkbox */
                height: 18px;
                cursor: pointer;  /* Shows pointer on hover */
            }
                
            .login label[for="pw-weak"] {
                margin-bottom: 0;  /* Removes the bottom margin */
            }

            /* Apply custom styles to the Generate Password button */
            .login .button.wp-generate-pw {
                background-color: rgb(0, 38, 255); /* Fresh, modern green for a positive look */
                border: none;
                border-radius: 6px;
                margin-top: 10px;
                width: 80%;
                
                color: #fff;
                cursor: pointer;
                font-weight: 600; /* Slightly bolder text for prominence */
                box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1); /* Subtle shadow for depth */
                transition: background-color 0.3s ease, box-shadow 0.3s ease; /* Smooth color and shadow transitions */
            }

            /* Hover effect for the Generate Password button */
            .login .button.wp-generate-pw:hover {
                background-color: rgb(0, 89, 255); /* Slightly darker green on hover */
                box-shadow: 0 6px 15px rgba(0, 0, 0, 0.15); /* Enhanced shadow on hover */
            }

            /* Focus effect for the Generate Password button */
            .login .button.wp-generate-pw:focus {
                outline: none; /* Remove default outline */
                box-shadow: 0 0 10px rgba(52, 152, 219, 0.6); /* Blue glow effect on focus */
            }
                
            /* Message Styling */
            /* Error Messages */
            .login .notice-error,
            .login #login_error {
                border-color: #e74c3c; /* Red border for error */
                background: #f8d7da; /* Light red background */
                color: #721c24; /* Darker red text */
                padding: 10px 15px;
                border-radius: 12px; /* Rounded corners */
                margin-bottom: 20px;
                animation: scaleIn 0.3s ease-out forwards; /* Apply scaleIn animation */
            }

            /* Success Messages */
            .login .notice-success {
                border-color: #28a745; /* Green border for success */
                color: #155724; /* Dark green text */
                padding: 10px 15px;
                border-radius: 12px; /* Rounded corners */
                margin-bottom: 20px;
                animation: scaleIn 0.3s ease-out forwards; /* Apply scaleIn animation */
            }

            /* Info Messages (Nice Blue) */
            .login .notice-info,
            .login .message.info {
                border-color: #3498db; /* Blue border for info */
                color: #2c3e50; /* Dark blue text */
                padding: 10px 15px;
                border-radius: 12px; /* Rounded corners */
                margin-bottom: 20px;
                animation: scaleIn 0.3s ease-out forwards; /* Apply scaleIn animation */
            }

            /* Scale-in Animation */
            @keyframes scaleIn {
                0% {
                    transform: scale(0.8); /* Start at 80% size */
                    opacity: 0; /* Start invisible */
                }
                100% {
                    transform: scale(1); /* Scale to 100% */
                    opacity: 1; /* Become visible */
                }
            }

            /* Language Switcher Styling */
            body.login #language-switcher {
                display: flex;
                margin-top: 20px;
                font-size: 14px;
                color: #666;
                width: 100%;
                justify-content: center;
                align-items: center;
                gap: 12px; /* Increased gap between elements for better spacing */
                padding: 0px !important;

            }

            /* Custom Select Styling */
            body.login #language-switcher select {
                background-color: #f1f1f1; /* Light background */
                color: #333; /* Darker text color */
                border: 1px solid #ccc; /* Light border */
                border-radius: 8px; /* Rounded corners */
                font-size: 14px;
                appearance: none; /* Removes default dropdown appearance */
                cursor: pointer;
                transition: all 0.3s ease; /* Smooth transition */
                box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1); /* Subtle box shadow */
            }

            /* Focus Effect for Select */
            body.login #language-switcher select:focus {
                border-color: #3498db; /* Blue border on focus */
                outline: none; /* Remove default outline */
                box-shadow: 0 0 10px rgba(52, 152, 219, 0.5); /* Blue glow effect */
            }

            /* Style for the Change button */
            body.login #language-switcher button {
                background-color: #3498db; /* Modern blue background */
                color: white; /* White text */
                border: none;
                border-radius: 8px; /* Rounded corners */
                font-size: 14px;
                cursor: pointer;
                transition: background-color 0.3s ease, transform 0.2s ease;
                box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1); /* Subtle box shadow */
            }

            /* Add hover effect for the Change button */
            body.login #language-switcher button:hover {
                background-color: #2980b9; /* Darker blue on hover */
                transform: scale(1.05); /* Slightly scale the button on hover */
            }

            /* Custom "Change" Button Styling (Like the main button) */
            body.login #language-switcher input[type="submit"] {
                background-color:rgb(0, 38, 255); /* Fresh, modern green for a positive look */
                border: none;
                border-radius: 6px;
                width: 100%;
                color: #fff;
                cursor: pointer;
                font-weight: 600; /* Slightly bolder text for prominence */
                box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1); /* Subtle shadow for depth */
                transition: background-color 0.3s ease, box-shadow 0.3s ease; /* Smooth color and shadow transitions */
            }

            /* Hover Effect for the "Change" button */
            body.login #language-switcher input[type="submit"]:hover {
                background-color:rgb(0, 89, 255); /* Slightly darker green on hover */
                box-shadow: 0 6px 15px rgba(0, 0, 0, 0.15); /* Enhanced shadow on hover */
            }

            /* Focus Effect for the "Change" button */
            body.login #language-switcher input[type="submit"]:focus {
                outline: none; /* Remove default outline */
                box-shadow: 0 0 10px rgba(52, 152, 219, 0.6); /* Blue glow effect on focus */
            }

            /* Remember Me Checkbox Styling */
            .login .forgetmenot {
                display: flex;
                align-items: center;
                font-size: 14px;
                color: #333;
            }

            .login .forgetmenot input[type="checkbox"] {
                margin-right: 8px;
                width: 16px;
                height: 16px;
                accent-color: #667eea; /* Makes the checkbox purple on supported browsers */
            }

            /* Responsive */
            @media (max-width: 540px) {
                body.login #language-switcher {
                    flex-direction: column; /* Stack the language options vertically */
                    gap: 8px; /* Reduced gap for mobile */
                }

            }
        </style>
    ';
});