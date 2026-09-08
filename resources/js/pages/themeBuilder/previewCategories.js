// The nine preview categories, in tab order. They drive the category tab
// bar, the frame's breadcrumb, and the picker set shown under the example
// (ThemeTokens::categories() assigns roles to categories in this same order).
export const PREVIEW_CATEGORIES = [
    { key: "chrome", labelKey: "theme.category.chrome" },
    { key: "menu", labelKey: "theme.category.menu" },
    { key: "text", labelKey: "theme.category.text" },
    { key: "buttons", labelKey: "theme.category.buttons" },
    { key: "tabs", labelKey: "theme.category.tabs" },
    { key: "table", labelKey: "theme.category.table" },
    { key: "forms", labelKey: "theme.category.forms" },
    { key: "status", labelKey: "theme.category.status" },
    { key: "surface", labelKey: "theme.category.surface" },
];

export const DEFAULT_PREVIEW_CATEGORY = PREVIEW_CATEGORIES[0].key;
