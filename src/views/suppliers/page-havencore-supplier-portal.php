<?php
    // Check if user is logged in and has the 'supplier' role
    if (!is_user_logged_in() || !wc_current_user_has_role('supplier')) {
        wp_redirect(wp_login_url($_SERVER['REQUEST_URI']));
        exit;
    }

    use HavenCore\Utils\ScriptHelpers;
    use HavenCore\Utils\Tracking_Carriers;

    ScriptHelpers::loadApiFetch(); // ✅ Injects wp-api-fetch and nonce safely

    ScriptHelpers::loadVue([
        'withDraggable'    => true,
    ]);

    // Get the current user and their locale
    $current_user = wp_get_current_user();
    $user_locale = get_user_locale($current_user); // Get the WordPress locale for the user
    $user_attributes = get_user_meta($current_user->ID, 'attributes', true);

    // If the user has a locale, switch to it
    if ($user_locale) {
        switch_to_locale($user_locale);
    }
    
    // Get the 'lang' and 'dir' attributes dynamically based on the locale
    ob_start();
    language_attributes();  // This function prints the 'lang' and 'dir' attributes
    $locale_attributes = ob_get_clean();  // Capture the output

    $tracking_carriers = Tracking_Carriers::get_carrier_groups();

    // Handle POST from the "Continue Anyway" form
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_continue'])) {
        // Set short-lived cookie
        setcookie('allow_mobile_dashboard', '1', time() + 60, "/");
        // Redirect to the same page to re-enter normal flow
        wp_redirect($_SERVER['REQUEST_URI']);
        exit;
    }

    // Show warning only on mobile AND if cookie is not set
    if (wp_is_mobile() && !isset($_COOKIE['allow_mobile_dashboard'])) {
        ?>
        <!DOCTYPE html>
        <html lang="en" style="overflow: hidden;">
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>⚠️ Not Built for Mobile</title>
            <style>
                html, body {
                    margin: 0;
                    padding: 0;
                    overflow: hidden;
                    height: 100%;
                    font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
                    background: #f9fafb;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    color: #1f2937;
                    -webkit-tap-highlight-color: transparent;
                    user-select: none;
                }
                .warning {
                    background: white;
                    padding: 2.25rem 2rem;
                    border-radius: 1.25rem;
                    box-shadow: 0 12px 28px rgba(0, 0, 0, 0.08);
                    text-align: center;
                    max-width: 95%;
                    width: 420px;
                    animation: fadeIn 0.4s ease-out both;
                }
                .warning h1 {
                    font-size: 1.75rem;
                    color: #dc2626;
                    margin-bottom: 1.2rem;
                }
                .warning p {
                    font-size: 1rem;
                    color: #374151;
                    line-height: 1.6;
                    margin-bottom: 2rem;
                }
                .warning button {
                    background: linear-gradient(135deg, #4f46e5, #3b82f6);
                    color: white;
                    padding: 1rem 1.75rem;
                    border-radius: 9999px;
                    border: none;
                    font-weight: 600;
                    font-size: 1rem;
                    cursor: pointer;
                    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
                    transition: transform 0.2s ease, box-shadow 0.2s ease;
                }
                .warning button:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 6px 18px rgba(59, 130, 246, 0.5);
                }
                @keyframes fadeIn {
                    from { opacity: 0; transform: translateY(20px); }
                    to { opacity: 1; transform: translateY(0); }
                }
            </style>
        </head>
        <body>
            <div class="warning">
                <h1>⚠️ Not Built for Mobile</h1>
                <p>
                    This dashboard is optimized for desktop and laptop devices.<br><br>
                    Mobile phones — even mid-range ones — may experience lag, glitches, or crashes.<br><br>
                    We strongly recommend using a computer or tablet.<br><br>
                    If you still want to continue, you may proceed below.
                </p>
                <form method="POST">
                    <button type="submit" name="confirm_continue">I Understand, Continue →</button>
                </form>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
?>

<html <?= $locale_attributes; ?> style="overflow: hidden; overscroll-behavior: none;">
    <head>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <style>

            .fade-in-scale {
                animation: fadeInScale 0.35s ease-out;
            }

            @keyframes fadeInScale {
                from {
                    opacity: 0;
                    transform: scale(0.95);
                }
                to {
                    opacity: 1;
                    transform: scale(1);
                }
            }

            .fade-slide-badge-enter-active,
            .fade-slide-badge-leave-active {
                transition: all 0.3s ease;
            }
            .fade-slide-badge-enter-from {
                opacity: 0;
                transform: translateY(-5px);
            }
            .fade-slide-badge-leave-to {
                opacity: 0;
                transform: translateY(5px);
            }

            .p-datatable-column-header-content {
                justify-content: center;
            }
            
            .p-datatable-tbody > tr > td {
                text-align: center !important;
            }
        </style>
    </head>
    <body>
        <?= havencore_render_loader([
            'count' => 6,
            'id' => 'app-loading',
            'on_ready' => 'initSupplierPortalApp'
        ]); ?>



        <div id="havencore-app" class="hc-supplier-portal-app">
            <havencore-app></havencore-app>
        </div>

        <script type="application/json" id="app-initial-data">
            <?= json_encode([
                'trackingCarriers' => $tracking_carriers,
                'loginUrl'        => wp_login_url($_SERVER['REQUEST_URI']),
                'i18n' => [

                    // ─── General UI ───────────────────────────────
                    'actions'                        => __('Actions', HAVEN_CORE_TEXT_DOMAIN),
                    'apply'                          => __('Apply', HAVEN_CORE_TEXT_DOMAIN),
                    'back_to_home'                   => __('Back to Home', HAVEN_CORE_TEXT_DOMAIN),
                    'cancel'                         => __('Cancel', HAVEN_CORE_TEXT_DOMAIN),
                    'collapse'                       => __('Collapse', HAVEN_CORE_TEXT_DOMAIN),
                    'expand'                         => __('Expand', HAVEN_CORE_TEXT_DOMAIN),
                    'home'                           => __('Home', HAVEN_CORE_TEXT_DOMAIN),
                    'logout'                         => __('Log Out', HAVEN_CORE_TEXT_DOMAIN),
                    'n_a'                            => __('N/A', HAVEN_CORE_TEXT_DOMAIN),
                    'refresh'                        => __('Refresh', HAVEN_CORE_TEXT_DOMAIN),
                    'save'                           => __('Save', HAVEN_CORE_TEXT_DOMAIN),
                    'search'                         => __('Search', HAVEN_CORE_TEXT_DOMAIN),
                    'title'                          => __('Supplier Portal', HAVEN_CORE_TEXT_DOMAIN),
                    'communications'                 => __('Communications', HAVEN_CORE_TEXT_DOMAIN),
                    'conversations'                  => __('Conversations', HAVEN_CORE_TEXT_DOMAIN),
                    'messages'                       => __('Messages', HAVEN_CORE_TEXT_DOMAIN),
                    'select_conversation'            => __('Select a conversation to get started.', HAVEN_CORE_TEXT_DOMAIN),
                    'no_conversations'               => __('No conversations yet.', HAVEN_CORE_TEXT_DOMAIN),
                    'no_messages'                    => __('No messages in this conversation yet.', HAVEN_CORE_TEXT_DOMAIN),
                    'start_messaging'                => __('Type your first message to kick things off.', HAVEN_CORE_TEXT_DOMAIN),
                    'new_message'                    => __('New Message', HAVEN_CORE_TEXT_DOMAIN),
                    'message_placeholder'            => __('Type a message…', HAVEN_CORE_TEXT_DOMAIN),
                    'send_message'                   => __('Send Message', HAVEN_CORE_TEXT_DOMAIN),
                    'you'                            => __('You', HAVEN_CORE_TEXT_DOMAIN),
                    'mark_as_read'                   => __('Mark as Read', HAVEN_CORE_TEXT_DOMAIN),
                    'delete_conversation'            => __('Delete Conversation', HAVEN_CORE_TEXT_DOMAIN),
                    'confirm_delete_conversation'    => __('Are you sure you want to delete this conversation? This action cannot be undone.', HAVEN_CORE_TEXT_DOMAIN),
                    'confirm'                        => __('Confirm', HAVEN_CORE_TEXT_DOMAIN),
                    'dismiss'                        => __('Cancel', HAVEN_CORE_TEXT_DOMAIN),
                    'new_conversation'               => __('New Conversation', HAVEN_CORE_TEXT_DOMAIN),
                    'create'                         => __('Create', HAVEN_CORE_TEXT_DOMAIN),
                    'cancel'                         => __('Cancel', HAVEN_CORE_TEXT_DOMAIN),
                    'supplier_id'                    => __('Supplier ID', HAVEN_CORE_TEXT_DOMAIN),
                    'order_id'                       => __('Order ID (optional)', HAVEN_CORE_TEXT_DOMAIN),
                    'subject'                        => __('Subject', HAVEN_CORE_TEXT_DOMAIN),
                    'initial_message'                => __('Initial Message', HAVEN_CORE_TEXT_DOMAIN),

                    // ─── Authentication / Password ───────────────
                    'change_password'               => __('Change Password', HAVEN_CORE_TEXT_DOMAIN),
                    'confirm_password'              => __('Confirm Password', HAVEN_CORE_TEXT_DOMAIN),
                    'confirm_password_error'        => __('Passwords do not match', HAVEN_CORE_TEXT_DOMAIN),
                    'enter_new_password'            => __('Enter a new password', HAVEN_CORE_TEXT_DOMAIN),
                    'lowercase_requirement'         => __('At least one lowercase letter', HAVEN_CORE_TEXT_DOMAIN),
                    'min_length_requirement'        => __('Minimum 8 characters', HAVEN_CORE_TEXT_DOMAIN),
                    'new_password'                  => __('New Password', HAVEN_CORE_TEXT_DOMAIN),
                    'numeric_requirement'           => __('At least one numeric character', HAVEN_CORE_TEXT_DOMAIN),
                    'pick_a_password'               => __('Pick a password', HAVEN_CORE_TEXT_DOMAIN),
                    'uppercase_requirement'         => __('At least one uppercase letter', HAVEN_CORE_TEXT_DOMAIN),

                    // ─── Customer & Contact Info ─────────────────
                    'company'                       => __('Company', HAVEN_CORE_TEXT_DOMAIN),
                    'customer_info'                 => __('Customer Info', HAVEN_CORE_TEXT_DOMAIN),
                    'email'                         => __('Email', HAVEN_CORE_TEXT_DOMAIN),
                    'name'                          => __('Name', HAVEN_CORE_TEXT_DOMAIN),
                    'phone'                         => __('Phone', HAVEN_CORE_TEXT_DOMAIN),
                    'shipping_address'              => __('Shipping Address', HAVEN_CORE_TEXT_DOMAIN),
                    'billing_address'               => __('Billing Address', HAVEN_CORE_TEXT_DOMAIN),
                    'shipping_address_fallback_badge' => __('Billing Address Used', HAVEN_CORE_TEXT_DOMAIN),
                    'shipping_address_fallback_note'  => __('Customer did not supply a shipping address, so we are showing the billing details instead.', HAVEN_CORE_TEXT_DOMAIN),

                    // ─── Fulfillment / Tracking ──────────────────
                    'add_tracking_number'           => __('Add Tracking Number', HAVEN_CORE_TEXT_DOMAIN),
                    'already_fulfilled'             => __('Already Fulfilled', HAVEN_CORE_TEXT_DOMAIN),
                    'already_fulfilled_text'        => __('Order is already marked as fulfilled.', HAVEN_CORE_TEXT_DOMAIN),
                    'confirm_fulfillment'           => __('Confirm Fulfillment', HAVEN_CORE_TEXT_DOMAIN),
                    'confirm_fulfillment_text'      => __('Are you sure you want to confirm fulfillment for:', HAVEN_CORE_TEXT_DOMAIN),
                    'confirm_submit'                => __('Submit', HAVEN_CORE_TEXT_DOMAIN),
                    'currently_in_tracking'         => __('Currently in Tracking #', HAVEN_CORE_TEXT_DOMAIN),
                    'fulfillment_info_text'         => __('All tracking info has been saved already. This will notify the customer and lock the tracking fields.', HAVEN_CORE_TEXT_DOMAIN),
                    'mark_as_fulfilled'             => __('Mark as Fulfilled', HAVEN_CORE_TEXT_DOMAIN),
                    'missing_tracking_number'       => __('Please enter a valid tracking number.', HAVEN_CORE_TEXT_DOMAIN),
                    'missing_tracking_number_detail'=> __('Please enter a valid tracking number.', HAVEN_CORE_TEXT_DOMAIN),
                    'missing_tracking_number_title' => __('Missing Tracking Number', HAVEN_CORE_TEXT_DOMAIN),
                    'tracking_exists'               => __('This tracking number already exists.', HAVEN_CORE_TEXT_DOMAIN),
                    'tracking_exists_detail'        => __('This tracking number already exists.', HAVEN_CORE_TEXT_DOMAIN),
                    'tracking_exists_title'         => __('Tracking Exists', HAVEN_CORE_TEXT_DOMAIN),
                    'tracking_number'               => __('Tracking Number', HAVEN_CORE_TEXT_DOMAIN),
                    'carrier'                       => __('Carrier', HAVEN_CORE_TEXT_DOMAIN),
                    'carrier_placeholder'           => __('Select Carrier', HAVEN_CORE_TEXT_DOMAIN),
                    'carrier_other'                 => __('Carrier Name', HAVEN_CORE_TEXT_DOMAIN),
                    'carrier_required'              => __('Carrier is required.', HAVEN_CORE_TEXT_DOMAIN),
                    'carrier_other_required'        => __('Carrier name is required for Other.', HAVEN_CORE_TEXT_DOMAIN),

                    // ─── Orders ──────────────────────────────────
                    'all'                           => __('All', HAVEN_CORE_TEXT_DOMAIN),
                    'error_loading_orders'          => __('Error Loading Orders', HAVEN_CORE_TEXT_DOMAIN),
                    'fetching_orders_error'         => __('Error Loading Orders', HAVEN_CORE_TEXT_DOMAIN),
                    'fetching_orders_loading'       => __('Fetching more orders...', HAVEN_CORE_TEXT_DOMAIN),
                    'fetching_orders_success'       => __('Orders loaded successfully!', HAVEN_CORE_TEXT_DOMAIN),
                    'filter_status'                 => __('Filter Status', HAVEN_CORE_TEXT_DOMAIN),
                    'fulfilled'                     => __('Fulfilled', HAVEN_CORE_TEXT_DOMAIN),
                    'no_changes_detail'             => __('No changes have been made!', HAVEN_CORE_TEXT_DOMAIN),
                    'no_orders_message_subtitle'    => __('Try adjusting your filters or check back later—new orders might pop up soon!', HAVEN_CORE_TEXT_DOMAIN),
                    'no_orders_message_title'       => __('No orders found.', HAVEN_CORE_TEXT_DOMAIN),
                    'order_number'                  => __('Order #', HAVEN_CORE_TEXT_DOMAIN),
                    'order_saved'                   => __('Order Saved Successfully', HAVEN_CORE_TEXT_DOMAIN),
                    'orders'                        => __('Orders', HAVEN_CORE_TEXT_DOMAIN),
                    'partially_fulfilled'           => __('Partially Fulfilled', HAVEN_CORE_TEXT_DOMAIN),
                    'pending'                       => __('Pending', HAVEN_CORE_TEXT_DOMAIN),
                    'ready_to_fulfill'              => __('Ready to Fulfill', HAVEN_CORE_TEXT_DOMAIN),

                    // ─── Products & Grouping ─────────────────────
                    'move'                          => __('Move', HAVEN_CORE_TEXT_DOMAIN),
                    'move_products_to_tracking_group' => __('Move Products to Tracking Group', HAVEN_CORE_TEXT_DOMAIN),
                    'note'                          => __('Note', HAVEN_CORE_TEXT_DOMAIN),
                    'products'                      => __('Products', HAVEN_CORE_TEXT_DOMAIN),
                    'quantity'                      => __('Quantity', HAVEN_CORE_TEXT_DOMAIN),
                    'stock'                         => __('Stock', HAVEN_CORE_TEXT_DOMAIN),
                    'status'                        => __('Status', HAVEN_CORE_TEXT_DOMAIN),
                    'managed'                       => __('Managed', HAVEN_CORE_TEXT_DOMAIN),
                    'unmanaged'                     => __('Unmanaged', HAVEN_CORE_TEXT_DOMAIN),
                    'edit'                          => __('Edit', HAVEN_CORE_TEXT_DOMAIN),
                    'variations'                    => __('Variations', HAVEN_CORE_TEXT_DOMAIN),
                    'edit_variations'               => __('Edit Variations', HAVEN_CORE_TEXT_DOMAIN),
                    'attributes'                    => __('Attributes', HAVEN_CORE_TEXT_DOMAIN),
                    'product_image_alt'             => __('product image', HAVEN_CORE_TEXT_DOMAIN),
                    'search_placeholder'            => __('Search...', HAVEN_CORE_TEXT_DOMAIN),
                    'id'                            => __('ID', HAVEN_CORE_TEXT_DOMAIN),
                    'instock'                       => __('In stock', HAVEN_CORE_TEXT_DOMAIN),
                    'outofstock'                    => __('Out of stock', HAVEN_CORE_TEXT_DOMAIN),
                    'onbackorder'                   => __('On backorder', HAVEN_CORE_TEXT_DOMAIN),
                    'remove_group_tooltip'          => __('Remove group and unassign products', HAVEN_CORE_TEXT_DOMAIN),
                    'select_products_to_move'       => __('Select products to move into', HAVEN_CORE_TEXT_DOMAIN),
                    'sku'                           => __('SKU', HAVEN_CORE_TEXT_DOMAIN),
                    'tap_here_to_move_products'     => __('Tap here to move products', HAVEN_CORE_TEXT_DOMAIN),
                    'tap_or_drag_products_here'     => __('Tap here or Drag products to add them', HAVEN_CORE_TEXT_DOMAIN),
                    'ungrouped_products'            => __('Ungrouped Products', HAVEN_CORE_TEXT_DOMAIN),

                    // ─── Notes ───────────────────────────────────
                    'add_note'                      => __('Add Note', HAVEN_CORE_TEXT_DOMAIN),
                    'delete_note'                   => __('Delete Note', HAVEN_CORE_TEXT_DOMAIN),
                    'edit_note'                     => __('Edit Note', HAVEN_CORE_TEXT_DOMAIN),

                    // ─── Toasts / Feedback ───────────────────────
                    'error'                         => __('Error', HAVEN_CORE_TEXT_DOMAIN),
                    'request_failed'                => __('The request failed. Please check your connection.', HAVEN_CORE_TEXT_DOMAIN),
                    'success'                       => __('Success', HAVEN_CORE_TEXT_DOMAIN),

                    'new_order_notification_title' => __('📦 New Order Received!', HAVEN_CORE_TEXT_DOMAIN),
                    'new_order_notification_body'  => __('You\'ve just been assigned Order #%order_id%. Tap to review the details.', HAVEN_CORE_TEXT_DOMAIN),

                ]
            ]) ?>
        </script>

        <script>
            const currentUser = <?= json_encode([
                'ID' => $current_user->ID,
                'user_login' => $current_user->user_login,
                'user_email' => $current_user->user_email,
                'display_name' => $current_user->display_name,
                'roles' => $current_user->roles,
                'avatar' => get_avatar_url($current_user->ID),
                'attributes' => $user_attributes,
                'logout_url' => html_entity_decode(wp_logout_url(home_url()))
            ]); ?>;

            // console.log('currentUser inital Data:', currentUser);
            
            const initialData = JSON.parse(document.getElementById('app-initial-data')?.textContent || '{}');
            const portalLoginUrl = initialData.loginUrl || <?= json_encode(wp_login_url($_SERVER['REQUEST_URI'])); ?>;

            let authRedirectGraceUntil = 0;
            const extendAuthRedirectGrace = (duration = 1000 * 10) => {
                authRedirectGraceUntil = Date.now() + duration;
            };

            const redirectToLogin = () => {
                const targetRoute = window.location.hash ? window.location.hash.replace(/^#/, '') : '/orders';
                const targetUrl = new URL(window.location.href);
                targetUrl.hash = targetRoute.startsWith('#') ? targetRoute : '#' + targetRoute.replace(/^\/?/, '/');

                if (portalLoginUrl) {
                    const url = new URL(portalLoginUrl, window.location.origin);
                    url.searchParams.set('redirect_to', targetUrl.toString());
                    window.location.href = url.toString();
                } else {
                    window.location.href = targetUrl.toString();
                }
            };

            const shouldRedirectForAuthError = (error) => {
                if (authRedirectGraceUntil && Date.now() < authRedirectGraceUntil) {
                    return false;
                }

                const code = (error?.code || error?.data?.code || '').toString();
                const status = error?.status || error?.data?.status;
                const message = (error?.message || error?.data?.message || '').toLowerCase();

                if (code === 'rest_cookie_invalid_nonce' || code === 'rest_not_logged_in') {
                    return true;
                }

                if (status === 401) {
                    return true;
                }

                if (status === 403) {
                    if (
                        code === 'rest_cookie_invalid_nonce' ||
                        code === 'rest_not_logged_in' ||
                        message.includes('cookie') ||
                        message.includes('logged in') ||
                        message.includes('nonce')
                    ) {
                        return true;
                    }
                }

                return false;
            };

            if (window.wp?.apiFetch?.use) {
                wp.apiFetch.use((options, next) => {
                    return next(options).catch(error => {
                        if (shouldRedirectForAuthError(error)) {
                            redirectToLogin();
                        }
                        throw error;
                    });
                });
            }

            function waitForWP(timeout = 5000, interval = 100) {
                return new Promise((resolve, reject) => {
                    const maxTries = Math.ceil(timeout / interval);
                    let tries = 0;

                    const check = () => {
                        if (typeof wp !== 'undefined' && (wp.apiFetch || wp.apiRequest)) {
                            resolve(wp);
                        } else if (++tries >= maxTries) {
                            reject(new Error('🛑 wp.apiFetch not available within timeout.'));
                        } else {
                            setTimeout(check, interval);
                        }
                    };

                    check();
                });
            }
            
            // console.log('initialData:', initialData);
            // console.log(Sortable)

            function debounce(func, wait) {
                let timeout;
                return function (...args) {
                    clearTimeout(timeout);
                    timeout = setTimeout(() => func.apply(this, args), wait);
                };
            }

            function notifyOSWithVibrate({
                title = '',
                body = '',
                icon = '/icon.png',
                silent = false,
                vibrate = true
            } = {}) {
                if (!('Notification' in window)) {
                    console.warn('[notifyOS] Notifications are not supported in this browser.');
                    return;
                }

                const notify = () => {
                    const options = {
                        body,
                        icon,
                        silent,
                    };

                    if (vibrate && 'vibrate' in navigator) {
                        options.vibrate = [200, 100, 200]; // pattern: vibrate, pause, vibrate
                    }

                    new Notification(title, options);
                };

                if (Notification.permission === 'granted') {
                    notify();
                } else if (Notification.permission !== 'denied') {
                    Notification.requestPermission().then(permission => {
                        if (permission === 'granted') {
                            notify();
                        }
                    });
                }
            }

            // console.log(VueSonner)

            const app = Vue.createApp({
                data() {
                    // Initialize sidebarItems dynamically based on i18n
                    const sidebarItems = [
                        {
                            name: initialData.i18n.orders,  // General label
                            icon: 'pi pi-shopping-bag',           // Icon for Home
                            route: '/orders'                    // Route for Home section
                        },
                        {
                            name: initialData.i18n.communications,
                            icon: 'pi pi-comments',
                            route: '/communications'
                        },
                        {
                            name: initialData.i18n.products,        // General label
                            icon: 'pi pi-shop',                     // Icon for Products
                            route: '/products'                    // Route for Products section
                        }
                    ];


                    // Log sidebarItems initialization to check if it's set correctly
                    // console.log('sidebarItems initialized:', sidebarItems);

                    // Access the Pinia store
                    const globalStore = useGlobalStore();

                    // Use a computed property to ensure reactivity
                    const mobile = Vue.computed(() => globalStore.isMobile || globalStore.isTablet);

                    const Rtl = Vue.computed(() => globalStore.isRtl);
                    
                    return {
                    i18n: initialData.i18n,
                    currentUser: currentUser,
                    mobileIcon: "<?= esc_url(get_site_icon_url()); ?>",
                    sidebarItems: sidebarItems || [], // Sidebar items for navigation
                    mobile: mobile,
                    isRtl: Rtl,
                    unreadSummary: null,
                    unreadPoller: null,
                    carrierGroups: initialData.trackingCarriers || {},
                };
            },
                provide() {
                    return {
                        i18n: this.i18n,
                        currentUser: this.currentUser,
                        mobileIcon: this.mobileIcon,
                        mobile: this.mobile,
                        isAdmin: false,
                        isRtl: this.isRtl,
                    };
                },
                computed: {
                    communicationsUnreadCount() {
                        return this.unreadSummary?.unread_count || 0;
                    },
                    hasUnreadCommunications() {
                        return this.communicationsUnreadCount > 0;
                    },
                },
                methods: {
                    carrierLabel(code) {
                        if (!code) {
                            return '';
                        }
                        const entry = this.carrierLabelMap[code];
                        return entry ? entry.label : '';
                    },
                    formatCarrierDisplay(group) {
                        if (!group) {
                            return '';
                        }
                        const code = group.carrier_code;
                        if (!code) {
                            return '';
                        }
                        if (code === this.carrierOtherCode) {
                            return group.carrier_name || this.i18n.carrier_other;
                        }
                        const entry = this.carrierLabelMap[code];
                        if (entry) {
                            return `${entry.group ? entry.group + ' • ' : ''}${entry.label}`;
                        }
                        return group.carrier_name || code;
                    },
                    shouldTrackUnread() {
                        if (this.$route?.name === 'communications') {
                            return false;
                        }

                        const hash = window.location.hash || '';
                        return !hash.startsWith('#/communications');
                    },
                    async fetchUnreadSummary({ silent = false, force = false } = {}) {
                        if (!force && !this.shouldTrackUnread()) {
                            this.unreadSummary = null;
                            return;
                        }
                        try {
                            const response = await wp.apiFetch({
                                path: '/hc/v1/communications/messaging/unread-summary',
                                method: 'GET',
                            });
                            if (response && typeof response === 'object') {
                                this.unreadSummary = response;
                            }
                        } catch (err) {
                            if (!silent) {
                                console.error('Failed to fetch unread summary', err);
                            }
                        }
                    },
                    navigateToOrder(orderId) {
                        if (!orderId || !this.$router) {
                            return;
                        }

                        this.$router.push({ name: 'orders', query: { order_id: orderId } });
                    },
                    isRouteActive(route) {
                        if (!this.$route) {
                            return false;
                        }

                        if (route === '/communications') {
                            return this.$route?.path?.startsWith('/communications');
                        }

                        return this.$route.path === route;
                    },
                    startUnreadPolling() {
                        this.stopUnreadPolling();
                        if (!this.shouldTrackUnread()) {
                            return;
                        }
                        if (!window.HavenCoreFetchClient?.create) {
                            console.warn('HavenCoreFetchClient not available.');
                            return;
                        }
                        this.unreadPoller = window.HavenCoreFetchClient.create({
                            id: 'havencore-unread-poller',
                            task: () => this.fetchUnreadSummary({ silent: true }),
                            interval: 20000,
                            runOnFocus: true
                        });
                        this.unreadPoller.start();
                    },
                    stopUnreadPolling() {
                        if (this.unreadPoller?.stop) {
                            this.unreadPoller.stop();
                        }
                        this.unreadPoller = null;
                    },
                    showUnreadBadge(item) {
                        if (!item?.route) {
                            return false;
                        }

                        if (item.route === '/communications') {
                            const active = this.isRouteActive('/communications');
                            return !active && this.hasUnreadCommunications;
                        }

                        return false;
                    },
                    manageUnreadPolling() {
                        if (this.shouldTrackUnread()) {
                            this.fetchUnreadSummary();
                            this.startUnreadPolling();
                        } else {
                            this.stopUnreadPolling();
                        }
                    },
                },
                mounted() {
                    this.manageUnreadPolling();
                },
                beforeUnmount() {
                    this.stopUnreadPolling();
                },
                watch: {
                    '$route.fullPath'() {
                        this.manageUnreadPolling();
                    }
                },
            });

            app.use(PrimeVue.Config, {
                theme: {
                    preset: PrimeVue.Themes.Aura,
                    options: {
                        darkModeSelector: true,
                    }
                }
            });

            // 🔌 Plugins
            app.use(VueSonner) // registers everything


            // 🧭 Directives
            app.directive('tooltip', PrimeVue.Tooltip);

            // 🧾 Form Inputs
            app.component('Password', PrimeVue.Password);
            app.component('InputText', PrimeVue.InputText);
            app.component('Textarea', PrimeVue.Textarea);
            app.component('FloatLabel', PrimeVue.FloatLabel);
            app.component('IconField', PrimeVue.IconField);
            app.component('InputIcon', PrimeVue.InputIcon);
            app.component('InputGroup', PrimeVue.InputGroup);
            app.component('InputGroupAddon', PrimeVue.InputGroupAddon);
            app.component('ToggleSwitch', PrimeVue.ToggleSwitch);
            app.component('Checkbox', PrimeVue.Checkbox);


            // 🕹️ UI Controls
            app.component('Button', PrimeVue.Button);
            app.component('Menu', PrimeVue.Menu);

            // 🧱 Layout & Containers
            app.component('Menubar', PrimeVue.Menubar);
            app.component('Panel', PrimeVue.Panel);
            app.component('Card', PrimeVue.Card);
            app.component('Dialog', PrimeVue.Dialog);
            app.component('ScrollPanel', PrimeVue.ScrollPanel);
            app.component('VirtualScroller', PrimeVue.VirtualScroller);
            app.component('ConfirmDialog', PrimeVue.ConfirmDialog);

            app.component('Select', PrimeVue.Select);
            app.component('Divider', PrimeVue.Divider);
            app.component('OverlayBadge', PrimeVue.OverlayBadge);
            app.component('Badge', PrimeVue.Badge);

            // 🗂️ Data & Tables
            app.component('DataTable', PrimeVue.DataTable);
            app.component('Column', PrimeVue.Column);
            app.component('Toolbar', PrimeVue.Toolbar);
            app.component('FileUpload', PrimeVue.FileUpload);
            app.component('Tag', PrimeVue.Tag);



            // 🎨 Visual Feedback
            app.component('Skeleton', PrimeVue.Skeleton);
            app.component('Image', PrimeVue.Image);
            app.component('ProgressSpinner', PrimeVue.ProgressSpinner);

            // 🧲 External Components
            app.component('draggable', vuedraggable);



            const Orders = {
                inject: ['i18n', 'mobileIcon'],
                data() {

                    // Access the Pinia store
                    const globalStore = useGlobalStore();

                    // Use a computed property to ensure reactivity
                    const mobile = Vue.computed(() => globalStore.isMobile || globalStore.isTablet);

                    const Rtl = Vue.computed(() => globalStore.isRtl);
                    
                    return {
                        mobile: mobile,
                        isRtl: Rtl,

                        orders: [], // initially empty, will fetch from AJAX
                        ordersLoaded: false,
                        ordersPoller: null,
                        ordersFetchPromise: null,
                        layoutSyncHandle: null,
                        panelPersistFrame: null,
                        carrierGroups: initialData.trackingCarriers || {},
                        carrierOtherCode: 'OTHER',

                        // Pagination + filtering logic
                        currentPage: 1,
                        perPage: 15,
                        totalPages: 1,
                        sort: 'desc',           // Default sort order: newest first
                        isLoadingMore: false,
                        allOrdersLoaded: false,
                        lastSavedTimestamps: {}, // 🆕 Track last save time per order ID


                        selectedStatus: { label: 'All', value: 'all', icon: 'pi pi-bars' },


                        statusOptions: [
                            { label: 'All', value: 'all', icon: 'pi pi-list' },
                            { label: 'Pending', value: 'pending', icon: 'pi pi-clock' },
                            { label: 'Partial', value: 'partially-fulfilled', icon: 'pi pi-exclamation-triangle' },
                            { label: 'Ready', value: 'ready-to-fulfill', icon: 'pi pi-send' },
                            { label: 'Fulfilled', value: 'fulfilled', icon: 'pi pi-check-circle' }
                        ],

                        searchDialog: {
                            visible: false,
                            SearchQuery: '',    // temp while typing
                        },
                        
                        searchQuery: '',
                        ordersSearchTimer: null,


                        fulfillmentDialog: {
                            visible: false,
                            order: null,
                        },

                        newTrackingDialog: {
                            visible: false,
                            order: null,
                            tracking_number: '',
                            carrier_code: 'OTHER',
                            carrier_name: ''
                        },

                        moveProductsDialog: {
                            visible: false,
                            order: null,
                            group: null
                        },

                        selectedProductsToMove: [],

                        noteDialog: {
                            visible: false,
                            order: null,
                            product: null,
                            note: ''
                        },

                        orderMenus: {},
                        trackingValidation: {
                            number: false,
                            carrier: false,
                            carrierOther: false
                        },

                        debouncedSaves: {}, // maps order IDs to debounced save functions
                    
                        confirmfulfillmentSubmitted: false,
                        panelCollapsedState: {},
                        // ID of group currently being dragged over, or null if none
                        dragOverGroupId: null,
                        // (optional) general dragging flag if you want
                        isDragging: false,

                        menuItems: [],

                    };
                },
                template: `
                    <div class="p-4">

                        <transition 
                            name="quick-fade" 
                            mode="out-in"
                        >
                            <!-- ⏳ Loading State -->
                            <div v-if="!ordersLoaded" class="space-y-6">
                                <div v-for="i in 8" :key="i" class="order-loader border rounded-xl shadow-sm p-4">
                                    <!-- Header Skeleton (title + buttons) -->
                                    <div class="flex justify-between items-center">
                                        <Skeleton height="1.5rem" width="30%" />
                                        <div class="flex gap-2">
                                            <Skeleton shape="circle" size="2rem" />
                                            <Skeleton shape="circle" size="2rem" />
                                        </div>
                                    </div>
                                </div>
                            </div>


                            <main v-else>
                                <nav class="px-5">
                                    <Menubar :model="menuItems">

                                        <template #start>
                                            <div class="flex items-center gap-4">
                                                <span v-if="!mobile" class="font-semibold text-lg">{{ i18n.orders }}</span>
                                                <FloatLabel class="w-48" variant="on">
                                                    <Select 
                                                        v-model="selectedStatus"
                                                        :options="statusOptions"
                                                        id="statusFilter"
                                                        @change="filterOrders"
                                                        class="w-full"
                                                    >

                                                        <template #value="{ value }">
                                                            <span class="flex items-center gap-2 w-full px-1">
                                                            <i :class="value?.icon || 'pi pi-filter'"></i>
                                                            <span>{{ value?.label || 'Filter Status' }}</span>
                                                            </span>
                                                        </template>


                                                        <template #option="{ option }">
                                                            <span class="flex items-center gap-2">
                                                                <i :class="option.icon"></i>
                                                                <span>{{ option.label }}</span>
                                                            </span>
                                                        </template>

                                                    </Select>

                                                    <label for="statusFilter">{{ i18n.filter_status }}</label>
                                                </FloatLabel>
                                            </div>
                                        </template>

                                        <template #end>
                                            <!-- If on mobile, show the button -->
                                            <template v-if="mobile">
                                                <Button 
                                                    icon="pi pi-search" 
                                                    @click="openSearchDialog"
                                                    severity="contrast"
                                                    variant="text"
                                                    raised
                                                />
                                            </template>

                                            <!-- Otherwise, show the inline search input -->
                                            <template v-else>
                                                <FloatLabel variant="on">
                                                    <IconField>
                                                        <InputIcon class="pi pi-search" />
                                                        <InputText 
                                                            v-model="searchQuery" 
                                                            id="search" 
                                                            class="w-full" 
                                                            @input="debouncedFetchOrders"
                                                        />
                                                    </IconField>
                                                    <label for="search">{{ i18n.search }}</label>
                                                </FloatLabel>
                                            </template>
                                        </template>

                                    </Menubar>

                                </nav>
                                
                                <transition 
                                    name="slide-scale" 
                                    mode="out-in"
                                >

                                    <div 
                                        v-if="filteredOrders.length === 0" 
                                        :key="'no-orders-message'"
                                        class="text-center py-10  space-y-2"
                                    >
                                        <i class="pi pi-folder-open" :style="{ fontSize: '2.5rem' }"></i>

                                            <div class="text-2xl font-semibold">
                                                {{ i18n.no_orders_message_title || 'No orders found yet.' }}
                                            </div>
                                            <div class="text-lg">
                                                {{ i18n.no_orders_message_subtitle || 'Why not grab a coffee and check back later?' }}
                                            </div>
                                    </div>

                                    <VirtualScroller
                                        v-else
                                        ref="ordersScrollPanel"
                                        :items="filteredOrders"
                                        :itemSize="360"
                                        :numToleratedItems="16"
                                        :autoSize="true"
                                        :showSpacer="false"
                                        :resizeDelay="0"
                                        :style="{ height: '80svh' }"
                                        :lazy="false"
                                        :trackBy="'id'"
                                        :pt="{
                                            content: {
                                                class: 'flex flex-col gap-y-6 p-6',
                                            }
                                        }"

                                        :class="[
                                            'flex-1 p-4 mt-2',
                                            'scrollbar-hidden optimize-scroll'

                                        ]"
                                    >
                                        <template v-slot:item="{ item: order, index, options }">

                                            <transition
                                                :name="isRtl ? '__hc_sp_portal_slide-rtl' : '__hc_sp_portal_slide-ltr'"
                                                tag="div"
                                            >

                                            <Panel
                                                :key="order.id || ('order-' + index)"
                                                :header="i18n.orders + ' #' + order.id"
                                                toggleable
                                                v-model:collapsed="panelCollapsedState[order.id]"
                                            >

                                                <template #header>
                                                    <div class="flex justify-between items-center w-full">
                                                        <div class="flex flex-col">
                                                            <span class="text-base font-semibold ">
                                                                {{ i18n.order_number }}{{ order.id }}
                                                            </span>
                                                            <span class="text-xs ">{{ order.date_created }}</span>
                                                        </div>

                                                        <div class="flex items-center gap-2">
                                                            <div class="bg-green-300 text-green-900 inline-flex items-center gap-x-2 px-2.5 py-1 rounded-full text-xs font-semibold shadow-sm transition-all mx-1" v-if="order && (order.supplier_total || order.supplier_total === 0)">
                                                                <span>{{ order.supplier_total_formatted || ((order.currency_symbol || '') + Number(order.supplier_total || 0).toFixed(2)) }}</span>
                                                            </div>
                                                            <transition name="fade-slide-badge" mode="out-in">
                                                                <span
                                                                    :key="order.supplier_status"
                                                                    class="inline-flex items-center gap-x-2 px-2.5 py-1 rounded-full text-xs font-semibold shadow-sm transition-all mx-1"
                                                                    :class="{
                                                                        'bg-green-100 text-green-700': order.supplier_status === 'fulfilled',
                                                                        'bg-blue-200 text-blue-700': order.supplier_status === 'ready-to-fulfill',
                                                                        'bg-yellow-100 text-yellow-700': order.supplier_status === 'partially-fulfilled',
                                                                        'bg-gray-200 text-gray-600': order.supplier_status === 'pending',
                                                                    }"
                                                                >
                                                                    <i
                                                                        :class="{
                                                                            'pi pi-check-circle': order.supplier_status === 'fulfilled',
                                                                            'pi pi-send': order.supplier_status === 'ready-to-fulfill',
                                                                            'pi pi-exclamation-triangle': order.supplier_status === 'partially-fulfilled',
                                                                            'pi pi-clock': order.supplier_status === 'pending'
                                                                        }"
                                                                        class="text-sm"
                                                                    />

                                                                    <span v-if="!mobile">
                                                                        {{ order.supplier_status.replace(/-/g, ' ').toUpperCase() }}
                                                                    </span>
                                                                </span>
                                                            </transition>

                                                            <!-- Fulfill Button -->
                                                            <transition name="fade-scale" appear>
                                                                <div
                                                                    v-if="order.supplier_status === 'ready-to-fulfill'"
                                                                    class="inline-block"
                                                                >
                                                                    <Button
                                                                        icon="pi pi-truck"
                                                                        v-tooltip.top="i18n.mark_as_fulfilled"
                                                                        @click.stop="openFulfillmentModal(order)"
                                                                        severity="success"
                                                                        variant="text"
                                                                        rounded
                                                                    />
                                                                </div>
                                                            </transition>

                                                            <!-- ⚙️ Cog Menu Per Order -->
                                                            <Menu
                                                                :ref="el => orderMenus[order.id] = el"
                                                                :model="getOrderActions(order)"
                                                                :popup="true"
                                                                appendTo="body"
                                                            />

                                                            <transition name="fade-scale" appear>
                                                                <!-- Render only if there are menu actions -->
                                                                <Button
                                                                    v-if="getOrderActions(order).length"
                                                                    icon="pi pi-briefcase"
                                                                    v-tooltip.bottom="'Actions'"
                                                                    @click="orderMenus[order.id]?.toggle($event)"
                                                                    severity="secondary"
                                                                    variant="text"
                                                                    rounded
                                                                />
                                                            </transition>
                                                        </div>
                                                    </div>
                                                </template>

                                                <Card class="-50 shadow-sm border rounded-xl overflow-hidden my-4">
                                                    <template #title>
                                                        <div class="flex items-center gap-2  text-lg font-semibold">
                                                            <i class="pi pi-user"></i>
                                                            <span>{{ i18n.customer_info }}</span>
                                                        </div>
                                                    </template>

                                                    <template #content>
                                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm ">
                                                        
                                                            <div class="flex items-center gap-2">
                                                                <i class="pi pi-id-card "></i>
                                                                <span class="font-medium">{{ i18n.name }}:</span>
                                                                <span>{{ resolveCustomerName(order) || i18n.n_a }}</span>
                                                            </div>

                                                            <div v-if="order.customer.shipping_company" class="flex items-center gap-2">
                                                                <i class="pi pi-building "></i>
                                                                <span class="font-medium">{{ i18n.company }}:</span>
                                                                <span>{{ order.customer.shipping_company }}</span>
                                                            </div>

                                                            <div class="flex items-center gap-2">
                                                                <i class="pi pi-envelope "></i>
                                                                <span class="font-medium">{{ i18n.email }}:</span>
                                                                <span>{{ resolveCustomerEmail(order) || i18n.n_a }}</span>
                                                            </div>

                                                            <div class="flex items-center gap-2">
                                                                <i class="pi pi-phone "></i>
                                                                <span class="font-medium">{{ i18n.phone }}:</span>
                                                                <span>{{ resolveCustomerPhone(order) || i18n.n_a }}</span>
                                                            </div>

                                                            <div class="flex flex-col gap-1">
                                                                <div class="flex items-start gap-2 flex-wrap">
                                                                    <i class="pi pi-truck "></i>
                                                                    <span class="font-medium">{{ resolveCustomerAddress(order).label }}:</span>
                                                                    <Badge
                                                                        v-if="resolveCustomerAddress(order).isFallback"
                                                                        :value="i18n.shipping_address_fallback_badge"
                                                                        severity="contrast"
                                                                        size="small"
                                                                    />
                                                                    <span class="break-words">
                                                                        {{ resolveCustomerAddress(order).value || i18n.n_a }}
                                                                    </span>
                                                                </div>

                                                                <span
                                                                    v-if="resolveCustomerAddress(order).isFallback"
                                                                    class="text-xs text-slate-500"
                                                                >
                                                                    {{ i18n.shipping_address_fallback_note }}
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </template>
                                                </Card>

                                                <!-- All groups: ungrouped + tracking, inside ONE transition-group -->
                                                <transition-group
                                                    tag="div"
                                                    class="space-y-4"
                                                    :name="isRtl ? '__hc_sp_portal_slide-rtl' : '__hc_sp_portal_slide-ltr'"
                                                >

                                                    <!-- Ungrouped Block -->
                                                    <div 
                                                        :key="'ungrouped-' + order.id"
                                                        v-show="order.ungroupedProducts.length > 0"
                                                        :class="[
                                                            'tracking-group rounded-xl p-6 shadow-md',
                                                            { 'fade-in-scale': order.ungroupedProducts.length > 0 }
                                                        ]"
                                                    >

                                                        <h3 class="text-lg gap-x-2 font-semibold  mb-4 flex items-center">
                                                            <i class="pi pi-box text-indigo-600"></i> 
                                                            <span>{{ i18n.ungrouped_products }}</span>
                                                        </h3>
                                                        
                                                        <draggable
                                                            :disabled="mobile || order.supplier_status === 'fulfilled'"
                                                            v-model="order.ungroupedProducts" 
                                                            v-bind="dragOptions"
                                                            itemKey="product_id"
                                                            class="space-y-5"
                                                            :group="{ name: 'ungrouped-products-' + order.id, pull: true, put: true }"
                                                            :sort="false"
                                                            :move="(e) => {
                                                                // console.log('from:', e.from, 'to:', e.to);
                                                                return e.from !== e.to;
                                                            }"
                                                            ghost-class="sortable-ghost"
                                                            chosen-class="sortable-chosen"
                                                            @start="isDragging = true"
                                                            @end="isDragging = false"
                                                            @dragenter="() => { dragOverGroupId = 'ungrouped' }"
                                                            @dragleave="() => { if (dragOverGroupId === 'ungrouped') dragOverGroupId = null }"
                                                        >
                                                            <template #item="{ element: product }">
                                                                <div
                                                                    :key="product.product_id"
                                                                    class="flex flex-col sm:flex-row items-start sm:items-center product-card shadow-md cursor-grab"
                                                                >
                                                                    <!-- Product Image -->
                                                                    <Image
                                                                        :src="product.thumbnail"
                                                                        alt="product image"
                                                                        preview
                                                                        class="flex-shrink-0 mx-auto sm:mx-0"
                                                                        :pt="{
                                                                            root: { class: 'rounded-lg overflow-hidden' },
                                                                            image: { class: 'w-20 h-20 object-cover' }
                                                                        }"
                                                                        loading="lazy"                                                     
                                                                    />

                                                                    <!-- Product Details -->
                                                                    <div class="flex-1 w-full text-sm sm:text-base">
                                                                        <h3 class="text-base sm:text-lg font-semibold  mb-2">
                                                                            {{ product.product_name || '[No Name]' }}
                                                                        </h3>
                                                                        <p v-if="product.variation" class="text-xs text-slate-500 mb-1">
                                                                            {{ product.variation }}
                                                                        </p>

                                                                        <p v-if="product.sku" class=" mb-1">
                                                                            <span class="font-semibold">{{ i18n.sku }}:</span>
                                                                            {{ product.sku || i18n.n_a }}
                                                                        </p>

                                                                        <p v-if="product.qty" class=" mb-1">
                                                                            <span class="font-semibold">{{ i18n.quantity }}:</span>
                                                                            {{ product.qty || i18n.n_a }}
                                                                        </p>

                                                                        <!-- Note Section -->
                                                                        <div class="mt-3">
                                                                            <div
                                                                                v-if="product.note"
                                                                                class="flex flex-col sm:flex-row items-center justify-between gap-2 bg-blue-50 border border-blue-200 p-2 rounded-lg text-blue-700 text-sm" 
                                                                            >
                                                                                <!-- Note Text -->
                                                                                <div class="flex items-center gap-2">
                                                                                    <i class="pi pi-info-circle"></i>
                                                                                    <span>{{ product.note }}</span>
                                                                                </div>

                                                                                <!-- Actions -->
                                                                                <div class="flex items-center">
                                                                                    <Button
                                                                                        icon="pi pi-pencil"
                                                                                        @click="openNoteEditor(order, product)"
                                                                                        v-tooltip.bottom="i18n.edit_note"
                                                                                        severity="info"
                                                                                        variant="text"
                                                                                        size="small"
                                                                                        rounded
                                                                                    />
                                                                                    <Button
                                                                                        icon="pi pi-trash"
                                                                                        @click="deleteNote(order, product)"
                                                                                        v-tooltip.bottom="i18n.delete_note"
                                                                                        severity="danger"
                                                                                        variant="text"
                                                                                        size="small"
                                                                                        rounded
                                                                                    />
                                                                                </div>
                                                                            </div>

                                                                            <!-- Add Note Button if no note -->
                                                                            <div v-else>
                                                                                <Button
                                                                                    icon="pi pi-pencil"
                                                                                    :label="i18n.add_note"
                                                                                    @click="openNoteEditor(order, product)"
                                                                                    v-tooltip.bottom="i18n.add_note"
                                                                                    severity="success"
                                                                                    variant="outlined"
                                                                                    size="small"
                                                                                    rounded
                                                                                />
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </template>
                                                        </draggable>
                                                    </div>

                                                    <!-- Tracking Groups -->
                                                    <div
                                                        v-for="(group, index) in order.trackingGroups"
                                                        :key="group.id"
                                                        :id="group.id"
                                                        class="tracking-group rounded-xl p-6 shadow-md"
                                                    >
                                                        <div class="flex justify-between items-center mb-4">
                                                            <div>
                                                                <h3 class="text-lg gap-x-2 font-semibold  mb-2 flex items-center">
                                                                    <i class="pi pi-barcode text-green-600"></i> 
                                                                    <span>{{ i18n.tracking_number }}: {{ group.tracking_number }}</span>
                                                                </h3>
                                                                <p class="text-xs text-slate-400 flex items-center gap-2" v-if="formatCarrierDisplay(group)">
                                                                    <i class="pi pi-truck"></i>
                                                                    <span>{{ formatCarrierDisplay(group) }}</span>
                                                                </p>
                                                            </div>
                                                            <div>
                                                                <Button
                                                                    v-if="mobile && hasProductsToMove(order, group) && order.supplier_status !== 'fulfilled'"
                                                                    icon="pi pi-plus"
                                                                    @click="openMoveProductsModal(order, group)"
                                                                    v-tooltip.top="i18n.add_products_to_group"
                                                                    severity="success"
                                                                    variant="text"
                                                                    rounded
                                                                />
                                                                    
                                                                <Button
                                                                    v-if="order.supplier_status !== 'fulfilled'"
                                                                    icon="pi pi-times"
                                                                    @click="removeTrackingGroup(order, index)"
                                                                    v-tooltip.top="i18n.remove_group_tooltip"
                                                                    severity="danger"
                                                                    variant="text"
                                                                    rounded
                                                                />
                                                            </div>
                                                        </div>

                                                        <div
                                                            class="relative min-h-[130px]"
                                                        >
                                                            <div
                                                                v-if="group.products.length === 0"
                                                                @dragenter="() => { dragOverGroupId = group.id; }"
                                                                @dragleave="() => { if (dragOverGroupId === group.id) dragOverGroupId = null; }"
                                                                @dragover.prevent
                                                                @drop.prevent
                                                                :class="[
                                                                    'absolute inset-0 flex flex-col items-center justify-center rounded-xl z-10 transition-all duration-300 pointer-events-auto select-none',
                                                                    'text-blue-700 font-medium gap-2 px-6 py-4 text-center',
                                                                    {
                                                                        'dragging-over': dragOverGroupId === group.id,
                                                                        'is-dragging': isDragging,
                                                                        'default-border': !(dragOverGroupId === group.id || isDragging)
                                                                    }
                                                                ]"

                                                            >
                                                                <i class="pi pi-arrow-right-arrow-left text-xl" />

                                                                <span
                                                                    role="button"
                                                                    tabindex="0"
                                                                    @click="openMoveProductsModal(order, group)"
                                                                    @keydown.enter.space.prevent="mobile && openMoveProductsModal(order, group)"
                                                                    class="cursor-pointer text-blue-700 hover:text-blue-900 transition"
                                                                >
                                                                    {{ mobile ? i18n.tap_here_to_move_products : i18n.tap_or_drag_products_here }}
                                                                </span>

                                                            </div>

                                                            <draggable
                                                                :disabled="mobile || order.supplier_status === 'fulfilled'"
                                                                v-if="group && group.products"
                                                                v-model="group.products"
                                                                v-bind="dragOptions"
                                                                itemKey="product_id"
                                                                :class="['group-products flex flex-col gap-y-4', { 'dragging-active': isDragging }]"
                                                                ghost-class="sortable-ghost"
                                                                chosen-class="sortable-chosen"
                                                                :item-key="'product_id'"
                                                                :group="{ name: 'group-products-'+ group.id + order.id, pull: true, put: true }"
                                                                :sort="false"
                                                                :move="(e) => {
                                                                    // console.log('from:', e.from, 'to:', e.to);
                                                                    return e.from !== e.to;
                                                                }"
                                                                @start="isDragging = true"
                                                                @end="isDragging = false"
                                                                @change="handleGroupChange(order, $event)"
                                                                @dragenter="() => { dragOverGroupId = group.id; }"
                                                                @dragleave="() => { if (dragOverGroupId === group.id) dragOverGroupId = null; }"
                                                            >
                                                                <template #item="{ element: product }">
                                                                    <div
                                                                        :key="product.product_id"
                                                                        class="flex flex-col sm:flex-row items-start sm:items-center product-card shadow-md cursor-grab"
                                                                    >
                                                                        <!-- Product Image -->
                                                                        <Image
                                                                            :src="product.thumbnail"
                                                                            alt="product image"
                                                                            preview
                                                                            class="flex-shrink-0 mx-auto sm:mx-0"
                                                                            :pt="{
                                                                                root: { class: 'rounded-lg overflow-hidden' },
                                                                                image: { class: 'w-20 h-20 object-cover' }
                                                                            }"
                                                                            loading="lazy"                                                   
                                                                        />

                                                                        <!-- Product Details -->
                                                                        <div class="flex-1 w-full text-sm sm:text-base">
                                                                            <h3 class="text-base sm:text-lg font-semibold  mb-2">
                                                                                {{ product.product_name || '[No Name]' }}
                                                                            </h3>
                                                                            <p v-if="product.variation" class="text-xs text-slate-500 mb-1">
                                                                                {{ product.variation }}
                                                                            </p>

                                                                            <p v-if="product.sku" class=" mb-1">
                                                                                <span class="font-semibold">{{ i18n.sku }}:</span>
                                                                                {{ product.sku || i18n.n_a }}
                                                                            </p>

                                                                            <p v-if="product.qty" class=" mb-1">
                                                                                <span class="font-semibold">{{ i18n.quantity }}:</span>
                                                                                {{ product.qty || i18n.n_a }}
                                                                            </p>

                                                                            <!-- Note Section -->
                                                                            <div class="mt-3">
                                                                                <div
                                                                                    v-if="product.note"
                                                                                    class="flex flex-col sm:flex-row items-center justify-between gap-2 bg-blue-50 border border-blue-200 p-2 rounded-lg text-blue-700 text-sm" 
                                                                                >
                                                                                    <!-- Note Text -->
                                                                                    <div class="flex items-center gap-2">
                                                                                        <i class="pi pi-info-circle"></i>
                                                                                        <span>{{ product.note }}</span>
                                                                                    </div>

                                                                                    <!-- Actions -->
                                                                                    <div class="flex items-center">
                                                                                        <Button
                                                                                            v-if="order.supplier_status !== 'fulfilled'"
                                                                                            icon="pi pi-pencil"
                                                                                            @click="openNoteEditor(order, product)"
                                                                                            v-tooltip.bottom="i18n.edit_note"
                                                                                            severity="info"
                                                                                            variant="text"
                                                                                            size="small"
                                                                                            rounded
                                                                                            
                                                                                        />
                                                                                        <Button
                                                                                            v-if="order.supplier_status !== 'fulfilled'"
                                                                                            icon="pi pi-trash"
                                                                                            @click="deleteNote(order, product)"
                                                                                            v-tooltip.bottom="i18n.delete_note"
                                                                                            severity="danger"
                                                                                            variant="text"
                                                                                            size="small"
                                                                                            rounded
                                                                                        />
                                                                                    </div>
                                                                                </div>

                                                                                <!-- Add Note Button if no note -->
                                                                                <div v-else>
                                                                                    <Button
                                                                                        v-if="order.supplier_status !== 'fulfilled'"
                                                                                        icon="pi pi-pencil"
                                                                                        :label="i18n.add_note"
                                                                                        @click="openNoteEditor(order, product)"
                                                                                        v-tooltip.bottom="i18n.add_note"
                                                                                        severity="success"
                                                                                        variant="outlined"
                                                                                        size="small"
                                                                                        rounded
                                                                                    />
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </template>
                                                            </draggable>
                                                        </div>
                                                    </div>
                                                </transition-group>
                                            </Panel>
                                        </transition>
                                        </template>
                                    </VirtualScroller>

                                    
                                </transition>
                            </main>
                        </transition>


                        <Dialog 
                            v-model:visible="fulfillmentDialog.visible"
                            :header="i18n.confirm_fulfillment"
                            :modal="true"
                            :style="{ width: '700px', maxWidth: '90vw' }"
                        >
                            <template #default>
                                <div class="space-y-4 text-center">
                                    <p class="text-lg font-medium ">
                                        {{ i18n.confirm_fulfillment_text }}
                                    </p>
                                    <p class="text-xl font-bold ">Order #{{ fulfillmentDialog.order?.id }}</p>

                                    <p class="text-sm ">
                                        {{ i18n.fulfillment_info_text }}
                                    </p>
                                </div>
                            </template>

                            <template #footer>
                                <Button 
                                    :label="i18n.cancel" 
                                    :disabled="confirmfulfillmentSubmitted" 
                                    @click="fulfillmentDialog.visible = false"
                                    severity="secondary"
                                    raised
                                />
                                <Button 
                                    :label="i18n.confirm_submit"
                                    icon="pi pi-check" 
                                    :loading="confirmfulfillmentSubmitted" 
                                    :disabled="confirmfulfillmentSubmitted"
                                    @click="confirmSupplierFulfillment"
                                    severity="contrast"
                                    raised
                                />
                            </template>
                        </Dialog>

                        <Dialog 
                            v-model:visible="newTrackingDialog.visible"
                            :header="i18n.add_tracking_number"
                            :modal="true"
                            :closable="true"
                            :style="{ width: '500px' }"
                        >
                            <template #default>
                                <div class="flex-1 pt-2">
                                    <FloatLabel variant="on">
                                        <IconField>
                                            <InputIcon class="pi pi-barcode" />
                                            <InputText
                                                id="tracking"
                                                v-model="newTrackingDialog.tracking_number"
                                                class="w-full"
                                                :class="{'p-invalid': trackingValidation.number}"
                                            />
                                        </IconField>
                                        <label for="tracking">Tracking Number</label>
                                    </FloatLabel>
                                    <small v-if="trackingValidation.number" class="p-error block mt-1">
                                        {{ i18n.missing_tracking_number_detail }}
                                    </small>
                                    <FloatLabel variant="on" class="mt-5 block">
                                        <Select
                                            v-model="newTrackingDialog.carrier_code"
                                            :options="carrierOptionGroups"
                                            optionGroupLabel="label"
                                            optionGroupChildren="items"
                                            optionLabel="label"
                                            optionValue="value"
                                            class="w-full"
                                            :class="{'p-invalid': trackingValidation.carrier}"
                                            inputId="trackingCarrier"
                                            filter
                                            :filterFields="['label']"
                                            :placeholder="i18n.carrier_placeholder"
                                            :virtualScrollerOptions="{ itemSize: 42 }"
                                        >
                                            <template #value="slotProps">
                                                <span v-if="slotProps.value" class="flex items-center gap-2">
                                                    <i class="pi pi-truck text-slate-400 text-xs"></i>
                                                    <span>{{ carrierLabel(slotProps.value) || slotProps.value }}</span>
                                                </span>
                                                <span v-else class="flex items-center gap-2 text-slate-400">
                                                    <i class="pi pi-truck text-xs"></i>
                                                    <span>{{ i18n.carrier_placeholder }}</span>
                                                </span>
                                            </template>
                                            <template #optiongroup="slotProps">
                                                <div class="text-xs uppercase tracking-wide text-slate-400 font-semibold px-1 py-1">
                                                    {{ slotProps.option.label }}
                                                </div>
                                            </template>
                                        </Select>
                                        <label for="trackingCarrier">{{ i18n.carrier }}</label>
                                    </FloatLabel>
                                    <small v-if="trackingValidation.carrier" class="p-error block mt-1">
                                        {{ i18n.carrier_required }}
                                    </small>
                                    <FloatLabel
                                        variant="on"
                                        class="mt-4 block"
                                        v-if="newTrackingDialog.carrier_code === carrierOtherCode"
                                    >
                                        <InputText
                                            v-model="newTrackingDialog.carrier_name"
                                            class="w-full"
                                            :class="{'p-invalid': trackingValidation.carrierOther}"
                                            id="carrierOtherInput"
                                        />
                                        <label for="carrierOtherInput">{{ i18n.carrier_other }}</label>
                                    </FloatLabel>
                                    <small v-if="newTrackingDialog.carrier_code === carrierOtherCode && trackingValidation.carrierOther" class="p-error block mt-1">
                                        {{ i18n.carrier_other_required }}
                                    </small>
                                </div>
                            </template>

                            <template #footer>
                                <Button 
                                    :label="i18n.cancel" 
                                    @click="newTrackingDialog.visible = false"
                                    severity="secondary"
                                    raised
                                />
                                <Button 
                                    :label="i18n.add_tracking_number"
                                    icon="pi pi-plus" 
                                    @click="confirmAddTrackingGroup"
                                    severity="contrast"
                                    raised
                                />
                            </template>
                        </Dialog>

                        <Dialog
                            v-model:visible="noteDialog.visible"
                            :header="noteDialog.product?.note ? i18n.edit_note : i18n.add_note"
                            :modal="true"
                            :style="{ width: '500px' }"
                        >
                        <template #default>
                            <div class="flex-1 pt-2 relative">
                                <FloatLabel variant="on">
                                    <!-- Just the Textarea -->
                                    <Textarea
                                        id="note"
                                        v-model="noteDialog.note"
                                        autoResize
                                        rows="4"
                                        class="w-full pl-10"
                                    />
                                    <label for="note">{{ i18n.note }}</label>
                                </FloatLabel>
                            </div>
                        </template>

                            <template #footer>
                                <Button
                                    :label="i18n.cancel"
                                    @click="noteDialog.visible = false"
                                    severity="secondary"
                                    raised
                                />
                                <Button
                                    :label="i18n.save"
                                    icon="pi pi-check"
                                    @click="saveProductNote"
                                    severity="contrast"
                                    raised
                                />
                            </template>
                        </Dialog>

                        <Dialog
                            v-model:visible="moveProductsDialog.visible"
                            :header="i18n.move_products_to_tracking_group"
                            modal
                            :style="{ width: '500px', maxHeight: '80vh' }"
                        >
                            <template #default>
                                <div class="space-y-4">
                                    <p class="">
                                        {{ i18n.select_products_to_move }} 
                                        <strong class="text-primary-600">
                                            {{ i18n.tracking_number }} #{{ moveProductsDialog.group.tracking_number }}
                                        </strong>
                                    </p>

                                    <ScrollPanel style="max-height: 400px">
                                        <div class="space-y-3 py-2 px-4">
                                            <Card
                                                v-for="product in allProductsToMove"
                                                :key="product.product_id + '-' + (product.tracking_number || 'ungrouped')"
                                                class="-50 shadow-sm border rounded-xl overflow-hidden my-4">

                                            >
                                                <template #content>
                                                    <div class="flex items-center gap-3">

                                                        <!-- Checkbox -->
                                                        <Checkbox
                                                            variant="filled"
                                                            size="normal"
                                                            v-model="selectedProductsToMove"
                                                            :value="product"
                                                            @click.stop
                                                        />

                                                        <!-- Image -->
                                                        <Image
                                                            :src="product.thumbnail"
                                                            alt="product image"
                                                            preview
                                                            class="flex-shrink-0 mx-auto sm:mx-0"
                                                            :pt="{
                                                                root: { class: 'rounded-lg overflow-hidden' },
                                                                image: { class: 'w-12 h-12 object-cover' }
                                                            }"
                                                        />

                                                        <!-- Product Details -->
                                                        <div class="flex-1">
                                                            <div class="font-medium ">
                                                                {{ product.product_name }}
                                                            </div>
                                                            <div
                                                                v-if="product.tracking_number"
                                                                class="text-xs "
                                                            >
                                                                {{ i18n.currently_in_tracking }}{{ product.tracking_number }}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </template>
                                            </Card>
                                        </div>
                                    </ScrollPanel>
                                </div>
                            </template>

                            <template #footer>
                                <Button
                                    :label="i18n.cancel" 
                                    @click="moveProductsDialog.visible = false"
                                    severity="secondary"
                                    raised
                                />
                                <Button
                                    :label="i18n.move" 
                                    icon="pi pi-check"
                                    @click="confirmMoveProducts"
                                    :disabled="selectedProductsToMove.length === 0"
                                    severity="contrast"
                                    raised
                                />
                            </template>
                        </Dialog>

                        <Dialog 
                            v-model:visible="searchDialog.visible" 
                            modal 
                            dismissableMask 
                            :header="i18n.search" 
                            class="w-full"
                            :style="{ width: '400px' }"
                        >
                            <FloatLabel variant="on">
                                <IconField class=" my-2">
                                    <InputIcon class="pi pi-search" />
                                    <InputText 
                                        v-model="searchDialog.SearchQuery" 
                                        id="mobileSearch" 
                                        class="w-full" 
                                        autofocus 
                                    />
                                </IconField>
                                <label for="mobileSearch">{{ i18n.search }}</label>
                            </FloatLabel>

                            <template #footer>
                                <div class="flex justify-end gap-2">
                                    <Button 
                                        :label="i18n.cancel" 
                                        @click="cancelSearch"
                                        severity="secondary" 
                                        size="small"
                                        raised
                                    />
                                    <Button 
                                        :label="i18n.apply" 
                                        icon="pi pi-check" 
                                        @click="applySearch"
                                        severity="contrast"
                                        size="small"
                                        raised
                                    />
                                </div>
                            </template>
                        </Dialog>
                    </div>
                `,
                computed: {
                    filteredOrders() {
                        // Server-side search now; return orders as-is
                        return this.orders;
                    },
                    carrierLabelMap() {
                        const map = {};
                        const groups = this.carrierGroups || {};
                        Object.entries(groups).forEach(([key, group]) => {
                            const groupName = group?.name || key;
                            Object.entries(group?.items || {}).forEach(([code, label]) => {
                                map[code] = {
                                    label,
                                    group: groupName
                                };
                            });
                        });
                        return map;
                    },
                    carrierOptionGroups() {
                        const output = [];
                        const groups = this.carrierGroups || {};

                        Object.entries(groups).forEach(([key, group]) => {
                            const items = Object.entries(group?.items || {}).map(([code, label]) => ({
                                label,
                                value: code,
                                group: group?.name || key
                            }));

                            if (items.length) {
                                output.push({
                                    key,
                                    label: group?.name || key,
                                    items
                                });
                            }
                        });

                        if (
                            !output.some(group =>
                                group.items.some(item => item.value === this.carrierOtherCode)
                            )
                        ) {
                            output.push({
                                key: 'other',
                                label: this.i18n.carrier_other || 'Other',
                                items: [
                                    {
                                        label: this.i18n.carrier_other || 'Other',
                                        value: this.carrierOtherCode,
                                        group: this.i18n.carrier_other || 'Other'
                                    }
                                ]
                            });
                        }

                        return output;
                    },

                    allProductsToMove() {
                        if (!this.moveProductsDialog.order) return [];
                        const ungrouped = this.moveProductsDialog.order.ungroupedProducts.map(p => ({
                            ...p,
                            tracking_number: null
                        }));
                        const grouped = this.moveProductsDialog.order.trackingGroups
                            .filter(g => g.tracking_number !== this.moveProductsDialog.group.tracking_number)
                            .flatMap(g =>
                                g.products.map(p => ({
                                    ...p,
                                    tracking_number: g.tracking_number
                                }))
                            );
                        return [...ungrouped, ...grouped];
                    },

                    dragOptions() {
                        return {
                                scroll: true, // Enable the plugin. Can be HTMLElement.
                                forceAutoScrollFallback: true, // force autoscroll plugin to enable even when native browser autoscroll is available
                                scrollSensitivity: 30, // px, how near the mouse must be to an edge to start scrolling.
                                scrollSpeed: 20, // px, speed of the scrolling
                                bubbleScroll: true // apply autoscroll to all parent elements, allowing for easier movement
                        };  
                    },
                    getGroupOptions() {
                        // Return a function so you can use it with arguments
                        return (groupId) => {
                            return {
                                name: 'group-' + groupId,
                                pull: true,
                                put: true
                            };
                        };
                    }
                },
                methods: {
                    carrierLabel(code) {
                        if (!code) {
                            return '';
                        }
                        const entry = this.carrierLabelMap[code];
                        return entry ? entry.label : '';
                    },
                    formatCarrierDisplay(group) {
                        if (!group || !group.carrier_code) {
                            return '';
                        }
                        if (group.carrier_code === this.carrierOtherCode) {
                            return group.carrier_name || this.i18n.carrier_other;
                        }
                        const entry = this.carrierLabelMap[group.carrier_code];
                        if (entry) {
                            return entry.group ? `${entry.group} • ${entry.label}` : entry.label;
                        }
                        return group.carrier_name || group.carrier_code;
                    },
                    addressHasData(address = {}) {
                        const keys = ['address_1', 'address_2', 'city', 'state', 'postcode', 'country'];
                        return keys.some((key) => {
                            const value = address[key];
                            return value !== undefined && value !== null && String(value).trim() !== '';
                        });
                    },
                    formatAddressString(address = {}) {
                        const parts = [];
                        const street = [address.address_1, address.address_2]
                            .map(part => (part ?? '').toString().trim())
                            .filter(Boolean)
                            .join(', ');
                        if (street) {
                            parts.push(street);
                        }

                        const localityPieces = [];
                        const cityState = [address.city, address.state]
                            .map(part => (part ?? '').toString().trim())
                            .filter(Boolean)
                            .join(', ');
                        if (cityState) {
                            localityPieces.push(cityState);
                        }
                        if (address.postcode) {
                            localityPieces.push(String(address.postcode).trim());
                        }
                        if (localityPieces.length) {
                            parts.push(localityPieces.join(' '));
                        }

                        if (address.country) {
                            parts.push(String(address.country).trim());
                        }

                        return parts.filter(Boolean).join(', ');
                    },
                    resolveCustomerAddress(order) {
                        const customer = order?.customer || {};
                        const shipping = {
                            address_1: customer.shipping_address_1,
                            address_2: customer.shipping_address_2,
                            city: customer.shipping_city,
                            state: customer.shipping_state,
                            postcode: customer.shipping_postcode,
                            country: customer.shipping_country
                        };
                        const billing = {
                            address_1: customer.address_1,
                            address_2: customer.address_2,
                            city: customer.city,
                            state: customer.state,
                            postcode: customer.postcode,
                            country: customer.country
                        };

                        const hasShipping = this.addressHasData(shipping);
                        const hasBilling = this.addressHasData(billing);
                        if (!hasShipping && !hasBilling) {
                            return {
                                label: this.i18n.shipping_address,
                                value: '',
                                isFallback: false
                            };
                        }

                        const preferred = hasShipping ? shipping : billing;

                        return {
                            label: hasShipping ? this.i18n.shipping_address : (this.i18n.billing_address || 'Billing Address'),
                            value: this.formatAddressString(preferred),
                            isFallback: !hasShipping && hasBilling
                        };
                    },
                    resolveCustomerName(order) {
                        const customer = order?.customer || {};
                        const buildName = (first, last) => {
                            return [first, last]
                                .map(part => (part ?? '').toString().trim())
                                .filter(Boolean)
                                .join(' ')
                                .trim();
                        };

                        const shippingName = buildName(customer.shipping_first_name, customer.shipping_last_name);
                        if (shippingName) {
                            return shippingName;
                        }

                        return buildName(customer.first_name, customer.last_name);
                    },
                    resolveCustomerEmail(order) {
                        const customer = order?.customer || {};
                        const shippingEmail = (customer.shipping_email ?? '').toString().trim();
                        if (shippingEmail) {
                            return shippingEmail;
                        }
                        return (customer.email ?? '').toString().trim();
                    },
                    resolveCustomerPhone(order) {
                        const customer = order?.customer || {};
                        const shippingPhone = (customer.shipping_phone ?? '').toString().trim();
                        if (shippingPhone) {
                            return shippingPhone;
                        }
                        return (customer.phone ?? '').toString().trim();
                    },
                    filterOrders() {
                        // When user selects a new status, reset pagination and fetch orders with the selected status
                        this.ordersLoaded = false;  // Show loading state while fetching
                        this.fetchSupplierOrders(true);
                    },

                    openSearchDialog() {
                        this.searchDialog.SearchQuery = this.searchQuery;  // prefill with current query
                        this.searchDialog.visible = true;
                    },
                    applySearch() {
                        this.searchQuery = this.searchDialog.SearchQuery;
                        this.searchDialog.visible = false;
                        this.debouncedFetchOrders();
                    },
                    onOrdersSearchInput() {
                        this.searchQuery = this.searchDialog.SearchQuery;
                        this.debouncedFetchOrders();
                    },
                    cancelSearch() {
                        this.searchDialog.visible = false;
                    },
                    debouncedFetchOrders() {
                        if (this.ordersSearchTimer) {
                            clearTimeout(this.ordersSearchTimer);
                        }
                        this.ordersSearchTimer = setTimeout(() => {
                            // show loading and reset pagination before fetching
                            this.ordersLoaded = false;
                            this.currentPage = 1;
                            this.fetchSupplierOrders(true);
                        }, 600);
                    },
                    
                    // ========================================
                    // 📦 PRODUCT & GROUP MANAGEMENT
                    // ========================================

                    getOrderActions(order) {
                        const actions = [];

                        if (order.supplier_status !== 'fulfilled') {
                            actions.push({
                                label: this.i18n.add_tracking_number,
                                icon: 'pi pi-barcode',
                                command: () => this.addTrackingGroup(order)
                            });
                        }

                        return actions;
                    },

                    /**
                     * Opens the dialog to create a new tracking group for an order.
                     */
                    addTrackingGroup(order) {
                        this.newTrackingDialog.visible = true;
                        this.newTrackingDialog.order = order;
                        this.newTrackingDialog.tracking_number = '';
                        this.newTrackingDialog.carrier_code = this.carrierOtherCode;
                        this.newTrackingDialog.carrier_name = '';
                    },

                    /**
                     * Validates and confirms the creation of a new tracking group.
                     * Adds the group and scrolls to it.
                     */
                    confirmAddTrackingGroup() {
                        const tracking = this.newTrackingDialog.tracking_number?.trim();
                        const order = this.newTrackingDialog.order;
                        const carrierCode = this.newTrackingDialog.carrier_code || '';
                        const carrierName = this.newTrackingDialog.carrier_name?.trim() || '';

                        this.trackingValidation.number = false;
                        this.trackingValidation.carrier = false;
                        this.trackingValidation.carrierOther = false;

                        if (!tracking) {
                            this.trackingValidation.number = true;
                            this.$toast.warning(this.i18n.missing_tracking_number_title, {
                                description: this.i18n.missing_tracking_number_detail,
                                duration: 3000
                            })

                            return;
                        }

                        if (!carrierCode) {
                            this.trackingValidation.carrier = true;
                            this.$toast.warning(this.i18n.carrier_required, {
                                duration: 3000
                            });
                            return;
                        }

                        if (carrierCode === this.carrierOtherCode && !carrierName) {
                            this.trackingValidation.carrierOther = true;
                            this.$toast.warning(this.i18n.carrier_other_required, {
                                duration: 3000
                            });
                            return;
                        }

                        if (order.trackingGroups.some(g => g.tracking_number === tracking)) {
                            this.$toast.warning(this.i18n.tracking_exists_title, {
                                description: this.i18n.tracking_exists_detail,
                                duration: 3000
                            })
                            return;
                        }

                        const groupId = 'group-' + tracking;

                        order.trackingGroups.push({
                            id: groupId,
                            tracking_number: tracking,
                            carrier_code: carrierCode,
                            carrier_name: carrierCode === this.carrierOtherCode ? carrierName : '',
                            products: []
                        });

                        this.getDebouncedSave(order.id)(order);

                        this.newTrackingDialog.visible = false;
                        this.newTrackingDialog.tracking_number = '';
                         this.newTrackingDialog.carrier_code = this.carrierOtherCode;
                         this.newTrackingDialog.carrier_name = '';

                        // Check if the panel was collapsed
                        const wasCollapsed = this.panelCollapsedState[order.id] === true;

                        // ✅ Open the panel
                        this.panelCollapsedState[order.id] = false;

                        // Wait for the panel animation to complete if it was collapsed
                        if (wasCollapsed) {
                            // Delay scrolling to wait for expansion animation (adjust time if needed)
                            setTimeout(() => {
                                this.scrollToGroup(groupId);
                            }, 500); // 500ms matches PrimeVue's panel toggle animation
                        } else {
                            // Scroll immediately if already expanded
                            this.scrollToGroup(groupId);
                        }
                    },

                    /**
                     * Removes a tracking group and moves its products back to the ungrouped list.
                     */
                    removeTrackingGroup(order, groupIndex) {
                        const group = order.trackingGroups[groupIndex];

                        if (group.products.length > 0) {
                            order.ungroupedProducts.push(...group.products);
                        }

                        order.trackingGroups.splice(groupIndex, 1);
                       this.getDebouncedSave(order.id)(order);
                    },

                    hasProductsToMove(order, group) {
                        const ungrouped = order.ungroupedProducts.length;
                        const otherGroups = order.trackingGroups.filter(g => g.tracking_number !== group.tracking_number);
                        const otherGroupsHaveProducts = otherGroups.some(g => g.products.length > 0);

                        return ungrouped > 0 || otherGroupsHaveProducts;
                    },
                    
                    openMoveProductsModal(order, group) {
                        this.moveProductsDialog = {
                            visible: true,
                            order,
                            group
                        };
                    },

                    confirmMoveProducts() {
                        const targetGroup = this.moveProductsDialog.group;
                        const order = this.moveProductsDialog.order;

                        // Move selected products to the target group
                        this.selectedProductsToMove.forEach(product => {
                            // Remove from current source
                            if (product.tracking_number) {
                                const sourceGroup = order.trackingGroups.find(g => g.tracking_number === product.tracking_number);
                                if (sourceGroup) {
                                    sourceGroup.products = sourceGroup.products.filter(p => p.product_id !== product.product_id);

                                    // Remove the source group if empty
                                    if (sourceGroup.products.length === 0) {
                                        const index = order.trackingGroups.findIndex(g => g.tracking_number === product.tracking_number);
                                        if (index !== -1) {
                                            order.trackingGroups.splice(index, 1);
                                        }
                                    }
                                }
                            } else {
                                // Remove from ungrouped
                                order.ungroupedProducts = order.ungroupedProducts.filter(p => p.product_id !== product.product_id);
                            }

                            // Add to target group
                            const target = order.trackingGroups.find(g => g.tracking_number === targetGroup.tracking_number);
                            if (target) {
                                target.products.push({ ...product, tracking_number: targetGroup.tracking_number });
                            }
                        });

                        // Save & close dialog
                        this.getDebouncedSave(order.id)(order);
                        this.moveProductsDialog.visible = false;
                        this.selectedProductsToMove = [];
                    },

                    /**
                     * Handles drag-and-drop changes between groups.
                     * Updates the data structure and saves the state.
                     */
                    handleGroupChange(order, event) {
                        if (event.added || event.removed) {
                            if (order.ungroupedProducts.length === 0 && this.dragOverGroupId === 'ungrouped') {
                                this.dragOverGroupId = null;
                                this.isDragging = false;
                            }

                            this.getDebouncedSave(order.id)(order);
                        }
                    },

                    /**
                     * Scrolls to a tracking group inside the ScrollPanel using a reliable internal reference.
                     * @param {string} groupId - The DOM ID of the tracking group element
                     */
                    scrollToGroup(groupId) {
                        const orderIndex = this.filteredOrders.findIndex(order =>
                            order.trackingGroups?.some(g => g.id === groupId)
                        );

                        if (orderIndex !== -1 && this.$refs.ordersScrollPanel) {

                            // Wait for DOM to update (virtual scroll), then scroll to the exact group element
                            this.$nextTick(() => {
                                setTimeout(() => {
                                    const el = document.getElementById(groupId);
                                    el?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                                }, 200); // enough time for virtual DOM to render
                            });
                        }
                    },

                    /**
                     * Reactively updates an order in the local state.
                     */
                    updateOrder(updatedData) {
                        const index = this.orders.findIndex(o => o.id === updatedData.id);
                        if (index === -1) return;

                        const target = this.orders[index];

                        for (const key in updatedData) {
                            if (key !== 'id' && updatedData[key] !== undefined && key in target) {
                                target[key] = updatedData[key];
                            }
                        }
                    },

                    scheduleOrdersLayoutSync() {
                        if (this.layoutSyncHandle) {
                            cancelAnimationFrame(this.layoutSyncHandle);
                        }

                        this.layoutSyncHandle = requestAnimationFrame(() => {
                            const scroller = this.$refs.ordersScrollPanel;
                            scroller?.handleResize?.();
                            scroller?.$el?.dispatchEvent(new Event('scroll'));
                            this.layoutSyncHandle = null;
                        });
                    },

                    queuePanelStatePersist(key, expanded) {
                        const persist = () => {
                            if (expanded.length === 0) {
                                localStorage.removeItem(key);
                            } else {
                                localStorage.setItem(key, JSON.stringify(expanded));
                            }
                            this.panelPersistFrame = null;
                        };

                        if (this.panelPersistFrame) {
                            cancelAnimationFrame(this.panelPersistFrame);
                        }

                        this.panelPersistFrame = requestAnimationFrame(persist);
                    },

                    // ========================================
                    // ✅ ORDER FULFILLMENT & METADATA
                    // ========================================

                    async fetchSupplierOrders(reset = false, page = null) {
                        if (this.ordersFetchPromise) {
                            return this.ordersFetchPromise;
                        }

                        const runner = async () => {
                            try {
                                if (reset) {
                                    this.currentPage = 1;
                                    this.orders = [];
                                    this.allOrdersLoaded = false;
                                }

                                const fetchPage = page !== null ? page : this.currentPage;

                                const result = await wp.apiFetch({
                                    path: `/hc/v1/suppliers/portal/assigned-orders?page=${fetchPage}&per_page=${this.perPage}&sort=${encodeURIComponent(this.sort)}&status=${this.selectedStatus?.value || 'all'}&search=${encodeURIComponent(this.searchQuery || '')}`,
                                    method: 'GET'
                                });

                                const newOrders = result.orders || [];

                                newOrders.forEach(newOrder => {
                                    const trackingMeta = newOrder.tracking_groups_meta || {};
                                    const trackingGroups = (newOrder.grouped_products || []).map(group => {
                                        const meta = trackingMeta[group.tracking_number] || {};
                                        return {
                                            id: 'group-' + group.tracking_number,
                                            tracking_number: group.tracking_number,
                                            carrier_code: group.carrier_code || meta.carrier_code || '',
                                            carrier_name: group.carrier_name_other || meta.carrier_name_other || '',
                                            products: group.products
                                        };
                                    });

                                    const finalOrder = {
                                        ...newOrder,
                                        trackingGroups,
                                        ungroupedProducts: newOrder.ungrouped_products || []
                                    };

                                    const existingIndex = this.orders.findIndex(order => order.id === finalOrder.id);
                                   if (existingIndex !== -1) {
                                        const localSavedAt = this.lastSavedTimestamps?.[finalOrder.id] || 0;
                                        const rawDate = finalOrder.date_modified;
                                        const serverModifiedAt = rawDate ? new Date(rawDate).getTime() : 0;


                                        if (serverModifiedAt >= localSavedAt) {
                                            this.updateOrder({
                                            ...this.orders[existingIndex],
                                            ...finalOrder
                                            });
                                        } else {
                                            console.debug(`⏳ Skipped update for order ${finalOrder.id} — local changes are newer`);
                                        }
                                    } else {
                                        this.orders.push(finalOrder);
                                        this.panelCollapsedState[finalOrder.id] = true;

                                        // OS-level notification if tab is inactive
                                        if (document.visibilityState === 'hidden') {
                                            notifyOSWithVibrate({
                                                title: this.i18n.new_order_notification_title,
                                                body: this.i18n.new_order_notification_body.replace('%order_id%', finalOrder.id),
                                                icon: this.mobileIcon
                                            });
                                        }
                                    }
                                });

                                this.orders.sort((a, b) => {
                                    const dateA = new Date(a.date_created);
                                    const dateB = new Date(b.date_created);
                                    return dateB - dateA;
                                });

                                const pagination = result.pagination || {};
                                this.currentPage = pagination.page || fetchPage;
                                this.totalPages = pagination.total_pages || 1;

                                if (this.currentPage >= this.totalPages || newOrders.length === 0) {
                                    this.allOrdersLoaded = true;
                                }

                                this.$nextTick(() => {
                                    this.scheduleOrdersLayoutSync();
                                });

                            } catch (error) {
                                this.$toast.error(this.i18n.error_loading_orders, {
                                    description: error.message,
                                    duration: 4000
                                });
                            } finally {
                                this.ordersLoaded = true;
                                this.isLoadingMore = false;
                            }
                        };

                        this.ordersFetchPromise = runner().finally(() => {
                            this.ordersFetchPromise = null;
                        });

                        return this.ordersFetchPromise;
                    },
                    // handleScroll: debounce(function (event) {
                    //     const scroller = event.target;
                    //     if (!scroller) return;

                    //     const scrollTop = scroller.scrollTop;
                    //     const clientHeight = scroller.clientHeight;
                    //     const scrollHeight = scroller.scrollHeight;
                    //     const scrollPosition = scrollTop + clientHeight;
                    //     const threshold = 10;

                    //     const atBottom = scrollHeight - scrollPosition <= threshold;

                    //     if (
                    //         atBottom &&
                    //         !this.isLoadingMore &&
                    //         !this.allOrdersLoaded
                    //     ) {
                    //         this.isLoadingMore = true;
                    //         this.currentPage++;

                    //         this.$toast
                    //             .promise(this.fetchSupplierOrders(), {
                    //             loading: this.i18n.fetching_orders_loading,
                    //             success: this.i18n.fetching_orders_success,
                    //             error: (error) =>
                    //                 this.i18n.fetching_orders_error + ': ' + error.message,
                    //                 duration: 4000
                    //             })
                    //             .unwrap()
                    //             .finally(() => {
                    //             this.isLoadingMore = false;
                    //         });
                    //     }
                    // }, 150),

                    openNoteEditor(order, product) {
                        this.noteDialog.visible = true;
                        this.noteDialog.order = order;
                        this.noteDialog.product = product;
                        this.noteDialog.note = product.note || '';
                    },

                    saveProductNote() {
                        const { order, product, note } = this.noteDialog;

                        // Check if note is unchanged
                        if ((product.note || '') === (note || '')) {
                            this.$toast.info(this.i18n.no_changes, {
                                description: this.i18n.no_changes_detail,
                                duration: 2500
                            })
                            this.noteDialog.visible = false;
                            return;
                        }

                        // Apply the updated note
                        product.note = note;

                        this.noteDialog.visible = false;
                        this.getDebouncedSave(order.id)(order); // trigger save
                    },

                    deleteNote(order, product) {
                        product.note = ''; // or null — depending on your data model

                        this.getDebouncedSave(order.id)(order); // trigger save
                    },
                    /**
                     * Opens the confirmation dialog to mark an order as fulfilled.
                     */
                    openFulfillmentModal(order) {
                        if (order.supplier_status === 'fulfilled') {
                            this.$toast.info(this.i18n.already_fulfilled, {
                                description: `${this.i18n.order_number}${order.id} - ${this.i18n.already_fulfilled_text}`,
                                duration: 3000
                            })

                            return;
                        }

                        this.fulfillmentDialog.visible = true;
                        this.fulfillmentDialog.order = order;
                    },

                    
                    getDebouncedSave(orderId, debounceDelay = 1000) {
                        if (!this.debouncedSaves[orderId]) {
                            this.debouncedSaves[orderId] = debounce((order) => {
                                this.saveOrderMetadata(order);
                            }, debounceDelay);
                        }
                        return this.debouncedSaves[orderId];
                    },

                    /**
                     * Saves updated supplier metadata for an order via the WP REST API.
                     */
                    async saveOrderMetadata(order) {
                        try {
                            const result = await wp.apiFetch({
                                path: '/hc/v1/suppliers/portal/save-metadata',
                                method: 'POST',
                                data: {
                                    order_id: order.id,
                                    metadata: {
                                        note: order.supplier_note,
                                        color_tag: order.color_tag,
                                        ungrouped_products: order.ungroupedProducts,
                                        grouped_products: order.trackingGroups.map(group => ({
                                            tracking_number: group.tracking_number,
                                            carrier_code: group.carrier_code || '',
                                            carrier_name_other: group.carrier_name || '',
                                            products: group.products
                                        }))
                                    }
                                }
                            });

                            // ✅ Timestamp the local save
                            this.lastSavedTimestamps[order.id] = Date.now();

                            order.supplier_status = result.status;
                            this.updateOrder(order);

                            if (order.supplier_status === 'fulfilled') {
                                this.panelCollapsedState[order.id] = true;
                            }

                            if (this.fulfillmentDialog.order?.id === order.id) {
                                this.fulfillmentDialog.order = { ...order };
                            }

                            this.$toast.success(this.i18n.success, {
                                description: result.message || this.i18n.order_saved,
                                duration: 2000
                            });

                        } catch (error) {
                            this.$toast.error(this.i18n.error, {
                                description: error.message || 'Something went wrong',
                                duration: 3000
                            });
                        }
                    },

                    /**
                     * Confirms fulfillment and notifies backend.
                     */
                    async confirmSupplierFulfillment() {
                        try {
                            this.confirmfulfillmentSubmitted = true;

                            const result = await wp.apiFetch({
                                path: '/hc/v1/suppliers/portal/confirm-fulfillment',
                                method: 'POST',
                                data: {
                                    order_id: this.fulfillmentDialog.order.id
                                }
                            });

                            this.fulfillmentDialog.order.supplier_status = result.status;
                            this.updateOrder(this.fulfillmentDialog.order);

                            if (result.status === 'fulfilled') {
                                this.panelCollapsedState[this.fulfillmentDialog.order.id] = true;
                            }

                            this.$toast.success(this.i18n.success, {
                                description: result.message || this.i18n.confirmed,
                                duration: 3000
                            });

                            this.fulfillmentDialog.visible = false;

                        } catch (error) {
                            this.$toast.error(this.i18n.error, {
                                description: error.message || 'Failed to confirm fulfillment',
                                duration: 3000
                            });
                        } finally {
                            this.confirmfulfillmentSubmitted = false;
                        }
                    }
                },  
                mounted: async function () {
                    await this.fetchSupplierOrders();

                    if (window.HavenCoreFetchClient?.create) {
                        this.ordersPoller = window.HavenCoreFetchClient.create({
                            id: 'havencore-orders-poller',
                            task: () => {
                                if (!this.ordersFetchPromise) {
                                    return this.fetchSupplierOrders(false, 1);
                                }

                                return this.ordersFetchPromise;
                            },
                            interval: 30000,
                            runOnFocus: true
                        });
                        this.ordersPoller.start();
                    } else {
                        console.warn('HavenCoreFetchClient not available.');
                    }

                    // Restore saved collapsed state from localStorage
                    const saved = localStorage.getItem('hc_supplier_portal_expanded_orders');
                    let expanded = [];
                    try {
                        expanded = JSON.parse(saved) || [];
                    } catch (e) {}

                    this.orders.forEach(order => {
                        this.panelCollapsedState[order.id] = !expanded.includes(order.id);
                    });

                    // Auto open fulfillment modal if ?order_id=X is present
                    const routeOrderId = parseInt(this.$route.query.order_id);
                    if (routeOrderId && this.orders.some(o => o.id === routeOrderId)) {
                        this.panelCollapsedState[routeOrderId] = false;

                    }
                },
                beforeUnmount() {
                    if (this.ordersPoller?.stop) {
                        this.ordersPoller.stop();
                    }
                    if (this.layoutSyncHandle) {
                        cancelAnimationFrame(this.layoutSyncHandle);
                        this.layoutSyncHandle = null;
                    }
                    if (this.panelPersistFrame) {
                        cancelAnimationFrame(this.panelPersistFrame);
                        this.panelPersistFrame = null;
                    }
                },
                watch: {
                    panelCollapsedState: {
                        handler(newVal) {
                            const expanded = Object.entries(newVal)
                                .filter(([, isCollapsed]) => !isCollapsed) // Only include expanded panels
                                .map(([id]) => Number(id));

                            const key = 'hc_supplier_portal_expanded_orders';

                            this.queuePanelStatePersist(key, expanded);
                        },
                        deep: true
                    }
                }
            };

            const Products = {
                inject: ['i18n', 'mobile'],
                data() {
                    return {
                        loading: false,
                        items: [],
                        selected: [],
                        page: 1,
                        perPage: 10,
                        total: 0,
                        totalPages: 1,
                        search: '',
                        saving: {},
                        filters: {
                            global: { value: '' }
                        },
                        searchTimer: null,
                        editDialog: {
                            visible: false,
                            item: null,
                            isVariation: false,
                            parent: null,
                        },
                        variationsDialog: {
                            visible: false,
                            product: null,
                            items: [],
                            loading: false,
                        },
                        
                    };
                },
                methods: {
                    async fetchProducts() {
                        this.loading = true;
                        try {
                            await waitForWP();
                            const res = await (wp.apiFetch ? wp.apiFetch({
                                path: `/hc/v1/suppliers/portal/products?search=${encodeURIComponent(this.search || '')}&page=${this.page}&per_page=${this.perPage}`,
                                method: 'GET',
                            }) : wp.apiRequest({
                                path: `/hc/v1/suppliers/portal/products?search=${encodeURIComponent(this.search || '')}&page=${this.page}&per_page=${this.perPage}`,
                                method: 'GET',
                            }));
                            this.items = res.products || [];
                            this.total = res.pagination?.total || 0;
                            this.totalPages = res.pagination?.total_pages || 1;
                        } catch (e) {
                            console.error('[Products.fetchProducts] error:', e);
                            const msg = (e?.data?.message || e?.message || '').toString() || (this.i18n?.request_failed || 'The request failed. Please check your connection.');
                            this.$toast?.error(this.i18n?.error || 'Error', { description: msg });
                        } finally {
                            this.loading = false;
                        }
                    },
                    onPage(event) {
                        // event.page is 0-based; DataTable also gives rows
                        this.page = (event.page ?? 0) + 1;
                        this.perPage = event.rows ?? this.perPage;
                        this.fetchProducts();
                    },
                    onGlobalSearch() {
                        this.page = 1;
                        this.search = this.filters.global?.value || '';
                        this.fetchProducts();
                    },
                    onSearchInput() {
                        // Debounce search to run after user stops typing
                        if (this.searchTimer) {
                            clearTimeout(this.searchTimer);
                        }
                        this.searchTimer = setTimeout(() => {
                            this.page = 1;
                            this.search = this.filters.global?.value || '';
                            this.fetchProducts();
                        }, 600);
                    },
                    async updateStock(item) {
                        if (this.saving[item.id]) return;
                        this.saving = { ...this.saving, [item.id]: true };
                        try {
                            await waitForWP();
                            const req = {
                                path: '/hc/v1/suppliers/portal/products/update-stock',
                                method: 'POST',
                                data: {
                                    product_id: item.id,
                                    manage_stock: !!item.manage_stock,
                                    stock_quantity: item.manage_stock ? Number(item.stock_quantity || 0) : null,
                                    stock_status: item.stock_status,
                                    supplier_price: (item.supplier_price ?? '') !== '' ? String(item.supplier_price) : null,
                                    sku: (typeof item.sku === 'string') ? item.sku.trim() : (item.sku == null ? '' : String(item.sku))
                                }
                            };
                            if (wp.apiFetch) {
                                await wp.apiFetch(req);
                            } else {
                                await wp.apiRequest(req);
                            }
                            this.$toast?.success(this.i18n?.success || 'Success');
                        } catch (e) {
                            console.error('[Products.updateStock] error:', e);
                            const msg = (e?.data?.message || e?.message || '').toString() || (this.i18n?.request_failed || 'The request failed. Please check your connection.');
                            this.$toast?.error(this.i18n?.error || 'Error', { description: msg });
                        } finally {
                            this.saving = { ...this.saving, [item.id]: false };
                        }
                    },
                    exportCSV() {
                        const rows = this.items.map(p => ({
                            id: p.id,
                            name: p.name,
                            sku: p.sku,
                            manage_stock: p.manage_stock ? '1' : '0',
                            stock_quantity: p.manage_stock ? (p.stock_quantity ?? '') : '',
                            stock_status: p.stock_status,
                        }));
                        const header = ['id','name','sku','manage_stock','stock_quantity','stock_status'];
                        const csv = [header.join(','), ...rows.map(r => header.map(h => `"${String(r[h] ?? '').replace(/"/g,'""')}"`).join(','))].join('\n');
                        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
                        const url = URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.href = url;
                        a.download = 'supplier-products.csv';
                        a.click();
                        URL.revokeObjectURL(url);
                    },
                    openEdit(item) {
                        // create a shallow copy to edit
                        this.editDialog.item = { ...item };
                        this.editDialog.isVariation = false;
                        this.editDialog.parent = null;
                        // normalize status immediately so Select has a valid option
                        this.ensureValidStatus(this.editDialog.item);
                        this.editDialog.visible = true;
                    },
                    openEditVariation(v) {
                        this.editDialog.item = { ...v };
                        this.editDialog.isVariation = true;
                        this.editDialog.parent = this.variationsDialog.product || null;
                        this.ensureValidStatus(this.editDialog.item);
                        this.editDialog.visible = true;
                    },
                    async saveEdit() {
                        const item = this.editDialog.item;
                        if (!item) return;
                        // Enforce status rules before saving
                        this.ensureValidStatus(item);
                        try {
                            await waitForWP();
                            if (this.editDialog.isVariation) {
                                // Start button loading for this variation id
                                this.saving = { ...this.saving, [item.id]: true };
                                const req = {
                                    path: '/hc/v1/suppliers/portal/variations/update-stock',
                                    method: 'POST',
                                    data: {
                                        variation_id: item.id,
                                        manage_stock: !!item.manage_stock,
                                        stock_quantity: item.manage_stock ? Number(item.stock_quantity || 0) : null,
                                        stock_status: item.stock_status,
                                        supplier_price: (item.supplier_price ?? '') !== '' ? String(item.supplier_price) : null,
                                        sku: (typeof item.sku === 'string') ? item.sku.trim() : (item.sku == null ? '' : String(item.sku))
                                    }
                                };
                                if (wp.apiFetch) { await wp.apiFetch(req); } else { await wp.apiRequest(req); }
                                // Close the edit modal first, then refresh the parent variations list
                                this.editDialog.visible = false;
                                if (this.variationsDialog.product) {
                                    const pid = this.variationsDialog.product.id;
                                    await this.fetchVariations(pid);
                                    const allOut = (this.variationsDialog.items || []).every(v => {
                                        const qty = Number(v.stock_quantity ?? 0);
                                        if (v.manage_stock) return qty <= 0 || v.stock_status === 'outofstock';
                                        return v.stock_status === 'outofstock';
                                    });
                                    const newStatus = allOut ? 'outofstock' : 'instock';
                                    const idx = this.items.findIndex(p => p.id === pid);
                                    if (idx !== -1) this.items[idx] = { ...this.items[idx], stock_status: newStatus };
                                }
                                // Always refresh the main products list so aggregates (totals, in-stock counts, etc.) are up to date
                                await this.fetchProducts();
                            } else {
                                await this.updateStock(item); // updateStock() already shows success toast
                                this.fetchProducts();
                            }
                            if (this.editDialog.isVariation) {
                                this.$toast?.success(this.i18n?.success || 'Success');
                            }
                        } catch (e) {
                            console.error('[Products.saveEdit] error:', e);
                            const msg = (e?.data?.message || e?.message || '').toString() || (this.i18n?.request_failed || 'The request failed. Please check your connection.');
                            this.$toast?.error(this.i18n?.error || 'Error', { description: msg });
                            return;
                        } finally {
                            if (this.editDialog?.isVariation && this.editDialog?.item?.id) {
                                this.saving = { ...this.saving, [this.editDialog.item.id]: false };
                            }
                            this.editDialog.visible = false;
                        }
                    },
                    ensureValidStatus(item) {
                        if (!item) return;
                        const qty = Number(item.stock_quantity ?? 0);
                        const managed = !!item.manage_stock;
                        // Disallow onbackorder selection entirely in UI; normalize if present
                        if (item.stock_status === 'onbackorder') {
                            item.stock_status = qty > 0 ? 'instock' : 'outofstock';
                        }
                        if (managed) {
                            if (qty <= 0 && item.stock_status !== 'outofstock') {
                                item.stock_status = 'outofstock';
                            }
                            if (qty > 0 && item.stock_status === 'outofstock') {
                                item.stock_status = 'instock';
                            }
                        }
                    },
                    statusOptionsFor(item) {
                        const all = [
                            { label: this.i18n.instock || 'In stock', value: 'instock' },
                            { label: this.i18n.outofstock || 'Out of stock', value: 'outofstock' }
                        ];
                        if (!item) return all;
                        const managed = !!item.manage_stock;
                        if (!managed) return all; // unmanaged: allow instock/outofstock only
                        const qty = Number(item.stock_quantity ?? 0);
                        if (qty <= 0) {
                            return all.filter(o => o.value === 'outofstock');
                        }
                        return all.filter(o => o.value !== 'outofstock');
                    },
                    statusSeverity(val) {
                        const v = String(val || '').toLowerCase();
                        if (v === 'instock') return 'success';
                        if (v === 'onbackorder') return 'warn';
                        return 'danger';
                    },
                    async openVariations(product) {
                        this.variationsDialog.product = product;
                        this.variationsDialog.visible = true;
                        await this.fetchVariations(product.id);
                    },
                    async fetchVariations(productId) {
                        try {
                            this.variationsDialog.loading = true;
                            await waitForWP();
                            const res = await (wp.apiFetch ? wp.apiFetch({
                                path: `/hc/v1/suppliers/portal/products/${productId}/variations`,
                                method: 'GET',
                            }) : wp.apiRequest({
                                path: `/hc/v1/suppliers/portal/products/${productId}/variations`,
                                method: 'GET',
                            }));
                            this.variationsDialog.items = res.variations || [];
                        } catch (e) {
                            console.error('[Products.fetchVariations] error:', e);
                            const msg = (e?.data?.message || e?.message || '').toString() || (this.i18n?.request_failed || 'The request failed. Please check your connection.');
                            this.$toast?.error(this.i18n?.error || 'Error', { description: msg });
                        } finally {
                            this.variationsDialog.loading = false;
                        }
                    },
                    
                },
                computed: {
                    first() {
                        const currentPage = Number(this.page) || 1;
                        const size = Number(this.perPage) || 10;
                        return Math.max(0, (currentPage - 1) * size);
                    },
                    skeletonRows() {
                        const size = Number(this.perPage) || 10;
                        return Array.from({ length: size }, (_, index) => ({ id: `skeleton-${index}` }));
                    }
                },
                mounted() {
                    this.fetchProducts();
                },
                watch: {
                    'editDialog.item.stock_quantity'(nv) {
                        if (this.editDialog?.item) {
                            this.ensureValidStatus(this.editDialog.item);
                        }
                    },
                    'editDialog.item.manage_stock'(nv) {
                        if (this.editDialog?.item) {
                            this.ensureValidStatus(this.editDialog.item);
                        }
                    }
                },
                template: `
                    <div class="overflow-y-auto h-full p-4 space-y-4">
                        <div class="flex flex-wrap gap-2 items-center justify-between">
                            <h4 class="m-0">{{ i18n.products }}</h4>
                            <IconField>
                                <InputIcon class="pi pi-search" />
                                <InputText
                                    v-model="filters.global.value"
                                    :placeholder="i18n.search_placeholder || i18n.search || 'Search'"
                                    @input="onSearchInput"
                                />
                            </IconField>
                        </div>

                        <Transition name="fade" mode="out-in">
                            <div v-if="loading" key="skeleton" class="shadow-sm rounded-lg overflow-hidden">

                                <div class="p-datatable" role="rowgroup">
                                    <div
                                        v-for="row in skeletonRows"
                                        :key="row.id"
                                        class="gap-4 px-4 py-3 items-center text-center"
                                        :class="mobile ? 'flex flex-row' : 'grid grid-cols-12'"
                                    >
                                        <div v-if="!mobile" class="col-span-1">
                                            <Skeleton shape="circle" size="1.75rem" />
                                        </div>
                                        <div v-if="!mobile" class="col-span-1">
                                            <Skeleton width="3rem" class="mx-auto sm:mx-0" />
                                        </div>
                                        <div class="space-y-2 col-span-3">
                                            <div class="flex items-center gap-3 flex-row justify-center">
                                                <Skeleton shape="circle" size="3rem" class="flex-shrink-0" />
                                                <div class="w-full space-y-2 flex flex-col">
                                                    <Skeleton width="22rem" height="1rem" />
                                                    <Skeleton width="12rem" height="0.75rem" />
                                                </div>
                                            </div>
                                        </div>
                                        <div v-if="!mobile" class="col-span-1">
                                            <Skeleton width="8rem" height="1.25rem" />
                                        </div>
                                        <div v-if="!mobile" class="col-span-1">
                                            <Skeleton width="5rem" height="1.1rem" />
                                        </div>
                                        <div v-if="!mobile" class="col-span-1">
                                            <Skeleton width="4rem" height="1.1rem" />
                                        </div>
                                        <div v-if="!mobile" class="flex justify-center gap-2 sm:col-span-2">
                                            <Skeleton width="12rem" height="1.4rem" class="mx-auto sm:mx-0" />
                                        </div>
                                        <div v-if="!mobile" class="flex justify-center sm:col-span-1">
                                            <Skeleton width="6rem" height="2.4rem" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <DataTable
                                v-else
                                key="table"
                                :value="items"
                                dataKey="id"
                                v-model:selection="selected"
                                selectionMode="single"
                                scrollable
                                scrollHeight="75svh"
                                :paginator="true"
                                :lazy="true"
                                :first="first"
                                :rows="perPage"
                                :totalRecords="total"
                                @page="onPage"
                                :filters="filters"
                            >
                                <Column selectionMode="multiple" style="width: 3rem" :exportable="false"></Column>
                                <Column field="id" :header="i18n.id || '#'" style="min-width: 6rem"></Column>
                                <Column field="name" :header="i18n.name || 'Name'" style="min-width: 16rem">
                                    <template #body="{ data }">
                                        <div class="flex items-center gap-3 text-center justify-center">
                                            <Image
                                                v-if="data.thumbnail"
                                                :src="data.thumbnail"
                                                :alt="i18n.product_image_alt || 'product image'"
                                                preview
                                                class="flex-shrink-0 mx-auto sm:mx-0"
                                                :pt="{
                                                    root: { class: 'rounded-lg overflow-hidden' },
                                                    image: { class: 'w-12 h-12 object-cover' }
                                                }"
                                            />
                                            <div>
                                                <div class="font-medium">{{ data.name }}</div>
                                            </div>
                                        </div>
                                    </template>
                                </Column>
                                <Column field="supplier_price" :header="i18n.supplier_price || 'Supplier Price'" style="min-width: 10rem; text-align: center">
                                    <template #body="{ data }">
                                        <div class="w-full text-center">
                                            {{ (data.supplier_price ?? '') !== '' 
                                                ? data.supplier_price 
                                                : (data.type === 'variable' ? (i18n.per_variation || 'Per variation') : (i18n.not_set || 'Not set')) }}
                                        </div>
                                    </template>
                                </Column>
                                <Column field="sku" :header="i18n.sku || 'SKU'" style="min-width: 10rem">
                                    <template #body="{ data }">
                                        <span>{{ (data.sku && data.sku.length) ? data.sku : '-' }}</span>
                                    </template>
                                </Column>
                                <Column :header="i18n.stock || 'Stock'" style="min-width: 10rem">
                                    <template #body="{ data }">
                                        <div class="w-full text-center">
                                            <template v-if="data.type === 'variable'">
                                                <template v-if="data.manage_stock">
                                                    {{ data.stock_quantity ?? 0 }}
                                                </template>
                                                <template v-else>
                                                    <div>{{ (data.variation_stock_total ?? 0) }}</div>
                                                </template>
                                            </template>
                                            <template v-else>
                                                {{ (data.manage_stock && (data.stock_quantity ?? null) !== null) ? data.stock_quantity : '-' }}
                                            </template>
                                        </div>
                                    </template>
                                </Column>
                                <Column :header="i18n.status || 'Status'" style="min-width: 12rem">
                                    <template #body="{ data }">
                                        <div class="flex items-center gap-2">
                                            <template v-if="data.type === 'variable' && !data.manage_stock">
                                                <Tag :value="i18n.per_variation || 'Per variation'" severity="info" />
                                                <span class="text-xs text-gray-500">{{ (data.variation_instock_count ?? 0) + '/' + (data.variation_total_count ?? 0) + ' ' + (i18n.in_stock || 'in stock') }}</span>
                                            </template>
                                            <template v-else>
                                                <Tag :value="data.stock_status" :severity="statusSeverity(data.stock_status)" />
                                                <span class="text-xs text-gray-500">{{ data.manage_stock ? (i18n.managed || 'Managed') : (i18n.unmanaged || 'Unmanaged') }}</span>
                                            </template>
                                        </div>
                                    </template>
                                </Column>
                                <Column :exportable="false" style="min-width: 12rem">
                                    <template #body="{ data }">
                                        <div class="flex gap-2">
                                            <Button v-if="data.type !== 'variable'" icon="pi pi-pencil" rounded variant="outlined" :aria-label="i18n.edit || 'Edit'" @click="openEdit(data)" />
                                            <Button v-else icon="pi pi-sitemap" rounded variant="outlined" :aria-label="i18n.edit_variations || 'Edit Variations'" @click="openVariations(data)" />
                                        </div>
                                    </template>
                                </Column>
                            </DataTable>
                        </Transition>

                        <Dialog v-model:visible="editDialog.visible" modal :header="i18n.edit || 'Edit'" :style="{ width: '32rem' }">
                            <div v-if="editDialog.item" class="space-y-4">
                                <div class="flex items-center gap-3 text-center justify-center">
                                    <Image v-if="editDialog.isVariation && editDialog.parent && editDialog.parent.thumbnail" :src="editDialog.parent.thumbnail" :alt="i18n.product_image_alt || 'product image'" preview class="flex-shrink-0" :pt="{ root: { class: 'rounded-lg overflow-hidden' }, image: { class: 'w-12 h-12 object-cover' } }" />
                                    <Image v-if="editDialog.item.thumbnail" :src="editDialog.item.thumbnail" :alt="i18n.product_image_alt || 'product image'" preview class="flex-shrink-0" :pt="{ root: { class: 'rounded-lg overflow-hidden' }, image: { class: 'w-12 h-12 object-cover' } }" />
                                    <div>
                                        <template v-if="editDialog.isVariation">
                                            <div class="text-sm">{{ (editDialog.item.attributes && editDialog.item.attributes.length) ? editDialog.item.attributes.join(', ') : (i18n.attributes || 'Attributes') }}</div>
                                            <div class="text-xs">{{ i18n.sku || 'SKU' }}: {{ (editDialog.item.sku && editDialog.item.sku.length) ? editDialog.item.sku : '-' }}</div>
                                        </template>
                                        <template v-else>
                                            <div class="font-medium">{{ editDialog.item.name }}</div>
                                            <div class="text-xs">{{ (i18n.id || 'ID') + ': ' + editDialog.item.id }} • {{ i18n.sku || 'SKU' }}: {{ editDialog.item.sku || (i18n.n_a || 'N/A') }}</div>
                                        </template>
                                    </div>
                                </div>
                                <Divider />
                                <div class="grid grid-cols-12 gap-4 items-center">
                                    <div class="col-span-4 text-sm">{{ i18n.sku || 'SKU' }}</div>
                                    <div class="col-span-8">
                                        <InputText v-model.trim="editDialog.item.sku" class="w-48" />
                                    </div>
                                    <div class="col-span-4 text-sm">{{ i18n.quantity || 'Quantity' }}</div>
                                    <div class="col-span-8">
                                        <InputText v-model.number="editDialog.item.stock_quantity" @input="ensureValidStatus(editDialog.item)" :disabled="!editDialog.item.manage_stock" class="w-40" />
                                        <div v-if="!editDialog.item.manage_stock" class="text-xs mt-1">{{ (i18n.n_a || 'N/A') + ' (' + (i18n.unmanaged || 'Unmanaged') + ')' }}</div>
                                    </div>
                                    <div class="col-span-4 text-sm">{{ i18n.supplier_price || 'Supplier Price' }}</div>
                                    <div class="col-span-8">
                                        <InputText v-model="editDialog.item.supplier_price" class="w-40" />
                                    </div>
                                    <div class="col-span-4 text-sm">{{ i18n.status || 'Status' }}</div>
                                    <div class="col-span-8">
                                        <Select
                                            v-model="editDialog.item.stock_status"
                                            :options="statusOptionsFor(editDialog.item)"
                                            optionLabel="label"
                                            optionValue="value"
                                            class="w-40"
                                        />
                                    </div>
                                </div>

                                <div class="flex justify-end gap-2 mt-4">
                                    <Button :label="i18n.cancel" severity="secondary" variant="outlined" @click="editDialog.visible=false" />
                                    <Button :label="i18n.save" icon="pi pi-check" :loading="!!saving[editDialog.item.id]" @click="saveEdit" />
                                </div>
                            </div>
                        </Dialog>

                        <!-- Variations List Dialog -->
                        <Dialog v-model:visible="variationsDialog.visible" modal :header="i18n.edit_variations || 'Edit Variations'" :style="{ width: '70%' }">
                            <div v-if="variationsDialog.product" class="mb-4 flex items-center gap-3">
                                <Image v-if="variationsDialog.product.thumbnail" :src="variationsDialog.product.thumbnail" :alt="i18n.product_image_alt || 'product image'" preview :pt="{ root: { class: 'rounded-lg overflow-hidden' }, image: { class: 'w-12 h-12 object-cover' } }" />
                                <div>
                                    <div class="font-medium">{{ variationsDialog.product.name }}</div>
                                    <div class="text-xs text-gray-500">{{ (i18n.id || 'ID') + ': ' + variationsDialog.product.id }}</div>
                                </div>
                            </div>
                            <div v-if="variationsDialog.loading" class="space-y-2">
                                <Skeleton height="2rem" v-for="i in 4" :key="i" />
                            </div>
                            <div v-else>
                                <DataTable :value="variationsDialog.items" dataKey="id" scrollable scrollHeight="45vh">
                                    <Column field="id" :header="i18n.id || 'ID'" style="min-width: 6rem" />
                                    <Column :header="i18n.name || 'Name'" style="min-width: 18rem">
                                        <template #body="{ data }">
                                            <div class="flex items-center gap-3 text-center justify-center">
                                                <Image v-if="data.thumbnail" :src="data.thumbnail" :alt="i18n.product_image_alt || 'product image'" preview class="flex-shrink-0" :pt="{ root: { class: 'rounded-lg overflow-hidden' }, image: { class: 'w-12 h-12 object-cover' } }" />
                                                <div>
                                                    <div class="text-sm">{{ (data.attributes && data.attributes.length) ? data.attributes.join(', ') : (i18n.attributes || 'Attributes') }}</div>
                                                </div>
                                            </div>
                                        </template>
                                    </Column>
                                    <Column field="sku" :header="i18n.sku || 'SKU'" style="min-width: 10rem">
                                        <template #body="{ data }">
                                            <span>{{ (data.sku && data.sku.length) ? data.sku : '-' }}</span>
                                        </template>
                                    </Column>
                                    <Column field="supplier_price" :header="i18n.supplier_price || 'Supplier Price'" style="min-width: 10rem; text-align: center">
                                        <template #body="{ data }">
                                            <div class="w-full text-center">{{ (data.supplier_price ?? '') !== '' ? data.supplier_price : (i18n.not_set || 'Not set') }}</div>
                                        </template>
                                    </Column>
                                    <Column :header="i18n.stock || 'Stock'" style="min-width: 8rem">
                                        <template #body="{ data }">
                                            <span>{{ (data.manage_stock && (data.stock_quantity ?? null) !== null) ? data.stock_quantity : '-' }}</span>
                                        </template>
                                    </Column>
                                    <Column :header="i18n.status || 'Status'" style="min-width: 10rem">
                                        <template #body="{ data }">
                                            <div class="flex items-center gap-2">
                                                <Tag :value="data.stock_status" :severity="statusSeverity(data.stock_status)" />
                                                <span class="text-xs text-gray-500">{{ data.manage_stock ? (i18n.managed || 'Managed') : (i18n.unmanaged || 'Unmanaged') }}</span>
                                            </div>
                                        </template>
                                    </Column>
                                    <Column :exportable="false" style="min-width: 8rem">
                                        <template #body="{ data }">
                                            <Button icon="pi pi-pencil" rounded variant="outlined" :aria-label="i18n.edit || 'Edit'" @click="openEditVariation(data)" />
                                        </template>
                                    </Column>
                                </DataTable>
                            </div>
                        </Dialog>

                        
                    </div>
                `
            };

            const ChangePassword = {
                template: `
                    <div class="overflow-y-auto h-full flex justify-center items-center ">
                        <!-- Password Change Form inside a Card -->
                        <Card class="w-full max-w-lg change-password-form-card">
                            <template #content>

                                <div class="text-center mb-6">
                                    <h2 class="text-2xl font-semibold  flex items-center justify-center">
                                        <i class="pi pi-lock mr-2"></i> Secure Your Account
                                    </h2>
                                    <p class=" mt-2">Please choose a strong password to keep your account safe.</p>
                                </div>

                                <form @submit.prevent="onSubmit" class="flex flex-col justify-center items-center space-y-8 w-full">

                                    <!-- New Password Field -->
                                    <FloatLabel variant="on">
                                        <Password 
                                            inputId="password"   
                                            v-model="password" 
                                            :feedback="true" 
                                            :minlength="8"
                                            :pattern="passwordPattern"
                                            required
                                            toggleMask
                                            autocomplete="new-password"
                                            :invalid="passwordError"
                                        >
                                            <template #header>
                                                <div class="font-semibold text-lg mb-4 ">{{ i18n.pick_a_password }}</div>
                                            </template>
                                            <template #footer>
                                                <Divider />
                                                <ul class="pl-2 my-0 leading-normal text-sm ">
                                                    <li :class="{
                                                        'text-yellow-500': !passwordRequirements.lowercase, 
                                                        'text-green-500': passwordRequirements.lowercase
                                                    }" class="flex items-center">
                                                        <i :class="{
                                                            'pi pi-times': !passwordRequirements.lowercase,
                                                            'pi pi-check': passwordRequirements.lowercase
                                                        }" class="mr-2"></i>{{ i18n.lowercase_requirement }}
                                                    </li>
                                                    <li :class="{
                                                        'text-yellow-500': !passwordRequirements.uppercase, 
                                                        'text-green-500': passwordRequirements.uppercase
                                                    }" class="flex items-center">
                                                        <i :class="{
                                                            'pi pi-times': !passwordRequirements.uppercase,
                                                            'pi pi-check': passwordRequirements.uppercase
                                                        }" class="mr-2"></i>{{ i18n.uppercase_requirement }}
                                                    </li>
                                                    <li :class="{
                                                        'text-yellow-500': !passwordRequirements.numeric, 
                                                        'text-green-500': passwordRequirements.numeric
                                                    }" class="flex items-center">
                                                        <i :class="{
                                                            'pi pi-times': !passwordRequirements.numeric,
                                                            'pi pi-check': passwordRequirements.numeric
                                                        }" class="mr-2"></i>{{ i18n.numeric_requirement }}
                                                    </li>
                                                    <li :class="{
                                                        'text-yellow-500': !passwordRequirements.minLength, 
                                                        'text-green-500': passwordRequirements.minLength
                                                    }" class="flex items-center">
                                                        <i :class="{
                                                            'pi pi-times': !passwordRequirements.minLength,
                                                            'pi pi-check': passwordRequirements.minLength
                                                        }" class="mr-2"></i>{{ i18n.min_length_requirement }}
                                                    </li>
                                                </ul>
                                            </template>
                                        </Password>
                                        <label for="password">{{ i18n.enter_new_password }}</label>
                                    </FloatLabel>

                                    <!-- Confirm Password Field -->
                                    <FloatLabel variant="on">

                                        <Password 
                                            inputId="confirmPassword" 
                                            v-model="confirmPassword" 
                                            :feedback="false" 
                                            required
                                            toggleMask 
                                            autocomplete="new-password"
                                            :invalid="confirmPasswordError"
                                        />

                                        <label for="confirmPassword">{{ i18n.confirm_password }}</label>
                                    </FloatLabel>
                                
                                    <!-- Submit Button -->
                                    <Button 
                                        type="submit"
                                        :label="i18n.confirm_submit" 
                                        icon="pi pi-check" 
                                        class="p-button-primary w-60"
                                        :disabled="!formIsValid"
                                    />
                                </form>
                            </template>
                        </Card>
                    </div>
                `,
                inject: ['i18n', 'currentUser'],
                data() {
                    return {
                        password: '',
                        confirmPassword: '',
                        passwordError: false,
                        confirmPasswordError: false,
                        passwordPattern: '(?=.*[a-z])(?=.*[A-Z])(?=.*\\d)', // Regex pattern for validation
                        passwordRequirements: {
                            lowercase: false,
                            uppercase: false,
                            numeric: false,
                            minLength: false,
                        },
                    };
                },
                computed: {
                    formIsValid() {
                        return (
                            this.password &&
                            this.confirmPassword &&
                            this.password === this.confirmPassword &&
                            !this.passwordError &&
                            !this.confirmPasswordError
                        );
                    }
                },
                methods: {
                    onSubmit() {
                        if (this.formIsValid) {
                            // console.log(this.currentUser.attributes)
                            // Send the AJAX request to change the password
                            this.changePassword();
                        } else {
                            // Show error message using PrimeVue Toast
                            this.$toast.error(this.i18n.error, {
                                description: 'cannot change password the form is not valid!',
                                duration: 3000
                            })
                        }
                    },

                    changePassword() {
                        wp.apiFetch({
                            path: '/hc/v1/suppliers/portal/change-password',
                            method: 'POST',
                            data: {
                                new_password: this.password
                            }
                        }).then((response) => {
                            this.$toast.success(this.i18n.success, {
                                description: response.message,
                                duration: 3000
                            });

                            if (response.updated_attributes) {
                                Object.assign(this.currentUser.attributes, response.updated_attributes);
                            }

                            extendAuthRedirectGrace(15000);

                            this.$nextTick(() => {
                                this.$router.push('/');
                            });

                        }).catch((error) => {
                            console.error('Password change failed:', error);

                            this.$toast.error(this.i18n.error, {
                                description: error.message || error?.data?.message || 'Something went wrong.',
                                duration: 3000
                            });
                        });
                    },


                    validatePassword() {
                        this.passwordError =
                            this.password.length < 8 || !this.password.match(this.passwordPattern);

                        // Set the password requirements
                        this.passwordRequirements.lowercase = /[a-z]/.test(this.password);
                        this.passwordRequirements.uppercase = /[A-Z]/.test(this.password);
                        this.passwordRequirements.numeric = /\d/.test(this.password);
                        this.passwordRequirements.minLength = this.password.length >= 8;
                    },

                    validateConfirmPassword() {
                        this.confirmPasswordError = this.password !== this.confirmPassword;
                    }
                },
                watch: {
                    password() {
                        this.validatePassword();
                        this.validateConfirmPassword();
                    },
                    confirmPassword() {
                        this.validateConfirmPassword();
                    }
                }
            };



            const Communications = {
                data() {
                    return {
                        conversations: [],
                        conversationsLoading: false,
                        conversationsLoaded: false,
                        conversationsError: null,
                        selectedConversationId: null,
                        messages: [],
                        messagesLoading: false,
                        messagesError: null,
                        messagesPaneReady: false,
                        newMessage: '',
                        sendingMessage: false,
                        newConversationVisible: false,
                        creatingConversation: false,
                        newConversation: {
                            supplier_id: '',
                            order_id: '',
                            subject: '',
                            message: '',
                        },
                        supplierOptions: [],
                        supplierLoading: false,
                        supplierError: null,
                        orderOptions: [],
                        orderLoading: false,
                        orderError: null,
                        deleteLoading: false,
                        autoRefreshPoller: null,
                        userLocale: navigator?.language || navigator?.userLanguage || 'en-US',
                        dateFormatOptions: { dateStyle: 'medium', timeStyle: 'short' },
                        pendingConversationId: null,
                    };
                },
                inject: ['i18n', 'isAdmin', 'currentUser', 'isRtl'],
                computed: {
                    mobile() {
                        const store = useGlobalStore();
                        return store.isMobile || store.isTablet;
                    },
                    tablet() {
                        const store = useGlobalStore();
                        return store.isTablet;
                    },
                    viewerIsSupplier() {
                        return !this.isAdmin;
                    },
                    selectedConversation() {
                        if (!this.selectedConversationId) return null;
                        return this.conversations.find(c => c.id === this.selectedConversationId) || null;
                    },
                    messageContainerStyle() {
                        return {
                            maxHeight: this.mobile ? '60dvh' : '65vh'
                        };
                    },
                    messagesLayoutClass() {
                        const side = this.viewerMessageSide();
                        return side === 'right' ? 'messages-layout-viewer-right' : 'messages-layout-viewer-left';
                    }
                },
                mounted() {
                    this.applyConversationFromRoute();
                    this.fetchConversations();
                    this.fetchSuppliers();
                    this.startAutoRefresh();
                },
                beforeUnmount() {
                    this.stopAutoRefresh();
                },
                watch: {
                    newConversationVisible(val) {
                        if (val && !this.supplierOptions.length) {
                            this.fetchSuppliers();
                        }
                    },
                    '$route.params.conversationId'(newVal, oldVal) {
                        if (newVal === oldVal) {
                            return;
                        }
                        this.applyConversationFromRoute(true);
                    },
                    'newConversation.supplier_id'(newVal) {
                        if (!newVal) {
                            this.orderOptions = [];
                            this.newConversation.order_id = '';
                            return;
                        }
                        this.fetchSupplierOrders(newVal);
                    },
                    'newTrackingDialog.carrier_code'(newVal) {
                        if (newVal !== this.carrierOtherCode) {
                            this.newTrackingDialog.carrier_name = '';
                            this.trackingValidation.carrierOther = false;
                        }
                        if (newVal) {
                            this.trackingValidation.carrier = false;
                        }
                    },
                    'newTrackingDialog.tracking_number'(val) {
                        if (val && val.trim().length) {
                            this.trackingValidation.number = false;
                        }
                    },
                    'newTrackingDialog.carrier_name'(val) {
                        if (val && val.trim().length) {
                            this.trackingValidation.carrierOther = false;
                        }
                    }
                },
                methods: {
                    formatDateTime(value) {
                        if (!value) {
                            return '';
                        }
                        try {
                            const date = value instanceof Date ? value : new Date(value);
                            return new Intl.DateTimeFormat(this.userLocale, this.dateFormatOptions).format(date);
                        } catch (err) {
                            return value;
                        }
                    },
                    startAutoRefresh() {
                        this.stopAutoRefresh();
                        if (!window.HavenCoreFetchClient?.create) {
                            console.warn('HavenCoreFetchClient not available.');
                            return;
                        }

                        const runner = async () => {
                            await this.fetchConversations({ silent: true });
                            if (this.selectedConversationId) {
                                await this.fetchMessages(this.selectedConversationId, { silent: true, detectNew: true });
                            }
                        };
                        this.autoRefreshPoller = window.HavenCoreFetchClient.create({
                            id: 'supplier-portal-auto-refresh-conversations-messages',
                            task: runner,
                            interval: 15000,
                            runOnFocus: true
                        });
                        this.autoRefreshPoller.start();
                    },
                    stopAutoRefresh() {
                        if (this.autoRefreshPoller?.stop) {
                            this.autoRefreshPoller.stop();
                        }
                        this.autoRefreshPoller = null;
                    },
                    async fetchConversations(options = {}) {
                        const { silent = false } = options;
                        const showSpinner = !this.conversationsLoaded && !silent;
                        if (showSpinner) {
                            this.conversationsLoading = true;
                        }
                        this.conversationsError = null;
                        try {
                            const response = await wp.apiFetch({
                                path: '/hc/v1/communications/messaging/conversations',
                                method: 'GET',
                            });
                            this.conversations = response?.conversations || [];
                            this.applyConversationFromRoute(true);
                            if (this.selectedConversationId) {
                                const exists = this.conversations.some(c => c.id === this.selectedConversationId);
                                if (!exists) {
                                    this.deselectConversation();
                                    this.messages = [];
                                }
                            }
                            this.conversationsLoaded = true;
                        } catch (err) {
                            console.error('Failed to fetch conversations', err);
                            this.conversationsError = err?.message || 'Unable to load conversations.';
                        } finally {
                            if (showSpinner) {
                                this.conversationsLoading = false;
                            }
                        }
                    },
                    selectConversation(conversation) {
                        if (!conversation) return;
                        if (this.selectedConversationId === conversation.id && this.messages?.length) {
                            if (this.mobile) {
                                this.$nextTick(() => this.scrollMessagesToBottom('auto'));
                            }
                            return;
                        }
                        this.selectedConversationId = conversation.id;
                        this.pendingConversationId = conversation.id;
                        this.updateConversationRoute(conversation.id);
                    },
                    deselectConversation() {
                        this.selectedConversationId = null;
                        this.pendingConversationId = null;
                        this.updateConversationRoute(null);
                    },
                    async fetchMessages(conversationId, options = {}) {
                        const {
                            silent = false,
                            scrollToLatest = false,
                            detectNew = false,
                        } = options;
                        if (!conversationId) return;
                        const showSpinner = !silent;
                        if (showSpinner) {
                            this.messagesLoading = true;
                            this.messagesPaneReady = false;
                        }
                        this.messagesError = null;
                        const previousLast = this.messages.length ? this.messages[this.messages.length - 1] : null;
                        const previousSignature = previousLast ? `${previousLast.id}-${previousLast.created_at}` : null;
                        try {
                            const response = await wp.apiFetch({
                                path: `/hc/v1/communications/messaging/conversations/${conversationId}/messages`,
                                method: 'GET',
                            });
                            this.messages = response?.messages || [];
                            await this.markConversationRead(conversationId);
                            const newLast = this.messages.length ? this.messages[this.messages.length - 1] : null;
                            const newSignature = newLast ? `${newLast.id}-${newLast.created_at}` : null;
                            if (newLast) {
                                this.updateConversationPreview(conversationId, newLast);
                            }
                            const scrollDelay = showSpinner ? 500 : 0;
                            if (
                                (scrollToLatest && !detectNew) ||
                                (detectNew && newSignature && newSignature !== previousSignature)
                            ) {
                                this.scrollMessagesToBottom(detectNew ? 'smooth' : 'auto', scrollDelay);
                            }
                        } catch (err) {
                            console.error('Failed to fetch messages', err);
                            this.messagesError = err?.message || 'Unable to load messages.';
                        } finally {
                            if (showSpinner) {
                                this.messagesLoading = false;
                            }
                            if (this.selectedConversationId === conversationId) {
                                setTimeout(() => {
                                    this.messagesPaneReady = true;
                                }, 600);
                            }
                        }
                    },
                    async sendMessage() {
                        if (!this.selectedConversation || !this.newMessage.trim()) {
                            return;
                        }
                        this.sendingMessage = true;
                        try {
                            const response = await wp.apiFetch({
                                path: `/hc/v1/communications/messaging/conversations/${this.selectedConversation.id}/messages`,
                                method: 'POST',
                                body: JSON.stringify({
                                    message: this.newMessage.trim(),
                                }),
                                headers: {
                                    'Content-Type': 'application/json'
                                }
                            });
                            if (response) {
                                this.messages.push(response);
                                this.newMessage = '';
                                const idx = this.conversations.findIndex(c => c.id === this.selectedConversation.id);
                                if (idx !== -1) {
                                    this.conversations[idx].last_message_at = response.created_at;
                                    if (this.isAdmin) {
                                        this.conversations[idx].unread_admin_count = 0;
                                    } else {
                                        this.conversations[idx].unread_supplier_count = 0;
                                    }
                                    this.updateConversationPreview(this.selectedConversation.id, response);
                                } else {
                                    this.fetchConversations();
                                }
                                this.scrollMessagesToBottom('smooth');
                                this.messagesPaneReady = true;
                            }
                        } catch (err) {
                            console.error('Failed to send message', err);
                            this.$toast?.error?.(err?.message || 'Unable to send message');
                        } finally {
                            this.sendingMessage = false;
                        }
                    },
                    scrollMessagesToBottom(behavior = 'smooth', delay = 0) {
                        this.$nextTick(() => {
                            const container = this.$refs.messagesContainer;
                            if (!container) {
                                return;
                            }
                            const run = () => {
                                container.scrollTo({
                                    top: container.scrollHeight,
                                    behavior,
                                });
                            };
                            if (delay > 0) {
                                setTimeout(() => requestAnimationFrame(run), delay);
                            } else {
                                requestAnimationFrame(run);
                            }
                        });
                    },
                    async markConversationRead(conversationId) {
                        try {
                            await wp.apiFetch({
                                path: `/hc/v1/communications/messaging/conversations/${conversationId}/mark-read`,
                                method: 'POST',
                            });
                        const idx = this.conversations.findIndex(c => c.id === conversationId);
                    if (idx !== -1) {
                        if (this.isAdmin) {
                            this.conversations[idx].unread_admin_count = 0;
                        } else {
                            this.conversations[idx].unread_supplier_count = 0;
                        }
                    }
                        this.$root?.fetchUnreadSummary?.({ silent: true });
                        } catch (err) {
                            console.error('Failed to mark conversation read', err);
                        }
                    },
                    conversationTitle(conversation) {
                        return conversation.subject || `${this.i18n.conversations} #${conversation.id}`;
                    },
                    conversationMeta(conversation) {
                        const parts = [];
                        if (conversation.status) {
                            parts.push(conversation.status);
                        }
                        if (conversation.last_message_at) {
                            parts.push(this.formatDateTime(conversation.last_message_at));
                        }
                        return parts.join(' • ');
                    },
                    conversationSupplierName(conversation) {
                        const name =
                            conversation?.supplier_name ||
                            conversation?.supplier_display_name ||
                            conversation?.supplier?.display_name ||
                            conversation?.supplier?.name ||
                            conversation?.supplier_user?.display_name ||
                            conversation?.supplier_user?.data?.display_name ||
                            conversation?.supplier_user?.user_nicename;

                        if (name && name.trim().length) {
                            return name;
                        }

                        const supplierId = conversation?.supplier_id;
                        return supplierId ? `${this.i18n.supplier_id} #${supplierId}` : this.i18n.supplier_id;
                    },
                    conversationListDisplayName(conversation) {
                        if (this.isAdmin) {
                            return this.conversationSupplierName(conversation);
                        }
                        return this.i18n.plugin_name || 'Store Team';
                    },
                    conversationListInitials(conversation) {
                        const source = this.conversationListDisplayName(conversation);
                        if (!source) {
                            return 'HC';
                        }
                        return source
                            .split(/\s+/)
                            .filter(Boolean)
                            .map(part => part[0])
                            .join('')
                            .slice(0, 2)
                            .toUpperCase();
                    },
                    applyConversationFromRoute(triggerFetch = false) {
                        const param = this.$route?.params?.conversationId ?? this.pendingConversationId;

                        if (!param) {
                            this.pendingConversationId = null;
                            if (this.selectedConversationId) {
                                this.selectedConversationId = null;
                                if (triggerFetch) {
                                    this.messages = [];
                                }
                            }
                            return;
                        }

                        const parsed = parseInt(param, 10);
                        if (!Number.isFinite(parsed)) {
                            this.pendingConversationId = null;
                            return;
                        }

                        this.pendingConversationId = parsed;

                        const conversation = this.conversations.find(c => c.id === parsed);
                        if (!conversation) {
                            return;
                        }

                        if (this.selectedConversationId !== conversation.id) {
                            this.selectedConversationId = conversation.id;
                            triggerFetch = true;
                        }

                        const hasMessagesForConversation = this.messages.length
                            ? this.messages[this.messages.length - 1]?.conversation_id === conversation.id
                            : false;
                        if (triggerFetch && !hasMessagesForConversation) {
                            this.fetchMessages(conversation.id, { scrollToLatest: true });
                        }

                        this.pendingConversationId = null;
                    },
                    conversationOrderId(conversation) {
                        const raw = conversation?.order_id;
                        if (raw === null || raw === undefined || raw === '') {
                            return null;
                        }
                        const parsed = parseInt(raw, 10);
                        return Number.isFinite(parsed) ? parsed : null;
                    },
                    conversationOrderLabel(conversation) {
                        const orderId = this.conversationOrderId(conversation);
                        if (!orderId) {
                            return '';
                        }
                        const label = this.i18n.order || 'Order';

                        return `${!this.mobile ? label + ' ' : ''}#${orderId}`;
                    },
                    conversationAvatarUrl(conversation) {
                        if (this.isAdmin) {
                            return conversation?.supplier_avatar_url || null;
                        }
                        return conversation?.admin_avatar_url || null;
                    },
                    updateConversationRoute(conversationId) {
                        if (!this.$router) {
                            return;
                        }

                        const currentId = this.$route?.params?.conversationId ?? null;
                        const query = { ...(this.$route?.query || {}) };

                        if (conversationId) {
                            const nextId = String(conversationId);
                            if (currentId === nextId) {
                                return;
                            }
                            this.pendingConversationId = conversationId;
                            this.$router.replace({ name: 'communications', params: { conversationId: nextId }, query });
                            return;
                        }

                        if (!currentId) {
                            return;
                        }

                        this.pendingConversationId = null;
                        this.$router.replace({ name: 'communications', params: { conversationId: undefined }, query });
                    },
                    navigateToOrder(orderId) {
                        if (!orderId || !this.$router) {
                            return;
                        }

                        this.$router.push({ name: 'orders', query: { order_id: orderId } });
                    },
                    isSelected(conversation) {
                        return this.selectedConversationId === conversation.id;
                    },
                    isOwnMessage(message) {
                        const senderType = (message?.sender_type || '').toLowerCase();
                        return this.isAdmin ? senderType === 'admin' : senderType === 'supplier';
                    },
                    viewerMessageSide() {
                        return this.isRtl ? 'right' : 'left';
                    },
                    otherMessageSide() {
                        return this.viewerMessageSide() === 'left' ? 'right' : 'left';
                    },
                    conversationTimestamp(conversation) {
                        return this.formatDateTime(conversation?.last_message_at);
                    },
                    sanitizePreviewText(content) {
                        if (!content) {
                            return '';
                        }
                        const div = document.createElement('div');
                        div.innerHTML = content;
                        const text = div.textContent || div.innerText || '';
                        return text.replace(/\s+/g, ' ').trim();
                    },
                    conversationLastMessage(conversation) {
                        const preview = conversation?.last_message_preview || conversation?.meta?.last_message_preview;
                        if (preview && preview.trim().length) {
                            return preview;
                        }
                        if (conversation?.subject) {
                            return conversation.subject;
                        }
                        return this.i18n.no_messages;
                    },
                    conversationPreviewLine(conversation) {
                        const preview = this.conversationLastMessage(conversation);
                        const sender = (conversation?.last_message_sender_type || '').toLowerCase();
                        const viewerIsSupplier = !this.isAdmin;
                        const isYou = viewerIsSupplier ? sender === 'supplier' : sender === 'admin';
                        const youLabel = this.i18n.you || 'You';
                        return isYou ? `${youLabel}: ${preview}` : preview;
                    },
                    conversationUnreadCount(conversation) {
                        return this.isAdmin
                            ? (conversation?.unread_admin_count || 0)
                            : (conversation?.unread_supplier_count || 0);
                    },
                    updateConversationPreview(conversationId, message) {
                        if (!conversationId || !message) {
                            return;
                        }
                        const idx = this.conversations.findIndex(c => c.id === conversationId);
                        if (idx === -1) {
                            return;
                        }
                        const previewText = this.sanitizePreviewText(message.message);
                        if (previewText) {
                            this.conversations[idx].last_message_preview = previewText;
                        }
                        if (message.created_at) {
                            this.conversations[idx].last_message_at = message.created_at;
                        }
                        if (message.sender_type) {
                            this.conversations[idx].last_message_sender_type = message.sender_type;
                        }
                    },
                    bubbleClass(message) {
                        return this.isOwnMessage(message)
                            ? 'bg-gradient-to-r from-primary via-primary-500 to-primary-400 text-white rounded-2xl rounded-tr-sm shadow-lg shadow-primary/25'
                            : 'bg-surface-100 dark:bg-surface-800/80 text-color rounded-2xl rounded-tl-sm shadow-lg shadow-black/10 border border-surface-200/70 dark:border-surface-700/60';
                    },
                    bubbleMetaClass(message) {
                        return this.isOwnMessage(message) ? 'justify-end pr-2' : 'justify-start pl-2';
                    },
                    messageSenderName(message) {
                        if (message?.sender_name) {
                            return message.sender_name;
                        }
                        if (this.isOwnMessage(message)) {
                            return this.currentUser?.display_name || 'Admin';
                        }
                        return message?.sender_type || this.i18n.conversations;
                    },
                    senderInitials(message) {
                        const source = this.messageSenderName(message) || '';
                        const initials = source
                            .split(/\s+/)
                            .filter(Boolean)
                            .map(part => part[0])
                            .join('')
                            .slice(0, 2)
                            .toUpperCase();
                        return initials || '•';
                    },
                    messageAlignmentClass(message) {
                        const side = this.isOwnMessage(message) ? this.viewerMessageSide() : this.otherMessageSide();
                        return side === 'right' ? 'flex-row-reverse text-right' : 'flex-row text-left';
                    },
                    messageAvatarUrl(message) {
                        if (this.isOwnMessage(message)) {
                            return this.isAdmin
                                ? this.currentUser?.avatar_url || message?.sender_avatar_url || this.selectedConversation?.admin_avatar_url || null
                                : message?.sender_avatar_url || null;
                        }
                        return (
                            message?.sender_avatar_url ||
                            (this.isAdmin ? this.selectedConversation?.supplier_avatar_url || null : this.selectedConversation?.admin_avatar_url || null)
                        );
                    },
                    messageRowClass(message) {
                        const side = this.isOwnMessage(message) ? this.viewerMessageSide() : this.otherMessageSide();
                        return side === 'right' ? 'chat-message-row--self' : 'chat-message-row--other';
                    },
                    formatMessageDate(date) {
                        if (!date) {
                            return '';
                        }
                        try {
                            const value = date instanceof Date ? date : new Date(date);
                            return new Intl.DateTimeFormat(this.userLocale, { timeStyle: 'short' }).format(value);
                        } catch (err) {
                            return this.formatDateTime(date);
                        }
                    },
                    async fetchSuppliers() {
                        if (!this.isAdmin) {
                            this.supplierOptions = [];
                            return;
                        }
                        this.supplierLoading = true;
                        this.supplierError = null;
                        try {
                            const response = await wp.apiFetch({
                                path: '/hc/v1/communications/messaging/suppliers',
                                method: 'GET',
                            });
                            this.supplierOptions = (response?.suppliers || []).map(user => ({
                                label: user.name,
                                value: user.id,
                                subtitle: user.email || '',
                            }));
                        } catch (err) {
                            console.error('Failed to load suppliers', err);
                            this.supplierError = err?.message || 'Unable to load suppliers.';
                        } finally {
                            this.supplierLoading = false;
                        }
                    },
                    async fetchSupplierOrders(supplierId) {
                        if (!supplierId) {
                            this.orderOptions = [];
                            this.newConversation.order_id = '';
                            return;
                        }

                        this.orderLoading = true;
                        this.orderError = null;
                        try {
                            const response = await wp.apiFetch({
                                path: `/hc/v1/communications/messaging/suppliers/${supplierId}/orders`,
                                method: 'GET',
                            });
                            this.orderOptions = (response?.orders || []).map(order => ({
                                label: `#${order.id} • ${order.customer || ''}`.trim(),
                                value: order.id,
                                detail: order.status,
                            }));
                        } catch (err) {
                            console.error('Failed to load orders', err);
                            this.orderError = err?.message || 'Unable to load orders.';
                        } finally {
                            this.orderLoading = false;
                        }
                    },
                    openNewConversation() {
                        this.newConversationVisible = true;
                        this.newConversation = {
                            supplier_id: this.isAdmin ? '' : this.currentUser?.ID,
                            order_id: '',
                            subject: '',
                            message: '',
                        };
                        if (!this.isAdmin && this.currentUser?.ID) {
                            this.fetchSupplierOrders(this.currentUser.ID);
                        } else {
                            this.orderOptions = [];
                        }
                        this.orderError = null;
                    },
                    closeNewConversation() {
                        this.newConversationVisible = false;
                    },
                    async createConversation() {
                        const supplierId = parseInt(this.newConversation.supplier_id, 10);
                        if (!supplierId || !this.newConversation.subject) {
                            return;
                        }
                        this.creatingConversation = true;
                        try {
                            const payload = {
                                supplier_id: supplierId,
                                order_id: this.newConversation.order_id ? parseInt(this.newConversation.order_id, 10) : undefined,
                                subject: this.newConversation.subject,
                                message: this.newConversation.message,
                            };
                            const response = await wp.apiFetch({
                                path: '/hc/v1/communications/messaging/conversations',
                                method: 'POST',
                                body: JSON.stringify(payload),
                                headers: {
                                    'Content-Type': 'application/json'
                                },
                            });
                            if (response?.id) {
                                this.conversations.unshift(response);
                                this.selectConversation(response);
                                this.newConversationVisible = false;
                            }
                        } catch (err) {
                            console.error('Failed to create conversation', err);
                            this.$toast?.error?.(err?.message || 'Unable to create conversation');
                        } finally {
                            this.creatingConversation = false;
                        }
                    },
                    confirmDeleteConversation() {
                        if (!this.selectedConversation || !this.isAdmin || this.deleteLoading) {
                            return;
                        }

                        this.$confirm.require({
                            group: 'conversation-delete',
                            message: this.i18n.confirm_delete_conversation,
                            header: this.i18n.delete_conversation,
                            icon: 'pi pi-exclamation-triangle',
                            acceptLabel: this.i18n.confirm,
                            rejectLabel: this.i18n.dismiss,
                            accept: () => this.deleteConversation(),
                        });
                    },
                    async deleteConversation() {
                        if (!this.selectedConversation || !this.isAdmin) {
                            return;
                        }

                        this.deleteLoading = true;
                        try {
                            await wp.apiFetch({
                                path: `/hc/v1/communications/messaging/conversations/${this.selectedConversation.id}`,
                                method: 'DELETE',
                            });
                            this.selectedConversationId = null;
                            this.messages = [];
                            await this.fetchConversations();
                        } catch (err) {
                            console.error('Failed to delete conversation', err);
                            this.$toast?.error?.(err?.message || 'Unable to delete conversation');
                        } finally {
                            this.deleteLoading = false;
                        }
                    },
                },
                template: `
                    <div class="h-full flex flex-col lg:flex-row overflow-hidden">
                        <div
                            class="flex flex-col lg:w-1/3"
                            v-if="!mobile || !selectedConversation"
                        >
                            <div class="px-4 py-3 flex items-center justify-between gap-2 flex-wrap">
                                <div class="min-w-0">
                                    <h1 class="text-lg font-semibold truncate">{{ i18n.conversations }}</h1>
                                    <p class="text-xs text-muted-color" v-if="conversationsError">{{ conversationsError }}</p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <Button 
                                        :label="i18n.new_conversation" 
                                        icon="pi pi-plus"
                                        size="small"
                                        severity="contrast"
                                        @click="openNewConversation"
                                    />
                                </div>
                            </div>
                            <Divider class="my-0" />
                            <div class="flex-1 overflow-y-auto px-1">
                                <Transition name="slide-scale" mode="out-in">
                                    <template v-if="conversationsLoading">
                                        <div class="p-4 space-y-3" key="loading">
                                            <div
                                                v-for="n in 4"
                                                :key="'conv-skel-' + n"
                                                class="p-3 rounded-xl conversation-loader flex items-center gap-3"
                                            >
                                                <Skeleton shape="circle" size="42px" class="flex-shrink-0" />
                                                <div class="flex-1 min-w-0 space-y-2">
                                                    <div class="flex items-center justify-between gap-2">
                                                        <Skeleton height="0.85rem" width="45%" />
                                                        <Skeleton height="0.75rem" width="25%" />
                                                    </div>
                                                    <div class="flex items-center justify-between gap-2">
                                                        <Skeleton height="0.75rem" width="70%" />
                                                        <Skeleton shape="circle" size="20px" />
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                    <template v-else-if="!conversations.length">
                                        <div class="p-6 text-center text-sm text-muted-color" key="empty">
                                            {{ i18n.no_conversations }}
                                        </div>
                                    </template>
                                    <TransitionGroup v-else tag="div" class="flex flex-col gap-y-2" key="list" name="conversation-slide">
                                        <template v-for="conversation in conversations" :key="conversation.id">
                                            <div
                                                @click="selectConversation(conversation)"
                                                class="p-3 cursor-pointer transition hover:bg-surface-100 rounded-xl flex items-center gap-3 conversation-item"
                                                :class="{
                                                    'conversation-selected': isSelected(conversation)
                                                }"
                                            >
                                                <div
                                                    class="conversation-avatar flex-shrink-0"
                                                    :class="conversationAvatarUrl(conversation) ? 'conversation-avatar--image' : ''"
                                                >
                                                    <img
                                                        v-if="conversationAvatarUrl(conversation)"
                                                        :src="conversationAvatarUrl(conversation)"
                                                        alt=""
                                                        class="conversation-avatar-image"
                                                    />
                                                    <span v-else>{{ conversationListInitials(conversation) }}</span>
                                                </div>
                                                <div class="flex-1 min-w-0">
                                                    <div class="flex items-center justify-between gap-2">
                                                        <span class="font-semibold text-sm truncate">
                                                            {{ conversationListDisplayName(conversation) }}
                                                        </span>
                                                        <span class="text-xs text-muted-color whitespace-nowrap">
                                                            {{ conversationTimestamp(conversation) }}
                                                        </span>
                                                    </div>
                                                    <div class="flex items-center justify-between gap-2 mt-1 text-xs text-muted-color">
                                                        <span class="truncate">{{ conversationPreviewLine(conversation) }}</span>
                                                        <Badge
                                                            v-if="conversationUnreadCount(conversation) > 0"
                                                            :value="conversationUnreadCount(conversation)"
                                                            severity="danger"
                                                            size="small"
                                                        />
                                                    </div>
                                                </div>
                                            </div>
                                        </template>
                                    </TransitionGroup>
                                </Transition>
                            </div>
                        </div>

                        <Divider v-if="mobile && !selectedConversation" layout="horizontal" class="lg:hidden my-0" />
                        <Divider v-if="!mobile && !tablet" layout="vertical" class="mx-0" />

                        <div
                            v-if="!mobile || selectedConversation"
                            class="flex-1 flex flex-col bg-surface-50 dark:bg-surface-900/60 rounded-t-none lg:rounded-tr-xl lg:rounded-br-xl"
                        >
                            <template v-if="selectedConversation">
                                <div class="px-4 py-3 flex flex-wrap gap-2 items-center justify-between sticky top-0 z-10 bg-surface-50/95 dark:bg-surface-900/95 backdrop-blur">
                                    <div class="flex items-center gap-2">
                                        <Button
                                            v-if="mobile"
                                            icon="pi pi-arrow-left"
                                            size="small"
                                            text
                                            @click="deselectConversation"
                                        />
                                        <div class="flex flex-col">
                                            <h2 class="text-lg font-semibold">{{ conversationTitle(selectedConversation) }}</h2>
                                            <p class="text-xs text-muted-color">{{ conversationMeta(selectedConversation) }}</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <Button
                                            v-if="conversationOrderId(selectedConversation)"
                                            :label="conversationOrderLabel(selectedConversation)"
                                            icon="pi pi-shopping-cart"
                                            severity="contrast"
                                            size="small"
                                            @click="navigateToOrder(conversationOrderId(selectedConversation))"
                                        />
                                    </div>
                                </div>

                                <Divider class="my-0" />

                                <div
                                    :class="[
                                        'flex-1 overflow-y-auto space-y-1 min-h-[250px]',
                                        messagesLayoutClass,
                                        {
                                            'messages-pane': !messagesLoading && selectedConversation,
                                            'messages-pane--ready': messagesPaneReady && !messagesLoading && selectedConversation,
                                            'p-2': !mobile
                                        }
                                    ]"
                                    :style="messageContainerStyle"
                                    ref="messagesContainer"
                                >
                                    <Transition name="slide-scale" mode="out-in">
                                        <template v-if="messagesLoading">
                                            <div class="space-y-4" key="messages-loading">
                                                <div
                                                    v-for="n in 4"
                                                    :key="'msg-skel-' + n"
                                                    class="chat-message-row"
                                                    :class="messageRowClass({ sender_type: n % 2 === 0 ? 'admin' : 'supplier' })"
                                                >
                                                    <div
                                                        class="chat-message-inner flex items-start gap-3 max-w-full"
                                                        :class="[
                                                            'chat-message-inner-base',
                                                            messageAlignmentClass({ sender_type: n % 2 === 0 ? 'admin' : 'supplier' })
                                                        ]"
                                                    >
                                                        <div
                                                            class="chat-avatar"
                                                            :class="n % 2 === 0 ? 'chat-avatar--self' : 'chat-avatar--other'"
                                                        >
                                                            <Skeleton shape="circle" size="42px" />
                                                        </div>
                                                        <div
                                                            class="w-full chat-bubble-card"
                                                            :class="n % 2 === 0 ? 'chat-bubble-card--self' : 'chat-bubble-card--other'"
                                                        >
                                                            <Skeleton height="18px" width="60%" class="mb-2" />
                                                            <Skeleton height="55px" borderRadius="1.35rem" />
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </template>
                                        <template v-else-if="messagesError">
                                            <div class="text-sm text-red-500" key="messages-error">{{ messagesError }}</div>
                                        </template>
                                        <template v-else-if="!messages.length">
                                            <div class="flex flex-col items-center justify-center text-center gap-3 py-10 px-6 text-muted-color" key="messages-empty">
                                                <span class="text-4xl">
                                                    <i class="pi pi-comments" style="font-size: 3rem;"></i>
                                                </span>
                                                <div class="space-y-1">
                                                    <p class="text-sm font-semibold">{{ i18n.no_messages }}</p>
                                                    <p class="text-xs opacity-80">{{ i18n.start_messaging }}</p>
                                                </div>
                                            </div>
                                        </template>
                                        <template v-else>
                                            <TransitionGroup
                                                tag="div"
                                                class="flex flex-col gap-2"
                                                name="message-slide"
                                                :key="selectedConversationId ? 'messages-' + selectedConversationId : 'messages'"
                                            >
                                                <div
                                                    v-for="message in messages"
                                                    :key="message.id"
                                                    class="chat-message-row"
                                                    :class="messageRowClass(message)"
                                                >
                                                    <div
                                                        class="chat-message-inner flex items-start gap-3 max-w-full"
                                                        :class="[
                                                            'chat-message-inner-base',
                                                            messageAlignmentClass(message)
                                                        ]"
                                                    >
                                                        <div
                                                            class="chat-avatar"
                                                            :class="[
                                                                isOwnMessage(message) ? 'chat-avatar--self' : 'chat-avatar--other',
                                                                messageAvatarUrl(message) ? 'chat-avatar--image' : ''
                                                            ]"
                                                        >
                                                            <img
                                                                v-if="messageAvatarUrl(message)"
                                                                :src="messageAvatarUrl(message)"
                                                                alt=""
                                                                class="chat-avatar-image"
                                                            />
                                                            <span v-else>{{ senderInitials(message) }}</span>
                                                        </div>
                                                        <Card
                                                            class="w-full chat-bubble-card"
                                                            :class="isOwnMessage(message) ? 'chat-bubble-card--self' : 'chat-bubble-card--other'"
                                                        >

                                                            <template #title>
                                                                <div
                                                                    class="flex items-center gap-3 uppercase tracking-wide opacity-80"
                                                                    :class="isOwnMessage(message) ? 'justify-start' : 'justify-end'"
                                                                >
                                                                    <span class="font-semibold truncate">{{ messageSenderName(message) }}</span>
                                                                </div>
                                                            </template>
                                                            <template #content>
                                                                <p class="chat-message-text text-md leading-relaxed m-0">
                                                                    {{ message.message }}
                                                                </p>
                                                            </template>
                                                            <template #footer>
                                                                <div
                                                                    class="flex items-center gap-3 uppercase tracking-wide opacity-80"
                                                                    :class="isOwnMessage(message) ? 'justify-end' : 'justify-start'"
                                                                >
                                                                    <span class="whitespace-nowrap">{{ formatMessageDate(message.created_at) }}</span>
                                                                </div>
                                                            </template>
                                                        </Card>
                                                    </div>
                                                </div>
                                            </TransitionGroup>
                                        </template>
                                    </Transition>
                                </div>

                                <Divider class="my-0" />

                                <div class="p-3">
                                    <div class="flex items-end gap-2">
                                        <InputGroup class="w-full">
                                            <Textarea
                                                v-model="newMessage"
                                                :placeholder="i18n.message_placeholder"
                                                auto-resize
                                                rows="1"
                                                class="flex-1 min-h-[40px]"
                                            />
                                            <InputGroupAddon class="p-0">
                                                <Button
                                                    :label="mobile ? null : i18n.send_message"
                                                    icon="pi pi-send"
                                                    :loading="sendingMessage"
                                                    :disabled="!newMessage.trim()"
                                                    class="h-full"
                                                    @click="sendMessage"
                                                />
                                            </InputGroupAddon>
                                        </InputGroup>
                                    </div>
                                </div>
                            </template>
                            <template v-else>
                                <div class="flex-1 flex items-center justify-center p-8 text-sm text-muted-color">
                                    {{ i18n.select_conversation }}
                                </div>
                            </template>
                        </div>
                        <Dialog
                            :visible="newConversationVisible"
                            @update:visible="val => newConversationVisible = val"
                            modal
                            :header="i18n.new_conversation"
                            :style="{ width: '500px' }"
                        >
                            <div class="space-y-4">
                                <div>
                                    <label class="text-sm font-medium block mb-2">{{ i18n.order_id }}</label>
                                    <Select
                                        v-model="newConversation.order_id"
                                        :options="orderOptions"
                                        :loading="orderLoading"
                                        class="w-full"
                                        :placeholder="i18n.order_id"
                                        option-label="label"
                                        option-value="value"
                                        :virtualScrollerOptions="{ itemSize: 48 }"
                                    >
                                        <template #option="{ option }">
                                            <div class="flex flex-col">
                                                <span class="font-medium">{{ option.label }}</span>
                                                <span class="text-xs text-muted-color" v-if="option.detail">{{ option.detail }}</span>
                                            </div>
                                        </template>
                                    </Select>
                                    <p v-if="orderError" class="text-xs text-red-500 mt-1">{{ orderError }}</p>
                                </div>

                                <div>
                                    <FloatLabel variant="on">
                                        <InputText v-model="newConversation.subject" class="w-full" />
                                        <label>{{ i18n.subject }}</label>
                                    </FloatLabel>
                                </div>

                                <div>
                                    <FloatLabel variant="on">
                                        <Textarea
                                            v-model="newConversation.message"
                                            rows="4"
                                            class="w-full"
                                        />
                                        <label>{{ i18n.initial_message }}</label>
                                    </FloatLabel>
                                </div>

                                <div class="flex justify-end gap-2">
                                    <Button
                                        :label="i18n.cancel"
                                        severity="secondary"
                                        text
                                        @click="closeNewConversation"
                                    />
                                        <Button
                                            :label="i18n.create"
                                            icon="pi pi-send"
                                            :loading="creatingConversation"
                                            :disabled="!newConversation.subject || (!newConversation.supplier_id && isAdmin)"
                                            @click="createConversation"
                                        />
                                </div>
                            </div>
                        </Dialog>

                        <ConfirmDialog group="conversation-delete">
                            <template #container="{ message, acceptCallback, rejectCallback }">
                                <div class="flex flex-col items-center p-8 text-center bg-surface-0 dark:bg-surface-900 rounded-2xl gap-4 max-w-sm">
                                    <div class="rounded-full bg-primary text-primary-contrast inline-flex justify-center items-center h-24 w-24 -mt-20 shadow-lg shadow-primary/40">
                                        <i class="pi pi-question text-4xl"></i>
                                    </div>
                                    <div class="space-y-2">
                                        <span class="font-bold text-2xl block">{{ message.header }}</span>
                                        <p class="text-sm text-muted-color m-0">{{ message.message }}</p>
                                    </div>
                                    <div class="flex items-center justify-center gap-3 w-full">
                                        <Button
                                            :label="message.acceptLabel || i18n.confirm"
                                            severity="danger"
                                            class="w-32"
                                            @click="acceptCallback"
                                        />
                                        <Button
                                            :label="message.rejectLabel || i18n.dismiss"
                                            outlined
                                            class="w-32"
                                            @click="rejectCallback"
                                        />
                                    </div>
                                </div>
                            </template>
                        </ConfirmDialog>
                    </div>
                `
            };

            const Routes = [
                { 
                    path: '/orders', 
                    name: 'orders',
                    component: Orders,
                    meta: { title: initialData.i18n.orders }

                },
                { 
                    path: '/communications/:conversationId?', 
                    name: 'communications',
                    component: Communications,
                    meta: { title: initialData.i18n.communications }
                },
                {
                    path: '/change-password',
                    name: 'change-password',
                    component: ChangePassword,
                },
                {
                    path: '/products', 
                    name: 'products',
                    component: Products,
                    meta: { title: initialData.i18n.products }
                },
                // Catch-all fallback: redirect unknown paths
                {
                    path: '/:pathMatch(.*)*',
                    redirect: { name: 'orders' }
                }
            ]
            
            const router = VueRouter.createRouter({
                history: VueRouter.createWebHashHistory(),
                routes: Routes
            });

            router.beforeEach((to, from, next) => {
                // Set the document title using route's meta title or default
                document.title = `${initialData.i18n.title} | ${to.meta.title || 'Default Title'}`;

                // Check if user needs to change password
                const needsToChangePassword = currentUser?.attributes?.needs_to_change_password;

                // If user needs to change password or the property doesn't exist, redirect to 'change-password'
                if (needsToChangePassword === true || needsToChangePassword === undefined) {
                    if (to.name !== 'change-password') {
                        return next({ name: 'change-password' });
                    }
                }

                // If user does not need to change password, allow navigation as usual
                if (needsToChangePassword === false || needsToChangePassword === undefined) {
                    if (to.name === 'change-password') {
                        return next({ name: 'orders' }); // Redirect back to orders or another valid route
                    }
                }

                // Handle unknown routes (fallback)
                if (!to.matched.length) {
                    return next({ name: 'orders' });
                }

                next();
            });


            app.component('havencore-app', {
                inject: ['mobileIcon', 'currentUser'],
                template: `

                    <Toaster
                        richColors
                        theme="system"
                        :position="mobile ? 'top-center' : 'bottom-center'"
                        :closeButton="!mobile"
                    />


                    <!-- Mobile Overlay (Shadow Behind Sidebar) -->
                    <transition name="fade-overlay" appear>
                        <div 
                            v-if="mobile && isSidebarOpen"
                            @click="toggleSidebar"
                            class="fixed inset-0 z-40 backdrop-overlay"
                        ></div>
                    </transition>


                    <div class="flex flex-col h-screen">
                        <!-- Static Header -->
                        <header class=" py-4 px-5 flex justify-between items-center z-20">
                            <div class="flex items-center justify-center gap-x-2">

                                <Button 
                                    severity="secondary"
                                    raised
                                    @click="goToHome"
                                    v-tooltip="{
                                        value: i18n.back_to_home,
                                        position: isRtl ? 'right' : 'left'
                                    }"
                                >
                                    <template #icon>
                                        <Image
                                            :src="mobileIcon"
                                            alt="Home"
                                            :pt="{
                                                image: {
                                                class:'w-6 h-6 rounded'
                                                }
                                            }"
                                        />
                                    </template>
                                </Button>


                                <Button 
                                    icon="pi pi-bars" 
                                    v-if="mobile && currentUser.attributes && !currentUser.attributes.needs_to_change_password"
                                    severity="secondary"
                                    raised
                                    @click="toggleSidebar" 
                                />
                            </div>

                            <!-- User Avatar Trigger -->
                            <Button 
                                variant="text"
                                rounded
                                raised
                                @click="toggleUserMenu"
                            >
                                <template #icon>
                                    <div class="w-8 h-8 flex items-center justify-center rounded-full bg-automatic font-medium text-sm uppercase">
                                        {{ getUserAvatarOrInitials }}
                                    </div>
                                </template>
                            </Button>

                            <Menu
                                ref="userMenu"
                                :model="userMenuItems"
                                popup
                            />

                        </header>

                        <!-- Main Area: Sidebar + Content -->
                        <div class="flex flex-1 overflow-hidden shadow-sm">
                            <!-- Static Sidebar -->
                            <aside v-if="!mobile" :class="['w-64', 'flex', 'flex-col', { 'collapsed': isCollapsed }]">
                                <!-- Sidebar items -->
                                <ul v-if="currentUser.attributes && !currentUser.attributes.needs_to_change_password" class="list-none m-0 p-4 space-y-2  text-sm flex-grow shrink-0">
                                    <li v-for="(item, index) in sidebarItems" :key="index" style="line-height: 1rem;">
                                    <router-link 
                                        :to="item.route" 
                                        class="aside-link"
                                        :class="{
                                            'aside-link-active': isRouteActive(item.route)
                                        }"
                                            v-tooltip="isCollapsed ? item.name : ''" 

                                        >
                                            <span class="relative inline-flex items-center">
                                                <OverlayBadge
                                                    v-if="showUnreadBadge(item)"
                                                    :value="communicationsUnreadCount"
                                                    severity="danger"
                                                    size="small"
                                                >
                                                    <i :class="item.icon + ' shrink-0 transform-none'" /> 
                                                </OverlayBadge>
                                                <i v-else :class="item.icon + ' shrink-0 transform-none'" /> 
                                            </span>

                                            <!-- Label fades in/out -->
                                            <transition name="fade-label">
                                                <span v-if="!isCollapsed" class="font-medium">{{ item.name }}</span>
                                            </transition>
                                        </router-link>
                                    </li>
                                </ul>

                                <!-- Collapse/Expand Button at the bottom -->
                                <div v-if="currentUser.attributes && !currentUser.attributes.needs_to_change_password" class="flex justify-center p-4 w-full mb-12">
                                    <button 
                                        @click="collapseSidebar" 
                                        class="aside-button mt-auto justify-center"
                                        v-tooltip="isCollapsed ? i18n.expand : ''" 
                                    >
                                        <i :class="{
                                                'pi pi-chevron-left': !isCollapsed && !isRtl,    
                                                'pi pi-chevron-right': !isCollapsed && isRtl,    
                                                'pi pi-chevron-right': isCollapsed && !isRtl,    
                                                'pi pi-chevron-left': isCollapsed && isRtl       
                                            }"
                                        />
                                        <span v-if="!isCollapsed" class="font-medium text-sm leading-tight">{{ i18n.collapse }}</span>
                                    </button>
                                </div>
                            </aside>

                            <!-- Mobile Sidebar (only on mobile) -->
                            <transition 
                                name="slide" 
                                @before-enter="sidebarBeforeEnter" 
                                @enter="sidebarEnter" 
                                @leave="sidebarLeave"
                            >
                                <aside 
                                    v-if="mobile && isSidebarOpen"
                                    class="fixed top-0 w-64 bg-automatic shadow-md h-full z-50"
                                >
                                    <ul class="list-none m-0 p-4 space-y-2  text-sm flex-grow">
                                        <li v-for="(item, index) in sidebarItems" :key="index">
                                    <router-link 
                                        :to="item.route" 
                                        class="aside-link"
                                        :class="{
                                            'aside-link-active': isRouteActive(item.route)
                                        }"
                                        @click="closeSidebar"
                                    >
                                                <span class="relative inline-flex items-center">
                                                    <i :class="item.icon + ' transform-none'"></i>
                                                </span>
                                                <span class="font-medium text-sm leading-tight">{{ item.name }}</span>
                                                <Badge
                                                    v-if="showUnreadBadge(item)"
                                                    :value="communicationsUnreadCount"
                                                    severity="danger"
                                                    size="small"
                                                />
                                            </router-link>
                                        </li>
                                    </ul>
                                </aside>
                            </transition>

                            <!-- Scrollable Main Content -->
                            <div class="view-container relative flex-1 h-full overflow-hidden">
                                <router-view v-slot="{ Component }">
                                    <transition name="card-swap">
                                        <component
                                            :is="Component"
                                            :key="$route.name || 'route-view'"
                                            class="app-content-styled h-full overflow-y-hidden"
                                        />
                                    </transition>
                                </router-view>
                            </div>
                        </div>
                    </div>
                `,
                props: [],
                data() {
                    // Access the Pinia store
                    const globalStore = useGlobalStore();

                    // Use a computed property to ensure reactivity
                    const mobile = Vue.computed(() => globalStore.isMobile || globalStore.isTablet);

                    const Rtl = Vue.computed(() => globalStore.isRtl);
                    
                    return {
                        i18n: this.$root.i18n || {},  // Fallback to empty object if i18n is not defined
                        sidebarItems: this.$root.sidebarItems || {},

                        isCollapsed: false, // Sidebar collapse state
                        mobile: mobile,
                        isRtl: Rtl,
                        isSidebarOpen: false,  // New state to track the sidebar visibility on mobile

                        userMenuItems: [] // inital empty then populate only on Mount hook the prevent undefined

                    };
                },
                mounted() {

                    this.userMenuItems = [
                        {
                            label: this.currentUser.display_name,
                            items: [
                                { separator: true },
                                {
                                    label: this.i18n.logout,
                                    icon: 'pi pi-sign-out',
                                    command: () => this.logout()
                                }
                            ]
                        }
                    ];

                    const needsToChangePassword = this.currentUser?.attributes?.needs_to_change_password;


                    // Check if 'needs_to_change_password' exists and is truthy
                    if (needsToChangePassword === true || needsToChangePassword === undefined) {
                        // If 'needs_to_change_password' is true or doesn't exist, redirect to 'change-password'
                        this.$router.push({ name: 'change-password' });
                        if (needsToChangePassword === undefined) {
                            // console.log('Routing to change-password because it does not exist.');
                        }
                    }

                    this.isCollapsed = true;

                    // ✅ Ask for notification permission (if not already handled)
                    if ('Notification' in window) {
                        const currentPermission = Notification.permission;

                        if (currentPermission === 'granted') {
                            console.log('[Notifications] Already granted ✅');
                        } else if (currentPermission === 'denied') {
                            console.warn('[Notifications] Previously denied ❌');
                        } else if (currentPermission === 'default') {
                            Notification.requestPermission().then(permission => {
                                if (permission === 'granted') {
                                    console.log('[Notifications] Permission granted ✅');
                                } else {
                                    console.warn('[Notifications] Permission denied or dismissed');
                                }
                            });
                        }
                    }
                },
                computed: {
                    getUserAvatarOrInitials() {
                        const avatar = this.currentUser.avatar || '';
                        const isDefault = avatar.includes('d=mm') ||
                                        avatar.includes('d=identicon') ||
                                        avatar.includes('d=retro') ||
                                        avatar.includes('d=blank') ||
                                        avatar.includes('d=monsterid') ||
                                        avatar.includes('d=wavatar') ||
                                        avatar.includes('d=robohash');

                        if (isDefault) {
                            const name = (this.currentUser.display_name || this.currentUser.user_login || 'U')
                                .trim()
                                .replace(/\s+/g, ' ');

                            const words = name.split(/[\s\-]+/).filter(Boolean);

                            if (words.length === 0) return 'U';
                            if (words.length === 1) return words[0].substring(0, 2).toUpperCase();

                            return (words[0][0] + words[1][0]).toUpperCase();
                        }

                        return avatar;
                    },
                    communicationsUnreadCount() {
                        return this.$root?.communicationsUnreadCount || 0;
                    },
                    hasUnreadCommunications() {
                        return this.$root?.hasUnreadCommunications || false;
                    },
                },
                methods: {
                    showUnreadBadge(item) {
                        if (typeof this.$root?.showUnreadBadge === 'function') {
                            return this.$root.showUnreadBadge(item);
                        }
                        return false;
                    },
                    isRouteActive(route) {
                        if (typeof this.$root?.isRouteActive === 'function') {
                            return this.$root.isRouteActive(route);
                        }
                        return false;
                    },
                    // =============================
                    // UserMenu Methods
                    // =============================

                    toggleUserMenu(event) {
                        this.$refs.userMenu.toggle(event);
                    },
                    logout() {
                        window.location.href = this.currentUser.logout_url;
                    },

                    // =============================
                    // General Utility Methods
                    // =============================

                    goToHome() {
                        window.location.href = '<?= home_url(); ?>';
                    },

                    // =============================
                    // Sidebar Toggle & Collapse Methods
                    // =============================
                    collapseSidebar() {
                        // console.log("Sidebar collapse clicked", this.isCollapsed);  // Debugging line to check if the method is fired
                        this.isCollapsed = !this.isCollapsed;
                    },

                    toggleSidebar() {
                        if (this.mobile) {
                            // console.log("Sidebar toggle clicked", this.isSidebarOpen);  // Debugging line to check if the method is fired
                            this.isSidebarOpen = !this.isSidebarOpen;
                        }
                    },

                    closeSidebar() {
                        if (this.mobile) {
                            // Set a timeout of 200ms before closing the sidebar
                            setTimeout(() => {
                                this.isSidebarOpen = false;  // Close the sidebar after 200ms
                            }, 0);
                        }
                    },

                    // =============================
                    // Sidebar Transition Methods
                    // =============================
                    sidebarBeforeEnter(sidebarElement) {
                        // Set the initial position off-screen (left for LTR, right for RTL)
                        if (this.isRtl) {
                            sidebarElement.style.transform = 'translateX(100%)';  // Right off-screen for RTL
                        } else {
                            sidebarElement.style.transform = 'translateX(-100%)';  // Left off-screen for LTR
                        }
                    },

                    sidebarEnter(sidebarElement, done) {
                        // After the transition begins, move the sidebar into place
                        sidebarElement.offsetHeight;  // Trigger reflow to apply styles correctly
                        sidebarElement.style.transition = 'transform 0.3s ease-in-out, opacity 0.3s ease-in-out';
                        sidebarElement.style.transform = 'translateX(0)';  // Move to the visible position
                        done();  // Finish the transition
                    },

                    sidebarLeave(sidebarElement, done) {
                        // When leaving, slide the sidebar off-screen again
                        if (this.isRtl) {
                            sidebarElement.style.transform = 'translateX(100%)';  // Slide off to the right for RTL
                        } else {
                            sidebarElement.style.transform = 'translateX(-100%)';  // Slide off to the left for LTR
                        }
                    }
                },        
                watch: {
                    // Watch for changes to mobile global store
                    mobile(newValue, oldValue) {                
                        if (!this.mobile) {
                            this.isSidebarOpen = false;  // Ensure sidebar is closed when moving to desktop view
                        }

                    }
                },
            });

            app.use(router);

            // First, make sure Pinia is initialized in the parent app
            const pinia = Pinia.createPinia();  // Initialize Pinia

            // Use Pinia (make sure Pinia is initialized from globalStore.js)
            app.use(pinia); // Pinia is initialized first

            // Call the global store initialization function after Pinia is installed
            initializeGlobalStore();

            app.mount('#havencore-app');
        </script>
    </body>
</html>
