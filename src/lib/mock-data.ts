import { Provider, Incident, UptimeDay, LatencyPoint, ProviderStatus } from "./types";

const randomBetween = (min: number, max: number) => Math.floor(Math.random() * (max - min + 1)) + min;

const statuses: ProviderStatus[] = ["ok", "ok", "ok", "ok", "ok", "ok", "ok", "ok", "degraded", "partial", "outage", "unknown"];
const pickStatus = (): ProviderStatus => statuses[Math.floor(Math.random() * statuses.length)];

const providerDefs = [
  { id: "openai", name: "OpenAI", slug: "openai", officialStatusUrl: "https://status.openai.com", isOfficial: true },
  { id: "anthropic", name: "Anthropic", slug: "anthropic", officialStatusUrl: "https://status.claude.com", isOfficial: true },
  { id: "google", name: "Google Gemini", slug: "google-gemini", officialStatusUrl: "https://status.cloud.google.com", isOfficial: true },
  { id: "azure", name: "Azure OpenAI", slug: "azure-openai", officialStatusUrl: "https://azure.status.microsoft/en-us/status/", isOfficial: true },
  { id: "aws", name: "AWS Bedrock", slug: "aws-bedrock", officialStatusUrl: "https://health.aws.amazon.com", isOfficial: true },
  { id: "cohere", name: "Cohere", slug: "cohere", officialStatusUrl: "https://status.cohere.com", isOfficial: true },
  { id: "mistral", name: "Mistral AI", slug: "mistral", officialStatusUrl: "https://status.mistral.ai", isOfficial: true },
  { id: "groq", name: "Groq", slug: "groq", officialStatusUrl: "https://status.groq.com", isOfficial: true },
  { id: "perplexity", name: "Perplexity", slug: "perplexity", officialStatusUrl: "https://status.perplexity.ai", isOfficial: true },
  { id: "xai", name: "xAI (Grok)", slug: "xai", officialStatusUrl: "https://status.x.ai", isOfficial: true },
  { id: "huggingface", name: "Hugging Face", slug: "huggingface", officialStatusUrl: "https://status.huggingface.co", isOfficial: true },
  { id: "replicate", name: "Replicate", slug: "replicate", officialStatusUrl: "https://status.replicate.com", isOfficial: true },
  { id: "stability", name: "Stability AI", slug: "stability", officialStatusUrl: "https://status.stability.ai", isOfficial: true },
  { id: "together", name: "Together AI", slug: "together", officialStatusUrl: "https://status.together.ai", isOfficial: true },
  { id: "deepseek", name: "DeepSeek", slug: "deepseek", officialStatusUrl: "https://status.deepseek.com", isOfficial: true },
  { id: "meta", name: "Meta Llama (via API)", slug: "meta-llama", officialStatusUrl: null, isOfficial: false },
  { id: "ai21", name: "AI21 Labs", slug: "ai21", officialStatusUrl: "https://status.ai21.com", isOfficial: true },
  { id: "fireworks", name: "Fireworks AI", slug: "fireworks", officialStatusUrl: "https://status.fireworks.ai", isOfficial: true },
  { id: "anyscale", name: "Anyscale", slug: "anyscale", officialStatusUrl: null, isOfficial: false },
  { id: "databricks", name: "Databricks DBRX", slug: "databricks", officialStatusUrl: "https://status.databricks.com", isOfficial: true },
  { id: "claude-code", name: "Claude Code", slug: "claude-code", officialStatusUrl: "https://status.claude.com", isOfficial: true },
];

// Deterministic seed for consistent mock data
let seed = 42;
const seededRandom = () => {
  seed = (seed * 16807) % 2147483647;
  return (seed - 1) / 2147483646;
};

