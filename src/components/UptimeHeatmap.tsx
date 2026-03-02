import { UptimeDay, ProviderStatus } from "@/lib/types";
import { Tooltip, TooltipContent, TooltipTrigger } from "@/components/ui/tooltip";

interface UptimeHeatmapProps {
  data: UptimeDay[];
  label?: string;
}

const cellColor: Record<ProviderStatus, string> = {
  ok: "bg-success/80 hover:bg-success",
  degraded: "bg-degraded/80 hover:bg-degraded",
  partial: "bg-warning/80 hover:bg-warning",
  outage: "bg-outage/80 hover:bg-outage",
  unknown: "bg-unknown/40 hover:bg-unknown/60",
};

const UptimeHeatmap = ({ data, label = "90-Day Uptime" }: UptimeHeatmapProps) => {
  return (
    <div className="bg-card border border-border rounded-lg p-4">
      <div className="flex items-center justify-between mb-3">
        <h3 className="text-xs font-mono font-semibold text-foreground">{label}</h3>
        <div className="flex items-center gap-2 text-[9px] font-mono text-muted-foreground">
          <span className="flex items-center gap-1"><span className="h-2 w-2 rounded-sm bg-success/80" /> OK</span>
          <span className="flex items-center gap-1"><span className="h-2 w-2 rounded-sm bg-degraded/80" /> Deg</span>
          <span className="flex items-center gap-1"><span className="h-2 w-2 rounded-sm bg-outage/80" /> Out</span>
        </div>
      </div>
      <div className="flex gap-[2px] flex-wrap">
        {data.map((day) => (
          <Tooltip key={day.date}>
            <TooltipTrigger asChild>
              <div
                className={`h-3 w-3 rounded-[2px] ${cellColor[day.status]} transition-colors cursor-default`}
              />
            </TooltipTrigger>
            <TooltipContent className="bg-popover border-border text-xs font-mono">
              <p>{day.date}</p>
              <p>{day.uptimePercent}% uptime</p>
            </TooltipContent>
          </Tooltip>
        ))}
      </div>
    </div>
  );
};

export default UptimeHeatmap;
