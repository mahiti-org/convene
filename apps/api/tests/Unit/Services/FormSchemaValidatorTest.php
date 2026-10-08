<?php

namespace Tests\Unit\Services;

use App\Services\Form\FormSchemaValidator;
use PHPUnit\Framework\TestCase;

class FormSchemaValidatorTest extends TestCase
{
    public function test_valid_schema_has_no_errors(): void
    {
        $validator = new FormSchemaValidator;

        $errors = $validator->validate([
            ['key' => 'name', 'type' => 'single_line_text', 'label' => 'Name', 'required' => true],
            ['key' => 'dob', 'type' => 'date', 'label' => 'Date of Birth'],
            ['key' => 'village', 'type' => 'cascading_select', 'label' => 'Village', 'options' => ['start_geography_level' => 0]],
        ]);

        $this->assertEmpty($errors);
    }

    public function test_missing_key_is_an_error(): void
    {
        $validator = new FormSchemaValidator;

        $errors = $validator->validate([
            ['type' => 'single_line_text', 'label' => 'Name'],
        ]);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('key', $errors[0]);
    }

    public function test_duplicate_key_is_an_error(): void
    {
        $validator = new FormSchemaValidator;

        $errors = $validator->validate([
            ['key' => 'name', 'type' => 'single_line_text', 'label' => 'Name'],
            ['key' => 'name', 'type' => 'single_line_text', 'label' => 'Name Again'],
        ]);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('duplicate', strtolower($errors[0]));
    }

    public function test_invalid_widget_type_is_an_error(): void
    {
        $validator = new FormSchemaValidator;

        $errors = $validator->validate([
            ['key' => 'name', 'type' => 'not_a_real_widget', 'label' => 'Name'],
        ]);

        $this->assertNotEmpty($errors);
    }

    public function test_missing_label_is_an_error(): void
    {
        $validator = new FormSchemaValidator;

        $errors = $validator->validate([
            ['key' => 'name', 'type' => 'single_line_text'],
        ]);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('label', $errors[0]);
    }

    public function test_repeat_group_referencing_unknown_child_key_is_an_error(): void
    {
        $validator = new FormSchemaValidator;

        $errors = $validator->validate([
            ['key' => 'household_members', 'type' => 'repeat_group', 'label' => 'Household Members', 'options' => [
                'child_widget_keys' => ['member_name', 'member_age'],
            ]],
            ['key' => 'member_name', 'type' => 'single_line_text', 'label' => 'Member Name'],
            // member_age is NOT defined, so it should be flagged.
        ]);

        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('member_age', $errors[0]);
    }

    public function test_repeat_group_with_valid_child_keys_has_no_errors(): void
    {
        $validator = new FormSchemaValidator;

        $errors = $validator->validate([
            ['key' => 'household_members', 'type' => 'repeat_group', 'label' => 'Household Members', 'options' => [
                'child_widget_keys' => ['member_name'],
            ]],
            ['key' => 'member_name', 'type' => 'single_line_text', 'label' => 'Member Name'],
        ]);

        $this->assertEmpty($errors);
    }

    public function test_empty_schema_has_no_errors(): void
    {
        // Empty is valid for a work-in-progress draft; publish() enforces non-empty separately.
        $validator = new FormSchemaValidator;

        $this->assertEmpty($validator->validate([]));
    }
}
