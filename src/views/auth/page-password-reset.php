<?php

use HavenCore\Utils\ScriptHelpers;

defined('ABSPATH') || exit;

ScriptHelpers::loadApiFetch();
ScriptHelpers::loadVue([
    'withDraggable'   => false,
    'withFrontendCss' => true,
]);

$token        = isset($_GET['token']) ? sanitize_text_field(wp_unslash($_GET['token'])) : '';
$redirect_url = add_query_arg('conf_activate_account', '1', home_url('/'));

$i18n = [
    'title'                     => __('Secure Your Account', HAVEN_CORE_TEXT_DOMAIN),
    'subtitle'                  => __('Please choose a strong password to keep your account safe.', HAVEN_CORE_TEXT_DOMAIN),
    'enter_new_password'        => __('Enter a new password', HAVEN_CORE_TEXT_DOMAIN),
    'confirm_password'          => __('Confirm password', HAVEN_CORE_TEXT_DOMAIN),
    'confirm_password_short'    => __('Confirm Password', HAVEN_CORE_TEXT_DOMAIN),
    'pick_a_password'           => __('Pick a password', HAVEN_CORE_TEXT_DOMAIN),
    'lowercase_requirement'     => __('At least one lowercase letter', HAVEN_CORE_TEXT_DOMAIN),
    'uppercase_requirement'     => __('At least one uppercase letter', HAVEN_CORE_TEXT_DOMAIN),
    'numeric_requirement'       => __('At least one numeric character', HAVEN_CORE_TEXT_DOMAIN),
    'min_length_requirement'    => __('Minimum 8 characters', HAVEN_CORE_TEXT_DOMAIN),
    'confirm_submit'            => __('Set Password', HAVEN_CORE_TEXT_DOMAIN),
    'success'                   => __('Success', HAVEN_CORE_TEXT_DOMAIN),
    'error'                     => __('Error', HAVEN_CORE_TEXT_DOMAIN),
    'token_invalid'             => __('This link is invalid or has already been used. Please request a new one.', HAVEN_CORE_TEXT_DOMAIN),
    'token_missing'             => __('We could not detect a reset token. Please open the link from your email.', HAVEN_CORE_TEXT_DOMAIN),
    'try_again'                 => __('Try again', HAVEN_CORE_TEXT_DOMAIN),
    'loading'                   => __('Validating your request…', HAVEN_CORE_TEXT_DOMAIN),
    'form_invalid'              => __('Cannot change password because the form is not valid yet.', HAVEN_CORE_TEXT_DOMAIN),
    'submitting'                => __('Setting password…', HAVEN_CORE_TEXT_DOMAIN),
];

$initial_data = [
    'token'        => $token,
    'redirectUrl'  => $redirect_url,
    'i18n'         => $i18n,
];

$page_title = esc_html__('Secure Your Account', HAVEN_CORE_TEXT_DOMAIN);

get_header();
?>

<h1 class="screen-reader-text"><?php echo $page_title; ?></h1>

<div id="havencore-password-reset-app"></div>

