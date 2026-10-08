import { z } from "zod";

// Widget contract shared by the web form-builder and the mobile offline renderer.

export type WidgetType =
  // Plain MVP widgets
  | "single_line_text"
  | "number_integer"
  | "decimal"
  | "dropdown"
  | "radio"
  | "checkboxes"
  | "date"
  | "photo_capture"
  | "file_upload"
  | "gps_point"
  | "note_display"
  | "section_break"
  // Data-model-critical widgets pulled into Phase 1
  | "cascading_select"
  | "repeat_group"
  | "beneficiary_lookup"
  | "government_id";

export interface WidgetDefinition<TOptions = Record<string, unknown>> {
  type: WidgetType;
  category: "text" | "numeric" | "choice" | "date" | "media" | "location" | "structural" | "domain" | "display";
  availability: "both" | "mobile" | "mobile_first";
  optionsSchema: z.ZodType<TOptions>;
}

const registry: Record<WidgetType, WidgetDefinition> = {
  single_line_text: {
    type: "single_line_text",
    category: "text",
    availability: "both",
    optionsSchema: z.object({ minLength: z.number().optional(), maxLength: z.number().optional(), mode: z.enum(["chars_only", "alphanumeric"]).optional() }),
  },
  number_integer: {
    type: "number_integer",
    category: "numeric",
    availability: "both",
    optionsSchema: z.object({ min: z.number().optional(), max: z.number().optional() }),
  },
  decimal: {
    type: "decimal",
    category: "numeric",
    availability: "both",
    optionsSchema: z.object({ min: z.number().optional(), max: z.number().optional(), precision: z.number().optional() }),
  },
  dropdown: {
    type: "dropdown",
    category: "choice",
    availability: "both",
    optionsSchema: z.object({ sourceMasterKey: z.string().optional(), options: z.array(z.string()).optional(), searchable: z.boolean().optional() }),
  },
  radio: {
    type: "radio",
    category: "choice",
    availability: "both",
    optionsSchema: z.object({ options: z.array(z.string()) }),
  },
  checkboxes: {
    type: "checkboxes",
    category: "choice",
    availability: "both",
    optionsSchema: z.object({ options: z.array(z.string()), minSelections: z.number().optional(), maxSelections: z.number().optional() }),
  },
  date: {
    type: "date",
    category: "date",
    availability: "both",
    optionsSchema: z.object({ notFuture: z.boolean().optional(), notPast: z.boolean().optional() }),
  },
  photo_capture: {
    type: "photo_capture",
    category: "media",
    availability: "mobile_first",
    optionsSchema: z.object({ maxCount: z.number().optional(), maxSizeMb: z.number().optional(), stampGpsAndTimestamp: z.boolean().default(true) }),
  },
  file_upload: {
    type: "file_upload",
    category: "media",
    availability: "both",
    optionsSchema: z.object({ allowedTypes: z.array(z.string()).optional(), maxSizeMb: z.number().optional() }),
  },
  gps_point: {
    type: "gps_point",
    category: "location",
    availability: "mobile",
    optionsSchema: z.object({ accuracyThresholdMeters: z.number().optional() }),
  },
  note_display: {
    type: "note_display",
    category: "display",
    availability: "both",
    optionsSchema: z.object({ text: z.string() }),
  },
  section_break: {
    type: "section_break",
    category: "structural",
    availability: "both",
    optionsSchema: z.object({ title: z.string().optional() }),
  },
  cascading_select: {
    type: "cascading_select",
    category: "choice",
    availability: "both",
    optionsSchema: z.object({ startGeographyLevel: z.number() }),
  },
  repeat_group: {
    type: "repeat_group",
    category: "structural",
    availability: "both",
    optionsSchema: z.object({ minRepeats: z.number().optional(), maxRepeats: z.number().optional(), childWidgetKeys: z.array(z.string()) }),
  },
  beneficiary_lookup: {
    type: "beneficiary_lookup",
    category: "domain",
    availability: "both",
    optionsSchema: z.object({ beneficiaryType: z.string().optional(), blockOnDuplicate: z.boolean().default(false) }),
  },
  government_id: {
    type: "government_id",
    category: "domain",
    availability: "both",
    optionsSchema: z.object({ idType: z.enum(["aadhaar", "voter_id", "other"]), enforceChecksum: z.boolean().default(true) }),
  },
};

export function getWidgetDefinition(type: WidgetType): WidgetDefinition {
  return registry[type];
}

export function listWidgetDefinitions(): WidgetDefinition[] {
  return Object.values(registry);
}

// Aadhaar Verhoeff checksum. Shared client-side validator for the government_id widget
// so web and mobile validate identically without a network round-trip.
export function isValidVerhoeffChecksum(digits: string): boolean {
  const d = [
    [0, 1, 2, 3, 4, 5, 6, 7, 8, 9],
    [1, 2, 3, 4, 0, 6, 7, 8, 9, 5],
    [2, 3, 4, 0, 1, 7, 8, 9, 5, 6],
    [3, 4, 0, 1, 2, 8, 9, 5, 6, 7],
    [4, 0, 1, 2, 3, 9, 5, 6, 7, 8],
    [5, 9, 8, 7, 6, 0, 4, 3, 2, 1],
    [6, 5, 9, 8, 7, 1, 0, 4, 3, 2],
    [7, 6, 5, 9, 8, 2, 1, 0, 4, 3],
    [8, 7, 6, 5, 9, 3, 2, 1, 0, 4],
    [9, 8, 7, 6, 5, 4, 3, 2, 1, 0],
  ];
  const p = [
    [0, 1, 2, 3, 4, 5, 6, 7, 8, 9],
    [1, 5, 7, 6, 2, 8, 3, 0, 9, 4],
    [5, 8, 0, 3, 7, 9, 6, 1, 4, 2],
    [8, 9, 1, 6, 0, 4, 3, 5, 2, 7],
    [9, 4, 5, 3, 1, 2, 6, 8, 7, 0],
    [4, 2, 8, 6, 5, 7, 3, 9, 0, 1],
    [2, 7, 9, 3, 8, 0, 6, 4, 1, 5],
    [7, 0, 4, 6, 9, 1, 3, 2, 5, 8],
  ];
  const clean = digits.replace(/\D/g, "").split("").reverse().map(Number);
  let c = 0;
  clean.forEach((digit, i) => {
    c = d[c][p[i % 8][digit]];
  });
  return c === 0;
}
