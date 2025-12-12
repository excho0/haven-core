<?php

use HavenCore\Settings\Suppliers as SuppliersSettings;


add_filter('admin_body_class', function($classes) {
    if (isset($_GET['page']) && $_GET['page'] === 'manage-suppliers') {
        $classes .= ' manage_supplier_setting_admin_settings';
    }
    return $classes;
});

if (!current_user_can('manage_options')) return;

// Enqueue the core WP REST API script
wp_enqueue_script( 'wp-api' );
wp_enqueue_script( 'wp-api-fetch' );
wp_enqueue_script( 'wp-api-request' );


use HavenCore\Utils\ScriptHelpers;

ScriptHelpers::loadVue();

$countries = WC()->countries->get_countries();

$translations = wp_get_available_translations();
$installed_locales = get_available_languages(); // ['he_IL']

// Manually add English
array_unshift($installed_locales, 'en_US');

$languages = [];
foreach ($installed_locales as $locale) {
    if ($locale === 'en_US') {
        $languages[] = [
            'code' => 'en_US',
            'native_name' => 'English (United States)',
            'english_name' => 'English',
            'iso' => 'en',
        ];
    } elseif (isset($translations[$locale])) {
        $data = $translations[$locale];
        $languages[] = [
            'code' => $locale,
            'native_name' => $data['native_name'],
            'english_name' => $data['english_name'],
            'iso' => $data['iso'][0] ?? '',
        ];
    }
}

$suppliersSettings = SuppliersSettings::all();

?>



<?= havencore_render_loader([
    'count' => 6,
    'id' => 'supplier-loading',
    'on_ready' => 'initSupplierApp'
]); ?>



<div id="supplier-app-wrapper" style="display:none">
  <div id="supplier-app">
    <supplier-app v-if="suppliersLoaded"></supplier-app>
  </div>
</div>

<script type="application/json" id="app-initial-data">
    <?= json_encode([
        'i18n' => [
            // ─── Core Labels ─────────────────────────────
            'add'                  => __('Add', HAVEN_CORE_TEXT_DOMAIN),
            'cancel'               => __('Cancel', HAVEN_CORE_TEXT_DOMAIN),
            'confirm_delete'       => __('Confirm Delete', HAVEN_CORE_TEXT_DOMAIN),
            'confirm_delete_msg'   => __('Are you sure you want to delete this supplier?', HAVEN_CORE_TEXT_DOMAIN),
            'delete'               => __('Delete', HAVEN_CORE_TEXT_DOMAIN),
            'deleted'              => __('Deleted', HAVEN_CORE_TEXT_DOMAIN),
            'edit'                 => __('Edit', HAVEN_CORE_TEXT_DOMAIN),
            'no_suppliers'         => __('No suppliers found.', HAVEN_CORE_TEXT_DOMAIN),
            'select_country'       => __('Select a country', HAVEN_CORE_TEXT_DOMAIN),
            'yes_delete'           => __('Yes, Delete', HAVEN_CORE_TEXT_DOMAIN),

            // ─── Supplier Fields ────────────────────────
            'name'                 => __('Name', HAVEN_CORE_TEXT_DOMAIN),
            'email'                => __('Email', HAVEN_CORE_TEXT_DOMAIN),
            'paypal'               => __('PayPal', HAVEN_CORE_TEXT_DOMAIN),
            'phone'                => __('Phone', HAVEN_CORE_TEXT_DOMAIN),
            'country'              => __('Country', HAVEN_CORE_TEXT_DOMAIN),
            'language'             => __('Language', HAVEN_CORE_TEXT_DOMAIN),

            // ─── UI Sections / Titles ───────────────────
            'add_supplier'         => __('Add Supplier', HAVEN_CORE_TEXT_DOMAIN),
            'manage_suppliers'     => __('Manage Suppliers', HAVEN_CORE_TEXT_DOMAIN),
            'save_changes'         => __('Save Changes', HAVEN_CORE_TEXT_DOMAIN),

            // ─── Socials ────────────────────────────────
            'add_social'           => __('Add Social Platform', HAVEN_CORE_TEXT_DOMAIN),

            // ─── Toasts / Notifications ─────────────────
            'adding_supplier'             => __('Adding supplier…', HAVEN_CORE_TEXT_DOMAIN),
            'deleting_supplier'           => __('Deleting supplier…', HAVEN_CORE_TEXT_DOMAIN),
            'saving_suppliers'            => __('Saving suppliers…', HAVEN_CORE_TEXT_DOMAIN),

            'supplier_added'              => __('Supplier added successfully!', HAVEN_CORE_TEXT_DOMAIN),
            'supplier_deleted_success'    => __('Supplier deleted successfully.', HAVEN_CORE_TEXT_DOMAIN),
            'update_success'              => __('Suppliers updated successfully!', HAVEN_CORE_TEXT_DOMAIN),

            'welcome_email_sent'         => __('Welcome Email Sent', HAVEN_CORE_TEXT_DOMAIN),
            'sent_on'                    => __('Sent on', HAVEN_CORE_TEXT_DOMAIN),

            // ─── Errors ─────────────────────────────────
            'error'                 => __('Error', HAVEN_CORE_TEXT_DOMAIN),
            'success'               => __('Success', HAVEN_CORE_TEXT_DOMAIN),

            'error_missing_fields' => __('Please fill out all required fields.', HAVEN_CORE_TEXT_DOMAIN),
            'save_failed'          => __('Failed to save suppliers. Please try again.', HAVEN_CORE_TEXT_DOMAIN),
            'delete_failed'        => __('Failed to delete supplier. Please try again.', HAVEN_CORE_TEXT_DOMAIN),
            'add_failed'           => __('Failed to add supplier. Please try again.', HAVEN_CORE_TEXT_DOMAIN),
        ],
    ]) ?>
