import { Provider, ProviderStatus } from "@/lib/types";
import { CheckCircle2, AlertTriangle, XCircle, HelpCircle } from "lucide-react";

interface StatusSummaryProps {
  providers: Provider[];
}

const StatusSummary = ({ providers }: StatusSummaryProps) => {
  const counts: Record<ProviderStatus, number> = { ok: 0, degraded: 0, partial: 0, outage: 0, unknown: 0 };
  providers.forEach(p => counts[p.status]++);

  const items = [
    { label: "Operational", count: counts.ok, icon: CheckCircle2, color: "text-success", bg: "bg-success/10" },
    { label: "Degraded", count: counts.degraded, icon: AlertTriangle, color: "text-degraded", bg: "bg-degraded/10" },
    { label: "Outage", count: counts.outage + counts.partial, icon: XCircle, color: "text-outage", bg: "bg-outage/10" },
    { label: "Unknown", count: counts.unknown, icon: HelpCircle, color: "text-unknown", bg: "bg-unknown/10" },
  ];

  return (
    <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
      {items.map((item) => (
        <div key={item.label} className={`${item.bg} border border-border rounded-lg p-3 flex items-center gap-3`}>
          <item.icon className={`h-5 w-5 ${item.color}`} />
          <div>
            <div className={`text-xl font-bold font-mono ${item.color}`}>{item.count}</div>
            <div className="text-[10px] font-mono text-muted-foreground uppercase tracking-wider">{item.label}</div>
          </div>
        </div>
      ))}
    </div>
  );
};

export default StatusSummary;
