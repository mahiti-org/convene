// Shared TypeScript contracts between apps/web and apps/mobile.
// Mirrors the Laravel backend's PermissionEvaluator tuple shape (apps/api). Keep in sync by hand.

export type Channel = "web" | "mobile" | "any";

export type PermissionAction =
  | "view"
  | "create"
  | "edit"
  | "soft_delete"
  | "export"
  | "search"
  | "approve"
  | "reject"
  | "verify"
  | "assign"
  | "import";

export type ResourceType =
  | "geography_node"
  | "master"
  | "user"
  | "role"
  | "grant"
  | "beneficiary_type"
  | "beneficiary"
  | "household"
  | "form_definition"
  | "form_response"
  | "program"
  | "project"
  | "activity"
  | "report"
  | "dashboard";

export interface PermissionTuple {
  resourceType: ResourceType;
  action: PermissionAction;
  channel: Channel;
}

export interface EffectivePermissionsResponse {
  permissions: PermissionTuple[];
  projectContexts: Array<{ projectId: string; projectName: string }>;
}

export interface GeographyNode {
  id: string;
  parentId: string | null;
  level: number;
  label: string;
  isActive: boolean;
}

export interface InstanceBranding {
  orgName: string;
  logoUrl: string | null;
  primaryColor: string;
  secondaryColor: string;
}
