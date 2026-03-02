import { Activity, Shield, Clock } from "lucide-react";

const NocHeader = () => {
  const now = new Date();

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

        <div className="flex items-center gap-6">
          <div className="hidden md:flex items-center gap-2 text-xs font-mono text-muted-foreground">
            <Shield className="h-3.5 w-3.5 text-primary" />
            <span>13 Providers Monitored</span>
          </div>
          <div className="flex items-center gap-2 text-xs font-mono text-muted-foreground">
            <Clock className="h-3.5 w-3.5" />
            <span>{now.toLocaleTimeString()}</span>
          </div>
          <div className="h-2 w-2 rounded-full bg-primary status-pulse" title="System Active" />
        </div>
      </div>
    </header>
  );
};

export default NocHeader;
