import { Incident } from "@/lib/types";
import { getStatusLabel, getStatusBgClass } from "@/lib/status-utils";
import { AlertTriangle, CheckCircle2, ExternalLink } from "lucide-react";
import { formatDistanceToNow } from "date-fns";

interface IncidentFeedProps {
  incidents: Incident[];
}

const IncidentFeed = ({ incidents }: IncidentFeedProps) => {
  return (
    <div className="bg-card border border-border rounded-lg overflow-hidden">
      <div className="px-4 py-3 border-b border-border flex items-center gap-2">
        <AlertTriangle className="h-4 w-4 text-warning" />
        <h2 className="text-sm font-semibold font-mono text-foreground">Incident Feed</h2>
        <span className="ml-auto text-[10px] font-mono text-muted-foreground px-2 py-0.5 bg-secondary rounded">
          {incidents.filter(i => !i.endTs).length} active
        </span>
      </div>
      <div className="divide-y divide-border/50 max-h-96 overflow-y-auto">
        {incidents.map((inc, i) => (
          <div key={inc.id} className="px-4 py-3 hover:bg-secondary/30 transition-colors animate-fade-in-up" style={{ animationDelay: `${i * 60}ms` }}>
            <div className="flex items-start gap-3">
              <div className="mt-0.5">
                {inc.endTs ? (
                  <CheckCircle2 className="h-4 w-4 text-success" />
                ) : (
                  <span className={`block h-2.5 w-2.5 rounded-full mt-0.5 ${getStatusBgClass(inc.severity)} status-pulse`} />
                )}
              </div>
              <div className="flex-1 min-w-0">
                <div className="flex items-center gap-2 mb-0.5">
                  <span className="text-xs font-semibold text-foreground">{inc.providerName}</span>
                  <span className={`text-[9px] font-mono px-1.5 py-0.5 rounded ${
                    inc.severity === 'outage' ? 'bg-outage/10 text-outage' :
                    inc.severity === 'degraded' ? 'bg-degraded/10 text-degraded' :
                    'bg-warning/10 text-warning'
                  }`}>
                    {getStatusLabel(inc.severity)}
                  </span>
                  <span className="text-[9px] font-mono text-muted-foreground uppercase">
                    {inc.source}
                  </span>
                </div>
                <p className="text-xs text-foreground/80 mb-1">{inc.title}</p>
                <p className="text-[11px] text-muted-foreground line-clamp-2">{inc.description}</p>
                <div className="flex items-center gap-3 mt-1.5">
                  <span className="text-[10px] font-mono text-muted-foreground">
                    {formatDistanceToNow(inc.startTs, { addSuffix: true })}
                  </span>
                  {inc.endTs && (
                    <span className="text-[10px] font-mono text-success">
                      Resolved {formatDistanceToNow(inc.endTs, { addSuffix: true })}
                    </span>
                  )}
                </div>
              </div>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
};

export default IncidentFeed;
