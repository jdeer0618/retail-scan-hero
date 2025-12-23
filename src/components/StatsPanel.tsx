import { Package, CheckCircle, AlertTriangle, XCircle, Hash, Clock } from 'lucide-react';
import { ReconciliationStats, InventoryMode } from '@/types/inventory';

interface StatsPanelProps {
  stats: ReconciliationStats;
  mode: InventoryMode;
}

export function StatsPanel({ stats, mode }: StatsPanelProps) {
  const isSerialMode = mode === 'serialized';

  const statItems = isSerialMode ? [
    {
      label: 'Total Serials',
      value: stats.totalExpectedItems,
      icon: Package,
      color: 'text-foreground',
      bgColor: 'bg-secondary',
    },
    {
      label: 'Scanned',
      value: stats.totalScannedItems,
      icon: CheckCircle,
      color: 'text-primary',
      bgColor: 'bg-primary/20',
    },
    {
      label: 'Not Scanned',
      value: stats.notScannedItems || 0,
      icon: Clock,
      color: 'text-accent',
      bgColor: 'bg-accent/20',
    },
    {
      label: 'Exceptions',
      value: stats.exceptionItems,
      icon: XCircle,
      color: 'text-destructive',
      bgColor: 'bg-destructive/20',
    },
  ] : [
    {
      label: 'Total Items',
      value: stats.totalExpectedItems,
      icon: Package,
      color: 'text-foreground',
      bgColor: 'bg-secondary',
    },
    {
      label: 'Scanned',
      value: stats.totalScannedItems,
      icon: Hash,
      color: 'text-primary',
      bgColor: 'bg-primary/20',
    },
    {
      label: 'Matched',
      value: stats.matchedItems,
      icon: CheckCircle,
      color: 'text-primary',
      bgColor: 'bg-primary/20',
    },
    {
      label: 'Discrepancies',
      value: stats.discrepancyItems,
      icon: AlertTriangle,
      color: 'text-accent',
      bgColor: 'bg-accent/20',
    },
    {
      label: 'Exceptions',
      value: stats.exceptionItems,
      icon: XCircle,
      color: 'text-destructive',
      bgColor: 'bg-destructive/20',
    },
  ];

  return (
    <div className={`grid grid-cols-2 ${isSerialMode ? 'md:grid-cols-4' : 'md:grid-cols-5'} gap-3`}>
      {statItems.map((stat) => (
        <div key={stat.label} className="stat-card">
          <div className="flex items-center gap-2 mb-2">
            <div className={`p-1.5 rounded ${stat.bgColor}`}>
              <stat.icon className={`w-4 h-4 ${stat.color}`} />
            </div>
            <span className="text-xs text-muted-foreground">{stat.label}</span>
          </div>
          <p className={`text-2xl font-bold font-mono ${stat.color}`}>
            {stat.value.toLocaleString()}
          </p>
        </div>
      ))}
    </div>
  );
}
