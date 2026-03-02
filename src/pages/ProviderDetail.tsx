import { useParams, useNavigate } from "react-router-dom";
import NocHeader from "@/components/NocHeader";
import UptimeHeatmap from "@/components/UptimeHeatmap";
import LatencyChart from "@/components/LatencyChart";
import { getProviders, getIncidents, getUptimeHeatmap, getLatencyData, getStatusLabel, getStatusBgClass, getStatusColorClass } from "@/lib/mock-data";
import { ArrowLeft, Globe, Shield, Clock, TrendingUp, Activity } from "lucide-react";
import { formatDistanceToNow } from "date-fns";

const ProviderDetail = () => {
  const { slug } = useParams();
  const navigate = useNavigate();
  const providers = getProviders();
  const provider = providers.find(p => p.slug === slug);

  if (!provider) {
    return (
      <div className="min-h-screen bg-background flex items-center justify-center">
        <p className="text-muted-foreground font-mono">Provider not found</p>
      </div>
    );
  }

  const heatmap = getUptimeHeatmap(provider.id);
  const latency = getLatencyData(provider.id);
  const incidents = getIncidents().filter(i => i.providerId === provider.id);

  return (
    <div className="min-h-screen bg-background noc-grid-bg relative">
      <div className="scanline fixed inset-0 pointer-events-none z-40 h-[200%]" />
      <NocHeader />

      <main className="container mx-auto px-4 py-6 space-y-6 relative z-10">
        {/* Back + Header */}
        <div>
          <button onClick={() => navigate("/")} className="flex items-center gap-2 text-xs font-mono text-muted-foreground hover:text-foreground transition-colors mb-4">
            <ArrowLeft className="h-3.5 w-3.5" /> Back to Dashboard
          </button>

          <div className="flex items-start justify-between flex-wrap gap-4">
            <div>
              <div className="flex items-center gap-3 mb-1">
                <span className={`h-3 w-3 rounded-full ${getStatusBgClass(provider.status)} ${provider.status === 'outage' ? 'status-pulse' : ''}`} />
                <h1 className="text-2xl font-bold text-foreground">{provider.name}</h1>
              </div>
              <div className="flex items-center gap-3 text-xs font-mono text-muted-foreground">
                <span className={getStatusColorClass(provider.status)}>{getStatusLabel(provider.status)}</span>
                <span>•</span>
                {provider.isOfficial ? (
                  <span className="flex items-center gap-1"><Shield className="h-3 w-3" /> Official feed</span>
                ) : (
                  <span className="flex items-center gap-1"><Activity className="h-3 w-3" /> Synthetic only</span>
                )}
              </div>
            </div>

            {/* Stats */}
            <div className="flex gap-4">
              {[
                { label: "Uptime", value: `${provider.uptimePercent}%`, icon: Globe },
                { label: "Latency", value: provider.latencyMs > 0 ? `${provider.latencyMs}ms` : "—", icon: TrendingUp },
                { label: "Last Check", value: formatDistanceToNow(provider.lastCheck, { addSuffix: true }), icon: Clock },
              ].map(stat => (
                <div key={stat.label} className="bg-card border border-border rounded-lg px-4 py-3 min-w-[120px]">
                  <div className="flex items-center gap-1.5 text-[10px] font-mono text-muted-foreground uppercase mb-1">
                    <stat.icon className="h-3 w-3" /> {stat.label}
                  </div>
                  <div className="text-lg font-bold font-mono text-foreground">{stat.value}</div>
                </div>
              ))}
            </div>
          </div>
        </div>

        {/* Charts */}
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
          <LatencyChart data={latency} title={`${provider.name} Latency (24h)`} />
          <UptimeHeatmap data={heatmap} label={`${provider.name} 90-Day Uptime`} />
        </div>

        {/* Endpoints */}
        <div className="bg-card border border-border rounded-lg overflow-hidden">
          <div className="px-4 py-3 border-b border-border">
            <h2 className="text-xs font-mono font-semibold text-foreground">Monitored Endpoints</h2>
          </div>
          <div className="divide-y divide-border/50">
            {provider.endpoints.map(ep => (
              <div key={ep.id} className="px-4 py-3 flex items-center justify-between">
                <div>
                  <span className="text-sm font-medium text-foreground">{ep.name}</span>
                  <p className="text-[11px] font-mono text-muted-foreground">{ep.url}</p>
                </div>
                <div className="flex items-center gap-2">
                  <span className="text-[10px] font-mono text-muted-foreground uppercase">{ep.type}</span>
                  <span className="h-2 w-2 rounded-full bg-success" />
                </div>
              </div>
            ))}
          </div>
        </div>

        {/* Incidents */}
        {incidents.length > 0 && (
          <div className="bg-card border border-border rounded-lg overflow-hidden">
            <div className="px-4 py-3 border-b border-border">
              <h2 className="text-xs font-mono font-semibold text-foreground">Recent Incidents</h2>
            </div>
            <div className="divide-y divide-border/50">
              {incidents.map(inc => (
                <div key={inc.id} className="px-4 py-3">
                  <div className="flex items-center gap-2 mb-1">
                    <span className={`h-2 w-2 rounded-full ${getStatusBgClass(inc.severity)}`} />
                    <span className="text-sm font-medium text-foreground">{inc.title}</span>
                    {inc.endTs && <span className="text-[10px] font-mono text-success">Resolved</span>}
                  </div>
                  <p className="text-[11px] text-muted-foreground">{inc.description}</p>
                  <span className="text-[10px] font-mono text-muted-foreground mt-1 block">
                    {formatDistanceToNow(inc.startTs, { addSuffix: true })}
                  </span>
                </div>
              ))}
            </div>
          </div>
        )}
      </main>
    </div>
  );
};

export default ProviderDetail;
