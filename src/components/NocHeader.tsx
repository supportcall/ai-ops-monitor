import { Activity, Shield, Clock, RefreshCw } from "lucide-react";
import { useState, useEffect, useCallback } from "react";

interface NocHeaderProps {
  onRefresh?: () => void;
  lastChecked?: Date;
}

const NocHeader = ({ onRefresh, lastChecked }: NocHeaderProps) => {
  const [now, setNow] = useState(new Date());
  const [isRefreshing, setIsRefreshing] = useState(false);

  useEffect(() => {
    const timer = setInterval(() => setNow(new Date()), 1000);
    return () => clearInterval(timer);
  }, []);

  const handleRefresh = useCallback(() => {
    setIsRefreshing(true);
    onRefresh?.();
    setTimeout(() => setIsRefreshing(false), 1000);
  }, [onRefresh]);

  const lastCheckedStr = lastChecked
    ? lastChecked.toLocaleTimeString()
    : "—";

  return (
    <header className="border-b border-border bg-card/50 backdrop-blur-sm sticky top-0 z-50">
      <div className="container mx-auto px-4 py-3 flex items-center justify-between">
        <div className="flex items-center gap-3">
          <div className="relative">
            <Activity className="h-7 w-7 text-primary" />
            <span className="absolute -top-0.5 -right-0.5 h-2 w-2 rounded-full bg-primary status-pulse" />
          </div>
          <div>
            <h1 className="text-lg font-bold font-mono tracking-tight text-foreground">
              AI-NOC
            </h1>
            <p className="text-[10px] font-mono text-muted-foreground uppercase tracking-widest">
              Network Operations Center
            </p>
          </div>
        </div>

        <div className="flex items-center gap-4">
          <div className="hidden md:flex items-center gap-2 text-xs font-mono text-muted-foreground">
            <Shield className="h-3.5 w-3.5 text-primary" />
            <span>21 Providers Monitored</span>
          </div>

          <div className="hidden sm:flex flex-col items-end text-[10px] font-mono text-muted-foreground leading-tight">
            <div className="flex items-center gap-1">
              <Clock className="h-3 w-3" />
              <span>Now: {now.toLocaleTimeString()}</span>
            </div>
            <span className="text-muted-foreground/70">Last check: {lastCheckedStr}</span>
          </div>

          <button
            onClick={handleRefresh}
            title="Force refresh"
            className="flex items-center gap-1.5 px-2.5 py-1.5 rounded-md border border-border bg-card hover:bg-secondary/80 text-xs font-mono text-muted-foreground hover:text-foreground transition-all"
          >
            <RefreshCw className={`h-3.5 w-3.5 ${isRefreshing ? "animate-spin" : ""}`} />
            <span className="hidden sm:inline">Refresh</span>
          </button>

          <div className="h-2 w-2 rounded-full bg-primary status-pulse" title="System Active" />
        </div>
      </div>
    </header>
  );
};

export default NocHeader;
