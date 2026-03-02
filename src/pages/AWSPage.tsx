import { useNavigate } from "react-router-dom";
import NocHeader from "@/components/NocHeader";
import UptimeHeatmap from "@/components/UptimeHeatmap";
import LatencyChart from "@/components/LatencyChart";
import { getUptimeHeatmap, getLatencyData, getStatusLabel, getStatusBgClass, getStatusColorClass } from "@/lib/mock-data";
import { ArrowLeft, Globe, Shield, Clock, TrendingUp, Server, Database, Cpu, HardDrive, Network, Cloud } from "lucide-react";
import { ProviderStatus } from "@/lib/types";

interface CloudService {
  name: string;
  slug: string;
  status: ProviderStatus;
  latencyMs: number;
  uptimePercent: number;
  category: string;
  description: string;
}

const awsServices: CloudService[] = [
  { name: "Amazon Bedrock", slug: "bedrock", status: "ok", latencyMs: 185, uptimePercent: 99.95, category: "AI/ML", description: "Foundation models as a service" },
  { name: "Amazon SageMaker", slug: "sagemaker", status: "ok", latencyMs: 210, uptimePercent: 99.92, category: "AI/ML", description: "ML model training & deployment" },
  { name: "AWS Lambda", slug: "lambda", status: "ok", latencyMs: 45, uptimePercent: 99.99, category: "Compute", description: "Serverless compute" },
  { name: "Amazon EC2", slug: "ec2", status: "ok", latencyMs: 32, uptimePercent: 99.98, category: "Compute", description: "Virtual servers in the cloud" },
  { name: "Amazon S3", slug: "s3", status: "ok", latencyMs: 28, uptimePercent: 99.99, category: "Storage", description: "Object storage" },
  { name: "Amazon RDS", slug: "rds", status: "degraded", latencyMs: 380, uptimePercent: 99.85, category: "Database", description: "Managed relational database" },
  { name: "Amazon DynamoDB", slug: "dynamodb", status: "ok", latencyMs: 12, uptimePercent: 99.999, category: "Database", description: "NoSQL database" },
  { name: "Amazon EKS", slug: "eks", status: "ok", latencyMs: 95, uptimePercent: 99.95, category: "Containers", description: "Managed Kubernetes" },
  { name: "Amazon CloudFront", slug: "cloudfront", status: "ok", latencyMs: 15, uptimePercent: 99.99, category: "Networking", description: "Content delivery network" },
  { name: "Amazon API Gateway", slug: "apigateway", status: "ok", latencyMs: 52, uptimePercent: 99.97, category: "Networking", description: "API management" },
  { name: "AWS Comprehend", slug: "comprehend", status: "ok", latencyMs: 165, uptimePercent: 99.93, category: "AI/ML", description: "NLP service" },
  { name: "Amazon Rekognition", slug: "rekognition", status: "ok", latencyMs: 198, uptimePercent: 99.91, category: "AI/ML", description: "Image & video analysis" },
];

const regions = [
  { name: "US East (N. Virginia)", code: "us-east-1", status: "ok" as ProviderStatus },
  { name: "US West (Oregon)", code: "us-west-2", status: "ok" as ProviderStatus },
  { name: "EU (Ireland)", code: "eu-west-1", status: "ok" as ProviderStatus },
  { name: "EU (Frankfurt)", code: "eu-central-1", status: "degraded" as ProviderStatus },
  { name: "Asia Pacific (Tokyo)", code: "ap-northeast-1", status: "ok" as ProviderStatus },
  { name: "Asia Pacific (Sydney)", code: "ap-southeast-2", status: "ok" as ProviderStatus },
  { name: "Asia Pacific (Singapore)", code: "ap-southeast-1", status: "ok" as ProviderStatus },
  { name: "South America (São Paulo)", code: "sa-east-1", status: "ok" as ProviderStatus },
];

const categoryIcons: Record<string, typeof Server> = {
  "AI/ML": Cpu,
  "Compute": Server,
  "Storage": HardDrive,
  "Database": Database,
  "Containers": Cloud,
  "Networking": Network,
};