<script type="application/json" id="havencore-password-reset-data">
    <?php echo wp_json_encode($initial_data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>
</script>

<script>
    function initHavenPasswordResetApp() {
        const dataEl = document.getElementById('havencore-password-reset-data');
        const initialData = dataEl ? JSON.parse(dataEl.textContent || '{}') : {};

        const ChangePassword = {
            template: `
                <div class="overflow-y-auto flex justify-center items-center" style="min-height:50dvh">
                    <div v-if="state === 'loading'" class="flex flex-col items-center justify-center">
                        <h2>{{ i18n.loading }}</h2>
                    </div>

                    <div v-else-if="state === 'error'" class="flex flex-col items-center justify-center">
                        <h2>{{ i18n.error }}</h2>
                        <p>{{ errorMessage }}</p>
                    </div>

                    <Card v-else class="w-full max-w-lg change-password-form-card">
                        <template #content>
                            <div class="text-center mb-6">
                                <h2 class="text-2xl font-semibold  flex items-center justify-center">
                                    <i class="pi pi-lock mr-2"></i> {{ i18n.title }}
                                </h2>
                                <p class=" mt-2">{{ i18n.subtitle }}</p>
                            </div>

                            <form @submit.prevent="onSubmit" class="flex flex-col justify-center items-center space-y-8 w-full">
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
                                        :disabled="isSubmitting"
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

                                <FloatLabel variant="on">
                                    <Password
                                        inputId="confirmPassword"
                                        v-model="confirmPassword"
                                        :feedback="false"
                                        required
                                        toggleMask
                                        autocomplete="new-password"
                                        :invalid="confirmPasswordError"
                                        :disabled="isSubmitting"
                                    />

                                    <label for="confirmPassword">{{ i18n.confirm_password }}</label>
                                </FloatLabel>

                                <Button
                                    type="submit"
                                    :label="isSubmitting ? i18n.submitting : i18n.confirm_submit"
                                    icon="pi pi-check"
                                    raised
                                    class="w-60"
                                    :disabled="!formIsValid || isSubmitting"
                                    :loading="isSubmitting"
                                    style="padding: 0.3rem; border-radius: 0.3rem;"
                                />
                            </form>
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
                    password: '',
                    confirmPassword: '',
                    passwordError: false,
                    confirmPasswordError: false,
                    passwordPattern: '(?=.*[a-z])(?=.*[A-Z])(?=.*\\d)',
                    passwordRequirements: {
                        lowercase: false,
                        uppercase: false,
                        numeric: false,
                        minLength: false,
                    },
                    state: this.token ? 'loading' : 'error',
                    errorMessage: this.token ? '' : this.i18n.token_missing,
                    isSubmitting: false,
                };
            },
            computed: {
                formIsValid() {
                    return (
                        this.state === 'ready' &&
                        this.password &&
                        this.confirmPassword &&
                        this.password === this.confirmPassword &&
                        !this.passwordError &&
                        !this.confirmPasswordError
                    );
                }
            },
            mounted() {
                if (this.token) {
                    this.fetchToken();
                }
            },
            methods: {
                fetchToken() {
                    this.state = 'loading';
                        wp.apiFetch({
                            path: `/hc/v1/account/password/validate?token=${encodeURIComponent(this.token)}`
                    }).then(() => {
                        this.state = 'ready';
                    }).catch((error) => {
                        this.state = 'error';
                        this.errorMessage = error?.data?.message || error?.message || this.i18n.token_invalid;
                    });
                },
                validatePassword() {
                    const pattern = new RegExp(this.passwordPattern);
                    this.passwordError =
                        this.password.length < 8 || !pattern.test(this.password);

                    this.passwordRequirements.lowercase = /[a-z]/.test(this.password);
                    this.passwordRequirements.uppercase = /[A-Z]/.test(this.password);
                    this.passwordRequirements.numeric = /\d/.test(this.password);
                    this.passwordRequirements.minLength = this.password.length >= 8;
                },
                validateConfirmPassword() {
                    this.confirmPasswordError = this.password !== this.confirmPassword;
                },
                onSubmit() {
                    if (!this.formIsValid || this.isSubmitting) {
                        this.$toast.error(this.i18n.error, {
                            description: this.i18n.form_invalid,
                            duration: 3000
                        });
                        return;
                    }

                    this.changePassword();
                },
                changePassword() {
                    this.isSubmitting = true;

                        wp.apiFetch({
                            path: '/hc/v1/account/password/complete',
                        method: 'POST',
                        data: {
                            token: this.token,
                            new_password: this.password,
                            confirm_password: this.confirmPassword,
                        }
                    }).then((response) => {
                        this.$toast.success(this.i18n.success, {
                            description: response?.message || '',
                            duration: 3000
                        });

                        setTimeout(() => {
                            window.location.href = response?.redirect_url || this.redirectUrl || '/';
                        }, 1200);
                    }).catch((error) => {
                        this.$toast.error(this.i18n.error, {
                            description: error?.data?.message || error?.message || 'Something went wrong.',
                            duration: 4000
                        });
                        this.isSubmitting = false;
                    })
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
            template: '<ChangePassword :token="initial.token" :redirect-url="initial.redirectUrl" />'
        });

        app.use(PrimeVue.Config, {
            theme: {
                preset: PrimeVue.Themes.Aura,
                options: {
                    darkModeSelector: false,
                }
            }
        });

        app.use(VueSonner);

        app.component('Password', PrimeVue.Password);
        app.component('FloatLabel', PrimeVue.FloatLabel);
        app.component('Button', PrimeVue.Button);
        app.component('Card', PrimeVue.Card);
        app.component('Divider', PrimeVue.Divider);

        app.component('ChangePassword', ChangePassword);
        app.mount('#havencore-password-reset-app');
    }

    document.addEventListener('DOMContentLoaded', initHavenPasswordResetApp);
</script>

<?php
get_footer();
