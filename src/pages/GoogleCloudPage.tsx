import { useNavigate } from "react-router-dom";
import NocHeader from "@/components/NocHeader";
import UptimeHeatmap from "@/components/UptimeHeatmap";
import LatencyChart from "@/components/LatencyChart";
import { getStatusLabel, getStatusBgClass, getStatusColorClass } from "@/lib/status-utils";
import { useDashboardData } from "@/hooks/use-dashboard-data";
import { ArrowLeft, Globe, Shield, Server, Database, Cpu, HardDrive, Network, Cloud, Lock, BarChart3 } from "lucide-react";
import { ProviderStatus } from "@/lib/types";

interface CloudService {
  name: string;
  slug: string;
  status: ProviderStatus;
  latencyMs: number;
  uptimePercent: number;
  category: string;
  description: string;
}

const gcpServices: CloudService[] = [
  { name: "Vertex AI", slug: "vertex", status: "ok", latencyMs: 195, uptimePercent: 99.94, category: "AI/ML", description: "ML platform with Gemini models" },
  { name: "Gemini API", slug: "gemini", status: "ok", latencyMs: 165, uptimePercent: 99.96, category: "AI/ML", description: "Generative AI API" },
  { name: "AI Studio", slug: "ai-studio", status: "ok", latencyMs: 140, uptimePercent: 99.95, category: "AI/ML", description: "Prototyping with Gemini" },
  { name: "Cloud Natural Language", slug: "nlp", status: "ok", latencyMs: 120, uptimePercent: 99.97, category: "AI/ML", description: "Text analysis & NLP" },
  { name: "Cloud Vision AI", slug: "vision", status: "ok", latencyMs: 155, uptimePercent: 99.93, category: "AI/ML", description: "Image analysis" },
  { name: "Compute Engine", slug: "compute", status: "ok", latencyMs: 30, uptimePercent: 99.99, category: "Compute", description: "Virtual machines" },
  { name: "Cloud Run", slug: "run", status: "ok", latencyMs: 42, uptimePercent: 99.98, category: "Compute", description: "Serverless containers" },
  { name: "Cloud Functions", slug: "functions", status: "ok", latencyMs: 50, uptimePercent: 99.97, category: "Compute", description: "Event-driven serverless" },
  { name: "Google Kubernetes Engine", slug: "gke", status: "ok", latencyMs: 75, uptimePercent: 99.95, category: "Containers", description: "Managed Kubernetes" },
  { name: "Cloud Storage", slug: "gcs", status: "ok", latencyMs: 18, uptimePercent: 99.999, category: "Storage", description: "Object storage" },
  { name: "Cloud SQL", slug: "cloudsql", status: "ok", latencyMs: 48, uptimePercent: 99.97, category: "Database", description: "Managed MySQL/PostgreSQL" },
  { name: "BigQuery", slug: "bigquery", status: "ok", latencyMs: 320, uptimePercent: 99.96, category: "Database", description: "Serverless data warehouse" },
  { name: "Cloud Spanner", slug: "spanner", status: "ok", latencyMs: 15, uptimePercent: 99.999, category: "Database", description: "Globally distributed SQL" },
  { name: "Firestore", slug: "firestore", status: "degraded", latencyMs: 280, uptimePercent: 99.88, category: "Database", description: "NoSQL document database" },
  { name: "Cloud CDN", slug: "cdn", status: "ok", latencyMs: 10, uptimePercent: 99.99, category: "Networking", description: "Content delivery" },
  { name: "Cloud Load Balancing", slug: "lb", status: "ok", latencyMs: 8, uptimePercent: 99.99, category: "Networking", description: "Global load balancing" },
];

const regions = [
  { name: "US Central (Iowa)", code: "us-central1", status: "ok" as ProviderStatus },
  { name: "US East (S. Carolina)", code: "us-east1", status: "ok" as ProviderStatus },
  { name: "Europe West (Belgium)", code: "europe-west1", status: "ok" as ProviderStatus },
  { name: "Europe West (London)", code: "europe-west2", status: "ok" as ProviderStatus },
  { name: "Asia East (Taiwan)", code: "asia-east1", status: "ok" as ProviderStatus },
  { name: "Asia NE (Tokyo)", code: "asia-northeast1", status: "ok" as ProviderStatus },
  { name: "Australia SE (Sydney)", code: "australia-southeast1", status: "degraded" as ProviderStatus },
  { name: "South America (São Paulo)", code: "southamerica-east1", status: "ok" as ProviderStatus },
];

const categoryIcons: Record<string, typeof Server> = {
  "AI/ML": Cpu,
  "Compute": Server,
  "Storage": HardDrive,
  "Database": Database,
  "Containers": Cloud,
  "Networking": Network,
};

