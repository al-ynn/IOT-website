import { execFileSync } from "node:child_process";

export function databaseRow(databasePath: string, sql: string, bindings: unknown[] = []) {
  const script = [
    '$pdo = new PDO("sqlite:".$argv[1]);',
    '$statement = $pdo->prepare($argv[2]);',
    '$statement->execute(json_decode($argv[3], true));',
    '$row = $statement->fetch(PDO::FETCH_ASSOC);',
    'echo json_encode($row === false ? null : $row, JSON_THROW_ON_ERROR);',
  ].join("");
  const output = execFileSync("php", ["-r", script, databasePath, sql, JSON.stringify(bindings)], { encoding: "utf8" });
  return JSON.parse(output) as Record<string, string | number | null> | null;
}
