<?php

use HavenCore\Utils\ScriptHelpers;

defined('ABSPATH') || exit;

ScriptHelpers::loadApiFetch();
ScriptHelpers::loadVue([
    'withDraggable'   => false,
    'withFrontendCss' => true,
]);

$token         = isset($_GET['token']) ? sanitize_text_field(wp_unslash($_GET['token'])) : '';
$redirect_url  = add_query_arg('acc_removal_completed', '1', home_url('/'));
$support_email = 'support@' . strtolower(get_bloginfo('name')) . '.com';

$i18n = [
    'title'                      => __('Final Step: Your Account Removal Confirmation', HAVEN_CORE_TEXT_DOMAIN),
    'subtitle'                   => __('This is the final confirmation needed to permanently delete your account.', HAVEN_CORE_TEXT_DOMAIN),
    'dear_prefix'                => __('Dear', HAVEN_CORE_TEXT_DOMAIN),
    'dear_default'               => __('Friend', HAVEN_CORE_TEXT_DOMAIN),
    'decision_title'             => __('Your Decision is Confirmed', HAVEN_CORE_TEXT_DOMAIN),
    'decision_body'              => __('You have already verified this request via email. Submitting this form will permanently and irreversibly delete your account.', HAVEN_CORE_TEXT_DOMAIN),
    'privacy_title'              => __('Your Privacy, Our Promise', HAVEN_CORE_TEXT_DOMAIN),
    'privacy_body'               => __('Every detail—profile information, purchase history, and related data—will be securely erased from our systems.', HAVEN_CORE_TEXT_DOMAIN),
    'next_steps_title'           => __('Next Steps', HAVEN_CORE_TEXT_DOMAIN),
    'next_steps_body'            => __('After you confirm below, the deletion is immediate and cannot be undone.', HAVEN_CORE_TEXT_DOMAIN),
    'gratitude_title'            => __('Gratitude Beyond Words', HAVEN_CORE_TEXT_DOMAIN),
    'gratitude_body'             => __('We are thankful you were part of our community. While we are sad to see you go, we respect your decision.', HAVEN_CORE_TEXT_DOMAIN),
    'support_title'              => __('Support Always Available', HAVEN_CORE_TEXT_DOMAIN),
    'support_body'               => __('If you need assistance at any step, our support team is here for you.', HAVEN_CORE_TEXT_DOMAIN),
    'confirmation_label'         => __('Enter Confirmation Word', HAVEN_CORE_TEXT_DOMAIN),
    'confirmation_placeholder'   => __('Type "confirm"', HAVEN_CORE_TEXT_DOMAIN),
    'confirmation_hint'          => __('Type the word confirm to enable the button.', HAVEN_CORE_TEXT_DOMAIN),
    'submit_label'               => __('Confirm Account Removal', HAVEN_CORE_TEXT_DOMAIN),
    'submitting'                 => __('Processing…', HAVEN_CORE_TEXT_DOMAIN),
    'success'                    => __('Success', HAVEN_CORE_TEXT_DOMAIN),
    'error'                      => __('Error', HAVEN_CORE_TEXT_DOMAIN),
    'loading'                    => __('Validating your request…', HAVEN_CORE_TEXT_DOMAIN),
    'token_invalid'              => __('This request is invalid or has already been processed. Please request a new link.', HAVEN_CORE_TEXT_DOMAIN),
    'token_missing'              => __('We could not detect a verification token. Please open the link directly from your email.', HAVEN_CORE_TEXT_DOMAIN),
];

$initial_data = [
    'token'        => $token,
    'redirectUrl'  => $redirect_url,
    'i18n'         => $i18n,
    'supportEmail' => $support_email,
];

$page_title = esc_html__('Account Removal Confirmation', HAVEN_CORE_TEXT_DOMAIN);

get_header();
?>

<h1 class="screen-reader-text"><?php echo $page_title; ?></h1>

<div id="havencore-account-removal-app"></div>

