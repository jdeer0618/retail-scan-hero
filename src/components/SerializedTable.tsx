import { useState, useMemo } from 'react';
import { Search, ArrowUpDown, ChevronDown, ChevronUp } from 'lucide-react';
import { SerializedItem } from '@/types/inventory';
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

interface SerializedTableProps {
  items: SerializedItem[];
}

type SortField = 'serialNumber' | 'manufacturer' | 'model' | 'caliber' | 'isScanned';
type SortDirection = 'asc' | 'desc';
type FilterMode = 'all' | 'scanned' | 'not-scanned';

export function SerializedTable({ items }: SerializedTableProps) {
  const [search, setSearch] = useState('');
  const [sortField, setSortField] = useState<SortField>('serialNumber');
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
          item.serialNumber.toLowerCase().includes(lowerSearch) ||
          item.manufacturer.toLowerCase().includes(lowerSearch) ||
          item.model.toLowerCase().includes(lowerSearch) ||
          item.itemDescription.toLowerCase().includes(lowerSearch)
      );
    }

    // Apply filter mode
    switch (filterMode) {
      case 'scanned':
        filtered = filtered.filter(item => item.isScanned);
        break;
      case 'not-scanned':
        filtered = filtered.filter(item => !item.isScanned);
        break;
    }

    // Apply sorting
    filtered.sort((a, b) => {
      let aVal: string | number | boolean;
      let bVal: string | number | boolean;

      switch (sortField) {
        case 'serialNumber':
          aVal = a.serialNumber.toLowerCase();
          bVal = b.serialNumber.toLowerCase();
          break;
        case 'manufacturer':
          aVal = a.manufacturer.toLowerCase();
          bVal = b.manufacturer.toLowerCase();
          break;
        case 'model':
          aVal = a.model.toLowerCase();
          bVal = b.model.toLowerCase();
          break;
        case 'caliber':
          aVal = a.caliber.toLowerCase();
          bVal = b.caliber.toLowerCase();
          break;
        case 'isScanned':
          aVal = a.isScanned ? 1 : 0;
          bVal = b.isScanned ? 1 : 0;
          break;
        default:
          aVal = a.serialNumber;
          bVal = b.serialNumber;
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

  return (
    <div className="stat-card">
      <button
        onClick={() => setIsExpanded(!isExpanded)}
        className="w-full flex items-center justify-between mb-4"
      >
        <h2 className="font-semibold">Serialized Inventory</h2>
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
                placeholder="Search by serial, manufacturer, or model..."
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                className="pl-9"
              />
            </div>
            <div className="flex gap-2">
              {(['all', 'scanned', 'not-scanned'] as FilterMode[]).map((mode) => (
                <Button
                  key={mode}
                  variant={filterMode === mode ? 'default' : 'outline'}
                  size="sm"
                  onClick={() => setFilterMode(mode)}
                  className="capitalize"
                >
                  {mode.replace('-', ' ')}
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
                      <SortButton field="serialNumber">Serial #</SortButton>
                    </TableHead>
                    <TableHead>
                      <SortButton field="manufacturer">Manufacturer</SortButton>
                    </TableHead>
                    <TableHead>
                      <SortButton field="model">Model</SortButton>
                    </TableHead>
                    <TableHead className="w-[100px]">
                      <SortButton field="caliber">Caliber</SortButton>
                    </TableHead>
                    <TableHead className="w-[100px] text-center">
                      <SortButton field="isScanned">Status</SortButton>
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
                    filteredAndSortedItems.slice(0, 100).map((item) => (
                      <TableRow
                        key={item.serialNumber}
                        className={item.isScanned ? 'bg-primary/5' : ''}
                      >
                        <TableCell className="font-mono text-sm">{item.serialNumber}</TableCell>
                        <TableCell>{item.manufacturer}</TableCell>
                        <TableCell className="max-w-[200px] truncate">{item.model}</TableCell>
                        <TableCell className="text-sm">{item.caliber}</TableCell>
                        <TableCell className="text-center">
                          <span className={`inline-flex px-2 py-1 rounded-full text-xs font-medium ${
                            item.isScanned 
                              ? 'bg-primary/20 text-primary' 
                              : 'bg-muted text-muted-foreground'
                          }`}>
                            {item.isScanned ? 'Scanned' : 'Pending'}
                          </span>
                        </TableCell>
                      </TableRow>
                    ))
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
