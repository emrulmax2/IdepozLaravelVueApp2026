import { defineStore } from "pinia";

const storage = typeof window !== "undefined" ? window.localStorage : null;
const storedPhoneCodeId = storage?.getItem("auth.phone_code_id");
const storedPhoneDigits = storage?.getItem("auth.phone_digits") || "";
const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || "/api";

const digitsOnly = (value: string): string => value.replace(/[^0-9]/g, "");

type AuthUser = {
  id: number;
  name: string | null;
  email: string | null;
  phone: string | null;
  country_phone_code_id: number | null;
  phone_verified_at: string | null;
  last_login_at: string | null;
};

type RequestOtpResponse = {
  message: string;
  expires_at: string;
  resend_available_in: number;
  preview_code?: string | null;
};

type VerifyOtpResponse = {
  token: string;
  user: AuthUser;
};

type PhoneSelectionPayload = {
  countryPhoneCodeId: number;
  dialCode: string;
  phone: string;
};

type LoginRequestPayload = PhoneSelectionPayload;

type LoginVerifyPayload = PhoneSelectionPayload & {
  otp: string;
};

type RegisterRequestPayload = PhoneSelectionPayload & {
  name: string;
};

type RegisterVerifyPayload = PhoneSelectionPayload & {
  otp: string;
};

type ApiError = {
  message?: string;
  errors?: Record<string, string[]>;
};

const getStoredJson = <T>(key: string): T | null => {
  try {
    const value = storage?.getItem(key);
    return value ? (JSON.parse(value) as T) : null;
  } catch (error) {
    console.warn(`Failed to parse storage item ${key}`, error);
    return null;
  }
};