<script type="application/json" id="havencore-account-removal-data">
    <?php echo wp_json_encode($initial_data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>
</script>

<script>
    function initHavenAccountRemovalApp() {
        const dataEl = document.getElementById('havencore-account-removal-data');
        const initialData = dataEl ? JSON.parse(dataEl.textContent || '{}') : {};

        const AccountRemoval = {
            template: `
                <div class="overflow-y-auto flex justify-center items-center py-8 px-4" style="min-height:50dvh;">
                    <div v-if="state === 'loading'" class="flex flex-col items-center justify-center text-center text-gray-700">
                        <h2>{{ i18n.loading }}</h2>
                    </div>

                    <div v-else-if="state === 'error'" class="flex flex-col items-center justify-center text-center text-gray-700">
                        <h2>{{ i18n.error }}</h2>
                        <p>{{ errorMessage }}</p>
                    </div>

                    <Card v-else class="w-full max-w-3xl change-password-form-card">
                        <template #header>
                            <h2 class="text-2xl flex-col font-semibold flex gap-4 pt-8 px-2 items-center text-center justify-center">
                                <i class="pi pi-user-minus" style="font-size: 1.5rem;" /> 
                                <span>{{ i18n.title }}</span>
                            </h2>
                        </template>
                        <template #content>
                            <div class="space-y-4 flex flex-col justify-center text-center items-center text-gray-700 text-base leading-relaxed mb-8">
                                <p><strong>{{ greetingLine }}</strong></p>
                                <p><strong>{{ i18n.decision_title }}:</strong> {{ i18n.decision_body }}</p>
                                <p><strong>{{ i18n.privacy_title }}:</strong> {{ i18n.privacy_body }}</p>
                                <p><strong>{{ i18n.next_steps_title }}:</strong> {{ i18n.next_steps_body }}</p>
                                <p><strong>{{ i18n.gratitude_title }}:</strong> {{ i18n.gratitude_body }}</p>
                                <p><strong>{{ i18n.support_title }}:</strong> {{ i18n.support_body }}
                                    <a :href="'mailto:' + supportEmail" class="font-semibold text-blue-600">{{ supportEmail }}</a>
                                </p>
                            </div>

                            <form @submit.prevent="onSubmit" class="flex flex-col space-y-6 w-full">
                                <div class="flex flex-col space-y-2 items-center justify-center text-center w-full">
                                    <small class="text-sm text-gray-500 block mb-2">{{ i18n.confirmation_hint }}</small>
                                    <InputGroup class="w-full">
                                        <InputGroupAddon class="flex items-center justify-center">
                                            <i class="pi pi-lock text-primary text-base"></i>
                                        </InputGroupAddon>
                                        <FloatLabel variant="on" class="flex-1">
                                            <InputText
                                                inputId="confirmationWord"
                                                v-model="confirmationWord"
                                                autocomplete="off"
                                                :invalid="confirmationError"
                                                class="w-full"
                                                :disabled="isSubmitting"
                                                required
                                            />
                                            <label for="confirmationWord">{{ i18n.confirmation_label }}</label>
                                        </FloatLabel>
                                    </InputGroup>
                                </div>

                                <Button
                                    type="submit"
                                    :label="isSubmitting ? i18n.submitting : i18n.submit_label"
                                    icon="pi pi-check"
                                    severity="danger"
                                    raised
                                    class="w-full mx-auto"
                                    :disabled="!formIsValid || isSubmitting"
                                    :loading="isSubmitting"
                                    style="padding: 0.3rem; border-radius: 0.3rem;"
                                />
                            </form>

                            <Dialog
                                v-model:visible="errorDialogVisible"
                                modal
                                :closable="true"
                                class="hc-error-dialog"
                                style="width: 30rem"
                            >
                                <div class="flex flex-col items-center justify-center text-center gap-4 pb-4 px-4">
                                    <div class="w-16 h-16 rounded-full bg-red-100 text-red-600 flex items-center justify-center shadow-md">
                                        <i class="pi pi-exclamation-triangle" style="font-size: 2rem;"></i>
                                    </div>
                                    <div>
                                        <h3 class="text-xl font-semibold text-gray-900 mb-2">{{ i18n.error }}</h3>
                                        <p class="text-gray-700 leading-relaxed m-0" style="white-space: pre-line;">
                                            {{ errorDialogMessage }}
                                        </p>
                                    </div>
                                </div>
                            </Dialog>
                        </template>
                    </Card>
                </div>
            `,
            props: {
                token: {
                    type: String,
                    default: ''
                },
                redirectUrl: {
                    type: String,
                    default: '/'
                }
            },
            inject: ['i18n'],
            data() {
                return {
                    confirmationWord: '',
                    confirmationError: false,
                    state: this.token ? 'loading' : 'error',
                    errorMessage: this.token ? '' : this.i18n.token_missing,
                    isSubmitting: false,
                    userName: '',
                    supportEmail: initialData.supportEmail || '',
                    errorDialogVisible: false,
                    errorDialogMessage: ''
                };
            },
            computed: {
                formIsValid() {
                    return this.state === 'ready' && this.confirmationWord.trim().toLowerCase() === 'confirm' && !this.confirmationError;
                },
                greetingLine() {
                    if (!this.userName) {
                        return this.i18n.dear_prefix + ' ' + this.i18n.dear_default;
                    }
                    return this.i18n.dear_prefix + ' ' + this.userName;
                }
            },
            mounted() {
                if (this.token) {
                    this.validateToken();
                }
            },
            methods: {
                validateToken() {
                    this.state = 'loading';
                    wp.apiFetch({
                        path: `/hc/v1/account/removal/validate?token=${encodeURIComponent(this.token)}`
                    }).then((response) => {
                        this.userName = response?.display_name || response?.user_login || '';
                        this.state = 'ready';
                    }).catch((error) => {
                        this.state = 'error';
                        this.errorMessage = error?.data?.message || error?.message || this.i18n.token_invalid;
                    });
                },
                onSubmit() {
                    if (!this.formIsValid || this.isSubmitting) {
                        this.$toast.error(this.i18n.error, {
                            description: this.i18n.confirmation_hint,
                            duration: 3000
                        });
                        return;
                    }
                    this.submitRemoval();
                },
                submitRemoval() {
                    this.isSubmitting = true;

                    wp.apiFetch({
                        path: '/hc/v1/account/removal/complete',
                        method: 'POST',
                        data: {
                            token: this.token,
                            confirmation_word: this.confirmationWord
                        }
                    }).then((response) => {
                        // this.$toast.success(this.i18n.success, {
                        //     description: response?.message || '',
                        //     duration: 3000
                        // });

                        window.location.href = response?.redirect_url || this.redirectUrl || '/';
                    }).catch((error) => {
                        this.$toast.error(this.i18n.error, {
                            description: error?.data?.message || error?.message || this.i18n.token_invalid,
                            duration: 4000
                        });
                        this.errorDialogMessage = error?.data?.message || error?.message || this.i18n.token_invalid;
                        this.errorDialogVisible = true;
                        this.isSubmitting = false;
                    })
                }
            },
            watch: {
                confirmationWord() {
                    this.confirmationError = this.confirmationWord !== '' && this.confirmationWord.trim().toLowerCase() !== 'confirm';
                }
            }
        };

        const app = Vue.createApp({
            data() {
                return {
                    initial: initialData
                };
            },
            provide() {
                return {
                    i18n: this.initial.i18n || {}
                };
            },
            template: '<AccountRemoval :token="initial.token" :redirect-url="initial.redirectUrl" />'
        });

        app.use(PrimeVue.Config, {
            theme: {
                preset: PrimeVue.Themes.Aura,
                options: {
                    darkModeSelector: false,
                }
            },
        });

        app.use(VueSonner);

        app.component('Card', PrimeVue.Card);
        app.component('FloatLabel', PrimeVue.FloatLabel);
        app.component('InputText', PrimeVue.InputText);
        app.component('InputGroup', PrimeVue.InputGroup);
        app.component('InputGroupAddon', PrimeVue.InputGroupAddon);
        app.component('Button', PrimeVue.Button);
        app.component('Dialog', PrimeVue.Dialog);

        app.component('AccountRemoval', AccountRemoval);
        app.mount('#havencore-account-removal-app');
    }

    document.addEventListener('DOMContentLoaded', initHavenAccountRemovalApp);
</script>

<?php
get_footer();
