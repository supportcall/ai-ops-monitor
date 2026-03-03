import { Provider, ProviderStatus } from "@/lib/types";
import { getStatusLabel, getStatusBgClass } from "@/lib/status-utils";
import { ArrowRight, Wifi, WifiOff, Clock, TrendingUp } from "lucide-react";
import { useNavigate } from "react-router-dom";

interface ProviderTileProps {
  provider: Provider;
  index: number;
}

const statusDotClass: Record<ProviderStatus, string> = {
  ok: "bg-success noc-glow",
  degraded: "bg-degraded",
  partial: "bg-warning",
  outage: "bg-outage noc-glow-red status-pulse",
  unknown: "bg-unknown",
};

const tileBorderClass: Record<ProviderStatus, string> = {
  ok: "border-success/20 hover:border-success/40",
  degraded: "border-degraded/30 hover:border-degraded/50",
  partial: "border-warning/30 hover:border-warning/50",
  outage: "border-outage/40 hover:border-outage/60",
  unknown: "border-border hover:border-muted-foreground/30",
};

const cloudPageRoutes: Record<string, string> = {
  "aws-bedrock": "/cloud/aws",
  "azure-openai": "/cloud/azure",
  "google-gemini": "/cloud/google",
};

const ProviderTile = ({ provider, index }: ProviderTileProps) => {
  const navigate = useNavigate();
  const timeSinceCheck = Math.floor((Date.now() - provider.lastCheck.getTime()) / 1000);
  const timeStr = timeSinceCheck < 60 ? `${timeSinceCheck}s ago` : `${Math.floor(timeSinceCheck / 60)}m ago`;
  const targetRoute = cloudPageRoutes[provider.slug] || `/provider/${provider.slug}`;

  return (
    <button
      onClick={() => navigate(targetRoute)}
      className={`group relative bg-card border ${tileBorderClass[provider.status]} rounded-lg p-4 text-left transition-all duration-300 hover:bg-secondary/50 animate-fade-in-up`}
      style={{ animationDelay: `${index * 40}ms` }}
    >
      {/* Status indicator line */}
      <div className={`absolute top-0 left-0 right-0 h-0.5 rounded-t-lg ${getStatusBgClass(provider.status)} opacity-60`} />

      <div className="flex items-start justify-between mb-3">
        <div className="flex items-center gap-2">
          <span className={`h-2.5 w-2.5 rounded-full ${statusDotClass[provider.status]}`} />
          <h3 className="font-semibold text-sm text-foreground">{provider.name}</h3>
        </div>
        {provider.status === "ok" ? (
          <Wifi className="h-3.5 w-3.5 text-success opacity-50" />
        ) : provider.status === "outage" ? (
          <WifiOff className="h-3.5 w-3.5 text-outage" />
        ) : null}
      </div>

      <div className="space-y-2">
        <div className="flex items-center justify-between">
          <span className={`text-xs font-mono font-medium ${getStatusBgClass(provider.status)} bg-opacity-10 px-2 py-0.5 rounded ${provider.status === 'ok' ? 'text-success' : provider.status === 'degraded' ? 'text-degraded' : provider.status === 'outage' ? 'text-outage' : 'text-warning'}`}>
            {getStatusLabel(provider.status)}
          </span>
          {!provider.isOfficial && (
            <span className="text-[9px] font-mono text-muted-foreground uppercase">Unofficial</span>
          )}
        </div>

        <div className="grid grid-cols-2 gap-2 pt-1">
          <div className="flex items-center gap-1 text-[11px] text-muted-foreground font-mono">
            <TrendingUp className="h-3 w-3" />
            <span>{provider.latencyMs > 0 ? `${provider.latencyMs}ms` : "—"}</span>
          </div>
          <div className="flex items-center gap-1 text-[11px] text-muted-foreground font-mono">
            <Clock className="h-3 w-3" />
            <span>{timeStr}</span>
          </div>
        </div>

        <div className="flex items-center justify-between pt-1 border-t border-border/50">
          <span className="text-[11px] font-mono text-muted-foreground">
            {provider.uptimePercent}% uptime
          </span>
          <ArrowRight className="h-3 w-3 text-muted-foreground opacity-0 group-hover:opacity-100 transition-opacity" />
        </div>
      </div>
    </button>
  );
};

export default ProviderTile;
