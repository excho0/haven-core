<?php

use \HavenCore\Services\HC_Supplier_Service;

// Get the order ID and the supplier data from the order's meta
$order_id = is_numeric($order) ? $order : $order->get_id();
$supplier_data = get_post_meta($order_id, '_supplier_data', true);

if (empty($supplier_data)) {
    echo '<small style="color:#999;">—</small>';
    return;
}

// Loop through the suppliers' data and display their fulfillment status
echo '<div class="supplier-fulfillment-badges-wrapper" style="display: flex; flex-direction: column; gap: 10px;">';

foreach ($supplier_data as $sid => $d) {
    $fulfillment_status = isset($d['fulfillment_status']) ? $d['fulfillment_status'] : 'pending';

    // Add the CSS class for the status
    $status_class = '';
    switch ($fulfillment_status) {
        case 'fulfilled':
            $status_class = 'fulfilled'; // Green
            break;
        case 'partially-fulfilled':
            $status_class = 'partially-fulfilled'; // Orange
            break;
        case 'pending':
            $status_class = 'pending'; // Yellow
            break;
        default:
            $status_class = 'pending'; // Default to pending
            break;
    }

    // Fetch the supplier name using the Supplier class (using getById)
    $service = new HC_Supplier_Service();
    $supplier = $service->get((int) $sid);
    $supplier_name = $supplier ? $supplier->get_name() : 'Unknown Supplier';

    // Render the fulfillment status with dynamic classes inside the wrapper div
    echo '<div class="supplier-fulfillment-status ' . esc_attr($status_class) . '" style="
            display: inline-flex; 
            align-items: center; 
            padding: 4px 10px; 
            font-size: 14px; 
            font-weight: 500; 
            color: #fff; 
            border-radius: 8px; 
            background-color: ' . ($status_class === 'fulfilled' ? '#28a745' : ($status_class === 'partially-fulfilled' ? '#fd7e14' : '#ffc107')) . '; 
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            ">
            <span style="font-size: 16px; padding-right: 6px;">' . esc_html( $status_class === 'fulfilled' ? '✔' : ($status_class === 'partially-fulfilled' ? '⚠️' : '⏳')) . '</span>
            <span style="font-size: 14px;">' . esc_html( $supplier_name ) . '</span>
          </div>';
}

echo '</div>';  // End of badges wrapper div
?>
