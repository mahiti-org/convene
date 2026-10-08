<?php

namespace App\Enums;

// Hand-synced mirror of WidgetType in packages/widget-schema/src/index.ts (the source of truth),
// for backend schema validation.
enum WidgetType: string
{
    // Plain MVP widgets
    case SingleLineText = 'single_line_text';
    case NumberInteger = 'number_integer';
    case Decimal = 'decimal';
    case Dropdown = 'dropdown';
    case Radio = 'radio';
    case Checkboxes = 'checkboxes';
    case Date = 'date';
    case PhotoCapture = 'photo_capture';
    case FileUpload = 'file_upload';
    case GpsPoint = 'gps_point';
    case NoteDisplay = 'note_display';
    case SectionBreak = 'section_break';

    // Data-model-critical widgets pulled into Phase 1
    case CascadingSelect = 'cascading_select';
    case RepeatGroup = 'repeat_group';
    case BeneficiaryLookup = 'beneficiary_lookup';
    case GovernmentId = 'government_id';
}
