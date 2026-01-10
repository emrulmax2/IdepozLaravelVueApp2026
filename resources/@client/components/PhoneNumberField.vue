<script setup lang="ts">
import {
  computed,
  nextTick,
  onBeforeUnmount,
  onMounted,
  ref,
  watch,
} from "vue";
import { FormInput } from "@/components/Base/Form";
import Lucide from "@/components/Base/Lucide";
import { usePhoneCodeStore } from "@/stores/phoneCodes";

const props = withDefaults(
  defineProps<{
    modelValue: string;
    countryCodeId: number | null;
    label?: string;
    hint?: string;
    error?: string | null;
    disabled?: boolean;
  }>(),
  {
    modelValue: "",
    countryCodeId: null,
    label: "Mobile Number",
    hint: "",
    error: null,
    disabled: false,
  }
);

const emit = defineEmits<{
  (e: "update:modelValue", value: string): void;
  (e: "update:countryCodeId", value: number | null): void;
}>();

const phoneCodeStore = usePhoneCodeStore();
const dropdownOpen = ref(false);
const searchTerm = ref("");
const dropdownRef = ref<HTMLElement | null>(null);
const triggerRef = ref<HTMLElement | null>(null);
const searchInputRef = ref<HTMLInputElement | null>(null);

const closeDropdown = () => {
  dropdownOpen.value = false;
};

const openDropdown = () => {
  if (props.disabled || phoneCodeStore.loading) {
    return;
  }

  dropdownOpen.value = true;
  searchTerm.value = "";
  nextTick(() => searchInputRef.value?.focus());
};

const toggleDropdown = () => {
  if (dropdownOpen.value) {
    closeDropdown();
  } else {
    openDropdown();
  }
};

const handleClickOutside = (event: MouseEvent) => {
  if (!dropdownOpen.value) {
    return;
  }

  const target = event.target as Node;
  if (
    dropdownRef.value?.contains(target) ||
    triggerRef.value?.contains(target)
  ) {
    return;
  }

  closeDropdown();
};

onMounted(() => {
  phoneCodeStore.ensureLoaded().catch(() => undefined);
  document.addEventListener("click", handleClickOutside);
});

onBeforeUnmount(() => {
  document.removeEventListener("click", handleClickOutside);
});

watch(
  () => phoneCodeStore.defaultCodeId,
  (defaultId) => {
    if (!props.countryCodeId && defaultId) {
      emit("update:countryCodeId", defaultId);
    }
  },
  { immediate: true }
);

const resolvedCodeId = computed(() => {
  return props.countryCodeId ?? phoneCodeStore.defaultCodeId ?? null;
});

const selectedCode = computed(() => {
  return phoneCodeStore.byId(resolvedCodeId.value) ?? null;
});

const isoToFlagEmoji = (iso?: string | null) => {
  if (!iso) {
    return "🌐";
  }

  const letters = iso
    .trim()
    .toUpperCase()
    .replace(/[^A-Z]/g, "")
    .slice(0, 2);

  if (letters.length !== 2) {
    return "🌐";
  }

  const points = letters
    .split("")
    .map((char) => 127397 + char.charCodeAt(0));

  return String.fromCodePoint(...points);
};

const isoToFlagSrc = (iso?: string | null, size: 16 | 24 | 32 = 24) => {
  if (!iso) {
    return null;
  }

  const code = iso.trim().toLowerCase();
  if (!/^[a-z]{2}$/.test(code)) {
    return null;
  }

  return `https://flagcdn.com/h${size}/${code}.png`;
};

const selectedFlag = computed(() => isoToFlagEmoji(selectedCode.value?.iso_code));
const selectedFlagSrc = computed(() =>
  isoToFlagSrc(selectedCode.value?.iso_code, 24)
);

const filteredCodes = computed(() => {
  if (!searchTerm.value.trim()) {
    return phoneCodeStore.codes;
  }

  const query = searchTerm.value.toLowerCase();
  return phoneCodeStore.codes.filter((code) => {
    return (
      code.name.toLowerCase().includes(query) ||
      code.dial_code.toLowerCase().includes(query) ||
      code.iso_code.toLowerCase().includes(query)
    );
  });
});

const maxDigits = computed(() => selectedCode.value?.max_nsn_length ?? 15);

const formattedExample = computed(() => {
  if (!selectedCode.value) {
    return null;
  }

  if (selectedCode.value.example_format) {
    return `${selectedCode.value.dial_code} ${selectedCode.value.example_format}`;
  }

  return null;
});

const handleNumberInput = (value: string) => {
  const digits = value.replace(/[^0-9]/g, "").slice(0, maxDigits.value);
  emit("update:modelValue", digits);
};

const handleCodeSelect = (codeId: number) => {
  emit("update:countryCodeId", codeId);
  closeDropdown();
};

const placeholder = computed(() => {
  if (formattedExample.value) {
    return formattedExample.value.replace(/^[+0-9]+\s*/, "");
  }

  return "Enter number";
});
</script>