const GoogleCloudPage = () => {
  const navigate = useNavigate();
  const { data } = useDashboardData("google-gemini");
  const heatmap = data?.providerHeatmap ?? data?.globalHeatmap ?? [];
  const latency = data?.providerLatency ?? data?.globalLatency ?? [];

  const categories = [...new Set(gcpServices.map(s => s.category))];
  const overallStatus: ProviderStatus = gcpServices.some(s => s.status === "outage") ? "outage"
    : gcpServices.some(s => s.status === "partial") ? "partial"
    : gcpServices.some(s => s.status === "degraded") ? "degraded" : "ok";

  const statusCounts = { ok: 0, degraded: 0, partial: 0, outage: 0 };
  gcpServices.forEach(s => { if (s.status in statusCounts) statusCounts[s.status as keyof typeof statusCounts]++; });

  return (
    <div className="min-h-screen bg-background noc-grid-bg relative">
      <div className="scanline fixed inset-0 pointer-events-none z-40 h-[200%]" />
      <NocHeader />

      <main className="container mx-auto px-4 py-6 space-y-6 relative z-10">
        <button onClick={() => navigate("/")} className="flex items-center gap-2 text-xs font-mono text-muted-foreground hover:text-foreground transition-colors">
          <ArrowLeft className="h-3.5 w-3.5" /> Back to Dashboard
        </button>

        <div className="flex items-start justify-between flex-wrap gap-4">
          <div>
            <div className="flex items-center gap-3 mb-1">
              <span className={`h-3 w-3 rounded-full ${getStatusBgClass(overallStatus)}`} />
              <h1 className="text-2xl font-bold text-foreground">Google Cloud Platform</h1>
            </div>
            <div className="flex items-center gap-3 text-xs font-mono text-muted-foreground">
              <span className={getStatusColorClass(overallStatus)}>{getStatusLabel(overallStatus)}</span>
              <span>•</span>
              <span className="flex items-center gap-1"><Shield className="h-3 w-3" /> Official: status.cloud.google.com</span>
              <span>•</span>
              <span>{gcpServices.length} services monitored</span>
            </div>
          </div>
          <div className="flex gap-3">
            {Object.entries(statusCounts).filter(([, v]) => v > 0).map(([status, count]) => (
              <div key={status} className="bg-card border border-border rounded-lg px-4 py-3 text-center min-w-[80px]">
                <div className={`text-lg font-bold font-mono ${getStatusColorClass(status as ProviderStatus)}`}>{count}</div>
                <div className="text-[10px] font-mono text-muted-foreground uppercase">{getStatusLabel(status as ProviderStatus)}</div>
              </div>
            ))}
          </div>
        </div>

        {/* Regions */}
        <section>
          <h2 className="text-xs font-mono font-semibold text-muted-foreground uppercase tracking-widest mb-3 flex items-center gap-2">
            <Globe className="h-3.5 w-3.5" /> Region Status
          </h2>
          <div className="grid grid-cols-2 sm:grid-cols-4 gap-2">
            {regions.map(r => (
              <div key={r.code} className="bg-card border border-border rounded-lg px-3 py-2.5 flex items-center gap-2.5">
                <span className={`h-2 w-2 rounded-full flex-shrink-0 ${getStatusBgClass(r.status)}`} />
                <div className="min-w-0">
                  <div className="text-xs font-medium text-foreground truncate">{r.name}</div>
                  <div className="text-[10px] font-mono text-muted-foreground">{r.code}</div>
                </div>
              </div>
            ))}
          </div>
        </section>

        {/* Services by Category */}
        {categories.map(cat => {
          const Icon = categoryIcons[cat] || Server;
          const services = gcpServices.filter(s => s.category === cat);
          return (
            <section key={cat}>
              <h2 className="text-xs font-mono font-semibold text-muted-foreground uppercase tracking-widest mb-3 flex items-center gap-2">
                <Icon className="h-3.5 w-3.5" /> {cat}
              </h2>
              <div className="bg-card border border-border rounded-lg overflow-hidden divide-y divide-border/50">
                {services.map(svc => (
                  <div key={svc.slug} className="px-4 py-3 flex items-center justify-between">
                    <div className="flex items-center gap-3">
                      <span className={`h-2 w-2 rounded-full ${getStatusBgClass(svc.status)}`} />
                      <div>
                        <span className="text-sm font-medium text-foreground">{svc.name}</span>
                        <p className="text-[11px] text-muted-foreground">{svc.description}</p>
                      </div>
                    </div>
                    <div className="flex items-center gap-6 text-right">
                      <div>
                        <div className="text-xs font-mono text-foreground">{svc.latencyMs}ms</div>
                        <div className="text-[10px] font-mono text-muted-foreground">latency</div>
                      </div>
                      <div>
                        <div className="text-xs font-mono text-foreground">{svc.uptimePercent}%</div>
                        <div className="text-[10px] font-mono text-muted-foreground">uptime</div>
                      </div>
                      <span className={`text-xs font-mono ${getStatusColorClass(svc.status)}`}>{getStatusLabel(svc.status)}</span>
                    </div>
                  </div>
                ))}
              </div>
            </section>
          );
        })}

        <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
          <LatencyChart data={latency} title="GCP Global Latency (24h)" />
          <UptimeHeatmap data={heatmap} label="GCP 90-Day Uptime" />
        </div>
      </main>
    </div>
  );
};

export default GoogleCloudPage;
