import { defineStore } from "pinia";

const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || "/api";

type PhoneCode = {
  id: number;
  name: string;
  iso_code: string;
  dial_code: string;
  min_nsn_length: number;
  max_nsn_length: number;
  example_format: string | null;
  is_default: boolean;
};

type PhoneCodeResponse = {
  data: PhoneCode[];
};

export const usePhoneCodeStore = defineStore("phone-codes", {
  state: () => ({
    codes: [] as PhoneCode[],
    loading: false,
    loaded: false,
    error: null as string | null,
  }),
  getters: {
    defaultCodeId(state): number | null {
      if (!state.codes.length) {
        return null;
      }

      const preferred = state.codes.find((code) => code.is_default);
      return preferred?.id ?? state.codes[0]?.id ?? null;
    },
    byId: (state) => (id?: number | null): PhoneCode | null => {
      if (!id) {
        return null;
      }

      return state.codes.find((code) => code.id === id) ?? null;
    },
  },
  actions: {
    async fetchCodes(force = false): Promise<void> {
      if (this.loading || (this.loaded && !force)) {
        return;
      }

      this.loading = true;
      this.error = null;

      try {
        const response = await fetch(`${API_BASE_URL}/phone-codes`, {
          headers: {
            Accept: "application/json",
          },
        });

        if (!response.ok) {
          throw new Error("Unable to load country phone codes.");
        }

        const payload = (await response.json()) as PhoneCodeResponse;
        this.codes = payload.data;
        this.loaded = true;
      } catch (error) {
        const message =
          (error as Error).message || "Unable to load country phone codes.";
        this.error = message;
        throw new Error(message);
      } finally {
        this.loading = false;
      }
    },
    async ensureLoaded(): Promise<void> {
      if (!this.loaded) {
        await this.fetchCodes();
      }
    },
  },
});

export type { PhoneCode };