<template>
  <div>
    <label class="text-sm font-semibold text-slate-600 dark:text-slate-300">
      {{ label }}
    </label>
    <div class="relative mt-2">
      <div class="flex">
        <button
          ref="triggerRef"
          type="button"
          class="flex items-center gap-3 rounded-l-xl border border-r-0 border-slate-200 bg-white px-3 py-2 text-left text-sm font-medium text-slate-700 transition focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-primary/30 disabled:cursor-not-allowed disabled:opacity-70 dark:border-darkmode-400 dark:bg-darkmode-700 dark:text-slate-200"
          :class="{ 'rounded-r-xl border-r': disabled }"
          :disabled="disabled || phoneCodeStore.loading"
          @click="toggleDropdown"
        >
          <span class="flex items-center justify-center">
            <img
              v-if="selectedFlagSrc"
              :src="selectedFlagSrc"
              :alt="selectedCode?.iso_code ?? 'Flag'"
              class="h-6 w-6 rounded-full object-cover shadow-sm"
              decoding="async"
            />
            <span v-else class="text-xl leading-none">{{ selectedFlag }}</span>
          </span>
          <div>
            <p class="text-xs uppercase tracking-wide text-slate-400 dark:text-slate-500">
              {{ selectedCode ? selectedCode.iso_code : "Code" }}
            </p>
            <p class="font-semibold">
              {{ selectedCode ? selectedCode.dial_code : "Select" }}
            </p>
          </div>
          <Lucide icon="ChevronDown" class="ml-auto h-4 w-4 text-slate-400" />
        </button>
        <FormInput
          :model-value="modelValue"
          type="tel"
          inputmode="numeric"
          autocomplete="tel"
          :maxlength="maxDigits"
          :placeholder="placeholder"
          :disabled="disabled"
          class="flex-1 rounded-l-none rounded-r-xl border-l-0"
          @update:modelValue="handleNumberInput"
        />
      </div>
      <transition
        enter-active-class="transition ease-out duration-150"
        enter-from-class="opacity-0 translate-y-1"
        enter-to-class="opacity-100 translate-y-0"
        leave-active-class="transition ease-in duration-100"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div
          v-if="dropdownOpen"
          ref="dropdownRef"
          class="absolute z-50 mt-2 w-full rounded-2xl border border-slate-200 bg-white p-4 shadow-xl dark:border-darkmode-400 dark:bg-darkmode-700"
        >
          <div class="flex items-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm dark:border-darkmode-400 dark:bg-darkmode-600">
            <Lucide icon="Search" class="h-4 w-4 text-slate-400" />
            <input
              ref="searchInputRef"
              v-model="searchTerm"
              type="text"
              placeholder="Search for country"
              class="flex-1 bg-transparent text-slate-600 placeholder:text-slate-400 focus:outline-none dark:text-slate-200"
              @keydown.esc.prevent="closeDropdown"
            />
          </div>
          <div class="mt-3 max-h-72 overflow-y-auto pr-1">
            <p v-if="phoneCodeStore.loading" class="py-6 text-center text-sm text-slate-500 dark:text-slate-300">
              Loading country codes...
            </p>
            <template v-else>
              <p
                v-if="!filteredCodes.length"
                class="py-6 text-center text-sm text-slate-500 dark:text-slate-300"
              >
                No matches found.
              </p>
              <ul v-else class="space-y-1">
                <li v-for="code in filteredCodes" :key="code.id">
                  <button
                    type="button"
                    class="flex w-full items-center gap-3 rounded-xl px-3 py-2 text-left transition hover:bg-slate-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 dark:hover:bg-darkmode-600"
                    :class="{
                      'bg-primary/5 dark:bg-primary/20 text-primary dark:text-white':
                        code.id === resolvedCodeId,
                    }"
                    @click="handleCodeSelect(code.id)"
                  >
                    <span class="flex items-center justify-center">
                      <img
                        v-if="isoToFlagSrc(code.iso_code)"
                        :src="isoToFlagSrc(code.iso_code)"
                        :alt="code.iso_code"
                        class="h-6 w-6 rounded-full object-cover shadow-sm"
                        loading="lazy"
                        decoding="async"
                      />
                      <span v-else class="text-2xl leading-none">
                        {{ isoToFlagEmoji(code.iso_code) }}
                      </span>
                    </span>
                    <div class="flex-1">
                      <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">
                        {{ code.name }}
                      </p>
                      <p class="text-xs text-slate-500 dark:text-slate-300">
                        {{ code.dial_code }}
                      </p>
                    </div>
                    <span class="text-xs font-semibold text-slate-500 dark:text-slate-300">
                      {{ code.iso_code }}
                    </span>
                  </button>
                </li>
              </ul>
            </template>
          </div>
        </div>
      </transition>
    </div>
    <p v-if="error" class="mt-2 text-sm text-danger">
      {{ error }}
    </p>
    <p v-else-if="hint" class="mt-2 text-xs text-slate-500 dark:text-slate-400">
      {{ hint }}
    </p>
    <p
      v-else-if="formattedExample"
      class="mt-2 text-xs text-slate-500 dark:text-slate-400"
    >
      Example: {{ formattedExample }}
    </p>
    <p
      v-if="phoneCodeStore.error"
      class="mt-2 text-xs text-danger"
    >
      {{ phoneCodeStore.error }}
    </p>
  </div>
</template>
