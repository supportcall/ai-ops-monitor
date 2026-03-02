export type ProviderStatus = "ok" | "degraded" | "partial" | "outage" | "unknown";

export interface Provider {
  id: string;
  name: string;
  slug: string;
  logo?: string;
  enabled: boolean;
  officialStatusUrl: string | null;
  status: ProviderStatus;
  lastCheck: Date;
  latencyMs: number;
  uptimePercent: number;
  incidentCount: number;
  endpoints: Endpoint[];
  isOfficial: boolean;
}

export interface Endpoint {
  id: string;
  providerId: string;
  name: string;
  url: string;
  type: "http" | "tls" | "dns";
  enabled: boolean;
}

export interface Check {
  id: string;
  endpointId: string;
  ts: Date;
  success: boolean;
  httpCode: number;
  dnsMs: number;
  tlsMs: number;
  ttfbMs: number;
  totalMs: number;
}

export interface Incident {
  id: string;
  providerId: string;
  providerName: string;
  startTs: Date;
  endTs: Date | null;
  severity: ProviderStatus;
  title: string;
  description: string;
  source: "official" | "synthetic";
}

export interface UptimeDay {
  date: string;
  status: ProviderStatus;
  uptimePercent: number;
}

export interface LatencyPoint {
  ts: string;
  p50: number;
  p95: number;
}