export function getProviders(): Provider[] {
  return providerDefs.map((def, i) => {
    const status = i < 8 ? "ok" : pickStatus();
    // Force a couple non-ok for visual interest
    const overrides: Record<string, ProviderStatus> = {
      "groq": "degraded",
      "stability": "outage",
      "deepseek": "degraded",
      "anyscale": "partial",
    };
    const finalStatus = overrides[def.id] || status;
    
    return {
      ...def,
      enabled: true,
      status: finalStatus,
      lastCheck: new Date(Date.now() - randomBetween(10, 300) * 1000),
      latencyMs: finalStatus === "outage" ? 0 : randomBetween(80, 450),
      uptimePercent: finalStatus === "outage" ? 94.2 : finalStatus === "degraded" ? 97.8 : parseFloat((99 + Math.random()).toFixed(2)),
      incidentCount: finalStatus === "ok" ? 0 : randomBetween(1, 5),
      endpoints: [
        { id: `${def.id}-1`, providerId: def.id, name: "API Health", url: `https://api.${def.slug}.com/health`, type: "http" as const, enabled: true },
        { id: `${def.id}-2`, providerId: def.id, name: "Homepage", url: `https://${def.slug}.com`, type: "http" as const, enabled: true },
      ],
      logo: undefined,
    };
  });
}

export function getIncidents(): Incident[] {
  const now = Date.now();
  return [
    {
      id: "inc-1", providerId: "stability", providerName: "Stability AI",
      startTs: new Date(now - 3600000), endTs: null,
      severity: "outage", title: "API endpoints unreachable",
      description: "All synthetic checks failing with connection timeouts. No official status available.",
      source: "synthetic",
    },
    {
      id: "inc-2", providerId: "groq", providerName: "Groq",
      startTs: new Date(now - 7200000), endTs: null,
      severity: "degraded", title: "Elevated latency on inference endpoints",
      description: "P95 latency exceeded threshold for 5 consecutive checks.",
      source: "synthetic",
    },
    {
      id: "inc-3", providerId: "openai", providerName: "OpenAI",
      startTs: new Date(now - 86400000), endTs: new Date(now - 82800000),
      severity: "partial", title: "Partial API degradation",
      description: "Official status reported partial outage affecting GPT-4 endpoints. Resolved after 1 hour.",
      source: "official",
    },
    {
      id: "inc-4", providerId: "anthropic", providerName: "Anthropic",
      startTs: new Date(now - 172800000), endTs: new Date(now - 169200000),
      severity: "degraded", title: "Increased error rates",
      description: "Higher than normal 500 error rates detected via synthetic checks.",
      source: "synthetic",
    },
    {
      id: "inc-5", providerId: "azure", providerName: "Azure OpenAI",
      startTs: new Date(now - 259200000), endTs: new Date(now - 255600000),
      severity: "outage", title: "Regional outage - East US",
      description: "Official status: Major outage affecting Azure OpenAI in East US region.",
      source: "official",
    },
  ];
}

export function getUptimeHeatmap(providerId: string): UptimeDay[] {
  const days: UptimeDay[] = [];
  const now = new Date();
  for (let i = 89; i >= 0; i--) {
    const date = new Date(now);
    date.setDate(date.getDate() - i);
    const rand = seededRandom();
    let status: ProviderStatus = "ok";
    let uptime = 99.9 + seededRandom() * 0.1;
    if (rand < 0.03) { status = "outage"; uptime = 90 + seededRandom() * 5; }
    else if (rand < 0.08) { status = "degraded"; uptime = 97 + seededRandom() * 2; }
    else if (rand < 0.12) { status = "partial"; uptime = 98 + seededRandom() * 1.5; }
    days.push({
      date: date.toISOString().split("T")[0],
      status,
      uptimePercent: parseFloat(uptime.toFixed(2)),
    });
  }
  return days;
}

export function getLatencyData(providerId: string): LatencyPoint[] {
  const points: LatencyPoint[] = [];
  const now = Date.now();
  for (let i = 47; i >= 0; i--) {
    const ts = new Date(now - i * 1800000);
    const base = 120 + seededRandom() * 200;
    points.push({
      ts: ts.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" }),
      p50: Math.round(base),
      p95: Math.round(base * (1.3 + seededRandom() * 0.7)),
    });
  }
  return points;
}

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
