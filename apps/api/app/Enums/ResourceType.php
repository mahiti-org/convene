<?php

namespace App\Enums;

// Kept in sync by hand with packages/shared-types/src/index.ts's ResourceType union.
enum ResourceType: string
{
    case GeographyNode = 'geography_node';
    case Master = 'master';
    case User = 'user';
    case Role = 'role';
    case Grant = 'grant';
    case BeneficiaryType = 'beneficiary_type';
    case Beneficiary = 'beneficiary';
    case Household = 'household';
    case FormDefinition = 'form_definition';
    case FormResponse = 'form_response';
    case Program = 'program';
    case Project = 'project';
    case Activity = 'activity';
    case Report = 'report';
    case Dashboard = 'dashboard';
}
