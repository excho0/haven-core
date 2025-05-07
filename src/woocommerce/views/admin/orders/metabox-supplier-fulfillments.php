<?php

use HavenCore\Services\HC_Supplier_Service;
use HavenCore\Utils\ScriptHelpers;

/** @var WP_Post|WC_Order $post */

if ($post instanceof WC_Order) {
    $order_id = $post->get_id();
    $order = $post;
} else {
    $order_id = isset($post->ID) ? (int) $post->ID : 0;
    $order = $order_id ? wc_get_order($order_id) : null;
}

$supplier_data = get_post_meta($order_id, '_supplier_data', true) ?: [];

if (!defined('HC_SUPPLIER_FULFILLMENT_VUE')) {
    define('HC_SUPPLIER_FULFILLMENT_VUE', true);

    ScriptHelpers::loadVue([
        'withTailwind'    => true,
        'withGlobalStore' => false,
        'withSonner'      => false,
        'withDotLottie'   => false,
        'withConfetti'    => false,
        'withDraggable'   => false,
        'withFrontendCss' => true,
        'withMainStyle'   => true,
    ]);

    ScriptHelpers::loadApiFetch();
}

$format_money = static function (float $amount): ?string {
    if ($amount <= 0) {
        return null;
    }

    $text = wp_strip_all_tags(wc_price($amount));
    $text = html_entity_decode($text, ENT_QUOTES, get_bloginfo('charset'));
    $text = str_replace("\xc2\xa0", ' ', $text); // replace non-breaking space

    return trim($text);
};

$format_product = static function (int $product_id, int $qty) use ($order, $format_money): array {
    $product_name = esc_html__('Product not found', 'woocommerce');
    $variation = '';

    if ($order) {
        foreach ($order->get_items() as $item) {
            if ($item instanceof WC_Order_Item_Product && (int) $item->get_product_id() === $product_id) {
                $product_name = esc_html($item->get_name());

                if ($item->get_variation_id()) {
                    $meta_data = $item->get_meta_data();
                    $variation_parts = [];

                    foreach ($meta_data as $meta) {
                        if (strpos($meta->key, 'attribute_') === 0) {
                            $label = wc_attribute_label(str_replace('attribute_', '', $meta->key));
                            $variation_parts[] = $label . ': ' . esc_html($meta->value);
                        }
                    }

                    if (!empty($variation_parts)) {
                        $variation = implode(', ', $variation_parts);
                    }
                }

                break;
            }
        }
    }

    $product_url = get_permalink($product_id) ?: '';
    $thumb = get_the_post_thumbnail_url($product_id, 'thumbnail') ?: '';
    $supplier_price = (float) get_post_meta($product_id, '_supplier_price', true);
    $line_total = $supplier_price * $qty;

    return [
        'product_id'               => $product_id,
        'quantity'                 => $qty,
        'name'                     => $product_name,
        'variation'                => $variation,
        'product_url'              => $product_url,
        'thumbnail'                => $thumb,
        'supplier_price'           => $supplier_price > 0 ? $supplier_price : null,
        'supplier_price_formatted' => $supplier_price > 0 ? $format_money($supplier_price) : null,
        'line_total_formatted'     => $supplier_price > 0 ? $format_money($line_total) : null,
        'line_total_raw'           => $line_total,
    ];
};

$decode_text = static function (string $text): string {
    return wp_specialchars_decode($text, ENT_QUOTES);
};

$status_map = [
    'fulfilled'           => [
        'label' => esc_html__('Fulfilled', 'woocommerce'),
        'badge' => 'success',
    ],
    'pending'             => [
        'label' => esc_html__('Pending', 'woocommerce'),
        'badge' => 'warning',
    ],
    'partially-fulfilled' => [
        'label' => esc_html__('Partially Fulfilled', 'woocommerce'),
        'badge' => 'info',
    ],
];

$service = new HC_Supplier_Service();
$suppliers = [];