const AWSPage = () => {
  const navigate = useNavigate();
  const heatmap = getUptimeHeatmap("aws");
  const latency = getLatencyData("aws");

  const categories = [...new Set(awsServices.map(s => s.category))];
  const overallStatus: ProviderStatus = awsServices.some(s => s.status === "outage") ? "outage"
    : awsServices.some(s => s.status === "partial") ? "partial"
    : awsServices.some(s => s.status === "degraded") ? "degraded" : "ok";

  const statusCounts = { ok: 0, degraded: 0, partial: 0, outage: 0 };
  awsServices.forEach(s => { if (s.status in statusCounts) statusCounts[s.status as keyof typeof statusCounts]++; });

  return (
    <div className="min-h-screen bg-background noc-grid-bg relative">
      <div className="scanline fixed inset-0 pointer-events-none z-40 h-[200%]" />
      <NocHeader />

      <main className="container mx-auto px-4 py-6 space-y-6 relative z-10">
        <button onClick={() => navigate("/")} className="flex items-center gap-2 text-xs font-mono text-muted-foreground hover:text-foreground transition-colors">
          <ArrowLeft className="h-3.5 w-3.5" /> Back to Dashboard
        </button>

        {/* Header */}
        <div className="flex items-start justify-between flex-wrap gap-4">
          <div>
            <div className="flex items-center gap-3 mb-1">
              <span className={`h-3 w-3 rounded-full ${getStatusBgClass(overallStatus)}`} />
              <h1 className="text-2xl font-bold text-foreground">Amazon Web Services</h1>
            </div>
            <div className="flex items-center gap-3 text-xs font-mono text-muted-foreground">
              <span className={getStatusColorClass(overallStatus)}>{getStatusLabel(overallStatus)}</span>
              <span>•</span>
              <span className="flex items-center gap-1"><Shield className="h-3 w-3" /> Official: health.aws.amazon.com</span>
              <span>•</span>
              <span>{awsServices.length} services monitored</span>
            </div>
          </div>
          <div className="flex gap-3">
            {Object.entries(statusCounts).filter(([, v]) => v > 0).map(([status, count]) => (
              <div key={status} className="bg-card border border-border rounded-lg px-4 py-3 text-center min-w-[80px]">
                <div className={`text-lg font-bold font-mono ${getStatusColorClass(status as ProviderStatus)}`}>{count}</div>
                <div className="text-[10px] font-mono text-muted-foreground uppercase">{getStatusLabel(status as ProviderStatus)}</div>
              </div>
            ))}
          </div>
        </div>

        {/* Regions */}
        <section>
          <h2 className="text-xs font-mono font-semibold text-muted-foreground uppercase tracking-widest mb-3 flex items-center gap-2">
            <Globe className="h-3.5 w-3.5" /> Region Status
          </h2>
          <div className="grid grid-cols-2 sm:grid-cols-4 gap-2">
            {regions.map(r => (
              <div key={r.code} className="bg-card border border-border rounded-lg px-3 py-2.5 flex items-center gap-2.5">
                <span className={`h-2 w-2 rounded-full flex-shrink-0 ${getStatusBgClass(r.status)}`} />
                <div className="min-w-0">
                  <div className="text-xs font-medium text-foreground truncate">{r.name}</div>
                  <div className="text-[10px] font-mono text-muted-foreground">{r.code}</div>
                </div>
              </div>
            ))}
          </div>
        </section>

        {/* Services by Category */}
        {categories.map(cat => {
          const Icon = categoryIcons[cat] || Server;
          const services = awsServices.filter(s => s.category === cat);
          return (
            <section key={cat}>
              <h2 className="text-xs font-mono font-semibold text-muted-foreground uppercase tracking-widest mb-3 flex items-center gap-2">
                <Icon className="h-3.5 w-3.5" /> {cat}
              </h2>
              <div className="bg-card border border-border rounded-lg overflow-hidden divide-y divide-border/50">
                {services.map(svc => (
                  <div key={svc.slug} className="px-4 py-3 flex items-center justify-between">
                    <div className="flex items-center gap-3">
                      <span className={`h-2 w-2 rounded-full ${getStatusBgClass(svc.status)}`} />
                      <div>
                        <span className="text-sm font-medium text-foreground">{svc.name}</span>
                        <p className="text-[11px] text-muted-foreground">{svc.description}</p>
                      </div>
                    </div>
                    <div className="flex items-center gap-6 text-right">
                      <div>
                        <div className="text-xs font-mono text-foreground">{svc.latencyMs}ms</div>
                        <div className="text-[10px] font-mono text-muted-foreground">latency</div>
                      </div>
                      <div>
                        <div className="text-xs font-mono text-foreground">{svc.uptimePercent}%</div>
                        <div className="text-[10px] font-mono text-muted-foreground">uptime</div>
                      </div>
                      <span className={`text-xs font-mono ${getStatusColorClass(svc.status)}`}>{getStatusLabel(svc.status)}</span>
                    </div>
                  </div>
                ))}
              </div>
            </section>
          );
        })}

        {/* Charts */}
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
          <LatencyChart data={latency} title="AWS Global Latency (24h)" />
          <UptimeHeatmap data={heatmap} label="AWS 90-Day Uptime" />
        </div>
      </main>
    </div>
  );
};

export default AWSPage;
