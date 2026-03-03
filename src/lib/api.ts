import { API_BASE_URL, isLiveMode } from "./api-config";
import { Provider, Incident, LatencyPoint, UptimeDay, ProviderStatus } from "./types";

interface ApiResponse {
  generated_at: string;
  version: string;
  providers: ApiProvider[];
  incidents: ApiIncident[];
  global_latency: LatencyPoint[];
  global_heatmap: ApiUptimeDay[];
  provider_latency?: LatencyPoint[];
  provider_heatmap?: ApiUptimeDay[];
}

interface ApiProvider {
  id: string;
  name: string;
  slug: string;
  status: ProviderStatus;
  source: string;
  last_checked: string | null;
  latency_ms: number | null;
  uptime_percent: number | null;
  open_incidents: number;
  official_status_url: string | null;
  is_official: boolean;
  endpoints: { id: string; name: string; url: string; type: "http" | "tls" | "dns"; enabled: boolean }[];
}

interface ApiIncident {
  id: string;
  provider_id: string;
  provider_name: string;
  provider_slug: string;
  start_ts: string;
  end_ts: string | null;
  severity: ProviderStatus;
  title: string;
  description: string;
  source: "official" | "synthetic";
}

interface ApiUptimeDay {
  date: string;
  status: ProviderStatus;
  uptime_percent: number;
}

function mapProvider(p: ApiProvider): Provider {
  return {
    id: p.id,
    name: p.name,
    slug: p.slug,
    enabled: true,
    officialStatusUrl: p.official_status_url,
    status: p.status,
    lastCheck: p.last_checked ? new Date(p.last_checked) : new Date(),
    latencyMs: p.latency_ms ?? 0,
    uptimePercent: p.uptime_percent ?? 0,
    incidentCount: p.open_incidents,
    isOfficial: p.is_official,
    endpoints: p.endpoints.map(ep => ({
      ...ep,
      providerId: p.id,
    })),
  };
}

function mapIncident(i: ApiIncident): Incident {
  return {
    id: i.id,
    providerId: i.provider_id,
    providerName: i.provider_name,
    startTs: new Date(i.start_ts),
    endTs: i.end_ts ? new Date(i.end_ts) : null,
    severity: i.severity,
    title: i.title,
    description: i.description,
    source: i.source,
  };
}

function mapUptimeDay(d: ApiUptimeDay): UptimeDay {
  return {
    date: d.date,
    status: d.status,
    uptimePercent: d.uptime_percent,
  };
}

export async function fetchDashboardData(providerSlug?: string): Promise<{
  providers: Provider[];
  incidents: Incident[];
  globalLatency: LatencyPoint[];
  globalHeatmap: UptimeDay[];
  providerLatency?: LatencyPoint[];
  providerHeatmap?: UptimeDay[];
  generatedAt: string;
}> {
  const url = new URL(`${API_BASE_URL}/status.php`);
  if (providerSlug) {
    url.searchParams.set("provider", providerSlug);
  }

  const res = await fetch(url.toString());
  if (!res.ok) throw new Error(`API error: ${res.status}`);
  
  const data: ApiResponse = await res.json();

  return {
    providers: data.providers.map(mapProvider),
    incidents: data.incidents.map(mapIncident),
    globalLatency: data.global_latency,
    globalHeatmap: data.global_heatmap.map(mapUptimeDay),
    providerLatency: data.provider_latency,
    providerHeatmap: data.provider_heatmap?.map(mapUptimeDay),
    generatedAt: data.generated_at,
  };
}
