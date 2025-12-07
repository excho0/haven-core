<?php

    use HavenCore\Classes\HC_Settings;
    use HavenCore\Utils\ArrayHelpers;

    if (!current_user_can('manage_options')) {
        return;
    }

    // Enqueue the core WP REST API script
	wp_enqueue_script( 'wp-api-fetch' );

    use HavenCore\Utils\ScriptHelpers;

    ScriptHelpers::loadVue();


    $settings = new HC_Settings();
    $settingsData = $settings->getAll();
    $fieldMeta = $settings->getMeta();
    $i18n = ArrayHelpers::extractTranslations(HC_Settings::$settingSchema);
?>


<div id="havencore-app">
    <havencore-app></havencore-app>
</div>

<script type="application/json" id="app-initial-data">
  <?= json_encode([
    'settings' => $settingsData,
    'fieldMeta' => $fieldMeta,
    'i18n' => array_merge(
        $i18n,
        [
            // ─── Plugin UI ──────────────────────────────────────
            'plugin_name'         => PLUGIN_NAME,
            'settings'            => __('Settings', HAVEN_CORE_TEXT_DOMAIN),
            'save'                => __('Save Settings', HAVEN_CORE_TEXT_DOMAIN),
            'no_data'             => __('No settings available.', HAVEN_CORE_TEXT_DOMAIN),
            'back_to_wp_admin'    => __('Back to WP Admin', HAVEN_CORE_TEXT_DOMAIN),

            // ─── Sidebar / Navigation ──────────────────────────
            'home'                => __('Home', HAVEN_CORE_TEXT_DOMAIN),
            'dashboard'           => __('Dashboard', HAVEN_CORE_TEXT_DOMAIN),
            'collapse'            => __('Collapse', HAVEN_CORE_TEXT_DOMAIN),
            'expand'              => __('Expand', HAVEN_CORE_TEXT_DOMAIN),
            // 'general'         => __('General', HAVEN_CORE_TEXT_DOMAIN),

            // ─── Toast Notifications ───────────────────────────
            'settings_updated'    => __('Settings updated successfully!', HAVEN_CORE_TEXT_DOMAIN),
            'saving_settings'     => __('Saving your settings…', HAVEN_CORE_TEXT_DOMAIN),
            'save_failed'         => __('Failed to save settings. Please try again.', HAVEN_CORE_TEXT_DOMAIN),
            'success'             => __('Success', HAVEN_CORE_TEXT_DOMAIN),
            'error'               => __('Error', HAVEN_CORE_TEXT_DOMAIN)
        ]
    )
  ]) ?>
</script>

