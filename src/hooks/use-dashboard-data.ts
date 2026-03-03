import { useQuery } from "@tanstack/react-query";
import { isLiveMode } from "@/lib/api-config";
import { fetchDashboardData } from "@/lib/api";
import { getProviders, getIncidents, getUptimeHeatmap, getLatencyData } from "@/lib/mock-data";

export function useDashboardData(providerSlug?: string) {
  return useQuery({
    queryKey: ["dashboard", providerSlug ?? "global"],
    queryFn: async () => {
      if (!isLiveMode()) {
        // Fallback to mock data when no API URL configured
        const providers = getProviders();
        const incidents = getIncidents();
        const globalHeatmap = getUptimeHeatmap("global");
        const globalLatency = getLatencyData("global");

        return {
          providers,
          incidents,
          globalLatency,
          globalHeatmap,
          providerLatency: providerSlug ? getLatencyData(providerSlug) : undefined,
          providerHeatmap: providerSlug ? getUptimeHeatmap(providerSlug) : undefined,
          generatedAt: new Date().toISOString(),
          isLive: false,
        };
      }

      const data = await fetchDashboardData(providerSlug);
      return { ...data, isLive: true };
    },
    refetchInterval: 60_000, // Auto-refresh every 60s
    staleTime: 30_000,
  });
}
