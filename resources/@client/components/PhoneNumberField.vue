<script setup lang="ts">
import { computed, onMounted, watch } from "vue";
import { FormInput, FormSelect } from "@/components/Base/Form";
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

onMounted(() => {
  phoneCodeStore.ensureLoaded().catch(() => undefined);
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

const selectModel = computed({
  get: () => (resolvedCodeId.value ? String(resolvedCodeId.value) : ""),
  set: (value: string) => {
    emit("update:countryCodeId", value ? Number(value) : null);
  },
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
    <div class="flex gap-2 mt-2">
      <FormSelect
        v-model="selectModel"
        :disabled="disabled || phoneCodeStore.loading"
        class="w-32 shrink-0"
      >
        <option value="" disabled>Select</option>
        <option
          v-for="code in phoneCodeStore.codes"
          :key="code.id"
          :value="code.id"
        >
          {{ code.dial_code }} — {{ code.name }}
        </option>
      </FormSelect>
      <FormInput
        :model-value="modelValue"
        type="tel"
        inputmode="numeric"
        autocomplete="tel"
        :maxlength="maxDigits"
        :placeholder="placeholder"
        :disabled="disabled"
        class="flex-1"
        @update:modelValue="handleNumberInput"
      />
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