<script>    
    const initialData = JSON.parse(document.getElementById('app-initial-data')?.textContent || '{}');

    const useLocalStore = Pinia.defineStore('LocalStore', () => {
        const appSettings = Vue.reactive({
            data: <?= json_encode($settingsData)?>,       // This holds the actual settings object
            ready: false    // Indicates if settings have been loaded
        });

        setTimeout(()=> {appSettings.ready = true;}, 1000 )

        async function saveSettings() {
            try {

                const res = await wp.apiRequest({
                    path: '/hc/v1/settings',
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    data: JSON.stringify({ settings: appSettings.data }) // this is the correct line

                });

                console.log('✅ Settings saved:', res);
                return true;
            } catch (err) {
                console.error('❌ Failed to save settings:', err);
                return false;
            }
        }

        return {
            appSettings,
            saveSettings,
        };
    });

    // console.log('initialData:', initialData);

    const app = Vue.createApp({
        data() {
            // Initialize sidebarItems dynamically based on i18n
            const sidebarItems = [
                {
                    name: initialData.i18n.home,  // General label
                    icon: 'pi pi-home',           // Icon for Home
                    route: '/'                    // Route for Home section
                },
                {
                    name: initialData.i18n.dashboard,
                    icon: 'pi pi-chart-bar',           
                    route: '/dashboard'
                },
                {
                    name: initialData.i18n.settings,
                    icon: 'pi pi-cog',
                    route: '/settings'
                }
            ];


            // Log sidebarItems initialization to check if it's set correctly
            // console.log('sidebarItems initialized:', sidebarItems);

            // Access the Pinia stores
            const globalStore = useGlobalStore();

            // Use a computed property to ensure reactivity
            const mobile = Vue.computed(() => globalStore.isMobile);

            const Rtl = Vue.computed(() => globalStore.isRtl);


            // Directly return the settings object here
            return {
                i18n: initialData.i18n,

                fieldMeta: initialData.fieldMeta || {},
                sidebarItems: sidebarItems || [], // Sidebar items for navigation
                mobile: mobile,
                isRtl: Rtl,
            };
        },
        provide() {
            return {
                i18n: this.i18n,
                fieldMeta: this.fieldMeta,
            };
        }
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
    app.use(VueSonner)



    app.component('InputText', PrimeVue.InputText);
    app.component('FloatLabel', PrimeVue.FloatLabel);
    app.component('IconField', PrimeVue.IconField);
    app.component('InputIcon', PrimeVue.InputIcon);

    app.component('Button', PrimeVue.Button);
    app.directive('tooltip', PrimeVue.Tooltip);
    app.component('Panel', PrimeVue.Panel);
    app.component('ToggleSwitch', PrimeVue.ToggleSwitch);
    app.component('ScrollPanel', PrimeVue.ScrollPanel);

    // 🎨 Visual Feedback
    app.component('Skeleton', PrimeVue.Skeleton);


    const Home = {
        template: `
            <div class="overflow-y-auto h-full p-4">
                <h1 class="text-xl font-semibold">{{ i18n.home }}</h1>
                <p>Home Content Goes Here...</p>
            </div>
        `,
        inject: ['i18n'],  // Inject the i18n data into this component
    };

    const Dashboard = {
        template: `
            <div class="overflow-y-auto h-full p-4">
                <h1 class="text-xl font-semibold">{{ i18n.dashboard }}</h1>
                <p>Dash Content Goes Here...</p>
            </div>
        `,
        inject: ['i18n'],  // Inject the i18n data into this component
    };

    const Settings = {
        data() {
            const globalStore = useGlobalStore();
            const localStore = useLocalStore();

            const mobile = Vue.computed(() => globalStore.isMobile);
            const isRtl = Vue.computed(() => globalStore.isRtl);

            const AppSettings = Vue.computed(() => localStore.appSettings);
            const OriginalSettings = Vue.ref(JSON.parse(JSON.stringify(localStore.appSettings.data)));

            return {
                globalStore,
                localStore,

                mobile,
                isRtl,

                settings: AppSettings,
                originalSettings: OriginalSettings,

                saving_settings: false,
                panelStates: {},
                panelsReady: false
            };
        },
        template: `
            <div class="h-full p-4 flex flex-col space-y-4">

                <!-- Header + Buttons Row (NOT sticky, stays at top of scrollable area) -->
                <div class="flex flex-wrap justify-between items-center gap-4">
                    <h1 class="text-xl font-semibold">{{ i18n.settings }}</h1>

                        <div class="flex items-center gap-3">

                            <!-- Show only when changes are detected -->
                            <transition name="fade" mode="out-in">
                                <div v-if="changesDetected" class="flex items-center gap-3">
                                    <Button 
                                        :label="mobile ? null : i18n.save"
                                        icon="pi pi-save"
                                        @click="saveSettings"
                                        :loading="saving_settings"
                                        :disabled="saving_settings"
                                        size="small"
                                        severity="contrast" 
                                        raised
                                    />
                                    <Button 
                                        :label="mobile ? null : 'Revert'"
                                        icon="pi pi-refresh"
                                        @click="revertChanges"
                                        :disabled="saving_settings"
                                        size="small"
                                        severity="contrast"
                                        variant="text" 
                                        raised
                                    />
                                </div>
                            </transition>



                            <Button
                                icon="pi pi-angle-double-up"
                                :label="mobile ? null : i18n.expand"
                                @click="setAllPanels(false)"
                                size="small"
                                severity="contrast"
                                variant="text" 
                                raised
                            />

                            <Button
                                icon="pi pi-angle-double-down"
                                :label="mobile ? null : i18n.collapse"
                                @click="setAllPanels(true)"
                                size="small"
                                severity="contrast"
                                variant="text" 
                                raised
                            />
                        </div>
                </div>

            <transition 
                name="quick-fade" 
                mode="out-in"
            >
                <!-- Show skeleton loader if settings are not ready -->
                <template v-if="!settings?.ready">
                    <div class="w-full lg:w-2/3 mx-auto p-4 space-y-4 h-full">

                        <!-- Panel 1: Expanded -->
                        <div class="p-4 rounded-xl border order-loader space-y-4">
                            <Skeleton height="1.5rem" width="35%" class="rounded" />

                            <div class="space-y-3">
                                <div class="flex items-center justify-between">
                                    <Skeleton height="1.25rem" width="60%" class="rounded" />
                                    <Skeleton height="1.25rem" width="2rem" class="rounded-full" />
                                </div>

                                <div class="pl-4 space-y-3 border-l order-loader">
                                    <Skeleton height="1.25rem" width="30%" class="rounded" />
                                    <Skeleton height="1.25rem" width="100%" class="rounded" />
                                    <Skeleton height="1.25rem" width="70%" class="rounded" />
                                </div>
                            </div>
                        </div>

                        <!-- Panel 2: Collapsed -->
                        <div class="p-4 rounded-xl border order-loader flex items-center justify-between">
                            <Skeleton height="1.5rem" width="25%" class="rounded" />
                            <Skeleton height="1.25rem" width="1.5rem" class="rounded-full" />
                        </div>

                        <!-- Panel 3: Expanded with nested subpanel -->
                        <div class="p-4 rounded-xl border order-loader space-y-4">
                            <Skeleton height="1.5rem" width="30%" class="rounded" />

                            <div class="pl-4 space-y-3 border-l order-loader">
                                <div class="flex items-center justify-between">
                                    <Skeleton height="1.25rem" width="50%" class="rounded" />
                                    <Skeleton height="1.25rem" width="2rem" class="rounded-full" />
                                </div>

                                <!-- Nested Panel -->
                                <div class="pl-4 border-l order-loader space-y-2">
                                    <Skeleton height="1.25rem" width="30%" class="rounded" />
                                    <Skeleton height="1.25rem" width="100%" class="rounded" />
                                    <Skeleton height="1.25rem" width="80%" class="rounded" />
                                </div>
                            </div>
                        </div>

                        <!-- Panel 4: Collapsed -->
                        <div class="p-4 rounded-xl border order-loader flex items-center justify-between">
                            <Skeleton height="1.5rem" width="20%" class="rounded" />
                            <Skeleton height="1.25rem" width="1.5rem" class="rounded-full" />
                        </div>

                        <!-- Panel 5: Expanded -->
                        <div class="p-4 rounded-xl border order-loader space-y-4">
                            <Skeleton height="1.5rem" width="35%" class="rounded" />

                            <div class="space-y-3">
                                <div class="flex items-center justify-between">
                                    <Skeleton height="1.25rem" width="60%" class="rounded" />
                                    <Skeleton height="1.25rem" width="2rem" class="rounded-full" />
                                </div>

                                <div class="pl-4 space-y-3 border-l order-loader">
                                    <Skeleton height="1.25rem" width="30%" class="rounded" />
                                    <Skeleton height="1.25rem" width="100%" class="rounded" />
                                    <Skeleton height="1.25rem" width="70%" class="rounded" />
                                </div>
                            </div>
                        </div>

                    </div>
                </template>


                <ScrollPanel
                    v-else
                    :style="{ height: '80svh' }"
                    :class="[
                        'flex-1 p-4 mx-auto w-full lg:w-2/3',
                        'overflow-hidden'

                    ]"

                    :pt="{
                        content: { class: 'flex flex-col gap-y-2' },

                    }"
                >

                    <Panel 
                        v-if="panelsReady"
                        v-for="(group, groupKey) in settings.data" 
                        :key="groupKey" 
                        toggleable
                        v-model:collapsed="panelStates[groupKey]"
                        class="m-2"
                    >
                        <template #header>
                            <span class="flex items-center gap-3 text-base font-semibold capitalize tracking-wide">
                                <i
                                    :class="fieldMeta?.[groupKey]?.icon || 'pi pi-folder'"
                                    class="text-lg text-primary-500"
                                    v-tooltip.top="i18n[groupKey]?.tooltip || ''"
                                />
                                {{ i18n[groupKey]?.label || groupKey }}
                            </span>
                        </template>

                        <div class="space-y-4">
                            <div v-for="(value, fieldKey) in group" :key="fieldKey">
                                <!-- Nested object -->
                                <div v-if="typeof value === 'object' && value !== null && !Array.isArray(value)">
                                    <Panel 
                                        toggleable 
                                        class="ml-4"
                                        v-if="panelsReady"
                                        v-model:collapsed="panelStates[groupKey + '.' + fieldKey]"
                                    >
                                        <template #header>
                                            <span class="flex items-center gap-3 text-base font-semibold capitalize tracking-wide">
                                                <i
                                                    :class="fieldMeta?.[groupKey]?.[fieldKey]?.icon || 'pi pi-folder-open'"
                                                    class="text-lg !text-primary-500"
                                                    v-tooltip.top="i18n[groupKey + '.' + fieldKey]?.tooltip || ''"
                                                />
                                                {{ i18n[groupKey + '.' + fieldKey]?.label || fieldKey.replace(/_/g, ' ') }}
                                            </span>
                                        </template>

                                        <div v-for="(nestedVal, nestedKey) in value" :key="nestedKey" class="mb-4">
                                            <template v-if="typeof nestedVal === 'boolean'">
                                                <div class="setting-option_boolean flex items-center justify-between shadow-sm">
                                                    <span class="flex items-center gap-2 font-medium capitalize">
                                                    <i 
                                                        :class="fieldMeta?.[groupKey]?.[fieldKey]?.[nestedKey]?.icon || 'pi pi-cog'"
                                                        class="text-lg !text-primary-500"
                                                    />
                                                    {{ i18n[groupKey + '.' + fieldKey + '.' + nestedKey]?.label || nestedKey.replace(/_/g, ' ') }}

                                                    <!-- Dedicated tooltip icon if tooltip exists -->
                                                    <i
                                                        v-if="i18n[groupKey + '.' + fieldKey + '.' + nestedKey]?.tooltip"
                                                        class="pi pi-question-circle text-primary-400 cursor-pointer"
                                                        v-tooltip="{
                                                            value: i18n[groupKey + '.' + fieldKey + '.' + nestedKey]?.tooltip,
                                                            position: isRtl ? 'right' : 'left',
                                                            autoHide: false
                                                        }"
                                                        style="font-size: 1rem;"
                                                    />
                                                    </span>

                                                    <ToggleSwitch
                                                        v-model="settings.data[groupKey][fieldKey][nestedKey]"
                                                        :aria-label="i18n[groupKey + '.' + fieldKey + '.' + nestedKey]?.label || nestedKey"
                                                    />
                                                </div>
                                            </template>

                                            <FloatLabel variant="on" v-else>
                                                <IconField>
                                                    <InputIcon>
                                                        <i :class="fieldMeta?.[groupKey]?.[fieldKey]?.[nestedKey]?.icon || 'pi pi-pencil'" />
                                                    </InputIcon>
                                                    <InputText
                                                        v-model="settings.data[groupKey][fieldKey][nestedKey]"
                                                        v-tooltip.focus.top="i18n[groupKey + '.' + fieldKey + '.' + nestedKey]?.tooltip || ''"
                                                        :class="{ 'changed-field': isChanged(groupKey, fieldKey, nestedKey) }"
                                                        class="w-full"
                                                    />
                                                </IconField>
                                                <label>
                                                    {{ i18n[groupKey + '.' + fieldKey + '.' + nestedKey]?.label || nestedKey.replace(/_/g, ' ') }}
                                                </label>
                                            </FloatLabel>
                                        </div>
                                    </Panel>
                                </div>

                                <!-- Primitive value -->
                                <div v-else class="mb-4">
                                    <template v-if="typeof value === 'boolean'">
                                        <div class="setting-option_boolean flex items-center justify-between shadow-sm">
                                            <span class="flex items-center gap-2 font-medium capitalize">
                                                <i
                                                    :class="fieldMeta?.[groupKey]?.[fieldKey]?.icon || 'pi pi-cog'"
                                                    class="text-lg text-primary-500"
                                                />
                                                {{ i18n[groupKey + '.' + fieldKey]?.label || fieldKey.replace(/_/g, ' ') }}

                                                <!-- Dedicated tooltip icon -->
                                                <i
                                                    v-if="i18n[groupKey + '.' + fieldKey]?.tooltip"
                                                    class="pi pi-question-circle text-primary-400 cursor-pointer"
                                                    v-tooltip="{
                                                        value: i18n[groupKey + '.' + fieldKey]?.tooltip,
                                                        position: isRtl ? 'right' : 'left',
                                                        autoHide: false
                                                    }"
                                                    style="font-size: 1rem;"
                                                />
                                            </span>

                                            <ToggleSwitch
                                                v-model="settings.data[groupKey][fieldKey]"
                                                :aria-label="i18n[groupKey + '.' + fieldKey]?.label || fieldKey"
                                            />
                                        </div>
                                    </template>

                                    <FloatLabel variant="on" v-else>
                                        <IconField>
                                            <InputIcon>
                                                <i :class="fieldMeta?.[groupKey]?.[fieldKey]?.icon || 'pi pi-pencil'" />
                                            </InputIcon>
                                            <InputText
                                                v-model="settings.data[groupKey][fieldKey]"
                                                v-tooltip.focus.top="i18n[groupKey + '.' + fieldKey]?.tooltip || ''"
                                                :class="{ 'changed-field': isChanged(groupKey, fieldKey) }"
                                                class="w-full"
                                            />
                                        </IconField>
                                        <label>
                                            {{ i18n[groupKey + '.' + fieldKey]?.label || fieldKey.replace(/_/g, ' ') }}
                                        </label>
                                    </FloatLabel>
                                </div>
                            </div>
                        </div>
                    </Panel>
                </ScrollPanel>
            </transition>

            </div>
        `,
        inject: ['i18n', 'fieldMeta'],
        computed: {
            changesDetected() {
                if (!this.settings?.ready || !this.settings.data) return false;
                    const data = this.settings.data;
                    const original = this.originalSettings || {};

                    for (const groupKey in data) {
                        const group = data[groupKey] || {};
                        for (const fieldKey in group) {
                            const value = group[fieldKey];
                            if (typeof value === 'object' && value !== null && !Array.isArray(value)) {
                                for (const nestedKey in value) {
                                    if (this.isChanged(groupKey, fieldKey, nestedKey)) {
                                        return true;
                                    }
                                }
                            } else {
                                if (this.isChanged(groupKey, fieldKey)) {
                                    return true;
                                }
                            }
                        }
                    }
                return false;
            }
        },
        mounted() {
            const savedStates = JSON.parse(localStorage.getItem('hc_settings_panelStates') || '{}');

            for (const groupKey in this.settings.data) {
                this.panelStates[groupKey] = savedStates[groupKey] === false ? false : true;

                const group = this.settings.data[groupKey];
                for (const fieldKey in group) {
                    const val = group[fieldKey];
                    if (typeof val === 'object' && val !== null && !Array.isArray(val)) {
                        const key = `${groupKey}.${fieldKey}`;
                        this.panelStates[key] = savedStates[key] === false ? false : true;
                    }
                }
            }

            this.panelsReady = true;
        },
        watch: {
            panelStates: {
                handler(newVal) {
                    const expanded = Object.entries(newVal)
                        .filter(([, collapsed]) => collapsed === false) // panels that are expanded
                        .reduce((acc, [key]) => {
                            acc[key] = false;
                            return acc;
                        }, {});

                    const key = 'hc_settings_panelStates';

                    if (Object.keys(expanded).length === 0) {
                        localStorage.removeItem(key); // Remove key if all are collapsed
                    } else {
                        localStorage.setItem(key, JSON.stringify(expanded));
                    }
                },
                deep: true
            }
        },
        methods: {
            setAllPanels(state) {
                for (const key in this.panelStates) {
                    this.panelStates[key] = state;
                }
            },
            saveSettings() {
                this.saving_settings = true;

                this.$toast.promise(
                    this.localStore.saveSettings(),
                    {
                        loading: this.i18n.saving_settings,
                        success: () => {
                            // Deep clone the saved data into originalSettings
                            this.originalSettings = JSON.parse(JSON.stringify(this.settings.data)); // FULL replacement
                            return this.i18n.settings_updated;
                        },
                        error: this.i18n.save_failed,
                        finally: () => {
                            this.saving_settings = false;
                        }
                    }
                )
            },

            revertChanges() {
                // Replace the current appSettings.data with a deep clone of originalSettings
                this.settings.data = JSON.parse(JSON.stringify(this.originalSettings));
            },

            isChanged(groupKey, fieldKey, nestedKey = null) {
                if (!this.settings?.ready || !this.settings.data?.[groupKey]) return false;

                const current = nestedKey
                    ? this.settings.data[groupKey][fieldKey]?.[nestedKey]
                    : this.settings.data[groupKey]?.[fieldKey];

                const original = nestedKey
                    ? this.originalSettings?.[groupKey]?.[fieldKey]?.[nestedKey]
                    : this.originalSettings?.[groupKey]?.[fieldKey];

                return current !== original;
            },
  
        }
    };

    const Routes = [
        { 
            path: '/', 
            name: 'home',
            component: Home,
            meta: { title: initialData.i18n.home }

        },
        { 
            path: '/dashboard', 
            name: 'dashboard',
            component: Dashboard,
            meta: { title: initialData.i18n.dashboard }

        },
        { 
            path: '/Settings', 
            name: 'settings',
            component: Settings,
            meta: { title: initialData.i18n.settings }
        },
        // Catch-all fallback: redirect unknown paths to home
        {
            path: '/:pathMatch(.*)*',
            redirect: { name: 'home' }
        }
    ]
    
    const router = VueRouter.createRouter({
        history: VueRouter.createWebHashHistory(),
        routes: Routes
    });

    router.beforeEach((to, from, next) => {
        // If the route has an i18n title set in its meta, use it
        const title = to.meta.title || 'Default Title';  // Use a fallback title if none is set
        document.title = `${initialData.i18n.plugin_name} | ${title}`;
        next();  // Always call next() to proceed with the navigation
    });

    app.component('havencore-app', {
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
                <header class="py-4 px-5 flex justify-between items-center z-20">
                    <div class="flex items-center justify-center gap-x-2">
                        <Button 
                            @click="goToAdmin"
                            icon="dashicons dashicons-wordpress"
                            iconPos="left"
                            severity="secondary"
                            raised
                            v-tooltip="{
                                value: i18n.back_to_wp_admin,
                                position: isRtl ? 'right' : 'left'
                                
                            }"
                        />

                        <Button 
                            icon="pi pi-bars" 
                            v-if="mobile"
                            severity="secondary"
                            raised
                            @click="toggleSidebar" 
                        />
                    </div>

                    <h2 class="text-2xl font-semibold">{{ i18n.plugin_name }}</h2>
                </header>

                <!-- Main Area: Sidebar + Content -->
                <div class="flex flex-1 overflow-hidden shadow-sm">
                    <!-- Static Sidebar -->
                    <aside v-if="!mobile" :class="['w-64', 'flex', 'flex-col', { 'collapsed': isCollapsed }]">
                        <!-- Sidebar items -->
                        <ul class="list-none m-0 p-4 space-y-2 text-sm flex-grow shrink-0">
                            <li v-for="(item, index) in sidebarItems" :key="index" style="line-height: 1rem;">
                                <router-link 
                                    :to="item.route" 
                                    class="aside-link"
                                    active-class="aside-link-active"
                                    v-tooltip="isCollapsed ? item.name : ''"
                                >
                                    <i :class="item.icon + ' shrink-0 transform-none'" />
                                
                                    <transition name="fade-label">
                                        <span v-if="!isCollapsed" class="font-medium">{{ item.name }}</span>
                                    </transition>
                    
                                </router-link>
                            </li>
                        </ul>

                        <!-- Collapse/Expand Button at the bottom -->
                        <div class="flex justify-center p-4 w-full mb-12">
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
                            class="fixed top-0 w-64 shadow-md h-full z-50 bg-automatic"
                        >
                            <ul class="list-none m-0 p-4 space-y-2 text-sm flex-grow">
                                <li v-for="(item, index) in sidebarItems" :key="index">
                                    <router-link 
                                        :to="item.route" 
                                        class="aside-link"
                                        active-class="aside-link-active"
                                        @click="closeSidebar"
                                    >
                                        <i :class="item.icon + ' transform-none'"></i>
                                        <span class="font-medium text-sm leading-tight">{{ item.name }}</span>
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
                                    :key="$route.fullPath"
                                    class="app-content-styled h-full"
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
            const mobile = Vue.computed(() => globalStore.isMobile);

            const Rtl = Vue.computed(() => globalStore.isRtl);


            return {
                i18n: this.$root.i18n || {},  // Fallback to empty object if i18n is not defined
                settings: this.$root.settings || {},
                sidebarItems: this.$root.sidebarItems || {},
                panelStates: {}, // e.g., { 1: false, 2: true }

                isCollapsed: false, // Sidebar collapse state
                isSidebarOpen: false,  // New state to track the sidebar visibility on mobile

                mobile: mobile,
                isRtl: Rtl,

            };
        },
        mounted() {

            this.isCollapsed = true;

        },
        methods: {
            // =============================
            // General Utility Methods
            // =============================
            isChanged(key) {
                return this.settings[key] !== this.$root.originalSettings[key];
            },

            goToAdmin() {
                window.location.href = '<?= admin_url(); ?>';
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
