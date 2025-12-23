import { useState, useCallback, useMemo } from 'react';
import { InventoryItem, ExceptionItem, ScanResult, ReconciliationStats, SerializedItem, InventoryMode } from '@/types/inventory';

export function useInventory() {
  const [mode, setMode] = useState<InventoryMode>('upc');
  const [inventoryMap, setInventoryMap] = useState<Map<string, InventoryItem>>(new Map());
  const [serializedMap, setSerializedMap] = useState<Map<string, SerializedItem>>(new Map());
  const [exceptionMap, setExceptionMap] = useState<Map<string, ExceptionItem>>(new Map());
  const [lastScan, setLastScan] = useState<ScanResult | null>(null);
  const [isLoaded, setIsLoaded] = useState(false);

  // Detect inventory type based on CSV headers
  const detectMode = useCallback((headers: string[]): InventoryMode => {
    const lowerHeaders = headers.map(h => h.toLowerCase());
    // Check for serialized inventory markers
    if (lowerHeaders.some(h => h.includes('serial number') || h.includes('boundbook'))) {
      return 'serialized';
    }
    return 'upc';
  }, []);

  const loadCSV = useCallback((csvContent: string) => {
    const lines = csvContent.split('\n');
    if (lines.length < 2) return;

    const headers = parseCSVLine(lines[0]);
    const detectedMode = detectMode(headers);
    setMode(detectedMode);

    if (detectedMode === 'serialized') {
      loadSerializedCSV(headers, lines);
    } else {
      loadUPCCSV(headers, lines);
    }
  }, [detectMode]);

  const loadUPCCSV = useCallback((headers: string[], lines: string[]) => {
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
    setSerializedMap(new Map());
    setExceptionMap(new Map());
    setIsLoaded(true);
    setLastScan(null);
  }, []);

  const loadSerializedCSV = useCallback((headers: string[], lines: string[]) => {
    const newMap = new Map<string, SerializedItem>();

    // Find column indices for serialized inventory
    const serialIdx = headers.findIndex(h => h.toLowerCase().includes('serial number'));
    const boundBookIdIdx = headers.findIndex(h => h.toLowerCase().includes('boundbook id'));
    const boundBookIdx = headers.findIndex(h => h.toLowerCase() === 'boundbook');
    const manufacturerIdx = headers.findIndex(h => h.toLowerCase() === 'manufacturer');
    const modelIdx = headers.findIndex(h => h.toLowerCase() === 'model');
    const caliberIdx = headers.findIndex(h => h.toLowerCase() === 'caliber');
    const itemIdx = headers.findIndex(h => h.toLowerCase() === 'item');

    for (let i = 1; i < lines.length; i++) {
      const line = lines[i].trim();
      if (!line) continue;

      const values = parseCSVLine(line);
      const serialNumber = cleanSerial(values[serialIdx] || '');
      
      if (!serialNumber) continue;

      newMap.set(serialNumber, {
        serialNumber,
        boundBookId: values[boundBookIdIdx] || '',
        boundBook: values[boundBookIdx] || '',
        manufacturer: values[manufacturerIdx] || '',
        model: values[modelIdx] || '',
        caliber: values[caliberIdx] || '',
        itemDescription: values[itemIdx] || '',
        isScanned: false,
      });
    }

    setSerializedMap(newMap);
    setInventoryMap(new Map());
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

  const scanSerial = useCallback((serial: string): ScanResult | null => {
    const cleanedSerial = cleanSerial(serial);
    if (!cleanedSerial) return null;

    const now = new Date();

    // Check if serial exists in inventory
    if (serializedMap.has(cleanedSerial)) {
      const item = serializedMap.get(cleanedSerial)!;
      const wasAlreadyScanned = item.isScanned;

      setSerializedMap(prev => {
        const newMap = new Map(prev);
        newMap.set(cleanedSerial, {
          ...item,
          isScanned: true,
          scannedAt: now,
        });
        return newMap;
      });

      const result: ScanResult = {
        type: 'matched',
        upc: cleanedSerial,
        itemName: `${item.manufacturer} ${item.model}`,
        newCount: 1,
        alreadyScanned: wasAlreadyScanned,
      };
      setLastScan(result);
      return result;
    }

    // Add to exception list
    setExceptionMap(prev => {
      const newMap = new Map(prev);
      const existing = newMap.get(cleanedSerial);
      if (existing) {
        newMap.set(cleanedSerial, {
          ...existing,
          scannedQuantity: existing.scannedQuantity + 1,
          lastScanned: now,
        });
      } else {
        newMap.set(cleanedSerial, {
          upc: cleanedSerial,
          scannedQuantity: 1,
          firstScanned: now,
          lastScanned: now,
        });
      }
      return newMap;
    });

    const existing = exceptionMap.get(cleanedSerial);
    const result: ScanResult = {
      type: 'exception',
      upc: cleanedSerial,
      newCount: (existing?.scannedQuantity || 0) + 1,
    };
    setLastScan(result);
    return result;
  }, [serializedMap, exceptionMap]);

  // Unified scan function that routes based on mode
  const scan = useCallback((value: string): ScanResult | null => {
    if (mode === 'serialized') {
      return scanSerial(value);
    }
    return scanUPC(value);
  }, [mode, scanSerial, scanUPC]);

  const stats: ReconciliationStats = useMemo(() => {
    if (mode === 'serialized') {
      const items = Array.from(serializedMap.values());
      const scannedItems = items.filter(i => i.isScanned);
      const notScannedItems = items.filter(i => !i.isScanned);

      return {
        totalExpectedItems: items.length,
        totalScannedItems: scannedItems.length,
        matchedItems: scannedItems.length, // In serialized mode, scanned = matched
        discrepancyItems: 0, // No quantity discrepancies in serialized mode
        exceptionItems: exceptionMap.size,
        totalExpectedQuantity: items.length, // Each serial is quantity 1
        totalScannedQuantity: scannedItems.length + 
          Array.from(exceptionMap.values()).reduce((sum, i) => sum + i.scannedQuantity, 0),
        notScannedItems: notScannedItems.length,
      };
    }

    // UPC mode stats
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
  }, [mode, inventoryMap, serializedMap, exceptionMap]);

  const inventoryItems = useMemo(() => Array.from(inventoryMap.values()), [inventoryMap]);
  const serializedItems = useMemo(() => Array.from(serializedMap.values()), [serializedMap]);
  const exceptionItems = useMemo(() => Array.from(exceptionMap.values()), [exceptionMap]);

  const resetScans = useCallback(() => {
    if (mode === 'serialized') {
      setSerializedMap(prev => {
        const newMap = new Map(prev);
        newMap.forEach((item, key) => {
          newMap.set(key, { ...item, isScanned: false, scannedAt: undefined });
        });
        return newMap;
      });
    } else {
      setInventoryMap(prev => {
        const newMap = new Map(prev);
        newMap.forEach((item, key) => {
          newMap.set(key, { ...item, scannedQuantity: 0, lastScanned: undefined });
        });
        return newMap;
      });
    }
    setExceptionMap(new Map());
    setLastScan(null);
  }, [mode]);

  const clearAll = useCallback(() => {
    setInventoryMap(new Map());
    setSerializedMap(new Map());
    setExceptionMap(new Map());
    setLastScan(null);
    setIsLoaded(false);
    setMode('upc');
  }, []);

  return {
    mode,
    inventoryItems,
    serializedItems,
    exceptionItems,
    stats,
    lastScan,
    isLoaded,
    loadCSV,
    scan,
    scanUPC,
    scanSerial,
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

function cleanSerial(serial: string): string {
  // Remove BOM and quotes, trim whitespace, keep alphanumeric and common separators
  return serial.replace(/[\ufeff"']/g, '').trim().toUpperCase();
}