foreach ($supplier_data as $sid => $data) {
    $supplier_grand_total = 0;
    $ungrouped = [];
    $grouped = [];

    if (!empty($data['ungrouped_products'])) {
        foreach ($data['ungrouped_products'] as $product) {
            $product_id = isset($product['product_id']) ? (int) $product['product_id'] : 0;
            $qty = isset($product['quantity']) ? (int) $product['quantity'] : 1;

            if (!$product_id) {
                continue;
            }

            $entry = $format_product($product_id, $qty);
            $supplier_grand_total += $entry['line_total_raw'];
            unset($entry['line_total_raw']);

            $ungrouped[] = $entry;
        }
    }

    if (!empty($data['grouped_products'])) {
        foreach ($data['grouped_products'] as $tracking_number => $products) {
            $group_products = [];

            foreach ($products as $product_id => $product_data) {
                $product_id = (int) $product_id;
                $qty = isset($product_data['quantity']) ? (int) $product_data['quantity'] : 1;

                if (!$product_id) {
                    continue;
                }

                $entry = $format_product($product_id, $qty);
                $supplier_grand_total += $entry['line_total_raw'];
                unset($entry['line_total_raw']);

                $group_products[] = $entry;
            }

            $grouped[] = [
                'tracking_number' => $tracking_number,
                'products'        => $group_products,
            ];
        }
    }

    $status_key = isset($data['fulfillment_status']) ? $data['fulfillment_status'] : 'pending';
    $status = $status_map[$status_key] ?? $status_map['pending'];

    $supplier_instance = $service->get((int) $sid);
    $supplier_name = $supplier_instance ? $supplier_instance->get_name() : esc_html__('Unknown Supplier', 'woocommerce');

    $suppliers[] = [
        'id'                  => (int) $sid,
        'name'                => $supplier_name,
        'status'              => [
            'value' => $status_key,
            'label' => $status['label'],
            'badge' => $status['badge'],
        ],
        'ungrouped_products'  => $ungrouped,
        'grouped_products'    => $grouped,
        'grand_total'         => $supplier_grand_total > 0 ? $format_money($supplier_grand_total) : null,
    ];
}

$initial_data = [
    'orderId'   => $order_id,
    'suppliers' => $suppliers,
    'rest'      => [
        'path'  => 'hc/v1/suppliers/portal/reset-fulfillment',
        'url'   => esc_url_raw(rest_url('hc/v1/suppliers/portal/reset-fulfillment')),
        'nonce' => wp_create_nonce('wp_rest'),
    ],
    'i18n'      => [
        'no_supplier_data'      => esc_html__('No supplier data found for this order.', 'woocommerce'),
        'status_label'          => esc_html__('Status', 'woocommerce'),
        'reset_fulfillment'     => esc_html__('Reset Fulfillment', 'woocommerce'),
        'reset_confirm'         => $decode_text(esc_html__("Reset this supplier's fulfillment data?", 'woocommerce')),
        'reset_confirm_body'    => $decode_text(esc_html__('This will clear fulfillment progress for the selected supplier so he can start over.', 'woocommerce')),
        'reset_confirm_button'  => esc_html__('Confirm Reset', 'woocommerce'),
        'cancel_button'         => esc_html__('Cancel', 'woocommerce'),
        'reset_failed'          => esc_html__('Failed to reset. Please try again.', 'woocommerce'),
        'error_title'           => esc_html__('Something went wrong', 'woocommerce'),
        'close_button'          => esc_html__('Close', 'woocommerce'),
        'ungrouped_products'    => esc_html__('Ungrouped Products', 'woocommerce'),
        'tracking_number'       => esc_html__('Tracking Number:', 'woocommerce'),
        'supplier_price'        => esc_html__('Supplier Price:', 'woocommerce'),
        'grand_total'           => esc_html__('Grand Total for Supplier:', 'woocommerce'),
    ],
];

$app_id = 'hc-supplier-fulfillment-app-' . $order_id;
$data_id = 'hc-supplier-fulfillment-data-' . $order_id;

?>

<div id="<?php echo esc_attr($app_id); ?>"></div>
<script type="application/json" id="<?php echo esc_attr($data_id); ?>">
    <?php echo wp_json_encode($initial_data); ?>