export const useAuthStore = defineStore("auth", {
  state: () => ({
    token: storage?.getItem("auth.token") || null,
    user: getStoredJson<AuthUser>("auth.user"),
    initialized: false,
    latestPhone: storage?.getItem("auth.phone") || "",
    latestPhoneDigits: storedPhoneDigits,
    latestPhoneCodeId: storedPhoneCodeId ? Number(storedPhoneCodeId) : null,
    otpExpiresAt: null as string | null,
    resendCountdown: 0,
    otpPreview: null as string | null,
  }),
  getters: {
    isAuthenticated: (state) => Boolean(state.token && state.user),
  },
  actions: {
    async initialize(): Promise<void> {
      if (this.initialized) {
        return;
      }

      if (this.token && !this.user) {
        try {
          await this.fetchUser();
        } catch (error) {
          console.error("Failed to hydrate auth session", error);
          this.clearSession();
        }
      }

      this.initialized = true;
    },
    async requestOtp(payload: LoginRequestPayload): Promise<RequestOtpResponse> {
      const normalizedPhone = digitsOnly(payload.phone);
      const response = await fetch(`${API_BASE_URL}/auth/request-otp`, {
        method: "POST",
        headers: this.buildJsonHeaders(),
        body: JSON.stringify({
          country_phone_code_id: payload.countryPhoneCodeId,
          phone: normalizedPhone,
        }),
      });

      if (!response.ok) {
        throw new Error(await this.extractError(response));
      }

      const data = (await response.json()) as RequestOtpResponse;
      const fullPhone = `${payload.dialCode}${normalizedPhone}`;
      this.rememberPhoneSelection(fullPhone, normalizedPhone, payload.countryPhoneCodeId);
      this.otpExpiresAt = data.expires_at;
      this.resendCountdown = Math.max(0, data.resend_available_in || 0);
      this.otpPreview = data.preview_code ?? null;

      return data;
    },
    async verifyOtp(payload: LoginVerifyPayload): Promise<VerifyOtpResponse> {
      const normalizedPhone = digitsOnly(payload.phone);
      const response = await fetch(`${API_BASE_URL}/auth/verify-otp`, {
        method: "POST",
        headers: this.buildJsonHeaders(),
        body: JSON.stringify({
          country_phone_code_id: payload.countryPhoneCodeId,
          phone: normalizedPhone,
          otp: payload.otp,
        }),
      });

      if (!response.ok) {
        throw new Error(await this.extractError(response));
      }

      const data = (await response.json()) as VerifyOtpResponse;
      this.token = data.token;
      this.user = data.user;
      this.otpPreview = null;
      this.persistSession();

      return data;
    },
    async registerRequestOtp(
      payload: RegisterRequestPayload
    ): Promise<RequestOtpResponse> {
      const normalizedPhone = digitsOnly(payload.phone);
      const response = await fetch(
        `${API_BASE_URL}/auth/register/request-otp`,
        {
          method: "POST",
          headers: this.buildJsonHeaders(),
          body: JSON.stringify({
            name: payload.name,
            country_phone_code_id: payload.countryPhoneCodeId,
            phone: normalizedPhone,
          }),
        }
      );

      if (!response.ok) {
        throw new Error(await this.extractError(response));
      }

      const data = (await response.json()) as RequestOtpResponse;
      const fullPhone = `${payload.dialCode}${normalizedPhone}`;
      this.rememberPhoneSelection(fullPhone, normalizedPhone, payload.countryPhoneCodeId);
      this.otpExpiresAt = data.expires_at;
      this.resendCountdown = Math.max(0, data.resend_available_in || 0);
      this.otpPreview = data.preview_code ?? null;

      return data;
    },
    async registerVerifyOtp(
      payload: RegisterVerifyPayload
    ): Promise<VerifyOtpResponse> {
      const normalizedPhone = digitsOnly(payload.phone);
      const response = await fetch(
        `${API_BASE_URL}/auth/register/verify-otp`,
        {
          method: "POST",
          headers: this.buildJsonHeaders(),
          body: JSON.stringify({
            country_phone_code_id: payload.countryPhoneCodeId,
            phone: normalizedPhone,
            otp: payload.otp,
          }),
        }
      );

      if (!response.ok) {
        throw new Error(await this.extractError(response));
      }

      const data = (await response.json()) as VerifyOtpResponse;
      this.token = data.token;
      this.user = data.user;
      this.otpPreview = null;
      this.persistSession();

      return data;
    },
    async fetchUser(): Promise<void> {
      const response = await this.authenticatedFetch("/user");

      if (!response.ok) {
        throw new Error(await this.extractError(response));
      }

      this.user = (await response.json()) as AuthUser;
      this.persistUser();
    },
    async logout(): Promise<void> {
      if (this.token) {
        await this
          .authenticatedFetch("/auth/logout", { method: "POST" })
          .catch(() => undefined);
      }

      this.clearSession();
    },
    setResendCountdown(seconds: number): void {
      this.resendCountdown = Math.max(0, seconds);
    },
    rememberPhoneSelection(
      fullPhone: string,
      digits: string,
      countryPhoneCodeId: number | null
    ): void {
      this.latestPhone = fullPhone;
      this.latestPhoneDigits = digits;
      storage?.setItem("auth.phone", fullPhone);
      storage?.setItem("auth.phone_digits", digits);

      if (countryPhoneCodeId) {
        this.latestPhoneCodeId = countryPhoneCodeId;
        storage?.setItem(
          "auth.phone_code_id",
          String(countryPhoneCodeId)
        );
      } else {
        this.latestPhoneCodeId = null;
        storage?.removeItem("auth.phone_code_id");
      }
    },
    persistSession(): void {
      if (this.token) {
        storage?.setItem("auth.token", this.token);
      } else {
        storage?.removeItem("auth.token");
      }

      this.persistUser();

      if (this.user?.phone) {
        const digits = digitsOnly(this.user.phone);
        this.rememberPhoneSelection(
          this.user.phone,
          digits,
          this.user.country_phone_code_id ?? this.latestPhoneCodeId
        );
      }
    },
    persistUser(): void {
      if (this.user) {
        storage?.setItem("auth.user", JSON.stringify(this.user));
      } else {
        storage?.removeItem("auth.user");
      }
    },
    clearSession(): void {
      this.token = null;
      this.user = null;
      this.otpExpiresAt = null;
      this.resendCountdown = 0;
      this.otpPreview = null;
      storage?.removeItem("auth.token");
      storage?.removeItem("auth.user");
    },
    buildJsonHeaders(): HeadersInit {
      return {
        Accept: "application/json",
        "Content-Type": "application/json",
      };
    },
    async authenticatedFetch(
      path: string,
      options: RequestInit = {}
    ): Promise<Response> {
      if (!this.token) {
        throw new Error("Missing authentication token");
      }

      const headers = new Headers(options.headers || {});
      headers.set("Authorization", `Bearer ${this.token}`);
      headers.set("Accept", "application/json");

      if (options.body && !(options.body instanceof FormData)) {
        headers.set("Content-Type", "application/json");
      }

      return fetch(`${API_BASE_URL}${path}`, {
        ...options,
        headers,
      });
    },
    async extractError(response: Response): Promise<string> {
      try {
        const data = (await response.json()) as ApiError;

        if (data.errors) {
          const firstKey = Object.keys(data.errors)[0];
          if (firstKey && data.errors[firstKey]?.length) {
            return data.errors[firstKey][0];
          }
        }

        return data.message || "Something went wrong.";
      } catch (error) {
        console.warn("Failed to parse error response", error);
        return "Something went wrong.";
      }
    },
  },
});
