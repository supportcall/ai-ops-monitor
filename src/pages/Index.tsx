import { useState, useCallback } from "react";
import NocHeader from "@/components/NocHeader";
import ProviderTile from "@/components/ProviderTile";
import IncidentFeed from "@/components/IncidentFeed";
import UptimeHeatmap from "@/components/UptimeHeatmap";
import LatencyChart from "@/components/LatencyChart";
import StatusSummary from "@/components/StatusSummary";
import { getProviders, getIncidents, getUptimeHeatmap, getLatencyData } from "@/lib/mock-data";

const Index = () => {
  const [refreshKey, setRefreshKey] = useState(0);
  const [lastChecked, setLastChecked] = useState<Date>(new Date());

  const handleRefresh = useCallback(() => {
    setRefreshKey((k) => k + 1);
    setLastChecked(new Date());
  }, []);

  const providers = getProviders();
  const incidents = getIncidents();
  const globalHeatmap = getUptimeHeatmap("global");
  const globalLatency = getLatencyData("global");

  return (
    <div className="min-h-screen bg-background noc-grid-bg relative" key={refreshKey}>
      {/* Scanline effect */}
      <div className="scanline fixed inset-0 pointer-events-none z-40 h-[200%]" />

      <NocHeader onRefresh={handleRefresh} lastChecked={lastChecked} />

      <main className="container mx-auto px-4 py-6 space-y-6 relative z-10">
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
          <span>Data refreshes every 60s • Mock data for demonstration</span>
        </div>
      </footer>
    </div>
  );
};

export default Index;