</script>
<script>
    (function () {
        const appTarget = document.getElementById('<?php echo esc_js($app_id); ?>');
        const payloadEl = document.getElementById('<?php echo esc_js($data_id); ?>');

        if (!appTarget || !payloadEl) {
            return;
        }

        let initialData = {};

        try {
            initialData = JSON.parse(payloadEl.textContent || '{}');
        } catch (err) {
            console.error('Unable to parse supplier fulfillment data', err);
            return;
        }

        if (typeof Vue === 'undefined' || typeof PrimeVue === 'undefined') {
            console.error('Vue or PrimeVue is not loaded.');
            return;
        }

        const app = Vue.createApp({
            data() {
                return {
                    orderId: initialData.orderId,
                    suppliers: initialData.suppliers || [],
                    rest: initialData.rest || {},
                    i18n: initialData.i18n || {},
                    loadingSupplier: null,
                    confirmDialogVisible: false,
                    confirmTarget: null,
                    errorDialogVisible: false,
                    errorMessage: '',
                };
            },
            computed: {
                hasSuppliers() {
                    return Array.isArray(this.suppliers) && this.suppliers.length > 0;
                },
            },
            methods: {
                badgeMeta(status) {
                    switch (status) {
                        case 'fulfilled':
                            return { severity: 'success', icon: 'pi pi-check-circle' };
                        case 'ready-to-fulfill':
                            return { severity: 'info', icon: 'pi pi-send' };
                        case 'partially-fulfilled':
                            return { severity: 'warn', icon: 'pi pi-exclamation-triangle' };
                        default:
                            return { severity: 'secondary', icon: 'pi pi-clock' };
                    }
                },
                promptReset(supplier) {
                    if (!supplier || !supplier.id) {
                        return;
                    }

                    this.confirmTarget = supplier;
                    this.confirmDialogVisible = true;
                },
                confirmReset() {
                    if (!this.confirmTarget) {
                        return;
                    }

                    const supplier = this.confirmTarget;

                    this.loadingSupplier = supplier.id;

                    const payload = {
                        order_id: this.orderId,
                        supplier_id: supplier.id,
                    };

                    const request = (window.wp && window.wp.apiFetch)
                        ? wp.apiFetch({
                            path: this.rest.path,
                            method: 'POST',
                            data: payload,
                        })
                        : fetch(this.rest.url, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-WP-Nonce': this.rest.nonce,
                            },
                            credentials: 'same-origin',
                            body: JSON.stringify(payload),
                        }).then((response) => {
                            if (!response.ok) {
                                throw new Error('Request failed');
                            }

                            return response.json();
                        });

                    request
                        .then(() => {
                            window.location.reload();
                        })
                        .catch((error) => {
                            console.error(error);
                            this.errorMessage = this.i18n.reset_failed || 'Action failed.';
                            this.errorDialogVisible = true;
                        })
                        .finally(() => {
                            this.loadingSupplier = null;
                            this.confirmDialogVisible = false;
                            this.confirmTarget = null;
                        });
                },
                cancelReset() {
                    this.confirmDialogVisible = false;
                    this.confirmTarget = null;
                },
                closeErrorDialog() {
                    this.errorDialogVisible = false;
                    this.errorMessage = '';
                },
            },
            template: `
                <div class="space-y-4">
                    <Dialog
                        v-model:visible="confirmDialogVisible"
                        modal
                        :header="confirmTarget ? (i18n.reset_confirm || 'Confirm Action') : (i18n.reset_confirm || 'Confirm Action')"
                        :style="{ width: '32rem' }"
                    >
                        <div class="mb-6 leading-relaxed space-y-2">
                            <p>
                                {{ i18n.reset_confirm_body || '' }}
                            </p>
                            <p v-if="confirmTarget" class="text-sm text-slate-600 flex items-center gap-2">
                                <i class="pi pi-user"></i>
                                <span class="font-semibold">{{ confirmTarget.name }}</span>
                            </p>
                        </div>
                        <div class="flex justify-end items-center gap-3">
                            <Button
                                :label="i18n.cancel_button || 'Cancel'"
                                severity="secondary"
                                @click="cancelReset"
                                :disabled="loadingSupplier !== null"
                            />
                            <Button
                                icon="pi pi-refresh"
                                severity="contrast"
                                raised
                                :label="i18n.reset_confirm_button || 'Confirm'"
                                @click="confirmReset"
                                :loading="loadingSupplier !== null"
                            />
                        </div>
                    </Dialog>

                    <Dialog
                        v-model:visible="errorDialogVisible"
                        modal
                        :header="i18n.error_title || 'Error'"
                        :style="{ width: '26rem' }"
                    >
                        <div class="flex flex-col items-center text-center space-y-3">
                            <i class="pi pi-times-circle text-3xl text-red-500"></i>
                            <p class="leading-relaxed">{{ errorMessage }}</p>
                        </div>
                        <template #footer>
                            <Button
                                :label="i18n.close_button || 'Close'"
                                @click="closeErrorDialog"
                            />
                        </template>
                    </Dialog>

                    <template v-if="hasSuppliers">
                        <Card
                            v-for="supplier in suppliers"
                            :key="supplier.id"
                            class="border border-slate-200 rounded-2xl shadow-sm"
                        >
                            <template #title>
                                <div class="flex flex-wrap items-start justify-between gap-4">
                                    <div class="flex flex-wrap items-center gap-3">
                                        <span class="font-semibold text-lg flex items-center gap-2 text-slate-900">
                                            <i class="pi pi-user text-primary"></i>
                                            <span>{{ supplier.name }}</span>
                                        </span>
                                        <Tag
                                            v-bind="badgeMeta(supplier.status ? supplier.status.value : '')"
                                            :value="supplier.status && supplier.status.label ? supplier.status.label : ''"
                                        />
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <Button
                                            size="small"
                                            :label="i18n.reset_fulfillment"
                                            icon="pi pi-refresh"
                                            severity="contrast"
                                            outlined
                                            @click="promptReset(supplier)"
                                            :loading="loadingSupplier === supplier.id"
                                            :disabled="loadingSupplier === supplier.id"
                                        />
                                    </div>
                                </div>
                            </template>

                            <template #content>
                                <div class="flex flex-col gap-6 w-full">
                                    <div v-if="supplier.ungrouped_products?.length" class="flex flex-col gap-4 w-full">
                                        <Card class="border border-slate-200 rounded-xl shadow-lg">
                                            <template #title>
                                                <span class="uppercase text-xs tracking-[0.2em] font-semibold text-slate-500 flex items-center gap-2">
                                                    <i class="pi pi-box text-slate-400"></i>
                                                    <span>{{ i18n.ungrouped_products }}</span>
                                                </span>
                                            </template>
                                            <template #content>
                                                <div class="flex flex-col gap-3 w-full">
                                                    <Card
                                                        v-for="product in supplier.ungrouped_products"
                                                        :key="product.product_id + '-ungrouped'"
                                                        class="border border-slate-200 rounded-xl shadow-md"
                                                    >
                                                        <template #content>
                                                            <div class="flex gap-3 items-start">
                                                                <Image
                                                                    v-if="product.thumbnail"
                                                                    :src="product.thumbnail"
                                                                    :alt="product.name"
                                                                    width="64"
                                                                    height="64"
                                                                    preview
                                                                    :pt="{ 
                                                                        root: { 
                                                                            class: 'border border-slate-200 rounded-xl shadow-md overflow-hidden' 
                                                                        }, 
                                                                        image: { 
                                                                            class: 'border border-slate-200 rounded-xl shadow-md flex shrink-0 object-cover'  
                                                                        }
                                                                    }"
                                                                    imageStyle="border-radius: 0.5rem; border-color: transparent; object-fit: cover; width: 64px; height: 64px;"
                                                                />
                                                                <div class="flex flex-col gap-1 flex-1">
                                                                    <a
                                                                        v-if="product.product_url"
                                                                        class="font-medium text-base text-slate-900"
                                                                        :href="product.product_url"
                                                                        target="_blank"
                                                                        rel="noopener noreferrer"
                                                                    >
                                                                        {{ product.name }}
                                                                    </a>
                                                                    <span v-else class="font-medium text-base text-slate-900">{{ product.name }}</span>
                                                                    <div class="text-xs text-slate-500" v-if="product.variation">
                                                                        {{ product.variation }}
                                                                    </div>
                                                                    <div class="text-sm text-slate-600" v-if="product.supplier_price_formatted">
                                                                        {{ i18n.supplier_price }}
                                                                        <strong>{{ product.supplier_price_formatted }}</strong>
                                                                        × {{ product.quantity }}
                                                                        <span v-if="product.line_total_formatted" class="ml-1">
                                                                            (= {{ product.line_total_formatted }})
                                                                        </span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </template>
                                                    </Card>
                                                </div>
                                            </template>
                                        </Card>
                                    </div>

                                    <div v-if="supplier.grouped_products?.length" class="flex flex-col gap-5 w-full">
                                        <div
                                            v-for="(group, groupIndex) in supplier.grouped_products"
                                            :key="'group-' + supplier.id + '-' + groupIndex"
                                        >
                                            <Card class="border border-slate-200 rounded-xl shadow-lg">
                                                <template #title>
                                                    <div class="flex flex-wrap items-center gap-2">
                                                        <i class="pi pi-barcode text-slate-400"></i>
                                                        <span class="text-sm font-semibold text-slate-700">{{ i18n.tracking_number }}</span>
                                                        <Tag
                                                            v-if="group.tracking_number"
                                                            :value="group.tracking_number"
                                                            severity="info"
                                                        />
                                                        <Tag
                                                            v-else
                                                            value="—"
                                                            severity="contrast"
                                                        />
                                                    </div>
                                                </template>
                                                <template #content>
                                                    <div class="flex flex-col w-full gap-4">
                                                        <Card
                                                            v-for="product in group.products"
                                                            :key="product.product_id + '-grouped'"
                                                            class="border border-slate-200 rounded-xl shadow-md"
                                                        >
                                                            <template #content>
                                                                <div class="flex gap-3 items-start">
                                                                    <Image
                                                                        v-if="product.thumbnail"
                                                                        :src="product.thumbnail"
                                                                        :alt="product.name"
                                                                        width="64"
                                                                        height="64"
                                                                        preview
                                                                        :pt="{ 
                                                                            root: { 
                                                                                class: 'border border-slate-200 rounded-xl shadow-md overflow-hidden' 
                                                                            }, 
                                                                            image: { 
                                                                                class: 'border border-slate-200 rounded-xl shadow-md flex shrink-0 object-cover'  
                                                                            }
                                                                        }"                                                                    
                                                                        imageStyle="border-radius: 0.5rem; border-color: transparent; object-fit: cover; width: 64px; height: 64px;"
                                                                    />
                                                                    <div class="flex flex-col gap-1 flex-1">
                                                                        <a
                                                                            v-if="product.product_url"
                                                                            class="font-medium text-base text-slate-900"
                                                                            :href="product.product_url"
                                                                            target="_blank"
                                                                            rel="noopener noreferrer"
                                                                        >
                                                                            {{ product.name }}
                                                                        </a>
                                                                        <span v-else class="font-medium text-base text-slate-900">{{ product.name }}</span>
                                                                        <div class="text-xs text-slate-500" v-if="product.variation">
                                                                            {{ product.variation }}
                                                                        </div>
                                                                        <div class="text-sm text-slate-600" v-if="product.supplier_price_formatted">
                                                                            {{ i18n.supplier_price }}
                                                                            <strong>{{ product.supplier_price_formatted }}</strong>
                                                                            × {{ product.quantity }}
                                                                            <span v-if="product.line_total_formatted" class="ml-1">
                                                                                (= {{ product.line_total_formatted }})
                                                                            </span>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </template>
                                                        </Card>
                                                    </div>
                                                </template>
                                            </Card>
                                        </div>
                                    </div>
                                </div>
                            </template>

                            <template #footer>
                                <template v-if="supplier.grand_total">
                                    <Divider />
                                    <div class="flex items-center justify-start text-sm font-semibold gap-2 text-slate-700">
                                        <span class="inline-flex items-center gap-2 text-slate-600">
                                            <i class="pi pi-calculator"></i>
                                            <strong>{{ i18n.grand_total }}</strong>
                                        </span>
                                        <Tag severity="success" :value="supplier.grand_total" />
                                    </div>
                                </template>
                            </template>
                        </Card>
                    </template>

                    <p v-else class="description">
                        {{ i18n.no_supplier_data }}
                    </p>
                </div>
            `,
        });

        app.use(PrimeVue.Config, {
            theme: {
                preset: PrimeVue.Themes?.Aura || PrimeVue.Themes?.Material,
                options: {
                    darkModeSelector: false,
                },
            },
        });

        app.component('Card', PrimeVue.Card);
        app.component('Button', PrimeVue.Button);
        app.component('Tag', PrimeVue.Tag);
        app.component('Divider', PrimeVue.Divider);
        app.component('Dialog', PrimeVue.Dialog);
        app.component('Image', PrimeVue.Image);

        app.mount(appTarget);
    })();
</script>
