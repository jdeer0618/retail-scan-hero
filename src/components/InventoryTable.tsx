import { useState, useMemo } from 'react';
import { Search, ArrowUpDown, ChevronDown, ChevronUp } from 'lucide-react';
import { InventoryItem } from '@/types/inventory';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';

interface InventoryTableProps {
  items: InventoryItem[];
}

type SortField = 'upc' | 'itemName' | 'expectedQuantity' | 'scannedQuantity' | 'difference';
type SortDirection = 'asc' | 'desc';
type FilterMode = 'all' | 'scanned' | 'discrepancy' | 'unscanned';

export function InventoryTable({ items }: InventoryTableProps) {
  const [search, setSearch] = useState('');
  const [sortField, setSortField] = useState<SortField>('itemName');
  const [sortDirection, setSortDirection] = useState<SortDirection>('asc');
  const [filterMode, setFilterMode] = useState<FilterMode>('all');
  const [isExpanded, setIsExpanded] = useState(true);

  const filteredAndSortedItems = useMemo(() => {
    let filtered = items;

    // Apply search
    if (search) {
      const lowerSearch = search.toLowerCase();
      filtered = filtered.filter(
        item =>
          item.upc.includes(search) ||
          item.itemName.toLowerCase().includes(lowerSearch)
      );
    }

    // Apply filter mode
    switch (filterMode) {
      case 'scanned':
        filtered = filtered.filter(item => item.scannedQuantity > 0);
        break;
      case 'discrepancy':
        filtered = filtered.filter(
          item => item.scannedQuantity > 0 && item.scannedQuantity !== item.expectedQuantity
        );
        break;
      case 'unscanned':
        filtered = filtered.filter(item => item.scannedQuantity === 0);
        break;
    }

    // Apply sorting
    filtered.sort((a, b) => {
      let aVal: string | number;
      let bVal: string | number;

      switch (sortField) {
        case 'upc':
          aVal = a.upc;
          bVal = b.upc;
          break;
        case 'itemName':
          aVal = a.itemName.toLowerCase();
          bVal = b.itemName.toLowerCase();
          break;
        case 'expectedQuantity':
          aVal = a.expectedQuantity;
          bVal = b.expectedQuantity;
          break;
        case 'scannedQuantity':
          aVal = a.scannedQuantity;
          bVal = b.scannedQuantity;
          break;
        case 'difference':
          aVal = a.scannedQuantity - a.expectedQuantity;
          bVal = b.scannedQuantity - b.expectedQuantity;
          break;
        default:
          aVal = a.itemName;
          bVal = b.itemName;
      }

      if (aVal < bVal) return sortDirection === 'asc' ? -1 : 1;
      if (aVal > bVal) return sortDirection === 'asc' ? 1 : -1;
      return 0;
    });

    return filtered;
  }, [items, search, sortField, sortDirection, filterMode]);

  const handleSort = (field: SortField) => {
    if (sortField === field) {
      setSortDirection(prev => (prev === 'asc' ? 'desc' : 'asc'));
    } else {
      setSortField(field);
      setSortDirection('asc');
    }
  };

  const SortButton = ({ field, children }: { field: SortField; children: React.ReactNode }) => (
    <button
      onClick={() => handleSort(field)}
      className="flex items-center gap-1 hover:text-foreground transition-colors"
    >
      {children}
      <ArrowUpDown className="w-3 h-3" />
    </button>
  );

  const getDifferenceColor = (expected: number, scanned: number) => {
    if (scanned === 0) return 'text-muted-foreground';
    if (scanned === expected) return 'text-primary';
    return scanned > expected ? 'text-accent' : 'text-destructive';
  };

  return (
    <div className="stat-card">
      <button
        onClick={() => setIsExpanded(!isExpanded)}
        className="w-full flex items-center justify-between mb-4"
      >
        <h2 className="font-semibold">Inventory Items</h2>
        {isExpanded ? (
          <ChevronUp className="w-5 h-5 text-muted-foreground" />
        ) : (
          <ChevronDown className="w-5 h-5 text-muted-foreground" />
        )}
      </button>

      {isExpanded && (
        <>
          <div className="flex flex-col sm:flex-row gap-3 mb-4">
            <div className="relative flex-1">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
              <Input
                placeholder="Search by UPC or name..."
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                className="pl-9"
              />
            </div>
            <div className="flex gap-2">
              {(['all', 'scanned', 'discrepancy', 'unscanned'] as FilterMode[]).map((mode) => (
                <Button
                  key={mode}
                  variant={filterMode === mode ? 'default' : 'outline'}
                  size="sm"
                  onClick={() => setFilterMode(mode)}
                  className="capitalize"
                >
                  {mode}
                </Button>
              ))}
            </div>
          </div>

          <div className="rounded-lg border border-border overflow-hidden">
            <div className="max-h-[400px] overflow-auto">
              <Table>
                <TableHeader className="sticky top-0 bg-card">
                  <TableRow>
                    <TableHead className="w-[140px]">
                      <SortButton field="upc">UPC</SortButton>
                    </TableHead>
                    <TableHead>
                      <SortButton field="itemName">Item Name</SortButton>
                    </TableHead>
                    <TableHead className="w-[100px] text-right">
                      <SortButton field="expectedQuantity">Expected</SortButton>
                    </TableHead>
                    <TableHead className="w-[100px] text-right">
                      <SortButton field="scannedQuantity">Scanned</SortButton>
                    </TableHead>
                    <TableHead className="w-[100px] text-right">
                      <SortButton field="difference">Diff</SortButton>
                    </TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {filteredAndSortedItems.length === 0 ? (
                    <TableRow>
                      <TableCell colSpan={5} className="text-center text-muted-foreground py-8">
                        No items found
                      </TableCell>
                    </TableRow>
                  ) : (
                    filteredAndSortedItems.slice(0, 100).map((item) => {
                      const diff = item.scannedQuantity - item.expectedQuantity;
                      return (
                        <TableRow
                          key={item.upc}
                          className={item.lastScanned ? 'bg-primary/5' : ''}
                        >
                          <TableCell className="font-mono text-sm">{item.upc}</TableCell>
                          <TableCell className="max-w-[300px] truncate">{item.itemName}</TableCell>
                          <TableCell className="text-right font-mono">
                            {item.expectedQuantity}
                          </TableCell>
                          <TableCell className="text-right font-mono font-medium">
                            {item.scannedQuantity}
                          </TableCell>
                          <TableCell
                            className={`text-right font-mono font-bold ${getDifferenceColor(
                              item.expectedQuantity,
                              item.scannedQuantity
                            )}`}
                          >
                            {item.scannedQuantity > 0 && (
                              <>
                                {diff > 0 && '+'}
                                {diff}
                              </>
                            )}
                          </TableCell>
                        </TableRow>
                      );
                    })
                  )}
                </TableBody>
              </Table>
            </div>
          </div>

          {filteredAndSortedItems.length > 100 && (
            <p className="text-sm text-muted-foreground mt-2 text-center">
              Showing 100 of {filteredAndSortedItems.length} items
            </p>
          )}
        </>
      )}
    </div>
  );
}
