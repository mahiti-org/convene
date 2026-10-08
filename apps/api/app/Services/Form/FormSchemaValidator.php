<?php

namespace App\Services\Form;

use App\Enums\WidgetType;

/**
 * Minimal schema_json checks: unique keys, valid widget types, repeat_group child keys.
 * packages/widget-schema is the source of truth.
 */
class FormSchemaValidator
{
    /** @return array<int, string> validation error messages; empty means valid */
    public function validate(array $schema): array
    {
        $errors = [];
        $seenKeys = [];

        foreach ($schema as $index => $widget) {
            $prefix = "Widget #{$index}";

            if (! is_array($widget)) {
                $errors[] = "{$prefix}: must be an object.";

                continue;
            }

            $key = $widget['key'] ?? null;
            if (! is_string($key) || $key === '') {
                $errors[] = "{$prefix}: missing a non-empty 'key'.";
            } elseif (isset($seenKeys[$key])) {
                $errors[] = "{$prefix}: duplicate key '{$key}'.";
            } else {
                $seenKeys[$key] = true;
            }

            $type = $widget['type'] ?? null;
            if (! is_string($type) || WidgetType::tryFrom($type) === null) {
                $errors[] = "{$prefix} ('{$key}'): '{$type}' is not a valid widget type.";
            }

            if (! isset($widget['label']) || ! is_string($widget['label']) || $widget['label'] === '') {
                $errors[] = "{$prefix} ('{$key}'): missing a non-empty 'label'.";
            }
        }

        // Second pass: repeat_group.options.child_widget_keys must reference keys that
        // actually exist elsewhere in this same flat schema.
        foreach ($schema as $index => $widget) {
            if (! is_array($widget) || ($widget['type'] ?? null) !== WidgetType::RepeatGroup->value) {
                continue;
            }

            $childKeys = $widget['options']['child_widget_keys'] ?? [];
            foreach ($childKeys as $childKey) {
                if (! isset($seenKeys[$childKey])) {
                    $errors[] = "Widget #{$index} ('{$widget['key']}'): child_widget_keys references unknown key '{$childKey}'.";
                }
            }
        }

        return $errors;
    }
}