</script>


<script>
const initialData = JSON.parse(document.getElementById('app-initial-data')?.textContent || '{}');
const countries = <?= json_encode($countries); ?>;
const supplierSettings = <?= json_encode($suppliersSettings); ?>;
const languages = <?= json_encode($languages); ?>;


function waitForWP(timeout = 20000, interval = 100) {
    return new Promise((resolve, reject) => {
        const maxTries = Math.ceil(timeout / interval);
        let tries = 0;

        const check = () => {
            if (typeof wp !== 'undefined' && wp.apiRequest) {
                resolve(wp);
            } else if (++tries >= maxTries) {
                reject(new Error('🛑 wp.apiRequest not available within timeout.'));
            } else {
                setTimeout(check, interval);
            }
        };

        check();
    });
}

// console.log(countries)


const app = Vue.createApp({
    data() {
        console.log(initialData)
        return {
            i18n: initialData.i18n || {},
            suppliers: [], // we will get the from ajax later so intialize empty
            supplierSettings: supplierSettings || [],
            originalSuppliers: [], // we will get the from ajax later so intialize empty
            newSupplier: {
                name: '', email: '', paypal_email: '', phone: '', country: '', language: '',
                socials: []
            },
            countries: countries || {},
            languages: languages || [],
            suppliersLoaded: false,

        };
    },
    computed: {
        countryOptions() {
            return Object.entries(this.countries).map(([code, name]) => ({ label: name, value: code }));
        }
    },
    methods: {
        async fetchSuppliers() {
            try {
                await waitForWP();
                
                const json = await wp.apiFetch({ path: '/hc/v1/suppliers/manage', method: 'GET' });

                const data = json.map(s => ({
                    ...s,
                    socials: s.socials || []
                }));

                this.suppliers = data;
                this.originalSuppliers = JSON.parse(JSON.stringify(data));
                this.suppliersLoaded = true;

                console.log('✅ Fetched suppliers:', this.suppliers);

            } catch (err) {
                console.error('❌ Failed to fetch suppliers:', err?.message || err);
            }
        },
    },
    mounted: async function () {
        try {
            await waitForWP(); // ensures wp.apiFetch is available
            await this.fetchSuppliers(); // your API logic
        } catch (err) {
            console.error('⚠️ Could not initialize WordPress API:', err);
            this.$toast?.error('Unable to load suppliers');
        }
    }
});

// console.log('PrimVueAura:', PrimeVue.Themes.Aura);

// console.log('PrmeVueLoaded:', PrimeVue);


app.use(PrimeVue.Config, {
  theme: {
    preset: PrimeVue.Themes.Aura,
    options: {
        darkModeSelector: true,
    },
  }
});

// 🔌 Plugins
app.use(VueSonner)


// Register UMD components
app.component('Button', PrimeVue.Button);
app.component('Dialog', PrimeVue.Dialog);
app.component('InputText', PrimeVue.InputText);

app.component('FloatLabel', PrimeVue.FloatLabel);
app.component('IconField', PrimeVue.IconField);
app.component('InputIcon', PrimeVue.InputIcon);

app.component('Select', PrimeVue.Select);

app.component('Divider', PrimeVue.Divider);

app.component('Panel', PrimeVue.Panel);
app.component('Menu', PrimeVue.Menu);
app.directive('tooltip', PrimeVue.Tooltip);
app.component('Chip', PrimeVue.Chip);
app.component('ScrollPanel', PrimeVue.ScrollPanel);






