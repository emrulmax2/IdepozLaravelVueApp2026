<script setup lang="ts">
import ThemeSwitcher from "@/components/ThemeSwitcher";
import logoUrl from "@/assets/images/logo.svg";
import illustrationUrl from "@/assets/images/illustration.svg";
import { FormInput } from "@/components/Base/Form";
import Button from "@/components/Base/Button";
import { useAuthStore } from "@/stores/auth";
import { computed, onBeforeUnmount, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import Toastify from "toastify-js";
import "@/assets/css/vendors/toastify.css";

const authStore = useAuthStore();
const router = useRouter();
const route = useRoute();

type Step = "request" | "verify";

const phone = ref(authStore.latestPhone || "");
const otp = ref("");
const activeStep = ref<Step>(authStore.otpExpiresAt ? "verify" : "request");
const loading = ref(false);
const countdown = ref(authStore.resendCountdown || 0);
const phoneError = ref<string | null>(null);
const otpError = ref<string | null>(null);
let timerId: number | undefined;

const destination = computed(
  () => (route.query.redirect as string | undefined) || "/"
);

const canRequestOtp = computed(() => phone.value.replace(/\D/g, "").length >= 8);
const canVerifyOtp = computed(() => otp.value.length === 6);

const formattedCountdown = computed(() => {
  const minutes = Math.floor(countdown.value / 60)
    .toString()
    .padStart(2, "0");
  const seconds = (countdown.value % 60).toString().padStart(2, "0");
  return `${minutes}:${seconds}`;
});

const notify = (message: string, type: "success" | "error" = "success") => {
  Toastify({
    text: message,
    duration: 4000,
    gravity: "top",
    position: "right",
    close: true,
    className:
      type === "success"
        ? "bg-success text-white border-0"
        : "bg-danger text-white border-0",
  }).showToast();
};

const clearTimer = () => {
  if (timerId) {
    window.clearInterval(timerId);
    timerId = undefined;
  }
};

const startCountdown = (seconds: number) => {
  countdown.value = Math.max(0, seconds);
  clearTimer();

  if (countdown.value === 0) {
    return;
  }

  timerId = window.setInterval(() => {
    if (countdown.value <= 1) {
      countdown.value = 0;
      clearTimer();
      return;
    }

    countdown.value -= 1;
  }, 1000);
};

if (countdown.value > 0) {
  startCountdown(countdown.value);
}

const handleRequestOtp = async () => {
  if (!canRequestOtp.value || loading.value) {
    return;
  }

  phoneError.value = null;
  loading.value = true;

  try {
    const normalizedPhone = phone.value.replace(/\s+/g, "");
    const response = await authStore.requestOtp(normalizedPhone);

    activeStep.value = "verify";
    otp.value = "";
    startCountdown(response.resend_available_in ?? 0);

    notify("OTP sent to your mobile number.");

    if (response.preview_code) {
      notify(`Preview OTP: ${response.preview_code}`, "success");
    }
  } catch (error) {
    const message = (error as Error).message || "Unable to send OTP.";
    phoneError.value = message;
    notify(message, "error");
  } finally {
    loading.value = false;
  }
};

const handleVerifyOtp = async () => {
  if (!canVerifyOtp.value || loading.value) {
    return;
  }

  otpError.value = null;
  loading.value = true;

  try {
    await authStore.verifyOtp(phone.value.replace(/\s+/g, ""), otp.value);
    notify("You are now signed in.");
    router.push(destination.value);
  } catch (error) {
    const message = (error as Error).message || "Invalid OTP.";
    otpError.value = message;
    notify(message, "error");
  } finally {
    loading.value = false;
  }
};

const handleResend = async () => {
  if (countdown.value > 0 || loading.value) {
    return;
  }

  phoneError.value = null;
  await handleRequestOtp();
};

const handleChangePhone = () => {
  activeStep.value = "request";
  otp.value = "";
  phoneError.value = null;
  otpError.value = null;
  authStore.$patch({ otpPreview: null, otpExpiresAt: null });
  clearTimer();
  countdown.value = 0;
};

const handleOtpInput = (value: string) => {
  otp.value = (value || "").replace(/\D/g, "").slice(0, 6);
};

onBeforeUnmount(() => {
  clearTimer();
});
</script>

<template>
  <div
    :class="[
      'p-3 sm:px-8 relative h-screen lg:overflow-hidden bg-primary xl:bg-white dark:bg-darkmode-800 xl:dark:bg-darkmode-600',
      'before:hidden before:xl:block before:content-[\'\'] before:w-[57%] before:-mt-[28%] before:-mb-[16%] before:-ml-[13%] before:absolute before:inset-y-0 before:left-0 before:transform before:rotate-[-4.5deg] before:bg-primary/20 before:rounded-[100%] before:dark:bg-darkmode-400',
      'after:hidden after:xl:block after:content-[\'\'] after:w-[57%] after:-mt-[20%] after:-mb-[13%] after:-ml-[13%] after:absolute after:inset-y-0 after:left-0 after:transform after:rotate-[-4.5deg] after:bg-primary after:rounded-[100%] after:dark:bg-darkmode-700',
    ]"
  >
    <ThemeSwitcher />
    <div class="container relative z-10 sm:px-10">
      <div class="block grid-cols-2 gap-4 xl:grid">
        <!-- BEGIN: Login Info -->
        <div class="flex-col hidden min-h-screen xl:flex">
          <a href="" class="flex items-center pt-5 -intro-x">
            <img alt="Midone" class="w-6" :src="logoUrl" />
            <span class="ml-3 text-lg text-white"> Midone </span>
          </a>
          <div class="my-auto">
            <img alt="OTP Login" class="w-1/2 -mt-16 -intro-x" :src="illustrationUrl" />
            <div class="mt-10 text-4xl font-medium leading-tight text-white -intro-x">
              Secure OTP login <br />
              keeps your account protected.
            </div>
            <div class="mt-5 text-lg text-white -intro-x text-opacity-70 dark:text-slate-400">
              Sign in with your mobile number and a one-time passcode delivered in seconds.
            </div>
          </div>
        </div>
        <!-- END: Login Info -->

        <!-- BEGIN: Login Form -->
        <div class="flex h-screen py-5 my-10 xl:h-auto xl:py-0 xl:my-0">
          <div
            class="w-full px-5 py-8 mx-auto my-auto bg-white rounded-md shadow-md xl:ml-20 dark:bg-darkmode-600 xl:bg-transparent sm:px-8 xl:p-0 xl:shadow-none sm:w-3/4 lg:w-2/4 xl:w-auto"
          >
            <h2 class="text-2xl font-bold text-center intro-x xl:text-3xl xl:text-left">
              Mobile OTP Login
            </h2>
            <div class="mt-2 text-center intro-x text-slate-400 xl:hidden">
              Enter your mobile number to receive a secure one-time passcode.
            </div>

            <div class="mt-8 space-y-6 intro-x">
              <div v-if="activeStep === 'request'">
                <label class="text-sm font-semibold text-slate-600 dark:text-slate-300">
                  Mobile Number
                </label>
                <FormInput
                  v-model="phone"
                  type="tel"
                  inputmode="tel"
                  autocomplete="tel"
                  maxlength="18"
                  class="block px-4 py-3 mt-2 login__input min-w-full xl:min-w-[350px]"
                  placeholder="e.g. +15551234567"
                />
                <p v-if="phoneError" class="mt-2 text-sm text-danger">{{ phoneError }}</p>
                <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                  Use your full international format so we can deliver the OTP quickly.
                </p>
              </div>

              <div v-else>
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                  <div>
                    <p class="text-xs font-semibold uppercase text-slate-500">
                      Code sent to
                    </p>
                    <p class="text-lg font-semibold text-slate-800 dark:text-slate-100">
                      {{ phone }}
                    </p>
                  </div>
                  <button
                    type="button"
                    class="text-sm font-medium text-primary underline decoration-dotted"
                    @click="handleChangePhone"
                  >
                    Change number
                  </button>
                </div>

                <FormInput
                  :model-value="otp"
                  type="text"
                  inputmode="numeric"
                  autocomplete="one-time-code"
                  maxlength="6"
                  class="block px-4 py-3 mt-6 text-xl text-center tracking-[0.5em] login__input"
                  placeholder="------"
                  @update:modelValue="handleOtpInput"
                />
                <p v-if="otpError" class="mt-2 text-sm text-danger">{{ otpError }}</p>

                <div class="flex flex-col gap-2 mt-4 text-sm text-slate-500 sm:flex-row sm:items-center sm:justify-between">
                  <span v-if="countdown > 0">Resend available in {{ formattedCountdown }}</span>
                  <button
                    type="button"
                    class="text-primary font-medium disabled:opacity-40 disabled:cursor-not-allowed"
                    :disabled="countdown > 0 || loading"
                    @click="handleResend"
                  >
                    Resend OTP
                  </button>
                </div>

                <div
                  v-if="authStore.otpPreview"
                  class="px-4 py-2 mt-4 text-xs font-mono text-primary bg-primary/10 rounded"
                >
                  Preview code (dev only): {{ authStore.otpPreview }}
                </div>
              </div>
            </div>

            <div class="mt-8 text-center intro-x xl:text-left">
              <Button
                v-if="activeStep === 'request'"
                type="button"
                variant="primary"
                class="w-full px-4 py-3 align-top xl:w-40"
                :disabled="!canRequestOtp || loading"
                @click="handleRequestOtp"
              >
                <span v-if="!loading">Send OTP</span>
                <span v-else>Sending...</span>
              </Button>

              <Button
                v-else
                type="button"
                variant="primary"
                class="w-full px-4 py-3 align-top xl:w-48"
                :disabled="!canVerifyOtp || loading"
                @click="handleVerifyOtp"
              >
                <span v-if="!loading">Verify & Sign In</span>
                <span v-else>Verifying...</span>
              </Button>
            </div>

            <div class="mt-10 text-center intro-x xl:mt-20 text-slate-600 dark:text-slate-500 xl:text-left">
              By continuing you agree to our
              <a class="text-primary dark:text-slate-200" href="">
                Terms & Conditions
              </a>
              and
              <a class="text-primary dark:text-slate-200" href="">
                Privacy Policy
              </a>
            </div>

            <div class="mt-6 text-center intro-x text-slate-600 dark:text-slate-500">
              New here?
              <button
                type="button"
                class="font-semibold text-primary underline decoration-dotted"
                @click="router.push({ name: 'register', query: route.query })"
              >
                Create an account
              </button>
            </div>
          </div>
        </div>
        <!-- END: Login Form -->
      </div>
    </div>
  </div>
</template>
