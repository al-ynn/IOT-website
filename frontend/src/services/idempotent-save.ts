const pending = new Map<string, string>();

export function beginIdempotentSave(scope: string, payload: unknown) {
  const command = `${scope}:${JSON.stringify(payload)}`;
  const key = pending.get(command) ?? crypto.randomUUID();
  pending.set(command, key);
  return { key, complete: () => pending.delete(command) };
}
