export type LifecycleState = "active" | "disabled" | "archived";
export interface ResourceLifecycle {
  resourceType:
    | "device"
    | "device_template"
    | "dashboard"
    | "automation"
    | "report"
    | "firmware"
    | "location"
    | "webhook";
  resourceId: string;
  resourceLabel: string;
  state: LifecycleState;
  label: string;
  changed: boolean;
  lifecycleGeneration: number;
  transitionedAt: string | null;
  disabledAt: string | null;
  restoredAt: string | null;
  archivedAt: string | null;
  capabilities: {
    canDisable: boolean;
    canRestore: boolean;
    canArchive: boolean;
  };
}
export interface LifecycleEvent {
  id: string;
  status: string;
  description: string;
  createdAt: string;
}
export interface MaintenanceRecord {
  id: string;
  title: string;
  description: string;
  technician: string;
}
