import { useState, useCallback, useMemo } from 'react';
import { InventoryItem, ExceptionItem, ScanResult, ReconciliationStats } from '@/types/inventory';

export function useInventory() {
  const [inventoryMap, setInventoryMap] = useState<Map<string, InventoryItem>>(new Map());
  const [exceptionMap, setExceptionMap] = useState<Map<string, ExceptionItem>>(new Map());
  const [lastScan, setLastScan] = useState<ScanResult | null>(null);
  const [isLoaded, setIsLoaded] = useState(false);

  const loadCSV = useCallback((csvContent: string) => {
    const lines = csvContent.split('\n');
    if (lines.length < 2) return;

    const headers = parseCSVLine(lines[0]);
    const newMap = new Map<string, InventoryItem>();

    // Find column indices
    const upcIdx = headers.findIndex(h => h.toLowerCase().includes('item number') || h === 'UPC');
    const nameIdx = headers.findIndex(h => h.toLowerCase().includes('item name'));
    const costIdx = headers.findIndex(h => h.toLowerCase() === 'cost');
    const sellingPriceIdx = headers.findIndex(h => h.toLowerCase().includes('selling price'));
    const listPriceIdx = headers.findIndex(h => h.toLowerCase().includes('list price'));
    const quantityIdx = headers.findIndex(h => h.toLowerCase() === 'quantity');
    const categoryIdx = headers.findIndex(h => h.toLowerCase() === 'category');

    for (let i = 1; i < lines.length; i++) {
      const line = lines[i].trim();
      if (!line) continue;

      const values = parseCSVLine(line);
      const upc = cleanUPC(values[upcIdx] || '');
      
      if (!upc) continue;

      newMap.set(upc, {
        upc,
        itemNumber: values[upcIdx] || '',
        itemName: values[nameIdx] || 'Unknown Item',
        cost: parseFloat(values[costIdx]) || 0,
        sellingPrice: parseFloat(values[sellingPriceIdx]) || 0,
        listPrice: parseFloat(values[listPriceIdx]) || 0,
        expectedQuantity: parseInt(values[quantityIdx]) || 0,
        scannedQuantity: 0,
        category: values[categoryIdx] || '',
      });
    }

    setInventoryMap(newMap);
    setExceptionMap(new Map());
    setIsLoaded(true);
    setLastScan(null);
  }, []);

  const scanUPC = useCallback((upc: string): ScanResult | null => {
    const cleanedUPC = cleanUPC(upc);
    if (!cleanedUPC) return null;

    const now = new Date();

    // Check if UPC exists in inventory
    if (inventoryMap.has(cleanedUPC)) {
      setInventoryMap(prev => {
        const newMap = new Map(prev);
        const item = newMap.get(cleanedUPC)!;
        newMap.set(cleanedUPC, {
          ...item,
          scannedQuantity: item.scannedQuantity + 1,
          lastScanned: now,
        });
        return newMap;
      });

      const item = inventoryMap.get(cleanedUPC)!;
      const result: ScanResult = {
        type: 'matched',
        upc: cleanedUPC,
        itemName: item.itemName,
        newCount: item.scannedQuantity + 1,
      };
      setLastScan(result);
      return result;
    }

    // Add to exception list
    setExceptionMap(prev => {
      const newMap = new Map(prev);
      const existing = newMap.get(cleanedUPC);
      if (existing) {
        newMap.set(cleanedUPC, {
          ...existing,
          scannedQuantity: existing.scannedQuantity + 1,
          lastScanned: now,
        });
      } else {
        newMap.set(cleanedUPC, {
          upc: cleanedUPC,
          scannedQuantity: 1,
          firstScanned: now,
          lastScanned: now,
        });
      }
      return newMap;
    });

    const existing = exceptionMap.get(cleanedUPC);
    const result: ScanResult = {
      type: 'exception',
      upc: cleanedUPC,
      newCount: (existing?.scannedQuantity || 0) + 1,
    };
    setLastScan(result);
    return result;
  }, [inventoryMap, exceptionMap]);

  const stats: ReconciliationStats = useMemo(() => {
    const items = Array.from(inventoryMap.values());
    const scannedItems = items.filter(i => i.scannedQuantity > 0);
    const matchedItems = scannedItems.filter(i => i.scannedQuantity === i.expectedQuantity);
    const discrepancyItems = scannedItems.filter(i => i.scannedQuantity !== i.expectedQuantity);

    return {
      totalExpectedItems: items.length,
      totalScannedItems: scannedItems.length,
      matchedItems: matchedItems.length,
      discrepancyItems: discrepancyItems.length,
      exceptionItems: exceptionMap.size,
      totalExpectedQuantity: items.reduce((sum, i) => sum + i.expectedQuantity, 0),
      totalScannedQuantity: items.reduce((sum, i) => sum + i.scannedQuantity, 0) + 
        Array.from(exceptionMap.values()).reduce((sum, i) => sum + i.scannedQuantity, 0),
    };
  }, [inventoryMap, exceptionMap]);

  const inventoryItems = useMemo(() => Array.from(inventoryMap.values()), [inventoryMap]);
  const exceptionItems = useMemo(() => Array.from(exceptionMap.values()), [exceptionMap]);

  const resetScans = useCallback(() => {
    setInventoryMap(prev => {
      const newMap = new Map(prev);
      newMap.forEach((item, key) => {
        newMap.set(key, { ...item, scannedQuantity: 0, lastScanned: undefined });
      });
      return newMap;
    });
    setExceptionMap(new Map());
    setLastScan(null);
  }, []);

  const clearAll = useCallback(() => {
    setInventoryMap(new Map());
    setExceptionMap(new Map());
    setLastScan(null);
    setIsLoaded(false);
  }, []);

  return {
    inventoryItems,
    exceptionItems,
    stats,
    lastScan,
    isLoaded,
    loadCSV,
    scanUPC,
    resetScans,
    clearAll,
  };
}

function parseCSVLine(line: string): string[] {
  const result: string[] = [];
  let current = '';
  let inQuotes = false;

  for (let i = 0; i < line.length; i++) {
    const char = line[i];
    
    if (char === '"') {
      inQuotes = !inQuotes;
    } else if (char === ',' && !inQuotes) {
      result.push(current.trim());
      current = '';
    } else {
      current += char;
    }
  }
  
  result.push(current.trim());
  return result;
}

function cleanUPC(upc: string): string {
  // Remove BOM, quotes, and whitespace, keep only digits
  return upc.replace(/[\ufeff"'\\s]/g, '').replace(/[^0-9]/g, '');
}
