<?php
    // Check if user is logged in 
    // if (!is_user_logged_in()) {
    //     wp_redirect(wp_login_url($_SERVER['REQUEST_URI']));
    //     exit;
    // }

    use HavenCore\Utils\ScriptHelpers;

    ScriptHelpers::loadApiFetch(); // ✅ Injects wp-api-fetch and nonce safely

    ScriptHelpers::loadVue([
        'withDotLottie'    => true,
        'withConfetti'     => true,
        'withFrontendCss'  => true,
        'withMainStyle'    => false,
    ]);


    $cart_payload = [];

    $countries = WC()->countries->get_countries();
    $states = WC()->countries->get_states();    


    // Determine the correct currency code based on active plugins
    $currency_code = get_woocommerce_currency(); // default fallback
    $currency_symbol = get_woocommerce_currency_symbol($currency_code); // default

    // TranslatePress
    if (function_exists('trp')) {
        $currency_code = apply_filters('trp_wc_get_currency', $currency_code);
        $currency_symbol = get_woocommerce_currency_symbol($currency_code);
    }

    // WPML (WooCommerce Multilingual)
    elseif (defined('WCML_VERSION') && function_exists('wcml_get_woocommerce_currency')) {
        $currency_code = wcml_get_woocommerce_currency();
        $currency_symbol = get_woocommerce_currency_symbol($currency_code);
    }

    // WOOCS – WooCommerce Currency Switcher
    elseif (class_exists('WOOCS') && isset($GLOBALS['WOOCS'])) {
        $currency_code = $GLOBALS['WOOCS']->current_currency;
        $currency_symbol = get_woocommerce_currency_symbol($currency_code);
    }

    // YayCurrency
    elseif (class_exists('YayCurrency\Helper\Functions')) {
        $currency_code = \YayCurrency\Helper\Functions::get_current_currency();
        $currency_symbol = get_woocommerce_currency_symbol($currency_code);
    }

    if (WC()->cart && !WC()->cart->is_empty()) {
        WC()->cart->calculate_totals(); // 🔄 Ensure shipping rates are available

        $items = [];

        foreach (WC()->cart->get_cart() as $cart_item_key => $item) {
            $product = $item['data'];
            if (!($product instanceof \WC_Product)) {
                continue;
            }

            $items[] = [
                'key'             => $cart_item_key,
                'product_id'      => $item['product_id'],
                'variation_id'    => $item['variation_id'],
                'name'            => $product->get_name(),
                'sku'             => $product->get_sku(),
                'quantity'        => $item['quantity'],
                'subtotal'        => floatval($item['line_subtotal']),
                'price'           => floatval(wc_get_price_to_display($product)),
                'total'           => floatval($item['line_total']),
                'image'           => wp_get_attachment_image_url($product->get_image_id(), 'thumbnail'),
                'permalink'       => $product->is_visible() ? $product->get_permalink() : '',
                'variation'       => $item['variation'],
                'is_downloadable' => $product->is_downloadable(),
                'is_virtual'      => $product->is_virtual(),
                'stock_status'    => $product->get_stock_status(),
                'manage_stock'    => $product->get_manage_stock(),
                'stock_quantity'  => $product->get_stock_quantity(),
            ];
        }

        // 🚚 Initialize empty shipping methods (will be populated later)
        $shipping_methods_data = [];


        $cart_payload = [
            'items' => $items,
            'totals' => [
                'subtotal'        => floatval(WC()->cart->get_subtotal()),
                'total'           => floatval(WC()->cart->get_total('edit')),
                'tax'             => floatval(WC()->cart->get_taxes_total()),
                'discount'        => floatval(WC()->cart->get_discount_total()),
                'currency_code'   => $currency_code,
                'currency_symbol' => $currency_symbol,
            ],
            'shipping_methods' => $shipping_methods_data ?? [],

        ];
    } else {
         // 🚨 Redirect to the cart page if it's empty
        wp_safe_redirect(wc_get_cart_url());
        exit;
    }


    // Get the current user and their locale
    $current_user = wp_get_current_user();
    $user_locale = get_user_locale($current_user); // Get the WordPress locale for the user
    $user_attributes = get_user_meta($current_user->ID, 'attributes', true);

    // If the user has a locale, switch to it
    if ($user_locale) {
        switch_to_locale($user_locale);
    }


    // Customer Setup
    $customer = new \WC_Customer( $current_user->ID );

    $preloaded_customer_information = [
        'email' => $customer->get_email(),
        'shipping' => [
            'first_name' => $customer->get_shipping_first_name(),
            'last_name'  => $customer->get_shipping_last_name(),
            'company'    => $customer->get_shipping_company(),
            'address_1'  => $customer->get_shipping_address(),
            'address_2'  => $customer->get_shipping_address_2(),
            'postcode'   => $customer->get_shipping_postcode(),
            'city'       => $customer->get_shipping_city(),
            'country'    => $customer->get_shipping_country(),
            'state'      => $customer->get_shipping_state(),
            'phone'      => $customer->get_shipping_phone(),
        ],
        'billing' => [
            'first_name' => $customer->get_billing_first_name(),
            'last_name'  => $customer->get_billing_last_name(),
            'company'    => $customer->get_billing_company(),
            'address_1'  => $customer->get_billing_address_1(),
            'address_2'  => $customer->get_billing_address_2(),
            'postcode'   => $customer->get_billing_postcode(),
            'city'       => $customer->get_billing_city(),
            'country'    => $customer->get_billing_country(),
            'state'      => $customer->get_billing_state(),
            'phone'      => $customer->get_billing_phone(),
        ],
    ];
            
    // Get the 'lang' and 'dir' attributes dynamically based on the locale
    ob_start();
    language_attributes();  // This function prints the 'lang' and 'dir' attributes
    $locale_attributes = ob_get_clean();  // Capture the output
?>

