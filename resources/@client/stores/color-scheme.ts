import { defineStore } from "pinia";

const colorSchemes = [
  "default",
  "theme-1",
  "theme-2",
  "theme-3",
  "theme-4",
] as const;

export type ColorSchemes = typeof colorSchemes[number];

interface ColorSchemeState {
  colorSchemeValue: ColorSchemes;
}

const DEFAULT_COLOR_SCHEME: ColorSchemes = "theme-3";

const getColorScheme = (): ColorSchemes => {
  const colorScheme = localStorage.getItem("colorScheme");

  if (!colorScheme) {
    return DEFAULT_COLOR_SCHEME;
  }

  return (
    colorSchemes.find((item) => item === colorScheme) ?? DEFAULT_COLOR_SCHEME
  );
};

export const useColorSchemeStore = defineStore("colorScheme", {
  state: (): ColorSchemeState => ({
    colorSchemeValue: getColorScheme(),
  }),
  getters: {
    colorScheme(state) {
      if (localStorage.getItem("colorScheme") === null) {
        localStorage.setItem("colorScheme", DEFAULT_COLOR_SCHEME);
      }

      return state.colorSchemeValue;
    },
  },
  actions: {
    setColorScheme(colorScheme: ColorSchemes) {
      localStorage.setItem("colorScheme", colorScheme);
      this.colorSchemeValue = colorScheme;
    },
  },
});
