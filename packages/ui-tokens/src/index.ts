// Default theme tokens, overridden at runtime by GET /api/v1/instance/branding.
// Shared by web, mobile and backend PDF/email templates.

export interface ThemeTokens {
  orgName: string;
  logoUrl: string | null;
  colorPrimary: string;
  colorSecondary: string;
  colorBackground: string;
  colorText: string;
}

export const DEFAULT_THEME_TOKENS: ThemeTokens = {
  orgName: "Convene",
  logoUrl: null,
  colorPrimary: "#1e5f4a",
  colorSecondary: "#f2a71b",
  colorBackground: "#ffffff",
  colorText: "#1a1a1a",
};

export const CSS_VARIABLE_NAMES: Record<keyof Omit<ThemeTokens, "orgName" | "logoUrl">, string> = {
  colorPrimary: "--convene-color-primary",
  colorSecondary: "--convene-color-secondary",
  colorBackground: "--convene-color-background",
  colorText: "--convene-color-text",
};

export function applyThemeToDocument(tokens: ThemeTokens, doc: Document = document): void {
  const root = doc.documentElement;
  root.style.setProperty(CSS_VARIABLE_NAMES.colorPrimary, tokens.colorPrimary);
  root.style.setProperty(CSS_VARIABLE_NAMES.colorSecondary, tokens.colorSecondary);
  root.style.setProperty(CSS_VARIABLE_NAMES.colorBackground, tokens.colorBackground);
  root.style.setProperty(CSS_VARIABLE_NAMES.colorText, tokens.colorText);
}
