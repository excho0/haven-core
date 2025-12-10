<?php

    use HavenCore\Classes\HC_Settings;
    use HavenCore\Utils\ArrayHelpers;

    if (!current_user_can('manage_options')) {
        return;
    }

    use HavenCore\Utils\ScriptHelpers;

    ScriptHelpers::loadVue();
    ScriptHelpers::loadApiFetch();


    $settings = new HC_Settings();
    $current_user = wp_get_current_user();
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
    'currentUser' => [
        'is_admin'     => current_user_can('manage_woocommerce') || current_user_can('manage_options'),
        'display_name' => $current_user->display_name ?: $current_user->user_login,
        'email'        => $current_user->user_email,
    ],
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
            'communications'      => __('Communications', HAVEN_CORE_TEXT_DOMAIN),
            'conversations'       => __('Conversations', HAVEN_CORE_TEXT_DOMAIN),
            'messages'            => __('Messages', HAVEN_CORE_TEXT_DOMAIN),
            'select_conversation' => __('Select a conversation to get started.', HAVEN_CORE_TEXT_DOMAIN),
            'no_conversations'    => __('No conversations yet.', HAVEN_CORE_TEXT_DOMAIN),
            'no_messages'         => __('No messages in this conversation yet.', HAVEN_CORE_TEXT_DOMAIN),
            'start_messaging'     => __('Type your first message to kick things off.', HAVEN_CORE_TEXT_DOMAIN),
            'refresh'             => __('Refresh', HAVEN_CORE_TEXT_DOMAIN),
            'new_message'         => __('New Message', HAVEN_CORE_TEXT_DOMAIN),
            'message_placeholder' => __('Type a message…', HAVEN_CORE_TEXT_DOMAIN),
            'send_message'        => __('Send Message', HAVEN_CORE_TEXT_DOMAIN),
            'you'                 => __('You', HAVEN_CORE_TEXT_DOMAIN),
            'mark_as_read'        => __('Mark as Read', HAVEN_CORE_TEXT_DOMAIN),
            'delete_conversation' => __('Delete Conversation', HAVEN_CORE_TEXT_DOMAIN),
            'confirm_delete_conversation' => __('Are you sure you want to delete this conversation? This action cannot be undone.', HAVEN_CORE_TEXT_DOMAIN),
            'confirm'             => __('Confirm', HAVEN_CORE_TEXT_DOMAIN),
            'dismiss'             => __('Cancel', HAVEN_CORE_TEXT_DOMAIN),
            'new_conversation'    => __('New Conversation', HAVEN_CORE_TEXT_DOMAIN),
            'create'              => __('Create', HAVEN_CORE_TEXT_DOMAIN),
            'cancel'              => __('Cancel', HAVEN_CORE_TEXT_DOMAIN),
            'supplier_id'         => __('Supplier ID', HAVEN_CORE_TEXT_DOMAIN),
            'supplier'            => __('Supplier', HAVEN_CORE_TEXT_DOMAIN),
            'supplier_placeholder' => __('Select a supplier', HAVEN_CORE_TEXT_DOMAIN),
            'order_id'            => __('Order ID (optional)', HAVEN_CORE_TEXT_DOMAIN),
            'subject'             => __('Subject', HAVEN_CORE_TEXT_DOMAIN),
            'initial_message'     => __('Initial Message', HAVEN_CORE_TEXT_DOMAIN),
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

                const res = await wp.apiFetch({
                    path: '/hc/v1/settings',
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ settings: appSettings.data })
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
                    name: initialData.i18n.communications,
                    icon: 'pi pi-comments',
                    route: '/communications'
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
            const mobile = Vue.computed(() => globalStore.isMobile || globalStore.isTablet);

            const Rtl = Vue.computed(() => globalStore.isRtl);


            // Directly return the settings object here
            return {
                i18n: initialData.i18n,

                fieldMeta: initialData.fieldMeta || {},
                sidebarItems: sidebarItems || [], // Sidebar items for navigation
                mobile: mobile,
                isRtl: Rtl,
                isAdmin: !!(initialData.currentUser?.is_admin),
                currentUser: initialData.currentUser || {},
                unreadSummary: null,
                unreadPoller: null,
                unreadFetchInFlight: false,
                unreadFetchQueued: null,
            };
        },
        provide() {
            return {
                i18n: this.i18n,
                fieldMeta: this.fieldMeta,
                isAdmin: this.isAdmin,
                currentUser: this.currentUser,
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
            shouldTrackUnread() {
                return this.$route?.path !== '/communications';
            },
            async fetchUnreadSummary({ silent = false, force = false } = {}) {
                if (!force && !this.shouldTrackUnread()) {
                    this.unreadSummary = null;
                    return;
                }

                if (this.unreadFetchInFlight) {
                    this.unreadFetchQueued = {
                        silent: true,
                        force: this.unreadFetchQueued?.force || force,
                    };
                    return;
                }

                this.unreadFetchInFlight = true;

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
                } finally {
                    this.unreadFetchInFlight = false;
                    if (this.unreadFetchQueued) {
                        const next = this.unreadFetchQueued;
                        this.unreadFetchQueued = null;
                        this.fetchUnreadSummary(next);
                    }
                }
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

                const pollTask = () => {
                    if (!this.shouldTrackUnread()) {
                        this.stopUnreadPolling();
                        return;
                    }
                    this.fetchUnreadSummary({ silent: true });
                };

                pollTask();

                this.unreadPoller = window.HavenCoreFetchClient.create({
                    task: pollTask,
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
                return item.route === '/communications' && this.hasUnreadCommunications;
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
    app.use(PrimeVue.ConfirmationService);

    // 🔌 Plugins
    app.use(VueSonner)

    // 🧭 Directives
    app.directive('tooltip', PrimeVue.Tooltip);

    // 🧾 Form Inputs
    app.component('InputText', PrimeVue.InputText);
    app.component('FloatLabel', PrimeVue.FloatLabel);
    app.component('IconField', PrimeVue.IconField);
    app.component('InputIcon', PrimeVue.InputIcon);
    app.component('InputGroup', PrimeVue.InputGroup);
    app.component('InputGroupAddon', PrimeVue.InputGroupAddon);

    // 🕹️ UI Controls
    app.component('Button', PrimeVue.Button);
    app.component('Panel', PrimeVue.Panel);
    app.component('ToggleSwitch', PrimeVue.ToggleSwitch);
    app.component('ScrollPanel', PrimeVue.ScrollPanel);
    app.component('Textarea', PrimeVue.Textarea);
    app.component('Dialog', PrimeVue.Dialog);
    app.component('Select', PrimeVue.Select);
    app.component('ConfirmDialog', PrimeVue.ConfirmDialog);
    app.component('Divider', PrimeVue.Divider);
    app.component('Card', PrimeVue.Card);
    app.component('OverlayBadge', PrimeVue.OverlayBadge);
    app.component('Badge', PrimeVue.Badge);

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
            };
        },
        inject: ['i18n', 'isAdmin', 'currentUser'],
        computed: {
            mobile() {
                const store = useGlobalStore();
                return store.isMobile || store.isTablet;
            },
            selectedConversation() {
                if (!this.selectedConversationId) return null;
                return this.conversations.find(c => c.id === this.selectedConversationId) || null;
            },
            messageContainerStyle() {
                return {
                    maxHeight: this.mobile ? '70dvh' : '65vh'
                };
            }
        },
        mounted() {
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
            'newConversation.supplier_id'(newVal) {
                if (!newVal) {
                    this.orderOptions = [];
                    this.newConversation.order_id = '';
                    return;
                }
                this.fetchSupplierOrders(newVal);
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
                    if (this.selectedConversationId) {
                        const exists = this.conversations.some(c => c.id === this.selectedConversationId);
                        if (!exists) {
                            this.selectedConversationId = null;
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
                this.fetchMessages(conversation.id, { scrollToLatest: true });
            },
            deselectConversation() {
                this.selectedConversationId = null;
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
                            this.conversations[idx].unread_admin_count = 0;
                            this.updateConversationPreview(this.selectedConversation.id, response);
                        } else {
                            this.fetchConversations();
                        }
                        this.scrollMessagesToBottom('smooth');
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
                        this.conversations[idx].unread_admin_count = 0;
                    }
                    if (this.$root?.shouldTrackUnread?.()) {
                        this.$root.fetchUnreadSummary({ silent: true, force: true });
                    }
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
                if (conversation.order_id) {
                    parts.push(`#${conversation.order_id}`);
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
                return this.conversationSupplierName(conversation);
            },
            conversationListInitials(conversation) {
                const source = this.conversationListDisplayName(conversation);
                if (!source) {
                    return 'SP';
                }
                return source
                    .split(/\s+/)
                    .filter(Boolean)
                    .map(part => part[0])
                    .join('')
                    .slice(0, 2)
                    .toUpperCase();
            },
            isSelected(conversation) {
                return this.selectedConversationId === conversation.id;
            },
            isOwnMessage(message) {
                const senderType = (message?.sender_type || '').toLowerCase();
                return this.isAdmin ? senderType === 'admin' : senderType === 'supplier';
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
                const isYou = this.isAdmin ? sender === 'admin' : sender === 'supplier';
                const youLabel = this.i18n.you || 'You';
                return isYou ? `${youLabel}: ${preview}` : preview;
            },
            conversationUnreadCount(conversation) {
                if (this.isAdmin) {
                    return conversation?.unread_admin_count || 0;
                }
                return conversation?.unread_supplier_count || 0;
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
            formatMessageDate(date) {
                return this.formatDateTime(date);
            },
            async fetchSuppliers() {
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
                    supplier_id: '',
                    order_id: '',
                    subject: '',
                    message: '',
                };
                this.orderOptions = [];
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
                        subject: this.newConversation.subject.trim(),
                        order_id: this.newConversation.order_id ? parseInt(this.newConversation.order_id, 10) || null : null,
                        status: 'open',
                    };
                    const conversation = await wp.apiFetch({
                        path: '/hc/v1/communications/messaging/conversations',
                        method: 'POST',
                        body: JSON.stringify(payload),
                        headers: {
                            'Content-Type': 'application/json'
                        },
                    });

                    if (conversation && this.newConversation.message.trim()) {
                        await wp.apiFetch({
                            path: `/hc/v1/communications/messaging/conversations/${conversation.id}/messages`,
                            method: 'POST',
                            body: JSON.stringify({
                                message: this.newConversation.message.trim(),
                            }),
                            headers: {
                                'Content-Type': 'application/json'
                            },
                        });
                    }

                    this.newConversationVisible = false;
                    this.fetchConversations().then(() => {
                        if (conversation?.id) {
                            this.selectConversation(conversation);
                        }
                    });
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
                    v-if="!mobile || !selectedConversation"
                    class="flex flex-col lg:w-1/3"
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
                    <Divider class="mb-2" />
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
                                        <div class="conversation-avatar flex-shrink-0">
                                            {{ conversationListInitials(conversation) }}
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
                <Divider v-if="!mobile" layout="vertical" class="hidden lg:flex" />

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
                                <div>
                                    <h2 class="text-lg font-semibold">{{ conversationTitle(selectedConversation) }}</h2>
                                    <p class="text-xs text-muted-color">{{ conversationMeta(selectedConversation) }}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 flex-wrap">
                                <Button
                                    v-if="isAdmin"
                                    :label="i18n.delete_conversation"
                                    icon="pi pi-trash"
                                    size="small"
                                    severity="danger"
                                    :loading="deleteLoading"
                                    @click="confirmDeleteConversation"
                                />
                            </div>
                        </div>

                        <Divider class="my-0" />

                        <div
                            class="flex-1 overflow-y-auto p-4 space-y-1 min-h-[250px]"
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
                                            :class="(n % 2 === 0) ? 'chat-message-row--self' : 'chat-message-row--other'"
                                        >
                                            <div
                                                class="chat-message-inner flex items-start gap-3 max-w-full"
                                                :class="(n % 2 === 0) ? 'flex-row-reverse text-right' : 'flex-row'"
                                            >
                                                <div
                                                    class="chat-avatar"
                                                    :class="(n % 2 === 0) ? 'chat-avatar--self' : 'chat-avatar--other'"
                                                >
                                                    <Skeleton shape="circle" size="42px" />
                                                </div>
                                                <div
                                                    class="w-full chat-bubble-card"
                                                    :class="(n % 2 === 0) ? 'chat-bubble-card--self' : 'chat-bubble-card--other'"
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
                                        name="message-slide"
                                        :key="selectedConversationId ? 'messages-' + selectedConversationId : 'messages'"
                                    >
                                        <div
                                            v-for="message in messages"
                                            :key="message.id"
                                            class="chat-message-row"
                                            :class="isOwnMessage(message) ? 'chat-message-row--self' : 'chat-message-row--other'"
                                        >
                                            <div
                                                class="chat-message-inner flex items-start gap-3 max-w-full"
                                                :class="isOwnMessage(message) ? 'flex-row-reverse text-right' : 'flex-row'"
                                            >
                                                <div
                                                    class="chat-avatar"
                                                    :class="isOwnMessage(message) ? 'chat-avatar--self' : 'chat-avatar--other'"
                                                >
                                                    {{ senderInitials(message) }}
                                                </div>
                                                <Card
                                                    class="w-full chat-bubble-card"
                                                    :class="isOwnMessage(message) ? 'chat-bubble-card--self' : 'chat-bubble-card--other'"
                                                >
                                                    <template #title>
                                                        <div class="flex items-center justify-between gap-3 text-[11px] uppercase tracking-wide opacity-80">
                                                            <span class="font-semibold truncate">{{ messageSenderName(message) }}</span>
                                                            <span class="whitespace-nowrap">{{ formatMessageDate(message.created_at) }}</span>
                                                        </div>
                                                    </template>
                                                    <template #content>
                                                        <p class="whitespace-pre-line text-sm leading-relaxed m-0">
                                                            {{ message.message }}
                                                        </p>
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
                            <label class="text-sm font-medium block mb-2">{{ i18n.supplier }}</label>
                            <Select
                                v-model="newConversation.supplier_id"
                                :options="supplierOptions"
                                filter
                                :loading="supplierLoading"
                                class="w-full"
                                :placeholder="i18n.supplier"
                                option-label="label"
                                option-value="value"
                                :virtualScrollerOptions="{ itemSize: 48 }"
                            >
                                <template #option="{ option }">
                                    <div class="flex flex-col">
                                        <span class="font-medium">{{ option.label }}</span>
                                        <span class="text-xs text-muted-color" v-if="option.subtitle">{{ option.subtitle }}</span>
                                    </div>
                                </template>
                            </Select>
                            <p v-if="supplierError" class="text-xs text-red-500 mt-1">{{ supplierError }}</p>
                        </div>

                        <div>
                            <label class="text-sm font-medium block mb-2">{{ i18n.order_id }}</label>
                            <Select
                                v-model="newConversation.order_id"
                                :options="orderOptions"
                                :loading="orderLoading"
                                :disabled="!newConversation.supplier_id || supplierLoading"
                                class="w-full"
                                :placeholder="newConversation.supplier_id ? i18n.order_id : i18n.supplier_placeholder"
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

                        <FloatLabel variant="on">
                            <InputText
                                v-model="newConversation.subject"
                                class="w-full"
                            />
                            <label>{{ i18n.subject }}</label>
                        </FloatLabel>

                        <div>
                        <FloatLabel variant="on">
                            <Textarea
                                v-model="newConversation.message"
                                auto-resize
                                rows="3"
                                class="w-full"
                            />
                            <label>{{ i18n.initial_message }}</label>
                        </FloatLabel>
                        </div>

                        <div class="flex justify-end gap-2">
                            <Button
                                :label="i18n.cancel"
                                severity="secondary"
                                size="small"
                                @click="closeNewConversation"
                                :disabled="creatingConversation"
                            />
                            <Button
                                :label="i18n.create"
                                icon="pi pi-check"
                                size="small"
                                :loading="creatingConversation"
                                :disabled="!newConversation.supplier_id || !newConversation.subject"
                                @click="createConversation"
                            />
                        </div>
                    </div>
                </Dialog>

            </div>
        `
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

            const mobile = Vue.computed(() => globalStore.isMobile || globalStore.isTablet);
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
        {
            path: '/communications',
            name: 'communications',
            component: Communications,
            meta: { title: initialData.i18n.communications }
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
            <ConfirmDialog group="conversation-delete">
                <template #container="{ message, acceptCallback, rejectCallback }">
                    <div class="flex flex-col items-center p-8 text-center bg-surface-0 dark:bg-surface-900 rounded-2xl gap-4 max-w-sm">
                        <div class="rounded-full bg-primary text-primary-contrast inline-flex justify-center items-center h-24 w-24 -mt-20 shadow-lg shadow-primary/40">
                            <i class="pi pi-exclamation-triangle text-4xl"></i>
                        </div>
                        <div class="space-y-2">
                            <span class="font-bold text-2xl block">{{ message.header }}</span>
                            <p class="text-sm text-muted-color m-0">{{ message.message }}</p>
                        </div>
                        <div class="flex items-center justify-center gap-3 w-full">
                            <Button
                                :label="message.rejectLabel || i18n.dismiss"
                                outlined
                                severity="secondary"
                                class="w-32"
                                @click="rejectCallback"
                            />
                            <Button
                                :label="message.acceptLabel || i18n.confirm"
                                severity="danger"
                                class="w-32"
                                @click="acceptCallback"
                            />
                        </div>
                    </div>
                </template>
            </ConfirmDialog>


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
            const mobile = Vue.computed(() => globalStore.isMobile || globalStore.isTablet);

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
        computed: {
            communicationsUnreadCount() {
                return this.$root?.communicationsUnreadCount || 0;
            },
            hasUnreadCommunications() {
                return this.$root?.hasUnreadCommunications || false;
            },
        },
        mounted() {
            this.isCollapsed = true;
            this.$root?.manageUnreadPolling?.();
        },
        watch: {
            '$route.fullPath'() {
                this.$root?.manageUnreadPolling?.();
            }
        },
        methods: {
            showUnreadBadge(item) {
                if (typeof this.$root?.showUnreadBadge === 'function') {
                    return this.$root.showUnreadBadge(item);
                }
                return false;
            },
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
