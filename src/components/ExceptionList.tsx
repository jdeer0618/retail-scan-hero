import { useState } from 'react';
import { AlertTriangle, ChevronDown, ChevronUp, Download } from 'lucide-react';
import { ExceptionItem } from '@/types/inventory';
import { Button } from '@/components/ui/button';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';

interface ExceptionListProps {
  items: ExceptionItem[];
}

export function ExceptionList({ items }: ExceptionListProps) {
  const [isExpanded, setIsExpanded] = useState(true);

  const exportExceptions = () => {
    if (items.length === 0) return;

    const csv = [
      'UPC,Scanned Quantity,First Scanned,Last Scanned',
      ...items.map(
        item =>
          `${item.upc},${item.scannedQuantity},${item.firstScanned.toISOString()},${item.lastScanned.toISOString()}`
      ),
    ].join('\n');

    const blob = new Blob([csv], { type: 'text/csv' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `exceptions_${new Date().toISOString().split('T')[0]}.csv`;
    a.click();
    URL.revokeObjectURL(url);
  };

  if (items.length === 0) {
    return null;
  }

  return (
    <div className="stat-card border-destructive/30">
      <button
        onClick={() => setIsExpanded(!isExpanded)}
        className="w-full flex items-center justify-between"
      >
        <div className="flex items-center gap-2">
          <AlertTriangle className="w-5 h-5 text-destructive" />
          <h2 className="font-semibold">Exceptions ({items.length})</h2>
        </div>
        {isExpanded ? (
          <ChevronUp className="w-5 h-5 text-muted-foreground" />
        ) : (
          <ChevronDown className="w-5 h-5 text-muted-foreground" />
        )}
      </button>

      {isExpanded && (
        <>
          <p className="text-sm text-muted-foreground mt-2 mb-4">
            These UPCs were scanned but not found in the inventory CSV
          </p>

          <div className="rounded-lg border border-destructive/30 overflow-hidden mb-4">
            <div className="max-h-[200px] overflow-auto">
              <Table>
                <TableHeader className="sticky top-0 bg-card">
                  <TableRow>
                    <TableHead>UPC</TableHead>
                    <TableHead className="text-right">Count</TableHead>
                    <TableHead className="text-right">Last Scanned</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {items.map((item) => (
                    <TableRow key={item.upc} className="bg-destructive/5">
                      <TableCell className="font-mono">{item.upc}</TableCell>
                      <TableCell className="text-right font-mono font-bold">
                        {item.scannedQuantity}
                      </TableCell>
                      <TableCell className="text-right text-sm text-muted-foreground">
                        {item.lastScanned.toLocaleTimeString()}
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </div>
          </div>

          <Button
            variant="outline"
            size="sm"
            onClick={exportExceptions}
            className="w-full"
          >
            <Download className="w-4 h-4 mr-2" />
            Export Exceptions CSV
          </Button>
        </>
      )}
    </div>
  );
}