<html <?= $locale_attributes; ?> style="overflow: hidden;">

    <head>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <?php wp_site_icon(); ?>

        <style>
            .button-go-back {
                width: 3rem !important;
                height: 3rem !important;
                flex-shrink: 0;
            }

            .current-route {
                color: var(--p-surface-900) !important;
            }

            .current-route:hover {
                color: var(--p-surface-600) !important;  /* Adjust hover effect for dark mode */
            }

            .route {
                color: var(--p-breadcrumb-item-color);
            }

            .route:hover {
                color: var(--p-surface-900) !important;
            }

            /* Dark Mode Support */
            @media (prefers-color-scheme: dark) {
                .current-route {
                    color: var(--p-surface-200) !important;  /* Lighter color for dark mode */
                }

                .current-route:hover {
                    color: var(--p-surface-400) !important;  /* Adjust hover effect for dark mode */
                }

                .route:hover {
                    color: var(--p-surface-100) !important;  /* Adjust hover effect for dark mode */
                }
            }

            /* 
            // Customer Place Order Page
            // ===========================================================================
            */

            /* Card Swap transition using opacity and horizontal slide */
            .__hc_customer_place_order_card-swap-enter-active,
            .__hc_customer_place_order_card-swap-leave-active {
                transition: opacity 300ms ease-in-out, transform 300ms ease-in-out; /* Shorter, faster transition */
                position: relative; /* Cards remain in their flow in the layout */
                width: 100%;
                top: 0;
                left: 0;
                will-change: opacity, transform; /* Optimize for opacity and transform */
                z-index: 1;
            }

            /* New card enters quickly (slide and fade effect) */
            .__hc_customer_place_order_card-swap-enter-from {
                opacity: 0; /* Start invisible */
                transform: translateX(-30px); /* Slide from the right by default */
            }

            /* If in RTL mode, the card enters from the left */
            html[dir='rtl'] .__hc_customer_place_order_card-swap-enter-from {
                transform: translateX(50px); /* Slide from the left in RTL */
            }

            .__hc_customer_place_order_card-swap-enter-to {
                opacity: 1; /* Fade to visible */
                transform: translateX(0); /* Slide to final position */
            }

            /* Old card fades out and slides */
            .__hc_customer_place_order_card-swap-leave-from {
                opacity: 1; /* Start visible */
                transform: translateX(0); /* Stay in place initially */
            }

            /* If in RTL mode, old card slides to the right */
            html[dir='rtl'] .__hc_customer_place_order_card-swap-leave-to {
                opacity: 0; /* Fade out */
                transform: translateX(-50px); /* Slide to the right in RTL */
            }

            /* If in LTR, slide to the left */
            .__hc_customer_place_order_card-swap-leave-to {
                opacity: 0; /* Fade out */
                transform: translateX(50px); /* Slide to the left by default */
            }

            /* Remove unnecessary transition delays */
            .__hc_customer_place_order_card-swap-enter-active,
            .__hc_customer_place_order_card-swap-leave-active {
                transition-delay: 0ms;
            }


        </style>
    </head>
    <body>

        <div id="havencore-app-place-order" class="hc-place-order-app">
            <havencore-app-place-order></havencore-app-place-order>
        </div>

        <script type="application/json" id="app-initial-data">
            <?= json_encode([
                'i18n' => [
                    // ─── Core & Navigation ─────────────────────────────────────────────
                    'title'                 => __( 'Place Order',  HAVEN_CORE_TEXT_DOMAIN ),
                    'default_title'         => __( 'My Store',     HAVEN_CORE_TEXT_DOMAIN ),
                    'back_to_home'          => __( 'Back to Home', HAVEN_CORE_TEXT_DOMAIN ),
                    'home_alt'              => __( 'Home',         HAVEN_CORE_TEXT_DOMAIN ),
                    'cart'                  => __( 'Cart',         HAVEN_CORE_TEXT_DOMAIN ),
                    'information'           => __( 'Information',  HAVEN_CORE_TEXT_DOMAIN ),
                    'shipping'              => __( 'Shipping',     HAVEN_CORE_TEXT_DOMAIN ),
                    'confirmation'          => __( 'Confirmation', HAVEN_CORE_TEXT_DOMAIN ),
                    'actions'               => __( 'Actions',      HAVEN_CORE_TEXT_DOMAIN ),

                    // ─── Buttons & Actions ────────────────────────────────────────────
                    'continue'              => __( 'Continue',              HAVEN_CORE_TEXT_DOMAIN ),
                    'continue_to_shipping'  => __( 'Continue to shipping',  HAVEN_CORE_TEXT_DOMAIN ),
                    'place_order_button'    => __( 'Place Order',           HAVEN_CORE_TEXT_DOMAIN ),
                    'edit'                  => __( 'Edit',                  HAVEN_CORE_TEXT_DOMAIN ),
                    'change'                => __( 'Change',                HAVEN_CORE_TEXT_DOMAIN ),

                    // ─── Field Labels ─────────────────────────────────────────────────
                    'first_name_label'      => __( 'First Name',             HAVEN_CORE_TEXT_DOMAIN ),
                    'last_name_label'       => __( 'Last Name',              HAVEN_CORE_TEXT_DOMAIN ),
                    'company_label'         => __( 'Company',                HAVEN_CORE_TEXT_DOMAIN ),
                    'street_address_label'  => __( 'Street Address',         HAVEN_CORE_TEXT_DOMAIN ),
                    'street_address_2_label'=> __( 'Street Address 2',       HAVEN_CORE_TEXT_DOMAIN ),
                    'zip_label'             => __( 'ZIP',                    HAVEN_CORE_TEXT_DOMAIN ),
                    'city_label'            => __( 'City',                   HAVEN_CORE_TEXT_DOMAIN ),
                    'country_label'         => __( 'Country',                HAVEN_CORE_TEXT_DOMAIN ),
                    'state_label'           => __( 'State/Province',         HAVEN_CORE_TEXT_DOMAIN ),
                    'phone_number_label'    => __( 'Phone Number',           HAVEN_CORE_TEXT_DOMAIN ),
                    'email_label'           => __( 'Email',                  HAVEN_CORE_TEXT_DOMAIN ),

                    // ─── Step Titles & Panels ──────────────────────────────────────────
                    'shipping_info_title'   => __( 'Shipping Information',   HAVEN_CORE_TEXT_DOMAIN ),
                    'billing_info_title'    => __( 'Billing Information',    HAVEN_CORE_TEXT_DOMAIN ),
                    'order_summary_title'   => __( 'Order Summary',          HAVEN_CORE_TEXT_DOMAIN ),
                    'total_label'           => __( 'Total',                  HAVEN_CORE_TEXT_DOMAIN ),
                    'subtotal_label'        => __( 'Subtotal',               HAVEN_CORE_TEXT_DOMAIN ),
                    'discount_label'        => __( 'Discount',               HAVEN_CORE_TEXT_DOMAIN ),
                    'shipping_label'        => __( 'Shipping',               HAVEN_CORE_TEXT_DOMAIN ),





                    // ─── Wizard Labels ─────────────────────────────────────────────────
                    'same_as_shipping'              => __( 'Same as shipping',                  HAVEN_CORE_TEXT_DOMAIN ),
                    'contact_label'                 => __( 'Contact',                           HAVEN_CORE_TEXT_DOMAIN ),
                    'ship_to_label'                 => __( 'Ship to',                           HAVEN_CORE_TEXT_DOMAIN ),
                    'select_shipping_method'        => __( 'Select Shipping Method',            HAVEN_CORE_TEXT_DOMAIN ),
                    'order_notes_optional'          => __( 'Order Notes (Optional)',            HAVEN_CORE_TEXT_DOMAIN ),
                    'redirecting_to_cart'           => __( 'Redirecting to cart',               HAVEN_CORE_TEXT_DOMAIN ),

                    // ─── Validation & Toast Messages ──────────────────────────────────
                    'missing_field_summary'         => __( 'Missing Field',                     HAVEN_CORE_TEXT_DOMAIN ),
                    'missing_field_detail_prefix'   => __( 'Please fill out the',               HAVEN_CORE_TEXT_DOMAIN ),
                    'shipping_method_required_summary' => __( 'Shipping Method Required',       HAVEN_CORE_TEXT_DOMAIN ),
                    'shipping_method_required_detail'  => __( 'Please select a shipping method before placing your order.', HAVEN_CORE_TEXT_DOMAIN ),

                    // ─── Notifications ────────────────────────────────────────────────
                    'success_summary'               => __( 'Thank you! 🎉', HAVEN_CORE_TEXT_DOMAIN ),
                    'success_detail_default'        => __( 'Your order was placed successfully. A confirmation is on its way to your inbox.', HAVEN_CORE_TEXT_DOMAIN ),

                    'order_failed_summary'          => __( 'Order Could Not Be Completed', HAVEN_CORE_TEXT_DOMAIN ),
                    'order_failed_detail_default'   => __( 'We hit a snag while processing your order. Please check your info and try again.', HAVEN_CORE_TEXT_DOMAIN ),

                    'error_summary_general'         => __( 'Something Went Wrong', HAVEN_CORE_TEXT_DOMAIN ),
                    'error_detail_unexpected'       => __( 'An unexpected error occurred. Please refresh the page or try again shortly.', HAVEN_CORE_TEXT_DOMAIN ),

                    'placing_order'                 => __( 'Processing your order…', HAVEN_CORE_TEXT_DOMAIN ),

                ],
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

            const currentCustomer = <?= json_encode($preloaded_customer_information) ?>

            // console.log('currentUser inital Data:', currentUser);
            
            const cartData = <?= json_encode([
                'cart' => $cart_payload
            ]); ?>;


            const cart = Vue.reactive({
                items: cartData?.cart?.items || [],
                totals: cartData?.cart?.totals || {},
                shipping_methods: cartData?.cart?.shipping_methods || []
            });


            // console.log('cart Data:', cartData);

            const initialData = JSON.parse(document.getElementById('app-initial-data')?.textContent || '{}');

            
            const countries = <?= json_encode($countries); ?>;
            const states = <?= json_encode($states); ?>;

            // console.log('initialData:', initialData);
            // console.log(countries)

            const utils = {
                formatNumber(value, symbol = '') {
                    const number = parseFloat(value);

                    if (isNaN(number)) {
                        return { symbol, value: '0.00' };
                    }

                    return {
                        symbol,
                        value: new Intl.NumberFormat(undefined, {
                            style: 'decimal',
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2,
                        }).format(number)
                    };
                },

                round(value, decimals = 2) {
                    const factor = Math.pow(10, decimals);
                    return Math.round(parseFloat(value) * factor) / factor;
                },

                toNumber(value, fallback = 0) {
                    const num = parseFloat(value);
                    return isNaN(num) ? fallback : num;
                },

                debounce(func, wait) {
                    let timeout;
                    return function (...args) {
                        clearTimeout(timeout);
                        timeout = setTimeout(() => func.apply(this, args), wait);
                    };
                },
            };

            const useCustomerFormStore = Pinia.defineStore('customerForm', () => {
                const STORAGE_KEY = 'hc_place_order_customerFormData';

                const sameAsShipping = Vue.ref(true);
                const form = Vue.reactive({
                    email: '',
                    shipping: {
                        first_name: '',
                        last_name: '',
                        company: '',
                        address_1: '',
                        address_2: '',
                        postcode: '',
                        city: '',
                        country: '',
                        state: '',
                        phone: ''
                    },
                    billing: {
                        first_name: '',
                        last_name: '',
                        company: '',
                        address_1: '',
                        address_2: '',
                        postcode: '',
                        city: '',
                        country: '',
                        state: '',
                        phone: ''
                    },
                    shipping_method: null,
                    orderNotes: ''
                });

                function syncBillingWithShipping() {
                    form.billing = { ...form.shipping };
                }

                function clearBilling() {
                    form.billing = {
                        first_name: '',
                        last_name: '',
                        company: '',
                        address_1: '',
                        address_2: '',
                        postcode: '',
                        city: '',
                        country: '',
                        state: '',
                        phone: ''
                    };
                }

                function toggleSameAsShipping(value) {
                    sameAsShipping.value = value;
                    if (value) syncBillingWithShipping();
                    else clearBilling();
                }

                function isFieldFilled(field) {
                    return field && field.trim() !== '';
                }

                function hasStates(countryCode) {
                    return Object.keys(window._hc_states?.[countryCode] || {}).length > 0;
                }

                function validateForm() {
                    if (!isFieldFilled(form.email)) {
                        return { valid: false, field: 'email' };
                    }

                    const requiredFields = ['first_name', 'last_name', 'address_1', 'postcode', 'city', 'country', 'phone'];
                    
                    // Include state conditionally for shipping
                    if (hasStates(form.shipping.country)) {
                        requiredFields.push('state');
                    }

                    for (const field of requiredFields) {
                        if (!isFieldFilled(form.shipping[field])) {
                            return { valid: false, field };
                        }
                    }

                    if (!sameAsShipping.value) {
                        const billingRequiredFields = ['first_name', 'last_name', 'address_1', 'postcode', 'city', 'country', 'phone'];
                        
                        if (hasStates(form.billing.country)) {
                            billingRequiredFields.push('state');
                        }

                        for (const field of billingRequiredFields) {
                            if (!isFieldFilled(form.billing[field])) {
                                return { valid: false, field: `billing.${field}` };
                            }
                        }
                    }

                    return { valid: true };
                }

                function saveToSession() {
                    const payload = {
                        sameAsShipping: sameAsShipping.value,
                        form: JSON.parse(JSON.stringify(form))
                    };
                    sessionStorage.setItem(STORAGE_KEY, JSON.stringify(payload));
                }

                function clearSessionStorage() {
                    sessionStorage.removeItem(STORAGE_KEY);
                }


                function loadFromSession() {
                    const storedForm = sessionStorage.getItem(STORAGE_KEY);
                    if (!storedForm) {
                        // console.warn('No session data found');
                        return false;
                    }

                    try {
                        const parsedStoredForm = JSON.parse(storedForm);
                        // console.log('✅ Loaded session data:', parsed);
                        Object.assign(form, parsedStoredForm.form || {});
                        sameAsShipping.value = parsedStoredForm.sameAsShipping ?? true;
                        return true;
                    } catch (err) {
                        // console.warn('Session load failed:', err);
                        return false;
                    }
                }



                const debouncedSave = utils.debounce(saveToSession, 300);

                // Watch form + flag changes
                Vue.watch(
                    () => ({ ...form, sameAsShipping: sameAsShipping.value }),
                    debouncedSave,
                    { deep: true }
                );

                // Initialize from storage
                const loaded = loadFromSession();

                if (!loaded && currentCustomer) {
                    // 1) hydrate the form…
                    form.email = currentCustomer.email;
                    Object.assign(form.shipping, currentCustomer.shipping);
                    Object.assign(form.billing,  currentCustomer.billing);

                    // 2) now compare them field-by-field and set the flag
                    const ship = currentCustomer.shipping;
                    const bill = currentCustomer.billing;

                    // simple JSON-based deep-equal:
                    sameAsShipping.value = JSON.stringify(ship) === JSON.stringify(bill);

                    // —or— a manual field check, if you want to ignore e.g. empty strings vs null:
                    // const keys = Object.keys(ship);
                    // const isSame = keys.every(k => ship[k] === bill[k]);
                    // sameAsShipping.value = isSame;
                }


                // ✅ Properly access cart.value in computed
                const shippingMethodDetails = Vue.computed(() => {
                    const c = cart;
                    if (!c || !Array.isArray(c.shipping_methods)) return null;
                    return c.shipping_methods.find(m => m.id === form.shipping_method) || null;
                });

                const calculatedTotal = Vue.computed(() => {
                    const { subtotal = 0, tax = 0, discount = 0 } = cart.totals || {};
                    const shipping = shippingMethodDetails.value?.cost || 0;
                    return (subtotal + tax + shipping - discount).toFixed(2);
                });

                async function getAvailableShippingMethods() {
                    try {
                        const result = await wp.apiFetch({
                            path: '/hc/v1/customers/checkout/shipping-methods',
                            method: 'POST',
                            data: { form }
                        });

                        cart.shipping_methods = result.shipping_methods;

                        // 💣 Reset shipping method if it's no longer available
                        const availableIds = result.shipping_methods.map(m => m.id);
                        if (!availableIds.includes(form.shipping_method)) {
                            form.shipping_method = null;
                        }

                    } catch (error) {
                        console.error('❌ Failed to fetch shipping methods:', error.message || error);
                        
                        // ✅ Show generic error toast
                        toast?.error(this.i18n?.error_summary_general || 'Something went wrong', {
                            description: error.message || this.i18n?.error_detail_unexpected || 'Could not load shipping methods.',
                            duration: 4000
                        });
                    }
                }


                return {
                    sameAsShipping,
                    form,
                    syncBillingWithShipping,
                    clearBilling,
                    toggleSameAsShipping,
                    validateForm,
                    shippingMethodDetails,
                    calculatedTotal,
                    getAvailableShippingMethods,
                    clearSessionStorage,
                };

            });

            const useLocalUiStore = Pinia.defineStore('localUi', () => {
                // 🕐 Submitting state (used during shipping step)
                const isShippingSubmitting = Vue.ref(false);

                // ✅ Order completed successfully
                const orderPlacedSuccessfully = Vue.ref(false);

                return {
                    isShippingSubmitting,
                    orderPlacedSuccessfully
                };
            });


            
            const app = Vue.createApp({
                data() {

                    const breadcrumbItems = [
                        {
                            label: initialData.i18n.cart || 'Cart',
                            icon: 'pi pi-shopping-cart',
                            to: { name: 'cart' }
                        },
                        {
                            label: initialData.i18n.information || 'Information',
                            icon: 'pi pi-user',
                            to: { name: 'information' }
                        },
                        {
                            label: initialData.i18n.shipping || 'Shipping',
                            icon: 'pi pi-truck',
                            to: { name: 'shipping' }                        
                        },
                        {
                            label: initialData.i18n.confirmation || 'Confirmation',
                            icon: 'pi pi-check-circle',
                            to: { name: 'confirm' }
                        }
                    ];



                    // Log breadcrumbItems initialization to check if it's set correctly
                    // console.log('breadcrumbItems initialized:', breadcrumbItems);

                    return {
                        i18n: initialData.i18n,
                        countries: countries || {},
                        states: states || {},
                        currentUser: currentUser,
                        cart: cart,
                        mobileIcon: "<?= esc_url(get_site_icon_url()); ?>",
                        breadcrumbItems: breadcrumbItems || [], // breadcrumb items for navigation
                    };
                },
                provide() {
                    return {
                        i18n: this.i18n,
                        countries: this.countries || {},
                        states: this.states || {},
                        currentUser: this.currentUser,
                        breadcrumbItems: this.breadcrumbItems,
                        mobileIcon: this.mobileIcon,
                    };
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
            // app.use(PrimeVue.ToastService);
            app.use(VueSonner)
            console.log(VueSonner)
            app.use(DotLottieVue.default, {
                wasmUrl: '<?= HAVEN_CORE_URL . 'assets/wasm/dotlottie-loader.php'; ?>',
                name: 'DotLottiePlayer'
            });



            // 🧭 Directives
            app.directive('tooltip', PrimeVue.Tooltip);

            // 🧾 Form Inputs
            app.component('InputGroup', PrimeVue.InputGroup);  // Register InputGroup component
            app.component('InputGroupAddon', PrimeVue.InputGroupAddon);  // Register InputGroup component

            app.component('InputText', PrimeVue.InputText);
            app.component('Textarea', PrimeVue.Textarea);
            app.component('FloatLabel', PrimeVue.FloatLabel);
            app.component('IconField', PrimeVue.IconField);
            app.component('InputIcon', PrimeVue.InputIcon);
            app.component('ToggleSwitch', PrimeVue.ToggleSwitch);
            app.component('Checkbox', PrimeVue.Checkbox);


            // 🕹️ UI Controls
            app.component('Button', PrimeVue.Button);
            app.component('RadioButton', PrimeVue.RadioButton);

            app.component('Menu', PrimeVue.Menu);

            // 🧱 Layout & Containers
            app.component('Menubar', PrimeVue.Menubar);
            app.component('Breadcrumb', PrimeVue.Breadcrumb);
            app.component('Panel', PrimeVue.Panel);
            app.component('Card', PrimeVue.Card);
            app.component('Dialog', PrimeVue.Dialog);
            app.component('ScrollPanel', PrimeVue.ScrollPanel);
            app.component('Select', PrimeVue.Select);
            app.component('Divider', PrimeVue.Divider);



            // 🎨 Visual Feedback
            app.component('Skeleton', PrimeVue.Skeleton);
            app.component('Image', PrimeVue.Image);
            app.component('ProgressSpinner', PrimeVue.ProgressSpinner);


            const Cart = {
                template: `
                    <div class="p-4 flex justify-center items-center h-full text-xl font-semibold">
                        <div class="flex justify-center items-center h-full gap-x-4">
                            <ProgressSpinner 
                                style="width: 40px; height: 40px"
                                strokeWidth="3" 
                                fill="transparent"
                                animationDuration="1s" 
                                aria-label="redirecting to cart" 
                            />
                        
                            <p>{{ i18n.redirecting_to_cart }}</p>
                        </div>
                    </div>
                `,
                inject: ['i18n'],
                mounted() {
                    // Redirect to WooCommerce cart page
                    window.location.href = '/cart/'; // 🔁 Replace with actual cart URL if it's customized
                }
            };


            // 📩 Information Step Component
            const Information = {
                template: `
                    <div class="space-y-6">
                        <h2 class="text-xl font-semibold">{{ i18n.information }}</h2>

                        <!-- 📧 Email -->
                        <InputGroup>
                            <InputGroupAddon>
                                <i class="pi pi-envelope"></i>
                            </InputGroupAddon>
                            <FloatLabel variant="on">
                                <InputText 
                                    id="email" 
                                    v-model="form.email"
                                    :invalid="!!validationErrors.email" 
                                />
                                <label for="email">{{ i18n.email_label }}</label>
                            </FloatLabel>
                        </InputGroup>

                        <!-- 🚞 Shipping Info Panel -->
                        <Panel :toggleable="true">
                            <template #header>
                                <div class="flex items-center gap-2">
                                    <i class="pi pi-truck text-primary" />
                                    <span class="p-panel-title">{{ i18n.shipping_info_title }}</span>
                                </div>
                            </template>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                                <template v-for="(field, index) in shippingFields" :key="'shipping-' + index">
                                    <div v-if="field.model !== 'state' || hasStates(form.shipping.country)">
                                        <InputGroup :class="field.class">
                                            <InputGroupAddon>
                                                <i :class="field.icon" />
                                            </InputGroupAddon>
                                            <FloatLabel variant="on">
                                                <Select
                                                    v-if="field.type === 'dropdown'"
                                                    :id="field.id"
                                                    v-model="form.shipping[field.model]"
                                                    :options="field.model === 'state' ? shippingStateOptions : (field.options || countryOptions)"
                                                    optionLabel="label"
                                                    optionValue="value"
                                                    :filter="true"
                                                    :showClear="true"
                                                    :invalid="getValidationError(field)"
                                                    :pt="{ label: { style: 'width: 2rem !important;' } }"
                                                />
                                                <InputText
                                                    v-else
                                                    @input="field.onInput && field.onInput(this)"
                                                    @blur="field.onBlur && field.onBlur(this)"
                                                    @change="field.onChange && field.onChange(this)"
                                                    :id="field.id"
                                                    v-model="form.shipping[field.model]"
                                                    :invalid="getValidationError(field)"
                                                />
                                                <label :for="field.id">{{ field.label }}</label>
                                            </FloatLabel>
                                        </InputGroup>
                                    </div>
                                </template>
                            </div>
                        </Panel>

                        <!-- 🔄 Billing Info Checkbox -->
                        <Panel header="Billing Information" :toggleable="false" :collapsed="customerFormStore.sameAsShipping">
                            <template #header>
                                <div class="w-full flex justify-between items-center">
                                    <div class="flex items-center gap-2">
                                        <i class="pi pi-credit-card text-primary" />
                                        <span class="p-panel-title">{{ i18n.billing_info_title }}</span>
                                    </div>
                                    <div class="flex gap-x-2 justify-center items-center">
                                        <Checkbox inputId="customerFormStore.sameAsShipping" v-model="customerFormStore.sameAsShipping" binary />
                                        <label for="customerFormStore.sameAsShipping">{{ i18n.same_as_shipping }}</label>
                                    </div>
                                </div>
                            </template>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                                <template v-for="(field, index) in billingFields" :key="'billing-' + index">
                                    <div v-if="field.model !== 'state' || hasStates(form.billing.country)">
                                        <InputGroup :class="field.class">
                                            <InputGroupAddon>
                                                <i :class="field.icon" />
                                            </InputGroupAddon>
                                            <FloatLabel variant="on">
                                                <Select
                                                    v-if="field.type === 'dropdown'"
                                                    :id="field.id"
                                                    v-model="form.billing[field.model]"
                                                    :options="field.model === 'state' ? billingStateOptions : (field.options || countryOptions)"
                                                    optionLabel="label"
                                                    optionValue="value"
                                                    :filter="true"
                                                    :showClear="true"
                                                    :invalid="getValidationError(field, 'billing')"
                                                    :pt="{ label: { style: 'width: 2rem !important;' } }"
                                                />
                                                <InputText
                                                    v-else
                                                    @input="field.onInput && field.onInput(this)"
                                                    @blur="field.onBlur && field.onBlur(this)"
                                                    @change="field.onChange && field.onChange(this)"
                                                    :id="field.id"
                                                    v-model="form.billing[field.model]"
                                                    :invalid="getValidationError(field, 'billing')"
                                                />
                                                <label :for="field.id">{{ field.label }}</label>
                                            </FloatLabel>
                                        </InputGroup>
                                    </div>
                                </template>
                            </div>
                        </Panel>

                        <!-- 🚀 Continue -->
                        <div class="flex justify-center md:justify-start mt-6">
                            <Button 
                                :label="i18n.continue_to_shipping"
                                :icon="isRtl ? 'pi pi-angle-left' : 'pi pi-angle-right'" 
                                :iconPos="isRtl ? 'left' : 'right'" 
                                severity="secondary" 
                                raised 
                                @click="handleContinue"
                            />
                        </div>
                    </div>
                `,
                inject: ['i18n', 'countries', 'states'],
                data() {
                    
                    const globalStore = useGlobalStore();

                    const customerFormStore = useCustomerFormStore();

                    const isRtl = Vue.computed(() => globalStore.isRtl);


                    return {
                        customerFormStore,
                        isRtl,

                        form: customerFormStore.form,
                        validationErrors: Vue.reactive({}),
                        cart: cart,
                        

                        shippingFields: [
                            { id: 'first_name',  model: 'first_name',               label: this.i18n.first_name_label,                icon: 'pi pi-user',           class: '' },
                            { id: 'last_name',   model: 'last_name',                label: this.i18n.last_name_label,                 icon: 'pi pi-user',           class: '' },
                            { id: 'company',     model: 'company',                  label: this.i18n.company_label,                   icon: 'pi pi-building',       class: '', optional: true },
                            { id: 'address_1',   model: 'address_1',                label: this.i18n.street_address_label,            icon: 'pi pi-home',           class: 'md:col-span-2' },
                            { id: 'address_2',   model: 'address_2',                label: this.i18n.street_address_2_label,          icon: 'pi pi-home',           class: 'md:col-span-2', optional: true },
                            { id: 'postcode',    model: 'postcode',                 label: this.i18n.zip_label,                       icon: 'pi pi-map',            class: '', onBlur: vm => vm.customerFormStore.getAvailableShippingMethods() },
                            { id: 'city',        model: 'city',                     label: this.i18n.city_label,                      icon: 'pi pi-map-marker',     class: '' },
                            { id: 'country',     model: 'country',                  label: this.i18n.country_label,                   icon: 'pi pi-globe',          class: '', type: 'dropdown' },
                            { id: 'state',       model: 'state',                    label: this.i18n.state_label,                     icon: 'pi pi-map-marker',     class: '', type: 'dropdown', dynamicOptions: true },
                            { id: 'phone',       model: 'phone',                    label: this.i18n.phone_number_label,              icon: 'pi pi-phone',          class: '' }
                        ],      

                        billingFields: [  
                            { id: 'billing_first_name', model: 'first_name',        label: this.i18n.first_name_label,                icon: 'pi pi-user',           class: '' },
                            { id: 'billing_last_name',  model: 'last_name',         label: this.i18n.last_name_label,                 icon: 'pi pi-user',           class: '' },
                            { id: 'billing_company',    model: 'company',           label: this.i18n.company_label,                   icon: 'pi pi-building',       class: '', optional: true },
                            { id: 'billing_address_1',  model: 'address_1',         label: this.i18n.street_address_label,            icon: 'pi pi-home',           class: 'md:col-span-2' },
                            { id: 'billing_address_2',  model: 'address_2',         label: this.i18n.street_address_2_label,          icon: 'pi pi-home',           class: 'md:col-span-2', optional: true },
                            { id: 'billing_postcode',   model: 'postcode',          label: this.i18n.zip_label,                       icon: 'pi pi-map',            class: '' },
                            { id: 'billing_city',       model: 'city',              label: this.i18n.city_label,                      icon: 'pi pi-map-marker',     class: '' },
                            { id: 'billing_country',    model: 'country',           label: this.i18n.country_label,                   icon: 'pi pi-globe',          class: '', type: 'dropdown' },
                            { id: 'billing_state',      model: 'state',             label: this.i18n.state_label,                     icon: 'pi pi-map-marker',     class: '', type: 'dropdown', dynamicOptions: true },
                            { id: 'billing_phone',      model: 'phone',             label: this.i18n.phone_number_label,              icon: 'pi pi-phone',          class: '' }
                        ]



                    };
                },
                computed: {
                    countryOptions() {
                        return Object.entries(this.countries).map(([code, name]) => ({ label: name, value: code }));
                    },
                    stateOptions() {
                        // fallback based on current form context (e.g., billing country)
                        const countryCode = this.form.billing?.country || this.form.shipping?.country;
                        const stateList = this.states?.[countryCode] || {};
                        return Object.entries(stateList).map(([code, name]) => ({
                        label: name,
                        value: code
                        }));
                    },
                    shippingStateOptions() {
                        const country = this.form.shipping.country;
                        const list = this.states?.[country] || {};
                        return Object.entries(list).map(([code, name]) => ({ label: name, value: code }));
                    },
                    billingStateOptions() {
                        const country = this.form.billing.country;
                        const list = this.states?.[country] || {};
                        return Object.entries(list).map(([code, name]) => ({ label: name, value: code }));
                    },


                },
                methods: {
                    hasStates(countryCode) {
                        return Object.keys(this.states?.[countryCode] || {}).length > 0;
                    },
                    getValidationError(field, prefix = '') {
                        const key = prefix ? `${prefix}.${field.model}` : field.model;
                        return !!this.validationErrors[key];
                    },

                    handleContinue() {
                        this.validationErrors = {}; // clear previous

                        // ✅ Validate email separately
                        if (!this.form.email || this.form.email.trim() === '') {
                            this.validationErrors.email = true;
                        }

                        // ✅ Validate shipping fields
                        for (const field of this.shippingFields) {
                            if (field.optional) continue;

                            // 🔍 Skip state if the country doesn't have states
                            if (field.model === 'state' && !this.hasStates(this.form.shipping.country)) continue;

                            const value = this.form.shipping[field.model];
                            if (!value || value.trim?.() === '') {
                                this.validationErrors[field.model] = true;
                            }
                        }

                        // ✅ Validate billing fields only if different from shipping
                        if (!this.customerFormStore.sameAsShipping) {
                            for (const field of this.billingFields) {
                                if (field.optional) continue;

                                // 🔍 Skip state if the country doesn't have states
                                if (field.model === 'state' && !this.hasStates(this.form.billing.country)) continue;

                                const value = this.form.billing[field.model];
                                if (!value || value.trim?.() === '') {
                                    this.validationErrors[`billing.${field.model}`] = true;
                                }
                            }
                        }

                        // 🚨 If errors exist, notify user
                        if (Object.keys(this.validationErrors).length > 0) {
                            const firstInvalidField = Object.keys(this.validationErrors)[0];
                            this.$toast.warning(this.i18n.missing_field_summary, {
                                description: `${this.i18n.missing_field_detail_prefix} “${firstInvalidField.replace('billing.', '')}” field.`,
                                duration: 3000
                            });

                            return;
                        }

                        // ✅ Proceed
                        this.$router.push({ name: 'shipping' });
                    },

                },
                watch: {
                    'customerFormStore.sameAsShipping'(newVal, oldVal) {
                        // ✅ Only clear if user explicitly switched from true → false
                        if (oldVal === true && newVal === false) {
                            this.customerFormStore.clearBilling();
                        } else if (newVal === true) {
                            this.customerFormStore.syncBillingWithShipping();
                        }
                    },
                    'form.shipping': {
                        deep: true,
                        handler() {
                            if (this.customerFormStore.sameAsShipping) {
                                this.customerFormStore.syncBillingWithShipping();
                            }
                        }
                    },
                    'form.shipping.country'(newVal, oldVal) {
                        if (newVal !== oldVal) {
                            this.form.shipping.state = '';
                            this.$utils.debounce(() => this.customerFormStore.getAvailableShippingMethods(), 400)();
                        }

                        if (this.customerFormStore.sameAsShipping) {
                            this.customerFormStore.syncBillingWithShipping();
                        }
                    },

                    'form.shipping.state'(newVal, oldVal) {
                        if (newVal !== oldVal) {
                           this.$utils.debounce(() => this.customerFormStore.getAvailableShippingMethods(), 400)();
                        }
                    },

                    'form.billing.country'(newVal, oldVal) {
                        if (newVal !== oldVal) {
                            this.form.billing.state = '';
                        }
                    },
                    
                },

            };

            // 🚚 Shipping Step Component
            const Shipping = {
                template: `
                    <div class="space-y-6">
                        <h2 class="text-xl font-semibold">{{ i18n.shipping }}</h2>

                        <Card class="customer-info-card rounded space-y-2 overflow-hidden">
                            <template #content>
                                <!-- Contact Info -->
                                <div class="flex justify-between items-center gap-x-4">
                                    <div>
                                        <p class="font-medium text-sm">{{ i18n.contact_label }}</p>
                                        <p class="text-sm max-w-[calc(100%-80px)]">{{ contactInfo }}</p>
                                    </div>
                                    <Button 
                                        @click="handleChangeCustomerInfo"
                                        :label="i18n.change"
                                        severity="contrast"
                                        variant="text"
                                        size="small"
                                        raised
                                        icon="pi pi-pencil"
                                        class="whitespace-nowrap"
                                    />
                                </div>

                                <!-- Divider -->
                                <Divider class="my-2" />

                                <!-- Ship To Info -->
                                <div class="flex justify-between items-center gap-x-4">
                                    <div class="flex-1">
                                        <p class="font-medium text-sm">{{ i18n.ship_to_label }}</p>
                                        <p class="text-sm max-w-[calc(100%-80px)]">{{ shippingAddress }}</p>
                                    </div>
                                    <Button 
                                        @click="handleChangeCustomerInfo"
                                        :label="i18n.change"
                                        severity="contrast"
                                        variant="text"
                                        size="small"
                                        raised
                                        icon="pi pi-pencil"
                                        class="whitespace-nowrap"
                                    />
                                </div>

                            </template>
                        </Card>

                        <!-- 🚚 Shipping Method Selection Panel -->
                        <div class="mt-6">
                            <Panel toggleable class="rounded-xl" :collapsed="false">
                                <!-- 🧢 Header: Show selected method if any -->
                                <template #header>
                                    <div class="flex items-center gap-2 text-base font-semibold">
                                        <i class="pi pi-truck text-primary"></i>
                                        <span>
                                            {{ selectedMethodDetails?.label || i18n.select_shipping_method }}
                                            <span v-if="selectedMethodDetails">
                                                – <span v-html="$utils.formatNumber(selectedMethodDetails.cost, cart.totals.currency_symbol).symbol"></span>
                                                {{ $utils.formatNumber(selectedMethodDetails.cost, cart.totals.currency_symbol).value }}
                                            </span>
                                        </span>
                                    </div>
                                </template>

                                <!-- 🚚 Shipping Method List -->
                                <transition name="quick-fade" mode="out-in" class="flex flex-col gap-y-3 mt-2">
                                    <div v-if="!cart.shipping_methods.length" key="loading">
                                        <Skeleton height="3rem" class="rounded-lg" />
                                        <Skeleton height="3rem" class="rounded-lg" />
                                    </div>

                                    <div v-else key="loaded">
                                        <div 
                                            v-for="method in cart.shipping_methods" 
                                            :key="method.id"
                                            class="p-4 shipping-option rounded-lg cursor-pointer transition hover:shadow-md flex items-center justify-between"
                                            :class="{ 'ring-2 ring-primary': customerFormStore.form.shipping_method === method.id }"
                                            @click="customerFormStore.form.shipping_method = method.id"
                                        >
                                            <div class="flex items-center gap-3">
                                                <RadioButton 
                                                    :inputId="method.id" 
                                                    :value="method.id" 
                                                    v-model="customerFormStore.form.shipping_method" 
                                                />
                                                <label :for="method.id" class="cursor-pointer font-medium">
                                                    {{ method.label }}
                                                </label>
                                            </div>
                                            <div class="text-sm font-semibold">
                                                <span v-html="$utils.formatNumber(method.cost, cart.totals.currency_symbol).symbol"></span>
                                                {{ $utils.formatNumber(method.cost, cart.totals.currency_symbol).value }}
                                            </div>
                                        </div>
                                    </div>
                                </transition>
                            </Panel>
                        </div>


                        <!-- 📝 Order Notes -->
                        <FloatLabel variant="on">
                            <Textarea id="orderNotes" v-model="orderNotes" rows="3" class="w-full" />
                            <label for="orderNotes">{{ i18n.order_notes_optional }}</label>
                        </FloatLabel>


                        <!-- 🔒 Place Order -->
                        <div class="flex justify-center mt-6 w-full">
                            <Button 
                                class="w-full" 
                                :label="i18n.place_order_button"
                                icon="pi pi-lock" 
                                iconPos="right" 
                                severity="contrast" 
                                raised 
                                @click="submitOrder"
                                :disabled="!customerFormStore.form.shipping_method || localUiStore.isShippingSubmitting || localUiStore.orderPlacedSuccessfully "
                                :loading="localUiStore.isShippingSubmitting"

                            />
                        </div>
                    </div>
                `,
                inject: ['i18n'],
                data() {
                    const customerFormStore = useCustomerFormStore();

                    const { email, shipping } = customerFormStore.form;
                    const { first_name, last_name, ...rest } = shipping;

                    const shippingSummary = [
                        `${first_name} ${last_name}`.trim(), // Full name
                        ...Object.entries(rest)
                            .filter(([_, value]) => value && value !== '')
                            .map(([_, value]) => value)
                    ].join(', ');


                    const localUiStore = useLocalUiStore();

                    return {
                        customerFormStore,
                        localUiStore,

                        contactInfo: email,
                        shippingAddress: shippingSummary,
                        cart: cart,


                    };
                },
                computed: {
                    selectedMethodDetails() {
                        const selectedId = this.customerFormStore.form.shipping_method;
                        return this.cart.shipping_methods?.find(method => method.id === selectedId) || null;
                    },

                    orderNotes: {
                        get() {
                            return this.customerFormStore.form.orderNotes;
                        },
                        set(value) {
                            this.customerFormStore.form.orderNotes = value;
                        }
                    }
                },
                beforeMount() {
                    if (!cart.shipping_methods.length){
                        this.customerFormStore.getAvailableShippingMethods();
                    }
                },
                methods: {
                    handleChangeCustomerInfo() {
                        this.$router.push({ name: 'information' });
                    },

                    async submitOrder() {
                        if (!this.customerFormStore.form.shipping_method) {
                            this.$toast.warning(this.i18n.shipping_method_required_summary, {
                                description: this.i18n.shipping_method_required_detail,
                                duration: 4000
                            });
                            return;
                        }

                        this.localUiStore.isShippingSubmitting = true;

                        try {
                            const result = await this.$toast.promise(
                                wp.apiFetch({
                                    path: '/hc/v1/customers/checkout/place-order',
                                    method: 'POST',
                                    data: { form: this.customerFormStore.form }
                                }).then(async result => {
                                    this.localUiStore.orderPlacedSuccessfully = true;

                                    await new Promise(resolve => setTimeout(resolve, 100));
                                    this.$router.push({ name: 'confirm' });
                                    this.localUiStore.isShippingSubmitting = false;

                                    return result;
                                }),
                                {
                                    loading: this.i18n.placing_order || 'Processing your order…',
                                    success: () => {  
                                        this.customerFormStore.clearSessionStorage();
                                        return this.i18n.success_detail_default 
                                    },
                                    error: (err) => {
                                        
                                        return err?.message || this.i18n.order_failed_detail_default;
                                    },
                                    finally: () => {
                                        this.localUiStore.isShippingSubmitting = false;
                                    },
                                    duration: 5000
                                }
                            );

                        } catch (err) {
                            this.localUiStore.isShippingSubmitting = false;
                            this.$toast.error(this.i18n.error_summary_general, {
                                description: err?.message || this.i18n.error_detail_unexpected,
                                duration: 5000
                            });
                        }
                    },
                },
                watch: {
                    selectedMethod(newVal) {
                        this.customerFormStore.form.shipping_method = newVal;
                    }
                }

            };


            // ✅ Confirmation Step Component
            const Confirm = {
                template: `
                <div class="p-4">
                    <transition-group
                        name="soft-slide"
                        tag="div"
                        class="flex flex-col justify-center items-center my-28 lg:my-36"
                    >
                        <h2
                            v-if="showText"
                            key="text"
                            class="text-center text-xl font-semibold"
                        >
                            Your Order Has Been Received
                        </h2>

                        <DotLottiePlayer
                            key="animation"
                            :src="'<?= HAVEN_CORE_URL . 'assets/dotlotties/checkmark-success.lottie'; ?>'"
                            :autoplay="true"
                            :loop="false"
                            style="width: 275px; height: 275px;"
                        />
                    </transition-group>
                </div>
                `,
                inject: ['i18n'],
                data() {
                    // Access the Pinia store
                    const globalStore = useGlobalStore();

                    const mobile = Vue.computed(() => globalStore.isMobile);


                    return {
                        showText: false,

                        mobile,
                    }
                
                },
                mounted() {
                    setTimeout(() => {


                        // if (!this.mobile) {
                            // launch a few from the left edge
                            confetti({
                                particleCount: 80,
                                angle: 20,
                                spread: 255,
                                origin: { x: 0 }
                            });

                            // launch a few from the right edge
                            confetti({
                                particleCount: 80,
                                angle: 130,
                                spread: 255,
                                origin: { x: 1 }
                            // });
                        // } else {
                            // var count = 200;
                            // var defaults = {
                            //     origin: { y: 0.7 }
                            // };

                            // function fire(particleRatio, opts) {
                            //     confetti({
                            //         ...defaults,
                            //         ...opts,
                            //         particleCount: Math.floor(count * particleRatio)
                            //     });
                            // }

                            // fire(0.25, {
                            //     spread: 26,
                            //     startVelocity: 55,
                            // });
                            // fire(0.2, {
                            //     spread: 60,
                            // });
                            // fire(0.35, {
                            //     spread: 100,
                            //     decay: 0.91,
                            //     scalar: 0.8
                            // });
                            // fire(0.1, {
                            //     spread: 120,
                            //     startVelocity: 25,
                            //     decay: 0.92,
                            //     scalar: 1.2
                            // });
                            // fire(0.1, {
                            //     spread: 120,
                            //     startVelocity: 45,
                            });
                        // }
                    }, 400)

                    setTimeout(() => {
                        this.showText = true;
                    }, 800); // Delay in ms

                }
            };


            const Routes = [
                {
                    path: '/cart',
                    name: 'cart',
                    component: Cart,
                    meta: { title: initialData.i18n.cart || 'Cart' }
                },
                {
                    path: '/information',
                    name: 'information',
                    component: Information,
                    meta: { title: initialData.i18n.information || 'Information' }
                },
                {
                    path: '/shipping',
                    name: 'shipping',
                    component: Shipping,
                    meta: { title: initialData.i18n.shipping || 'Shipping' }
                },
                {
                    path: '/confirm',
                    name: 'confirm',
                    component: Confirm,
                    meta: { title: initialData.i18n.confirmation || 'Confirmation' }
                },
                // Catch-all fallback to Information step
                {
                    path: '/:pathMatch(.*)*',
                    redirect: { name: 'information' }

                }
            ];
            
            const router = VueRouter.createRouter({
                history: VueRouter.createWebHashHistory(),
                routes: Routes
            });

            router.beforeEach((to, from, next) => {
                const customerFormStore = useCustomerFormStore();

                const localUiStore = useLocalUiStore();

                // ⛔️ Block *all* navigation if order already placed
                if (localUiStore.orderPlacedSuccessfully && to.name !== 'confirm') {
                    return next(false); // cancel navigation
                }

                // ⛔️ Block confirm route if not yet placed
                if (to.name === 'confirm' && !localUiStore.orderPlacedSuccessfully) {
                    return next(false); // cancel navigation
                }

                // ⛔️ Block route change during submission
                if (localUiStore.isShippingSubmitting) {
                    return next(false); // cancel navigation
                }

                // ✅ Route-level validation
                const protectedRoutes = ['shipping', 'confirm'];

                if (protectedRoutes.includes(to.name)) {
                    const { valid, field } = customerFormStore.validateForm();
                    if (!valid) {
                        return next({ name: 'information' });
                    }
                }

                next(); // Proceed as normal
                
                // update the document title
                document.title = `${initialData.i18n.title} | ${to.meta.title || i18n.default_title}`;

            });


            app.component('havencore-app-place-order', {
                inject: ['mobileIcon', 'breadcrumbItems'],
                template: `
                    <Toaster
                        richColors
                        theme="system"
                        :position="mobile ? 'top-center' : 'bottom-center'"
                        :closeButton="!mobile"
                    />


                    <div 
                        :class="{
                            'p-2 max-w-6xl mx-auto': true,
                            'p-6': !mobile
                        }"
                    >
                        <div class="flex flex-col justify-center gap-y-4 p-3">
                            <div class="flex justify-start gap-x-4">
                                <!-- 🧭 Breadcrumb Navigation -->
                                <Card class="w-full overflow-hidden">
                                    <template #content>
                                        <div class="flex items-center justify-between">
                                            <Breadcrumb ref="breadcrumb" :model="breadcrumbItems" class="flex text-sm whitespace-nowrap">
                                                <template #item="{ item, props }">
                                                    <router-link
                                                        v-if="item.to && $router.hasRoute && $router.hasRoute(item.to.name)"
                                                        v-slot="{ href, navigate, isActive }"
                                                        :to="item.to"
                                                        custom
                                                    >
                                                        <a
                                                            :href="href"
                                                            ref="activeBreadcrumb"
                                                            v-bind="props.action"
                                                            @click="navigate"
                                                            :class="[
                                                                'inline-flex items-center font-medium transition-colors',
                                                                isActive ? 'current-route' : 'route'
                                                            ]"
                                                        >
                                                            <span :class="[item.icon]" />

                                                            <template v-if="mobile">
                                                                <transition name="fade-slide-breadcrumb" mode="out-in">
                                                                    <span v-if="!mobile || isActive" :key="isActive ? 'visible' : 'hidden'">
                                                                        {{ item.label }}
                                                                    </span>
                                                                </transition>
                                                            </template>
                                                            <template v-else>
                                                                <span>{{ item.label }}</span>
                                                            </template>

                                                        </a>
                                                    </router-link>
                                                </template>
                                            </Breadcrumb>

                                            <!-- Render button here only if NOT mobile -->
                                            <Button
                                                v-if="!mobile"
                                                class="button-go-back"
                                                severity="secondary"
                                                raised
                                                @click="goToHome"
                                                v-tooltip.right="{
                                                    value: i18n.back_to_home
                                                }"
                                            >
                                                <template #icon>
                                                    <Image
                                                        :src="mobileIcon"
                                                        :alt="i18n.home_alt"
                                                        :pt="{
                                                            image: { class: 'w-10 h-10 rounded flex shrink-0' }
                                                        }"
                                                    />
                                                </template>
                                            </Button>
                                        </div>
                                    </template>
                                </Card>

                                <!-- 📱 Clickable Button for Mobile Navigation -->
                                <button
                                    v-if="mobile"
                                    class="p-card p-component cursor-pointer hover:shadow-lg transition-shadow duration-300 flex justify-center items-center p-0 shrink-0 w-28"
                                    @click="goToHome"
                                >
                                        <Image
                                            :src="mobileIcon"
                                            alt="Home"
                                            :pt="{
                                                image: { class: 'w-16 h-16 rounded-full flex shrink-0' }
                                            }"
                                        />
                                </button>
                            </div>

                            <!-- Floating glossy total -->
                            <transition name="slide-scale" mode="out-in">
                                <div v-if="!isTotalInView">
                                    <Card
                                        class="inset-x-4 z-50 rounded-lg shadow-md border"
                                    >
                                        <template #content>

                                            <div class="flex justify-between items-center font-semibold text-base" >
                                                <span class="flex items-center gap-2">
                                                    <i class="pi pi-calculator" /> {{ i18n.total_label }}
                                                </span>
                                            
                                                <span v-html="$utils.formatNumber(customerFormStore.calculatedTotal, cart.totals.currency_symbol).symbol + $utils.formatNumber(customerFormStore.calculatedTotal).value"></span>
                                            </div>

                                        </template>
                                    </Card>
                                </div>
                            </transition>
                        </div>

                        <!-- 🧾 Main Content -->
                        <ScrollPanel
                            :style="{ height: isTotalInView ? '80svh' : 'calc(80svh - 6rem)' }"

                            :class="[
                                'p-1 overflow-hidden'

                            ]"
                        >
                            <div class="grid grid-cols-1 md:grid-cols-2 md:flex md:flex-row-reverse gap-6 p-2">

                                <!-- 🧾 Order Summary -->
                                <component
                                    v-if="$route.name !== 'confirm'"
                                    :is="mobile ? 'Panel' : 'Card'"
                                    class="md:order-1 w-full md:max-w-sm overflow-hidden"
                                    :toggleable="mobile"
                                    :collapsed="mobile ? isOverviewPanelCollapsed : false"
                                    @toggle="isOverviewPanelCollapsed = $event.value"
                                >
                                    <!-- ✅ Custom header with icon -->
                                    <template v-if="mobile" #header>
                                        <div class="flex items-center gap-4 text-lg font-semibold">
                                            <i class="pi pi-receipt" />
                                            {{ i18n.order_summary_title }}
                                        </div>
                                    </template>

                                    <!-- 📱 For Panel (mobile) -->
                                    <template v-if="mobile" #default>
                                        <div
                                            style="max-height: 50svh; overflow-y: auto;"
                                            class="order-sum-card p-1 my-2 overflow-hidden rounded-md shadow-md scrollbar-hidden"
                                        >
                                            <ul class="divide-y p-3">
                                                <li
                                                    v-for="item in cart.items || []"
                                                    :key="item.key"
                                                    class="py-6 flex gap-4"
                                                >
                                                    <Image
                                                        :src="item.image"
                                                        alt="item image"
                                                        preview
                                                        class="flex-shrink-0 mx-auto sm:mx-0"
                                                        :pt="{
                                                            root: { class: 'rounded-xl overflow-hidden' },
                                                            image: { class: 'w-20 h-20 object-cover' }
                                                        }"
                                                    />
                                                    <div class="flex-1">
                                                        <p class="font-medium">{{ item.name }}</p>
                                                        <p class="text-sm">
                                                            <span
                                                                v-html="$utils.formatNumber(item.price, cart.totals.currency_symbol).symbol + $utils.formatNumber(item.price).value"
                                                            ></span>
                                                            × {{ item.quantity }}
                                                        </p>
                                                    </div>
                                                </li>
                                            </ul>
                                        </div>

                                        <!-- 💵 Totals with icons -->
                                        <div class="space-y-3 pt-4 text-sm">
                                            <div class="flex justify-between items-center">
                                                <span class="flex items-center gap-2"><i class="pi pi-money-bill" /> {{ i18n.subtotal_label }}</span>
                                                <span v-html="$utils.formatNumber(cart.totals.subtotal, cart.totals.currency_symbol).symbol + $utils.formatNumber(cart.totals.subtotal).value"></span>
                                            </div>

                                            <div class="flex justify-between items-center" v-if="cart.totals.discount > 0">
                                                <span class="flex items-center gap-2 text-red-500"><i class="pi pi-percentage" /> {{ i18n.discount_label }}</span>
                                                <span dir="ltr" v-html="'– ' + $utils.formatNumber(cart.totals.discount, cart.totals.currency_symbol).symbol + $utils.formatNumber(cart.totals.discount).value" class="text-red-500"></span>
                                            </div>

                                            <div class="flex justify-between items-center">
                                                <span class="flex items-center gap-2"><i class="pi pi-truck" /> {{ i18n.shipping_label }}</span>
                                                <span v-html="$utils.formatNumber(customerFormStore.shippingMethodDetails?.cost || 0, cart.totals.currency_symbol).symbol + $utils.formatNumber(customerFormStore.shippingMethodDetails?.cost || 0).value"></span>
                                            </div>

                                            <Divider class="my-2" />

                                            <div ref="realTotal" class="real-total flex justify-between items-center font-semibold text-base">
                                                <span class="flex items-center gap-2"><i class="pi pi-calculator" /> {{ i18n.total_label }}</span>
                                                <span v-html="$utils.formatNumber(customerFormStore.calculatedTotal, cart.totals.currency_symbol).symbol + $utils.formatNumber(customerFormStore.calculatedTotal).value"></span>
                                            </div>
                                        </div>
                                    </template>



                                    <!-- 💻 For Card (desktop) -->
                                    <template v-else #content>
                                        <div class="flex flex-col h-full">
                                            <h3 class="text-lg font-semibold mb-4">
                                                {{ i18n.order_summary_title }}
                                            </h3>

                                            <!-- 🖱 Scrollable items list -->
                                            <div
                                                style="max-height: 50svh; overflow-y: auto;"
                                                class="order-sum-card p-1 my-2 overflow-hidden rounded-md shadow-md scrollbar-hidden"
                                            >
                                                <ul class="divide-y p-3">
                                                    <li v-for="item in cart.items || []" :key="item.key" class="py-6 flex gap-4">
                                                        <Image
                                                            :src="item.image"
                                                            alt="item image"
                                                            preview
                                                            :pt="{
                                                                root: { class: 'rounded-xl overflow-hidden' },
                                                                image: { class: 'w-20 h-20 object-cover' }
                                                            }"
                                                        />
                                                        <div class="flex-1">
                                                            <p class="font-medium">{{ item.name }}</p>
                                                            <p class="text-sm">
                                                                <span v-html="$utils.formatNumber(item.price, cart.totals.currency_symbol).symbol + $utils.formatNumber(item.price).value"></span>
                                                                × {{ item.quantity }}
                                                            </p>
                                                        </div>
                                                    </li>
                                                </ul>
                                            </div>

                                            <!-- 💵 Totals with icons -->
                                            <div class="space-y-3 pt-4 text-sm">
                                                <div class="flex justify-between items-center">
                                                    <span class="flex items-center gap-2"><i class="pi pi-money-bill" /> {{ i18n.subtotal_label }}</span>
                                                    <span v-html="$utils.formatNumber(cart.totals.subtotal, cart.totals.currency_symbol).symbol + $utils.formatNumber(cart.totals.subtotal).value"></span>
                                                </div>

                                                <div class="flex justify-between items-center" v-if="cart.totals.discount > 0">
                                                    <span class="flex items-center gap-2 text-red-500"><i class="pi pi-percentage" /> {{ i18n.discount_label }}</span>
                                                    <span dir="ltr" v-html="'– ' + $utils.formatNumber(cart.totals.discount, cart.totals.currency_symbol).symbol + $utils.formatNumber(cart.totals.discount).value" class="text-red-500"></span>
                                                </div>

                                                <div class="flex justify-between items-center">
                                                    <span class="flex items-center gap-2"><i class="pi pi-truck" /> {{ i18n.shipping_label }}</span>
                                                    <span v-html="$utils.formatNumber(customerFormStore.shippingMethodDetails?.cost || 0, cart.totals.currency_symbol).symbol + $utils.formatNumber(customerFormStore.shippingMethodDetails?.cost || 0).value"></span>
                                                </div>

                                                <Divider class="my-2" />

                                                <div class="flex justify-between items-center font-semibold text-base">
                                                    <span class="flex items-center gap-2"><i class="pi pi-calculator" /> {{ i18n.total_label }}</span>
                                                    <span v-html="$utils.formatNumber(customerFormStore.calculatedTotal, cart.totals.currency_symbol).symbol + $utils.formatNumber(customerFormStore.calculatedTotal).value"></span>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                </component>


                                <!-- 🚦 Route Content -->
                                <Card 
                                    class="md:order-2 flex-1 overflow-hidden"

                                    :pt="{
                                        body: { class: 'h-full' },
                                        content: { class: 'h-full' }

                                    }"
                                
                                >
                                    <template #content>
                                        <router-view v-slot="{ Component }">
                                            <transition name="__hc_customer_place_order_card-swap" mode="out-in">
                                                <component
                                                    v-if="Component"
                                                    :is="Component"
                                                    :key="$route.fullPath"
                                                    class="app-content-styled"
                                                />
                                            </transition>
                                        </router-view>
                                    </template>
                                </Card>

                            </div>
                        </ScrollPanel>
                    </div>
                `,
                props: [],
                data() {
                    // Access the Pinia store
                    const globalStore = useGlobalStore();

                    const mobile = Vue.computed(() => globalStore.isMobile);

                    const isRtl = Vue.computed(() => globalStore.isRtl);

                    const customerFormStore = useCustomerFormStore();


                    return {
                        i18n: this.$root.i18n || {},  // Fallback to empty object if i18n is not defined

                        currentUser: this.$root.currentUser || {} ,

                        mobile,

                        isRtl,

                        customerFormStore,

                        cart: cart,

                        isOverviewPanelCollapsed: true,
                        isTotalInView: true,
                        observer: null,

                    };
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
                    }
                },
                methods: {

                    // =============================
                    // General Utility Methods
                    // =============================

                    goToHome() {
                        window.location.href = '<?= home_url(); ?>';
                    },

                },
                watch: {
                    $route(to, from) {
                        this.$nextTick(() => {
                            // collapse the overview panel upon route change (i think it is better UX so customer can focus on the main content)
                            this.isOverviewPanelCollapsed = true; 

                            setTimeout(() => {
                                if (to.name === 'shipping' && this.mobile) {
                                    this.isTotalInView = false;
                                } else {
                                    this.isTotalInView = true;
                                }
                            }, 0)

                            if (this.mobile) {
                                const container = this.$refs.breadcrumb?.$el || this.$refs.breadcrumb;
                                const active = this.$refs.activeBreadcrumb;

                                if (container && active) {

                                    const activeCenter = active.offsetLeft + active.offsetWidth / 2 ;
                                    const containerCenter = container.clientWidth / 2;

                                    const scrollTo = activeCenter - containerCenter;

                                    container.scrollTo({
                                        left: scrollTo,
                                        behavior: 'smooth'
                                    });

                                    console.log({
                                        scrollTo
                                    });

                                    
                                }
                            }
                        });
                    },
                    isOverviewPanelCollapsed(newVal) {
                        if (!newVal) {
                            this.$nextTick(() => {
                                setTimeout(() => {
                                    const el = this.$refs.realTotal;
                                    if (!el) return;

                                    if (this.observer) {
                                        this.observer.disconnect();
                                    }

                                    let timeout = null;

                                    this.observer = new IntersectionObserver(
                                        ([entry]) => {
                                            console.log('[Observer] isIntersecting:', entry.isIntersecting);
                                            if (!this.isOverviewPanelCollapsed) {
                                                this.isTotalInView = entry.isIntersecting;
                                            }
                                        },
                                        { threshold: 0.1 }
                                    );

                                    this.observer.observe(el);
                                }, 800); // initial delay to wait for panel animation
                            });
                        } else {
                            if (this.observer && this.$refs.realTotal) {
                                this.observer.unobserve(this.$refs.realTotal);
                            }
                            this.observer = null;

                            if (this.$route.name === 'shipping') {
                                this.isTotalInView = false;
                            } else {
                                this.isTotalInView = true;
                            }
                        }
                    },
                    mobile(newVal) {
                        // React to layout mode switching
                        console.log('[Watch] mobile changed:', newVal);

                        // If switching to mobile mode, collapse the panel (if not already)
                        if (!newVal) {

                            if (this.observer && this.$refs.realTotal) {
                                this.observer.unobserve(this.$refs.realTotal);
                            }

                            this.observer = null;
                            this.isTotalInView = true;
                        } else {
                            if (!this.isOverviewPanelCollapsed) {
                                this.$nextTick(() => {
                                    setTimeout(() => {
                                        const el = this.$refs.realTotal;
                                        if (!el) return;

                                        if (this.observer) {
                                            this.observer.disconnect();
                                        }

                                        let timeout = null;

                                        this.observer = new IntersectionObserver(
                                            ([entry]) => {
                                                console.log('[Observer] isIntersecting:', entry.isIntersecting);
                                                this.isTotalInView = entry.isIntersecting;
                                            },
                                            { threshold: 0.1 }
                                        );

                                        this.observer.observe(el);
                                    }, 1050); // initial delay to wait for panel animation
                                });
                            }
                        }
                    }
                }
            });

            app.config.globalProperties.$utils = utils;


            app.use(router);

            // First, make sure Pinia is initialized in the parent app
            const pinia = Pinia.createPinia();  // Initialize Pinia

            // Use Pinia (make sure Pinia is initialized from globalStore.js)
            app.use(pinia); // Pinia is initialized first

            // Call the global store initialization function after Pinia is installed
            initializeGlobalStore();

            // Access the Pinia store for customer form data
            const customerFormStore = useCustomerFormStore();

            // Run the built-in form validation method
            const validationResult = customerFormStore.validateForm();

            // If the form is valid AND we're not already on the shipping page…
            if (validationResult.valid && router.currentRoute.value.name !== 'shipping') {
                // 🔁 Immediately navigate to the shipping step, replacing history to avoid back button flicker
                router.replace({ name: 'shipping' });
            }

            app.mount('#havencore-app-place-order');

        </script>
    </body>
</html>
