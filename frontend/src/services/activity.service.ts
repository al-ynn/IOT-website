import { getAutomationLogs } from "./automation-log.service";
import type { AutomationLog } from "../types/automation-log";

export interface ActivityEvent {
  id: string;
  category: "automation";
  title: string;
  actor?: string;
  resource: { type: "automation"; id: string; name: string };
  timestamp: string;
  status: AutomationLog["status"];
  metadata: { executionId: string; duration?: number; error?: string };
}

export async function getActivity(): Promise<ActivityEvent[]> {
  const logs = await getAutomationLogs();
  return logs.map(log => ({
    id: log.id,
    category: "automation",
    title: `Automation ${log.status === "success" ? "completed" : log.status}`,
    resource: { type: "automation", id: log.automationId, name: log.automationName },
    timestamp: log.executedAt,
    status: log.status,
    metadata: { executionId: log.id, duration: log.duration, error: log.error },
  }));
}
