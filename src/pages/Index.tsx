import { useCallback } from "react";
import NocHeader from "@/components/NocHeader";
import ProviderTile from "@/components/ProviderTile";
import IncidentFeed from "@/components/IncidentFeed";
import UptimeHeatmap from "@/components/UptimeHeatmap";
import LatencyChart from "@/components/LatencyChart";
import StatusSummary from "@/components/StatusSummary";
import { useDashboardData } from "@/hooks/use-dashboard-data";
import { Loader2, WifiOff } from "lucide-react";

const Index = () => {
  const { data, isLoading, isError, error, dataUpdatedAt, refetch } = useDashboardData();

  const handleRefresh = useCallback(() => {
    refetch();
  }, [refetch]);

  const lastChecked = dataUpdatedAt ? new Date(dataUpdatedAt) : new Date();

  if (isLoading) {
    return (
      <div className="min-h-screen bg-background flex items-center justify-center">
        <div className="flex items-center gap-3 text-muted-foreground font-mono text-sm">
          <Loader2 className="h-5 w-5 animate-spin" />
          <span>Fetching provider status…</span>
        </div>
      </div>
    );
  }

  if (isError || !data) {
    return (
      <div className="min-h-screen bg-background flex items-center justify-center">
        <div className="text-center space-y-3">
          <WifiOff className="h-8 w-8 text-outage mx-auto" />
          <p className="text-sm font-mono text-muted-foreground">Failed to load status data</p>
          <p className="text-xs font-mono text-muted-foreground/60">{(error as Error)?.message}</p>
          <button onClick={() => refetch()} className="text-xs font-mono text-primary hover:underline">
            Retry
          </button>
        </div>
      </div>
    );
  }

  const { providers, incidents, globalLatency, globalHeatmap, isLive } = data;

  return (
    <div className="min-h-screen bg-background noc-grid-bg relative">
      {/* Scanline effect */}
      <div className="scanline fixed inset-0 pointer-events-none z-40 h-[200%]" />

      <NocHeader onRefresh={handleRefresh} lastChecked={lastChecked} />

      <main className="container mx-auto px-4 py-6 space-y-6 relative z-10">
        {/* Data source indicator */}
        <div className="flex items-center gap-2 text-[10px] font-mono text-muted-foreground">
          <span className={`h-1.5 w-1.5 rounded-full ${isLive ? "bg-success" : "bg-warning"}`} />
          <span>{isLive ? "Live data from PHP backend" : "Demo mode — set VITE_API_BASE_URL for live data"}</span>
        </div>

        {/* Status summary */}
        <StatusSummary providers={providers} />

        {/* Provider grid */}
        <section>
          <h2 className="text-xs font-mono font-semibold text-muted-foreground uppercase tracking-widest mb-3">
            Provider Status
          </h2>
          <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3">
            {providers.map((provider, i) => (
              <ProviderTile key={provider.id} provider={provider} index={i} />
            ))}
          </div>
        </section>

        {/* Charts + Incidents */}
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          <div className="lg:col-span-2 space-y-4">
            <LatencyChart data={globalLatency} title="Global Avg Latency (24h)" />
            <UptimeHeatmap data={globalHeatmap} label="Global 90-Day Uptime" />
          </div>
          <div className="lg:col-span-1">
            <IncidentFeed incidents={incidents} />
          </div>
        </div>
      </main>

      {/* Footer */}
      <footer className="border-t border-border py-4 mt-8">
        <div className="container mx-auto px-4 flex items-center justify-between text-[10px] font-mono text-muted-foreground">
          <span>AI-NOC v1.0 — Network Operations Center</span>
          <span>Auto-refresh every 60s • {isLive ? "Live data" : "Mock data for demonstration"}</span>
        </div>
      </footer>
    </div>
  );
};

export default Index;
