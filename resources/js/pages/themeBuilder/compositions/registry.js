import ChromeComposition from "./ChromeComposition.vue";
import SurfaceComposition from "./SurfaceComposition.vue";
import ButtonsComposition from "./ButtonsComposition.vue";
import FormsComposition from "./FormsComposition.vue";
import DataComposition from "./DataComposition.vue";
import TabsComposition from "./TabsComposition.vue";
import StatusComposition from "./StatusComposition.vue";
import TextComposition from "./TextComposition.vue";
import SurfaceOverlay from "./SurfaceOverlay.vue";
import StatusOverlay from "./StatusOverlay.vue";

// Category key -> its preview composition component. Every preview category has
// one; an unknown key gets the Chrome note as a harmless fallback.
const COMPOSITIONS = {
    chrome: ChromeComposition,
    menu: ChromeComposition,
    surface: SurfaceComposition,
    buttons: ButtonsComposition,
    forms: FormsComposition,
    tabs: TabsComposition,
    table: DataComposition,
    status: StatusComposition,
    text: TextComposition,
};

export function compositionFor(categoryKey) {
    return COMPOSITIONS[categoryKey] ?? ChromeComposition;
}

// Category key -> a component rendered in the frame's overlay slot, over the
// whole frame (dimming the sidebar and header too). Held open while the
// category is active; null for categories with no overlay.
const OVERLAYS = {
    surface: SurfaceOverlay,
    status: StatusOverlay,
};

export function overlayFor(categoryKey) {
    return OVERLAYS[categoryKey] ?? null;
}
