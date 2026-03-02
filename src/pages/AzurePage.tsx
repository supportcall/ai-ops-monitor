import { useNavigate } from "react-router-dom";
import NocHeader from "@/components/NocHeader";
import UptimeHeatmap from "@/components/UptimeHeatmap";
import LatencyChart from "@/components/LatencyChart";
import { getUptimeHeatmap, getLatencyData, getStatusLabel, getStatusBgClass, getStatusColorClass } from "@/lib/mock-data";
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

const azureServices: CloudService[] = [
  { name: "Azure OpenAI Service", slug: "openai", status: "ok", latencyMs: 220, uptimePercent: 99.94, category: "AI/ML", description: "GPT-4, GPT-4o, DALL·E, Whisper" },
  { name: "Azure AI Search", slug: "ai-search", status: "ok", latencyMs: 145, uptimePercent: 99.96, category: "AI/ML", description: "AI-powered search service" },
  { name: "Azure Machine Learning", slug: "ml", status: "degraded", latencyMs: 350, uptimePercent: 99.82, category: "AI/ML", description: "End-to-end ML lifecycle" },
  { name: "Azure Cognitive Services", slug: "cognitive", status: "ok", latencyMs: 175, uptimePercent: 99.93, category: "AI/ML", description: "Vision, Speech, Language, Decision" },
  { name: "Azure Virtual Machines", slug: "vms", status: "ok", latencyMs: 35, uptimePercent: 99.99, category: "Compute", description: "IaaS virtual machines" },
  { name: "Azure Functions", slug: "functions", status: "ok", latencyMs: 48, uptimePercent: 99.98, category: "Compute", description: "Serverless compute" },
  { name: "Azure App Service", slug: "appservice", status: "ok", latencyMs: 62, uptimePercent: 99.97, category: "Compute", description: "Web app hosting" },
  { name: "Azure Blob Storage", slug: "blob", status: "ok", latencyMs: 22, uptimePercent: 99.99, category: "Storage", description: "Object storage" },
  { name: "Azure SQL Database", slug: "sql", status: "ok", latencyMs: 55, uptimePercent: 99.98, category: "Database", description: "Managed SQL database" },
  { name: "Azure Cosmos DB", slug: "cosmos", status: "ok", latencyMs: 8, uptimePercent: 99.999, category: "Database", description: "Globally distributed NoSQL" },
  { name: "Azure Kubernetes Service", slug: "aks", status: "ok", latencyMs: 88, uptimePercent: 99.95, category: "Containers", description: "Managed Kubernetes" },
  { name: "Azure CDN", slug: "cdn", status: "ok", latencyMs: 12, uptimePercent: 99.99, category: "Networking", description: "Content delivery network" },
  { name: "Azure Active Directory", slug: "aad", status: "ok", latencyMs: 42, uptimePercent: 99.99, category: "Identity", description: "Identity & access management" },
  { name: "Azure Monitor", slug: "monitor", status: "ok", latencyMs: 78, uptimePercent: 99.96, category: "Management", description: "Full-stack monitoring" },
];

const regions = [
  { name: "East US", code: "eastus", status: "ok" as ProviderStatus },
  { name: "West US 2", code: "westus2", status: "ok" as ProviderStatus },
  { name: "West Europe", code: "westeurope", status: "ok" as ProviderStatus },
  { name: "North Europe", code: "northeurope", status: "degraded" as ProviderStatus },
  { name: "Southeast Asia", code: "southeastasia", status: "ok" as ProviderStatus },
  { name: "Japan East", code: "japaneast", status: "ok" as ProviderStatus },
  { name: "Australia East", code: "australiaeast", status: "ok" as ProviderStatus },
  { name: "Central India", code: "centralindia", status: "ok" as ProviderStatus },
];

const categoryIcons: Record<string, typeof Server> = {
  "AI/ML": Cpu,
  "Compute": Server,
  "Storage": HardDrive,
  "Database": Database,
  "Containers": Cloud,
  "Networking": Network,
  "Identity": Lock,
  "Management": BarChart3,
};

const AzurePage = () => {
  const navigate = useNavigate();
  const heatmap = getUptimeHeatmap("azure");
  const latency = getLatencyData("azure");

  const categories = [...new Set(azureServices.map(s => s.category))];
  const overallStatus: ProviderStatus = azureServices.some(s => s.status === "outage") ? "outage"
    : azureServices.some(s => s.status === "partial") ? "partial"
    : azureServices.some(s => s.status === "degraded") ? "degraded" : "ok";

  const statusCounts = { ok: 0, degraded: 0, partial: 0, outage: 0 };
  azureServices.forEach(s => { if (s.status in statusCounts) statusCounts[s.status as keyof typeof statusCounts]++; });

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
              <h1 className="text-2xl font-bold text-foreground">Microsoft Azure</h1>
            </div>
            <div className="flex items-center gap-3 text-xs font-mono text-muted-foreground">
              <span className={getStatusColorClass(overallStatus)}>{getStatusLabel(overallStatus)}</span>
              <span>•</span>
              <span className="flex items-center gap-1"><Shield className="h-3 w-3" /> Official: status.azure.com</span>
              <span>•</span>
              <span>{azureServices.length} services monitored</span>
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
          const services = azureServices.filter(s => s.category === cat);
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
          <LatencyChart data={latency} title="Azure Global Latency (24h)" />
          <UptimeHeatmap data={heatmap} label="Azure 90-Day Uptime" />
        </div>
      </main>
    </div>
  );
};

export default AzurePage;