app.component('supplier-app', {
    template: `
    
    <Toaster
        richColors
        theme="system"
        :position="mobile ? 'top-center' : 'bottom-center'"
        :closeButton="!mobile"
    />



    <!-- Fixed Header -->
    <div
        :class="[
            'z-40  shadow-sm py-4 p-panel shadow-md border-none rounded-none px-6 flex justify-between items-center',
            notInsideAdmin ? '' : 'sticky top-0 sm:top-10 md:top-8 lg:top-8'
        ]"
    >

        <span class="text-xl font-semibold">{{ i18n.manage_suppliers }}</span>
        <div class="flex gap-2">
            <transition
                name="fade-scale"
                appear
            >
                <Button
                    v-if="anyChanges()"
                    icon="pi pi-save"
                    :label="i18n.save_changes"
                    :loading="supplierActionLoading"
                    :disabled="supplierActionLoading"
                    @click="saveSuppliers"
                    severity="contrast"
                    raised
                />
            </transition>
            <Button 
                icon="pi pi-user-plus" 
                :label="mobile ? '' : i18n.add_supplier" 
                @click="showModal = true"
                severity="contrast"
                variant="text"
                raised
            />
        </div>
    </div>

    <div class="bg-transparent p-6 w-full">
        <component
            :is="notInsideAdmin ? 'ScrollPanel' : 'div'"
            :class="[
                'scroll-content flex-1 p-4',
                notInsideAdmin ? 'scroll-panel-area overflow-y-hidden' : ''

            ]"
        >
        <transition-group
            :name="isRtl ? '__hc_sp_mgr_slide-rtl' : '__hc_sp_mgr_slide-ltr'"
            tag="div"
            class="space-y-4"
        >
            <Panel
                v-for="s in suppliers"
                :key="s.id"
                toggleable
                v-model:collapsed="panelStates[s.id]"
                class="mb-4 shadow-md rounded-xl"
            >

                <template #header>
                    <div class="flex justify-between items-center w-full text-lg font-semibold">
                        <div class="flex items-center gap-2 px-1 rounded">
                            <!-- Indicator bar (visible when panel is open) -->
                            <div
                                class="w-1 h-6 rounded-full transition-all duration-300 bg-blue-500 shadow-sm"
                                :class="{
                                    'opacity-100': !panelStates[s.id],
                                    'opacity-0': panelStates[s.id]
                                }"
                            />

                            <!-- Icon + Editable Name move together -->
                            <div
                                class="flex items-center gap-2 transition-all duration-200 transform"
                                :class="{
                                    'translate-x-1': !isRtl && !panelStates[s.id],   // Apply translate-x-1 for LTR
                                    '-translate-x-1': isRtl && !panelStates[s.id],    // Apply -translate-x-1 for RTL
                                    'scale-[1.01]': !panelStates[s.id],
                                    'changed-field': isChanged(s, 'name')
                                }"
                            >
                                <i class="pi pi-user text-base text-primary-500"></i>
                                <div
                                    :contenteditable="!panelStates[s.id]"
                                    class="outline-none"
                                    @input="s.name = $event.target.innerText"
                                    :ref="el => setInitialName(el, s.name)"
                                    spellcheck="false"
                                    autocorrect="off"
                                    autocomplete="off"
                                    autocapitalize="off"
                                />
                            </div>
                        </div>

                        <div class="flex items-center justify-center gap-x-2">
                            <!-- Email Sent Badge with Tooltip -->
                            <Chip
                                v-if="s.attributes?.greeting_email_sent_at"
                                class="bg-green-200 text-green-900  text-xs font-semibold shadow-sm  text-sm px-3 py-1 flex items-center gap-2"
                                v-tooltip.bottom="i18n.sent_on + ': ' + formatDate(s.attributes.greeting_email_sent_at)"
                            >
                                <i class="pi pi-envelope" />
                                <span v-if="!mobile">{{ i18n.welcome_email_sent }}</span>
                            </Chip>


                            <!-- Cog Button -->
                            <Button 
                                icon="pi pi-cog" 
                                variant="text"
                                rounded
                                @click="toggleMenu(s.id, $event)" 
                                aria-haspopup="true" 
                                :aria-controls="'menu_' + s.id"
                            />

                            <Menu 
                                :model="getMenuItems(s.id)" 
                                popup 
                                :ref="'menu-' + s.id" 
                                :id="'menu_' + s.id"
                            />
                        </div>
                    </div>
                </template>


                <!-- Panel content: shown when expanded -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 p-4">
                    <!-- Email -->
                    <div>
                        <FloatLabel variant="on">
                            <IconField>
                                <InputIcon class="pi pi-envelope" />
                                <InputText
                                    id="email"
                                    v-model="s.email"
                                    class="w-full"
                                    :class="{ 'changed-field': isChanged(s, 'email') }"
                                    :disabled="supplierActionLoading"
                                />
                            </IconField>
                            <label for="email">{{ i18n.email }}</label>
                        </FloatLabel>
                    </div>

                    <!-- PayPal -->
                    <div>
                        <FloatLabel variant="on">
                            <IconField>
                                <InputIcon class="pi pi-paypal" />
                                <InputText
                                    id="paypal"
                                    v-model="s.paypal_email"
                                    class="w-full"
                                    :class="{ 'changed-field': isChanged(s, 'paypal_email') }"
                                    :disabled="supplierActionLoading"
                                />
                            </IconField>
                            <label for="paypal">{{ i18n.paypal }}</label>
                        </FloatLabel>
                    </div>

                    <!-- Phone -->
                    <div>
                        <FloatLabel variant="on">
                            <IconField>
                                <InputIcon class="pi pi-phone" />
                                <InputText
                                    id="phone"
                                    v-model="s.phone"
                                    class="w-full"
                                    :class="{ 'changed-field': isChanged(s, 'phone') }"
                                    :disabled="supplierActionLoading"
                                />
                            </IconField>
                            <label for="phone">{{ i18n.phone }}</label>
                        </FloatLabel>
                    </div>

                    <!-- Country -->
                    <div>
                        <FloatLabel variant="on">
                            <Select
                                id="country"
                                v-model="s.country"
                                :options="countryOptions"
                                optionLabel="label"
                                optionValue="value"
                                filter
                                class="w-full flex gap-x-2"
                                showClear
                                :class="{ 'changed-field': isChanged(s, 'country') }"
                                dropdownIcon="pi pi-globe"
                                :disabled="supplierActionLoading"
                            />
                            <label for="country">{{ i18n.country }}</label>
                        </FloatLabel>
                    </div>

                    <!-- Language -->
                    <div class="md:col-span-2">
                        <FloatLabel variant="on">
                            <Select
                                id="language"
                                v-model="s.language"
                                :options="languageOptions"
                                optionLabel="label"
                                optionValue="value"
                                filter
                                class="w-full"
                                showClear
                                :class="{ 'changed-field': isChanged(s, 'language') }"
                                dropdownIcon="pi pi-language"
                                :disabled="supplierActionLoading"
                            />
                            <label for="language">{{ i18n.language }}</label>
                        </FloatLabel>
                    </div>

                    <!-- Social Media -->
                    <div class="md:col-span-2 space-y-3">
                        <div
                            v-for="(social, index) in s.socials"
                            :key="index"
                            class="flex gap-2 items-center"
                        >
                            <FloatLabel variant="on" class="flex-1">
                                <IconField>
                                    <InputIcon :class="getSocialIcon(social.type)" />
                                    <InputText
                                        :id="'edit-social-' + s.id + '-' + social.type"
                                        v-model="social.url"
                                        class="w-full"
                                        :class="{ 'changed-field': socialChanged(s, social.type) }"
                                        :disabled="supplierActionLoading"
                                    />
                                </IconField>
                                <label :for="'edit-social-' + s.id + '-' + social.type">
                                    {{ getSocialLabel(social.type) }}
                                </label>
                            </FloatLabel>
                            <Button 
                                icon="pi pi-trash" 
                                @click="removeSocialFromSupplier(s.id, index)"
                                :disabled="supplierActionLoading"
                                severity="danger"  
                                variant="text"
                                aria-label="remove"
                            />

                        </div>

                        <!-- Add new dropdown -->
                        <div class="flex gap-2 items-center">
                            <FloatLabel variant="on" class="flex-1">
                                <Select
                                    v-model="selectedSocials[s.id]"
                                    :options="availableSocialOptions(s.socials)"
                                    optionLabel="label"
                                    class="w-full"
                                    inputId="social"
                                    :disabled="supplierActionLoading"
                                >
                                    <!-- Dropdown options -->
                                    <template #option="slotProps">
                                        <div class="flex items-center gap-2">
                                            <i :class="slotProps.option.icon"></i>
                                            <span>{{ slotProps.option.label }}</span>
                                        </div>
                                    </template>

                                    <!-- Selected value display -->
                                    <template #value="slotProps">
                                        <div v-if="slotProps.value" class="flex items-center gap-2">
                                            <i :class="slotProps.value.icon"></i>
                                            <span>{{ slotProps.value.label }}</span>
                                        </div>
                                    </template>
                                </Select>
                                <label for="social">{{ i18n.add_social }}</label>
                            </FloatLabel>

                            <Button
                                icon="pi pi-plus"
                                :label="i18n.add"
                                @click="addSocialToSupplier(s.id)"
                                :disabled="!selectedSocials[s.id] || supplierActionLoading"
                                severity="success" 
                                variant="outlined"
                                
                            />
                        </div>
                    </div>
                </div>
            </Panel>
        </transition-group>
       </component>
        
        <Dialog
            v-model:visible="showModal"
            modal
            :style="{ width: '40rem' }"
            class="rounded-xl"
        >
            <template #header>
                <div class="flex items-center gap-2 text-xl font-semibold">
                    <i class="pi pi-user-plus text-2xl"></i>
                    {{ i18n.add_supplier }}
                </div>
            </template>

            <div class="p-4 space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Name Input with IconField and Validation -->
                    <div>
                        <FloatLabel variant="on">
                            <IconField>
                                <InputIcon class="pi pi-user" />
                                <InputText 
                                    id="name" 
                                    v-model="newSupplier.name" 
                                    :invalid="validationErrors.name" 
                                    required
                                    class="w-full"
                                    :disabled="modalLoading"
                                />
                            </IconField>
                            <label for="name">{{ i18n.name }}</label>
                        </FloatLabel>
                    </div>

                    <!-- Email Input with IconField and Validation -->
                    <div>
                        <FloatLabel variant="on">
                            <IconField>
                                <InputIcon class="pi pi-envelope" />
                                <InputText 
                                    id="email" 
                                    v-model="newSupplier.email" 
                                    :invalid="validationErrors.email" 
                                    required
                                    class="w-full" 
                                    :disabled="modalLoading"
                                />
                            </IconField>
                            <label for="email">{{ i18n.email }}</label>
                        </FloatLabel>
                    </div>

                    <!-- PayPal Email Input with IconField and Validation -->
                    <div>
                        <FloatLabel variant="on">
                            <IconField>
                                <InputIcon class="pi pi-paypal" />
                                <InputText 
                                    id="paypal_email" 
                                    v-model="newSupplier.paypal_email" 
                                    :invalid="validationErrors.paypal_email" 
                                    required
                                    class="w-full" 
                                    :disabled="modalLoading"
                                />
                            </IconField>
                            <label for="paypal_email">{{ i18n.paypal }}</label>
                        </FloatLabel>
                    </div>

                    <!-- Phone Input with IconField and Validation -->
                    <div>
                        <FloatLabel variant="on">
                            <IconField>
                                <InputIcon class="pi pi-phone" />
                                <InputText 
                                    id="phone" 
                                    v-model="newSupplier.phone" 
                                    :invalid="validationErrors.phone" 
                                    required
                                    class="w-full" 
                                    :disabled="modalLoading"
                                />
                            </IconField>
                            <label for="phone">{{ i18n.phone }}</label>
                        </FloatLabel>
                    </div>

                    <!-- Country Select Input -->
                    <div>
                        <FloatLabel variant="on">
                            <Select
                                v-model="newSupplier.country"
                                :options="countryOptions"
                                optionLabel="label"
                                optionValue="value"
                                filter
                                class="w-full"
                                showClear
                                :invalid="validationErrors.country"
                                dropdownIcon="pi pi-globe"
                                :disabled="modalLoading"
                            />
                            <label for="country">{{ i18n.select_country }}</label>
                        </FloatLabel>
                    </div>

                    <!-- Language -->
                    <div>
                        <FloatLabel variant="on">
                            <Select
                                v-model="newSupplier.language"
                                :options="languageOptions"
                                optionLabel="label"
                                optionValue="value"
                                filter
                                class="w-full"
                                showClear
                                :invalid="validationErrors.language"
                                dropdownIcon="pi pi-language"
                                :disabled="modalLoading"
                            />
                            <label for="language">{{ i18n.language }}</label>
                        </FloatLabel>
                    </div>

                    <!-- Social Media Dropdown and Add Button -->
                    <div class="md:col-span-2 flex gap-2 items-center">
                        <FloatLabel variant="on" class="flex-1">
                            <Select
                                v-model="selectedSocial"
                                :options="availableSocialOptionsForNew"
                                optionLabel="label"
                                class="w-full"
                                inputId="social"
                                :disabled="modalLoading"
                            >
                                <!-- Dropdown options -->
                                <template #option="slotProps">
                                    <div class="flex items-center gap-2">
                                        <i :class="slotProps.option.icon"></i>
                                        <span>{{ slotProps.option.label }}</span>
                                    </div>
                                </template>

                                <!-- Selected value display -->
                                <template #value="slotProps">
                                    <div v-if="slotProps.value" class="flex items-center gap-2">
                                        <i :class="slotProps.value.icon"></i>
                                        <span>{{ slotProps.value.label }}</span>
                                    </div>
                                </template>
                            </Select>
                            <label for="social">{{ i18n.add_social }}</label>
                        </FloatLabel>

                        <!-- Add button (outside FloatLabel) -->
                        <Button
                            icon="pi pi-plus"
                            :label="i18n.add"
                            @click="addSocialPlatform"
                            :disabled="!selectedSocial"
                            severity="success" 
                            variant="outlined"
                        />
                    </div>


                    <!-- Render Styled Social Inputs -->
                    <div
                        v-for="(social, index) in newSupplier.socials"
                        :key="social.type"
                        class="flex items-center gap-3 mt-3"
                    >
                    <!-- Floating Label Input -->
                    <div class="flex-1">
                        <FloatLabel variant="on">
                            <IconField>
                                <InputIcon :class="getSocialIcon(social.type)" />
                                <InputText
                                :id="'social-' + social.type"
                                v-model="social.url"
                                class="w-full"
                                :disabled="modalLoading"
                            />
                        </IconField>
                            <label :for="'social-' + social.type">{{ getSocialLabel(social.type) }}</label>
                        </FloatLabel>
                    </div>

                    <!-- Remove Button -->
                    <Button
                        icon="pi pi-trash"
                        @click="removeSocialPlatform(index)"
                        :disabled="modalLoading"
                        severity="danger"
                        variant="text"
                        aria-label="remove"
                    />
                    </div>
                </div>

                <!-- Add Supplier Button -->
                <div class="flex justify-center text-center">
                    <Button 
                        :label="i18n.add_supplier"
                        icon="pi pi-check"
                        :loading="modalLoading"
                        :disabled="modalLoading"
                        @click="addSupplier"
                        class="w-full"
                        severity="contrast"
                        raised
                    />
                </div>
            </div>
        </Dialog>

        <Dialog
            :header="i18n.confirm_delete"
            v-model:visible="confirmDeleteVisible"
            modal
            :style="{ width: '30rem' }"
            class="rounded-xl"
        >
            <div class="p-4 space-y-4">
                <p class="text-base">{{ i18n.confirm_delete_msg }}</p>
                <div class="flex justify-end gap-2">

                <Button 
                    :label="i18n.cancel" 
                    @click="confirmDeleteVisible = false"
                    severity="contrast"
                    variant="text"
                    raised
                />

                <Button 
                    :label="i18n.yes_delete"
                    :loading="supplierActionLoading"
                    :disabled="supplierActionLoading"
                    @click="confirmDelete"
                    severity="danger"
                    raised
                />
                </div>
            </div>
        </Dialog>
    </div>
    `,
    data() {
        return {
            i18n: this.$root.i18n,
            countries: this.$root.countries,
            languages: this.$root.languages,
            newSupplier: this.$root.newSupplier,
            suppliers: {},
            supplierSettings: this.$root.supplierSettings || {},
            originalSuppliers: {},
            panelStates: {}, // e.g., { 1: false, 2: true }

            selectedSocial: null,
            selectedSocials: {},
            socialOptions: [
                { label: 'Etsy', value: 'etsy', icon: 'fa-brands fa-etsy' },
                { label: 'Facebook', value: 'facebook', icon: 'pi pi-facebook' },
                { label: 'Instagram', value: 'instagram', icon: 'pi pi-instagram' },
                { label: 'Twitter', value: 'twitter', icon: 'pi pi-twitter' },
                { label: 'LinkedIn', value: 'linkedin', icon: 'pi pi-linkedin' },
                { label: 'Discord', value: 'discord', icon: 'pi pi-discord' },
                { label: 'Pinterest', value: 'pinterest', icon: 'pi pi-pinterest' },
                { label: 'Reddit', value: 'reddit', icon: 'pi pi-reddit' },
                { label: 'Tiktok', value: 'tiktok', icon: 'pi pi-tiktok' },
            ],


            isRtl: document.documentElement.getAttribute('dir') === 'rtl',
            mobile: false,

            showModal: false,
            confirmDeleteVisible: false,
            supplierToDelete: null,
            validationErrors: {}, // No predefined errors

            supplierActionLoading: false,   // 🌍 page-level bulk saving
            modalLoading: false,      // 📦 individual modal actions (add/edit)

        };
    },
    computed: {
        countryOptions() {
            return Object.entries(this.countries).map(([code, name]) => ({ label: name, value: code }));
        },
        languageOptions() {
            if (!Array.isArray(this.languages)) return [];
            return this.languages.map(lang => ({
                label: lang.english_name,
                value: lang.code,
                english_name: lang.english_name,
                native_name: lang.native_name
            }));
        },
        availableSocialOptionsForNew() {
            const used = this.newSupplier.socials.map(s => s.type);
            return this.socialOptions.filter(opt => !used.includes(opt.value));
        },
        notInsideAdmin() {
            const standalone = document.body.classList.contains('havencore-manage-suppliers-standalone');
            // console.log('standalone:', standalone)
            return standalone
        }   
    },

    mounted() {
        this.$nextTick(() => {
            // Ensure the DOM is fully rendered before initializing the observer
            this.isRtl = document.documentElement.getAttribute('dir') === 'rtl';
            // console.log('Initial RTL: ', this.isRtl); // Debugging output

            this.checkScreenSize();
            window.addEventListener('resize', this.checkScreenSize);

            const observer = new MutationObserver(() => {
                const dir = document.documentElement.getAttribute('dir');
                this.isRtl = dir === 'rtl';
                // console.log('Updated RTL: ', this.isRtl); // Debugging output
            });

            observer.observe(document.documentElement, {
                attributes: true,
                attributeFilter: ['dir'],
            });
        });

        this.suppliers = JSON.parse(JSON.stringify(this.$root.suppliers));
        
        this.suppliers = JSON.parse(JSON.stringify(this.$root.suppliers)).map(s => ({
            ...s,
            socials: s.socials || []
        }));

        this.originalSuppliers = JSON.parse(JSON.stringify(this.suppliers));

        this.suppliers.forEach(s => {
            this.panelStates[s.id] = true;
            this.selectedSocials[s.id] = null; // <-- prevent undefined access
        });

    },
    beforeUnmount() {
        window.removeEventListener('resize', this.checkScreenSize);

        // Disconnect the MutationObserver when the component is unmounted
        if (this.observer) {
            this.observer.disconnect();
        }
    },
    methods: {
        formatDate(iso) {
            const d = new Date(iso);
            return d.toLocaleString(undefined, {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        },
        checkScreenSize() {
            this.mobile = window.innerWidth <= 768;
        },
        isChanged(supplier, field) {
            const original = this.originalSuppliers.find(o => o.id === supplier.id);
            if (!original) return false;
            return supplier[field] !== original[field];
        },
        socialChanged(supplier, type) {
            const original = this.originalSuppliers.find(o => o.id === supplier.id);
            if (!original) return false;

            const originalUrl = (original.socials || []).find(s => s.type === type)?.url || '';
            const currentUrl = (supplier.socials || []).find(s => s.type === type)?.url || '';

            return originalUrl !== currentUrl;
        },

        anyChanges() {
            if (!Array.isArray(this.suppliers)) return false;

            return this.suppliers.some(s => {
                const original = this.originalSuppliers.find(o => o.id === s.id);
                if (!original) return false;

                const topLevelChanged = ['name', 'email', 'paypal_email', 'phone', 'country', 'language']
                    .some(k => s[k] !== original[k]);

                const socialsChanged = (s.socials || []).some(social => this.socialChanged(s, social.type)) ||
                    (original.socials || []).length !== (s.socials || []).length;

                return topLevelChanged || socialsChanged;
            });
        },
        // Centralized validation function
        validateFields() {

            // Reset previous error messages
            this.validationErrors = {};

            // Name validation
            if (!this.newSupplier.name) {
                this.validationErrors.name = this.i18n.error_name || 'Name is required.';
            }

            // Email validation
            if (!this.newSupplier.email) {
                this.validationErrors.email = this.i18n.error_email || 'Email is required.';
            } else if (!this.isValidEmail(this.newSupplier.email)) {
                this.validationErrors.email = this.i18n.error_email_invalid || 'Email is not valid.';
            }

             // PayPal validation
            if (!this.newSupplier.paypal_email) {
                this.validationErrors.paypal_email = this.i18n.paypal_email || 'Paypal is required.';
            }

            // Phone validation
            if (!this.newSupplier.phone) {
                this.validationErrors.phone = this.i18n.error_phone || 'Phone is required.';
            }

            // Country validation
            if (!this.newSupplier.country) {
                this.validationErrors.country = this.i18n.error_country || 'Country is required.';
            }

            // Language validation
            if (!this.newSupplier.language) {
                this.validationErrors.language = this.i18n.error_language || 'Language is required.';
            }
        },

        // Reusable email validation method
        isValidEmail(email) {
            const regex = /^[a-zA-Z0-9._-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,6}$/;
            return regex.test(email);
        },


        saveSuppliers() {
            const groupedPayload = {
                name: {},
                email: {},
                paypal_email: {},
                phone: {},
                country: {},
                language: {},
                socials: {}
            };

            this.supplierActionLoading = true;

            // Group the data by field key for the PUT payload
            this.suppliers.forEach(supplier => {
                const id = supplier.id;
                for (const [key, value] of Object.entries(supplier)) {
                    if (key === 'id') continue;

                    if (key === 'socials') {
                        groupedPayload.socials[id] = [...value]; // Ensure array
                    } else if (groupedPayload[key] !== undefined) {
                        groupedPayload[key][id] = value;
                    }
                }
            });

            this.$toast.promise(
                wp.apiFetch({
                    path: '/hc/v1/suppliers/manage',
                    method: 'PUT',
                    data: groupedPayload
                }).then(response => {
                    this.originalSuppliers = JSON.parse(JSON.stringify(this.suppliers));
                    return response;
                }).catch(err => {
                    throw new Error(err?.message || this.i18n.save_failed);
                }),
                {
                    loading: this.i18n.saving_suppliers || 'Saving suppliers…',
                    success: this.i18n.update_success,
                    error: (err) => err.message || this.i18n.save_failed,
                    finally: () => { this.supplierActionLoading = false; },
                    duration: 3000
                }
            );
        },
        addSupplier() {
            this.validationErrors = {}; // Reset previous errors

            if (this.supplierSettings.strict_validation) {
                this.validateFields();

                if (Object.keys(this.validationErrors).length > 0) {
                    this.$toast.error(this.i18n.error, {
                        description: this.i18n.error_missing_fields,
                        duration: 3000
                    });
                    return;
                }
            }

            const payload = { ...this.newSupplier };
            payload.socials = [...payload.socials]; // Ensure clean array

            this.modalLoading = true;

            this.$toast.promise(
                wp.apiFetch({
                    path: '/hc/v1/suppliers/manage',
                    method: 'POST',
                    data: payload
                })
                .then(json => {
                    const newId = json.id;

                    const newSupplier = {
                        id: newId,
                        ...this.newSupplier,
                        socials: [...this.newSupplier.socials]
                    };

                    this.suppliers.unshift(newSupplier);
                    this.panelStates[newId] = true;
                    this.originalSuppliers = JSON.parse(JSON.stringify(this.suppliers));

                    this.newSupplier = {
                        name: '', email: '', paypal_email: '', phone: '', country: '', language: '',
                        socials: []
                    };
                    this.selectedSocial = null;
                    this.showModal = false;
                    this.validationErrors = {};

                    return json;
                })
                .catch(err => {
                    if (err?.data?.fields) {
                        this.validationErrors = err.data.fields;
                    }
                    throw new Error(err?.message || this.i18n.add_failed);
                }),
                {
                    loading: this.i18n.adding_supplier || 'Adding supplier…',
                    success: this.i18n.supplier_added,
                    error: (err) => err.message || this.i18n.add_failed,
                    finally: () => { this.modalLoading = false; },
                    duration: 3000
                }
            );
        },

        deleteSupplier(id) {
            this.supplierToDelete = id;
            this.confirmDeleteVisible = true;
        },
        confirmDelete() {
            this.supplierActionLoading = true;

            this.$toast.promise(
                wp.apiFetch({
                    path: '/hc/v1/suppliers/manage',
                    method: 'DELETE',
                    data: { supplier_id: this.supplierToDelete },
                })
                .then(json => {
                    this.suppliers = this.suppliers.filter(s => s.id !== this.supplierToDelete);
                    this.originalSuppliers = JSON.parse(JSON.stringify(this.suppliers));
                    return json;
                }),
                {
                    loading: this.i18n.deleting_supplier || 'Deleting supplier…',
                    success: this.i18n.supplier_deleted_success || 'Supplier deleted.',
                    error: (err) => err.message || this.i18n.delete_failed || 'Delete failed.',
                    finally: () => {
                        this.confirmDeleteVisible = false;
                        this.supplierToDelete = null;
                        this.supplierActionLoading = false;
                    },
                    duration: 3000
                }
            );
        },
        // Additional helpers
        addSocialToSupplier(id) {
            const supplier = this.suppliers.find(s => s.id === id);
            const selected = this.selectedSocials[id];
            if (supplier && selected) {
                const type = selected.value;
                supplier.socials.push({ type, url: '' });

                this.$nextTick(() => {
                    const input = document.getElementById(`edit-social-${id}-${type}`);
                    if (input) {
                        input.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        input.focus();
                        input.select();
                    }
                });

                this.selectedSocials[id] = null;
            }
        },
        removeSocialFromSupplier(id, index) {
            const supplier = this.suppliers.find(s => s.id === id);
            if (supplier) {
                supplier.socials.splice(index, 1);
            }
        },

        availableSocialOptions(currentSocials) {
            const used = currentSocials.map(s => s.type);
            return this.socialOptions.filter(opt => !used.includes(opt.value));
        },
        getMenuItems(supplierId) {
            return [
                // {
                //     label: 'Refresh',
                //     icon: 'pi pi-refresh',
                //     command: () =>
                // },
                // {
                //     label: 'Search',
                //     icon: 'pi pi-search',
                //     command: () => 
                // },
                //     { separator: true },
                {
                    label: this.i18n.delete,
                    icon: 'pi pi-times',
                    class: 'text-red-600 hover:bg-red-50 hover:text-red-700 font-semibold',
                    command: () => this.deleteSupplier(supplierId)
                }
            ];
        },
        toggleMenu(id, event) {
            this.$nextTick(() => {
                const menuRef = this.$refs['menu-' + id];
                if (Array.isArray(menuRef)) {
                    // In Vue 3 with v-for, $refs becomes an array
                    menuRef[0]?.toggle?.(event);
                } else {
                    menuRef?.toggle?.(event);
                }
            });
        },
        setInitialName(el, name) {
            if (el && !el.dataset.initialized) {
                el.innerText = name;
                el.dataset.initialized = 'true';
            }
        },
        addSocialPlatform() {
            if (this.selectedSocial) {
                const type = this.selectedSocial.value;
                this.newSupplier.socials.push({ type, url: '' });

                this.$nextTick(() => {
                    const input = document.getElementById('social-' + type);
                    if (input) {
                        input.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        input.focus();
                        input.select();
                    }
                });

                this.selectedSocial = null;
            }
        },
        removeSocialPlatform(index) {
            this.newSupplier.socials.splice(index, 1);
        },
        getSocialIcon(type) {
            return this.socialOptions.find(opt => opt.value === type)?.icon || '';
        },
        getSocialLabel(type) {
            return this.socialOptions.find(opt => opt.value === type)?.label || type;
        }

    }
});

app.mount('#supplier-app');

function initSupplierApp(loader) {
    const wrapper = document.getElementById('supplier-app-wrapper');
    if (wrapper) {
        wrapper.style.display = 'block';
        requestAnimationFrame(() => wrapper.classList.add('ready'));
    }
}

</script>

<style>

    img {
        border: none !important;
        display: inline-block !important;
    }

    .scroll-panel-area {
        height: 80vh;
        overflow-x: hidden;
    }

    #supplier-app-wrapper {
        display: none;
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    #supplier-app-wrapper.ready {
        opacity: 1;
    }

</style>