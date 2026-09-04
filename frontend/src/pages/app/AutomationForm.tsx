import { useCallback, useEffect, useRef, useState } from "react";
import { Link, useNavigate, useParams } from "react-router-dom";
import { ArrowLeft, Plus, Trash2 } from "lucide-react";
import {
  BodyText,
  Button,
  Card,
  CardContent,
  CardHeader,
  ErrorState,
  Input,
  LoadingState,
  PageTitle,
  Select,
  Textarea,
} from "../../components/ui";
import {
  createAutomation,
  getAutomation,
  updateAutomation,
} from "../../services/automation.service";
import { getDevices } from "../../services/device.service";
import { listDeviceParameters } from "../../services/device-parameter.service";
import type { AutomationDefinition, TriggerType } from "../../types/automation";
import type { ConditionOperator, RuleCondition } from "../../types/condition";
import type { Device, DeviceParameter } from "../../types/device";
import type { ScheduleType } from "../../types/schedule";
import { useEditingPresence } from "../../hooks/useEditingPresence";
const emptyCondition: RuleCondition = { field: "", operator: ">", value: "" };
const initial: AutomationDefinition = {
  name: "",
  description: "",
  enabled: false,
  trigger: { type: "telemetry", field: "" },
  conditions: { logic: "AND", conditions: [] },
  actions: [
    {
      type: "notification",
      target: "organization",
      payload: { message: "" },
      continueOnFailure: false,
    },
  ],
  schedule: null,
};
export default function AutomationForm() {
  const { id } = useParams();
  const editing = !!id;
  const navigate = useNavigate();
  const [form, setForm] = useState<AutomationDefinition>(initial);
  const [baseRevisionId, setBaseRevisionId] = useState<string | null>(null);
  const pendingSave = useRef<{
    key: string;
    baseRevisionId: string | null;
    command: AutomationDefinition;
    serialized: string;
  } | null>(null);
  const [devices, setDevices] = useState<Device[]>([]);
  const [parameters, setParameters] = useState<DeviceParameter[]>([]);
  const [loading, setLoading] = useState(editing);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState("");
  const { otherEditors, presenceUnavailable } = useEditingPresence(
    "automation",
    id,
    editing,
  );
  const load = useCallback(async () => {
    try {
      const deviceItems = (await getDevices()).filter(
        (d) => d.access?.level === "full_access",
      );
      setDevices(deviceItems);
      if (id) {
        const automation = await getAutomation(id);
        setForm(automation);
        setBaseRevisionId(automation.baseRevisionId ?? null);
      }
    } catch {
      setError("Automation form data could not be loaded.");
    } finally {
      setLoading(false);
    }
  }, [id]);
  useEffect(() => {
    const timer = window.setTimeout(() => void load(), 0);
    return () => window.clearTimeout(timer);
  }, [load]);
  useEffect(() => {
    const deviceId = form.trigger.deviceId;
    if (!deviceId) {
      // Clearing a resource-dependent list is the effect's synchronization action.
      // eslint-disable-next-line react-hooks/set-state-in-effect
      setParameters([]);
      return;
    }
    let active = true;
    listDeviceParameters(deviceId)
      .then((v) => {
        if (active) setParameters(v);
      })
      .catch(() => {
        if (active) setParameters([]);
      });
    return () => {
      active = false;
    };
  }, [form.trigger.deviceId]);
  const triggerType = (type: TriggerType) =>
    setForm((v) => ({
      ...v,
      trigger: {
        type,
        ...(type === "telemetry" || type === "device_status"
          ? { deviceId: v.trigger.deviceId, field: v.trigger.field }
          : {}),
      },
      schedule:
        type === "schedule"
          ? (v.schedule ?? {
              type: "interval",
              intervalMinutes: 5,
              enabled: true,
            })
          : null,
    }));
  const condition = (index: number, patch: Partial<RuleCondition>) =>
    setForm((v) => ({
      ...v,
      conditions: {
        ...v.conditions,
        conditions: v.conditions.conditions.map((c, i) =>
          i === index ? { ...c, ...patch } : c,
        ),
      },
    }));
  const save = async () => {
    setSaving(true);
    setError("");
    try {
      const serialized = JSON.stringify(form);
      const attempt =
        pendingSave.current?.serialized === serialized &&
        pendingSave.current.baseRevisionId === baseRevisionId
          ? pendingSave.current
          : {
              key: crypto.randomUUID(),
              baseRevisionId,
              command: structuredClone(form),
              serialized,
            };
      pendingSave.current = attempt;
      const result = editing
        ? await updateAutomation(
            id,
            attempt.command,
            attempt.baseRevisionId,
            attempt.key,
          )
        : await createAutomation(form);
      pendingSave.current = null;
      navigate(`/app/automations/${result.id}`);
    } catch {
      setError(
        "Automation could not be saved. Check required fields and current device access.",
      );
    } finally {
      setSaving(false);
    }
  };
  if (loading) return <LoadingState label="Loading automation..." />;
  const schedule = form.schedule;
  return (
    <div className="mx-auto max-w-4xl space-y-5">
      <Link
        to={editing ? `/app/automations/${id}` : "/app/automations"}
        className="inline-flex items-center gap-2 text-xs text-[var(--ds-text-muted)]"
      >
        <ArrowLeft size={14} />
        Automations
      </Link>
      <div>
        <PageTitle>
          {editing ? "Edit Automation" : "Create Automation"}
        </PageTitle>
        <BodyText className="mt-1">
          Configure a persisted rule executed by the Laravel automation engine.
        </BodyText>
      </div>
      {error && <ErrorState description={error} />}
      {otherEditors.length > 0 && (
        <p
          role="status"
          className="rounded border border-[var(--ds-warning)] p-3 text-sm"
        >
          {otherEditors.map((editor) => editor.user.displayName).join(", ")}{" "}
          {otherEditors.length === 1 ? "is" : "are"} also editing. Saves remain
          available and stale revisions are rejected.
        </p>
      )}
      {presenceUnavailable && (
        <p role="status" className="text-xs text-[var(--ds-text-muted)]">
          Editing presence is temporarily unavailable; save conflict protection
          remains active.
        </p>
      )}
      <Card>
        <CardHeader title="General" />
        <CardContent className="space-y-3">
          <Input
            label="Name"
            required
            value={form.name}
            onChange={(e) => setForm((v) => ({ ...v, name: e.target.value }))}
          />
          <Textarea
            label="Description"
            value={form.description ?? ""}
            onChange={(e) =>
              setForm((v) => ({ ...v, description: e.target.value }))
            }
          />
          <label className="flex items-center gap-2 text-sm">
            <input
              type="checkbox"
              checked={form.enabled}
              onChange={(e) =>
                setForm((v) => ({ ...v, enabled: e.target.checked }))
              }
            />
            Enabled
          </label>
        </CardContent>
      </Card>
      <Card>
        <CardHeader
          title="Trigger"
          description="Telemetry and device-status triggers may target only Devices with Full Access."
        />
        <CardContent className="grid gap-3 sm:grid-cols-2">
          <Select
            label="Trigger type"
            value={form.trigger.type}
            onChange={(e) => triggerType(e.target.value as TriggerType)}
          >
            <option value="telemetry">Telemetry</option>
            <option value="device_status">Device status</option>
            <option value="schedule">Schedule</option>
            <option value="manual">Manual</option>
          </Select>
          {(form.trigger.type === "telemetry" ||
            form.trigger.type === "device_status") && (
            <>
              <Select
                label="Device"
                helperText="Assignment-scoped Full Access devices only."
                value={form.trigger.deviceId ?? ""}
                onChange={(e) =>
                  setForm((v) => ({
                    ...v,
                    trigger: {
                      ...v.trigger,
                      deviceId: e.target.value || undefined,
                    },
                  }))
                }
              >
                <option value="">Any assigned device</option>
                {devices.map((d) => (
                  <option key={d.id} value={d.id}>
                    {d.name} — Full Access
                  </option>
                ))}
              </Select>
              <div className="sm:col-span-2">
                <Input
                  label="Parameter / field"
                  list="automation-parameters"
                  helperText="Choose a known Device Parameter or enter a raw metric key for backward compatibility."
                  value={form.trigger.field ?? ""}
                  onChange={(e) =>
                    setForm((v) => ({
                      ...v,
                      trigger: { ...v.trigger, field: e.target.value },
                    }))
                  }
                />
                <datalist id="automation-parameters">
                  {parameters.map((p) => (
                    <option key={p.id} value={p.key}>
                      {p.name}
                      {p.unit ? ` (${p.unit})` : ""}
                    </option>
                  ))}
                </datalist>
              </div>
            </>
          )}
        </CardContent>
      </Card>
      <Card>
        <CardHeader
          title="Conditions"
          description="All listed operators are implemented by the server evaluator."
          action={
            <Button
              size="small"
              variant="outline"
              leadingIcon={<Plus size={14} />}
              onClick={() =>
                setForm((v) => ({
                  ...v,
                  conditions: {
                    ...v.conditions,
                    conditions: [
                      ...v.conditions.conditions,
                      { ...emptyCondition },
                    ],
                  },
                }))
              }
            >
              Add condition
            </Button>
          }
        />
        <CardContent className="space-y-3">
          <Select
            label="Condition logic"
            value={form.conditions.logic}
            onChange={(e) =>
              setForm((v) => ({
                ...v,
                conditions: {
                  ...v.conditions,
                  logic: e.target.value as "AND" | "OR",
                },
              }))
            }
          >
            <option value="AND">All conditions (AND)</option>
            <option value="OR">Any condition (OR)</option>
          </Select>
          {form.conditions.conditions.length === 0 ? (
            <p className="text-xs text-[var(--ds-text-muted)]">
              No conditions. The action runs whenever the trigger matches.
            </p>
          ) : (
            form.conditions.conditions.map((c, i) => (
              <div
                key={i}
                className="grid gap-2 rounded-[8px] border border-[var(--ds-border-subtle)] p-3 sm:grid-cols-[1fr_150px_1fr_auto]"
              >
                <Input
                  aria-label={`Condition ${i + 1} field`}
                  placeholder="Field"
                  value={c.field}
                  onChange={(e) => condition(i, { field: e.target.value })}
                />
                <Select
                  aria-label={`Condition ${i + 1} operator`}
                  value={c.operator}
                  onChange={(e) =>
                    condition(i, {
                      operator: e.target.value as ConditionOperator,
                    })
                  }
                >
                  {[
                    ">",
                    "<",
                    "=",
                    ">=",
                    "<=",
                    "!=",
                    "contains",
                    "starts_with",
                    "ends_with",
                    "exists",
                  ].map((op) => (
                    <option key={op}>{op}</option>
                  ))}
                </Select>
                <Input
                  aria-label={`Condition ${i + 1} value`}
                  placeholder="Value"
                  disabled={c.operator === "exists"}
                  value={String(c.value ?? "")}
                  onChange={(e) => condition(i, { value: e.target.value })}
                />
                <Button
                  aria-label={`Remove condition ${i + 1}`}
                  size="compact"
                  variant="danger"
                  onClick={() =>
                    setForm((v) => ({
                      ...v,
                      conditions: {
                        ...v.conditions,
                        conditions: v.conditions.conditions.filter(
                          (_, x) => x !== i,
                        ),
                      },
                    }))
                  }
                >
                  <Trash2 size={14} />
                </Button>
              </div>
            ))
          )}
        </CardContent>
      </Card>
      <Card>
        <CardHeader
          title="Action"
          description="The canonical engine currently supports organization notifications only."
        />
        <CardContent>
          <Textarea
            label="Notification message"
            required
            value={String(form.actions[0]?.payload?.message ?? "")}
            onChange={(e) =>
              setForm((v) => ({
                ...v,
                actions: [
                  {
                    type: "notification",
                    target: "organization",
                    payload: { message: e.target.value },
                    continueOnFailure: false,
                  },
                ],
              }))
            }
          />
        </CardContent>
      </Card>
      {schedule && (
        <Card>
          <CardHeader
            title="Schedule"
            description="Executed server-side in application time; no browser timer is used."
          />
          <CardContent className="grid gap-3 sm:grid-cols-2">
            <Select
              label="Schedule type"
              value={schedule.type}
              onChange={(e) =>
                setForm((v) => ({
                  ...v,
                  schedule: {
                    type: e.target.value as ScheduleType,
                    enabled: true,
                  },
                }))
              }
            >
              <option value="once">Once</option>
              <option value="daily">Daily</option>
              <option value="weekly">Weekly</option>
              <option value="interval">Interval</option>
            </Select>
            {schedule.type === "interval" && (
              <Input
                label="Interval minutes"
                type="number"
                min={1}
                max={10080}
                value={schedule.intervalMinutes ?? 5}
                onChange={(e) =>
                  setForm((v) => ({
                    ...v,
                    schedule: {
                      ...schedule,
                      intervalMinutes: Number(e.target.value),
                    },
                  }))
                }
              />
            )}{" "}
            {(schedule.type === "daily" || schedule.type === "weekly") && (
              <Input
                label="Time"
                type="time"
                value={schedule.time ?? ""}
                onChange={(e) =>
                  setForm((v) => ({
                    ...v,
                    schedule: { ...schedule, time: e.target.value },
                  }))
                }
              />
            )}{" "}
            {schedule.type === "weekly" && (
              <Select
                label="Day"
                value={schedule.day ?? "Monday"}
                onChange={(e) =>
                  setForm((v) => ({
                    ...v,
                    schedule: { ...schedule, day: e.target.value },
                  }))
                }
              >
                {[
                  "Monday",
                  "Tuesday",
                  "Wednesday",
                  "Thursday",
                  "Friday",
                  "Saturday",
                  "Sunday",
                ].map((day) => (
                  <option key={day}>{day}</option>
                ))}
              </Select>
            )}{" "}
            {schedule.type === "once" && (
              <Input
                label="Run at"
                type="datetime-local"
                value={schedule.at?.slice(0, 16) ?? ""}
                onChange={(e) =>
                  setForm((v) => ({
                    ...v,
                    schedule: { ...schedule, at: e.target.value },
                  }))
                }
              />
            )}
            <label className="flex items-center gap-2 text-sm">
              <input
                type="checkbox"
                checked={schedule.enabled}
                onChange={(e) =>
                  setForm((v) => ({
                    ...v,
                    schedule: { ...schedule, enabled: e.target.checked },
                  }))
                }
              />
              Schedule enabled
            </label>
          </CardContent>
        </Card>
      )}
      <div className="flex justify-end gap-2">
        <Link to={editing ? `/app/automations/${id}` : "/app/automations"}>
          <Button variant="ghost">Cancel</Button>
        </Link>
        <Button loading={saving} onClick={() => void save()}>
          {editing ? "Save Automation" : "Create Automation"}
        </Button>
      </div>
    </div>
  );
}
