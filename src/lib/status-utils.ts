import { ProviderStatus } from "./types";

export function getStatusLabel(status: ProviderStatus): string {
  const map: Record<ProviderStatus, string> = {
    ok: "Operational",
    degraded: "Degraded",
    partial: "Partial Outage",
    outage: "Major Outage",
    unknown: "Unknown",
  };
  return map[status];
}

export function getStatusColorClass(status: ProviderStatus): string {
  const map: Record<ProviderStatus, string> = {
    ok: "text-success",
    degraded: "text-degraded",
    partial: "text-warning",
    outage: "text-outage",
    unknown: "text-unknown",
  };
  return map[status];
}

export function getStatusBgClass(status: ProviderStatus): string {
  const map: Record<ProviderStatus, string> = {
    ok: "bg-success",
    degraded: "bg-degraded",
    partial: "bg-warning",
    outage: "bg-outage",
    unknown: "bg-unknown",
  };
  return map[status];
}
