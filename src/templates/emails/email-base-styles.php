<?php
/**
 * Central Email CSS Styles
 */
?>

<style>
    body {
        font-family: Arial, sans-serif;
        background-color: #FFFFFF;
        margin: 0;
        padding: 0;
    }
    .email-container {
        max-width: 600px;
        margin: 20px auto;
        padding: 20px;
        background-color: #ffffff;
        border-radius: 10px;
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        text-align: center;
    }
    .email-header {
        border-bottom: 1px solid #dddddd;
        padding-bottom: 10px;
        margin-bottom: 20px;
    }
    .email-header img {
        max-width: 250px;
        height: auto;
    }
    .email-content {
        line-height: 1.6;
        font-size: 16px;
        color: #333333;
    }
    .email-content a {
        color: #1a73e8;
        text-decoration: none;
        font-weight: bold;
    }
    .email-content a:hover {
        text-decoration: underline;
    }
    .button {
        display: inline-block;
        padding: 10px 20px;
        margin: 20px 0;
        font-size: 16px;
        color: #ffffff !important;
        background-color: #1a73e8;
        border-radius: 20px;
        text-decoration: none;
        font-weight: bold;
        transition: background-color 0.3s ease-out, color 0.3s ease-out, opacity 0.3s ease-out;
    }
    .button span {
        color: #ffffff !important;
        font-weight: bold;
    }
    .button a {
        color: #ffffff !important;
        font-weight: bold;
    }
    .button:hover {
        background-color: #155bb5;
    }
    .email-footer {
        border-top: 1px solid #dddddd;
        padding-top: 10px;
        margin-top: 20px;
        font-size: 12px;
        color: #777777;
    }


    /* Card-style container for each tracking group */
    .tracking-card {
        background-color: #ffffff;            /* Clean white background */
        border: 1px solid #e5e7eb;            /* Subtle border */
        border-radius: 10px;
        padding: 16px;
        margin: 20px 0;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);  /* Soft shadow for depth */
    }

    /* Header row with tracking number left, button right */
    .tracking-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 16px;
        border-bottom: 1px solid #e5e7eb;     /* Divider line for separation */
        padding-bottom: 8px;
    }

    /* Tracking number text */
    .tracking-number {
        font-size: 16px;
        font-weight: 600;
        color: #374151;                      /* Slate gray for readability */
    }

    /* Track button */
    a.button-track {
        background-color: #3b82f6;            /* Primary blue */
        color: #ffffff !important;
        padding: 8px 16px;
        border-radius: 6px;
        font-size: 14px;
        text-decoration: none;
        transition: background 0.3s ease;
        border: none;
        cursor: pointer;
    }

    .button-track:hover {
        background-color: #2563eb;
    }

    /* Product list container */
    .product-list {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    /* Each product row with a rounded, spaced, clean look */
    .product-item {
        display: flex;
        align-items: center;
        gap: 12px;
        background-color: #f9fafb;           /* Light gray background for contrast */
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        padding: 12px;
        transition: box-shadow 0.2s ease;
    }

    .product-item:hover {
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
    }

    .product-image {
        width: 50px;
        height: 50px;
        border-radius: 6px;
        object-fit: cover;
    }

    .product-info {
        flex: 1;
    }

    .product-name {
        font-size: 15px;
        color: #2563eb;
        text-decoration: none;
        font-weight: 600;
    }

    .product-name:hover {
        text-decoration: underline;
    }

    .product-quantity {
        font-size: 13px;
        color: #6b7280;
    }



    .unfulfilled-product {
        background-color: #f9fafb;
        padding: 12px;
    }

    .unfulfilled-text {
        font-size: 14px;
        color: #6b7280;
    }

    .partially-shipped {
        border: 2px solid #f97316;  /* Custom border color */
        padding: 16px;
        border-radius: 8px;
        background-color: #f7f0e1; /* Soft orange background */
        color: #f97316;  /* Text color matching the border */
    }

    
    .fully-shipped {
        border: 2px solid rgb(0, 161, 48); /* Custom border color */
        padding: 16px;
        border-radius: 8px;
        background-color: #e1f7e1ab; /* Soft green background */
        color: rgb(1, 108, 5); /* Text color matching the border */
    }
</style>