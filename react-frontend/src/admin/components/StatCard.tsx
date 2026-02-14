/**
 * Admin Stat Card
 * Displays a key metric with label, value, and optional trend indicator
 */

import { Card, CardBody } from '@heroui/react';
import { TrendingUp, TrendingDown, type LucideIcon } from 'lucide-react';

interface StatCardProps {
  label: string;
  value: string | number;
  icon: LucideIcon;
  trend?: number;
  trendLabel?: string;
  color?: 'primary' | 'success' | 'warning' | 'danger' | 'secondary';
  loading?: boolean;
}

const colorMap = {
  primary: 'text-primary bg-primary/10',
  success: 'text-success bg-success/10',
  warning: 'text-warning bg-warning/10',
  danger: 'text-danger bg-danger/10',
  secondary: 'text-secondary bg-secondary/10',
};

export function StatCard({
  label,
  value,
  icon: Icon,
  trend,
  trendLabel,
  color = 'primary',
  loading = false,
}: StatCardProps) {
  return (
    <Card shadow="sm">
      <CardBody className="flex flex-row items-center gap-4 p-4">
        <div className={`flex h-12 w-12 shrink-0 items-center justify-center rounded-xl ${colorMap[color]}`}>
          <Icon size={24} />
        </div>
        <div className="min-w-0 flex-1">
          <p className="text-sm text-default-500">{label}</p>
          {loading ? (
            <div className="mt-1 h-7 w-20 animate-pulse rounded bg-default-200" />
          ) : (
            <p className="text-2xl font-bold text-foreground">
              {typeof value === 'number' ? value.toLocaleString() : value}
            </p>
          )}
          {trend !== undefined && (
            <div className="mt-0.5 flex items-center gap-1">
              {trend >= 0 ? (
                <TrendingUp size={14} className="text-success" />
              ) : (
                <TrendingDown size={14} className="text-danger" />
              )}
              <span className={`text-xs font-medium ${trend >= 0 ? 'text-success' : 'text-danger'}`}>
                {trend > 0 ? '+' : ''}{trend}%
              </span>
              {trendLabel && (
                <span className="text-xs text-default-400">{trendLabel}</span>
              )}
            </div>
          )}
        </div>
      </CardBody>
    </Card>
  );
}

export default StatCard;
