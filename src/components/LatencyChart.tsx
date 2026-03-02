import { LatencyPoint } from "@/lib/types";
import { Area, AreaChart, ResponsiveContainer, XAxis, YAxis, Tooltip as RechartsTooltip, CartesianGrid } from "recharts";

interface LatencyChartProps {
  data: LatencyPoint[];
  title?: string;
}

const LatencyChart = ({ data, title = "Latency (24h)" }: LatencyChartProps) => {
  return (
    <div className="bg-card border border-border rounded-lg p-4">
      <h3 className="text-xs font-mono font-semibold text-foreground mb-3">{title}</h3>
      <div className="h-48">
        <ResponsiveContainer width="100%" height="100%">
          <AreaChart data={data} margin={{ top: 5, right: 5, left: -20, bottom: 0 }}>
            <defs>
              <linearGradient id="p50Fill" x1="0" y1="0" x2="0" y2="1">
                <stop offset="5%" stopColor="hsl(160, 100%, 45%)" stopOpacity={0.3} />
                <stop offset="95%" stopColor="hsl(160, 100%, 45%)" stopOpacity={0} />
              </linearGradient>
              <linearGradient id="p95Fill" x1="0" y1="0" x2="0" y2="1">
                <stop offset="5%" stopColor="hsl(200, 90%, 50%)" stopOpacity={0.2} />
                <stop offset="95%" stopColor="hsl(200, 90%, 50%)" stopOpacity={0} />
              </linearGradient>
            </defs>
            <CartesianGrid strokeDasharray="3 3" stroke="hsl(220, 15%, 18%)" />
            <XAxis
              dataKey="ts"
              tick={{ fontSize: 9, fill: "hsl(215, 15%, 50%)", fontFamily: "JetBrains Mono" }}
              tickLine={false}
              axisLine={{ stroke: "hsl(220, 15%, 18%)" }}
              interval="preserveStartEnd"
            />
            <YAxis
              tick={{ fontSize: 9, fill: "hsl(215, 15%, 50%)", fontFamily: "JetBrains Mono" }}
              tickLine={false}
              axisLine={false}
              tickFormatter={(v) => `${v}ms`}
            />
            <RechartsTooltip
              contentStyle={{
                backgroundColor: "hsl(220, 18%, 10%)",
                border: "1px solid hsl(220, 15%, 18%)",
                borderRadius: "6px",
                fontSize: "11px",
                fontFamily: "JetBrains Mono",
                color: "hsl(210, 20%, 90%)",
              }}
            />
            <Area
              type="monotone"
              dataKey="p50"
              stroke="hsl(160, 100%, 45%)"
              fill="url(#p50Fill)"
              strokeWidth={1.5}
              name="P50"
            />
            <Area
              type="monotone"
              dataKey="p95"
              stroke="hsl(200, 90%, 50%)"
              fill="url(#p95Fill)"
              strokeWidth={1.5}
              name="P95"
              strokeDasharray="4 2"
            />
          </AreaChart>
        </ResponsiveContainer>
      </div>
      <div className="flex items-center gap-4 mt-2">
        <span className="flex items-center gap-1.5 text-[10px] font-mono text-muted-foreground">
          <span className="h-0.5 w-4 bg-primary rounded" /> P50
        </span>
        <span className="flex items-center gap-1.5 text-[10px] font-mono text-muted-foreground">
          <span className="h-0.5 w-4 bg-accent rounded border-dashed" /> P95
        </span>
      </div>
    </div>
  );
};

export default LatencyChart;
