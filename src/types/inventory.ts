export type InventoryMode = 'upc' | 'serialized';

// UPC-based inventory item (quantity tracking)
export interface InventoryItem {
  upc: string;
  itemNumber: string;
  itemName: string;
  cost: number;
  sellingPrice: number;
  listPrice: number;
  expectedQuantity: number;
  scannedQuantity: number;
  category: string;
  lastScanned?: Date;
}

// Serialized inventory item (unique serial numbers)
export interface SerializedItem {
  serialNumber: string;
  boundBookId: string;
  boundBook: string;
  manufacturer: string;
  model: string;
  caliber: string;
  itemDescription: string;
  isScanned: boolean;
  scannedAt?: Date;
}

// Exception items work for both modes (scanned but not in CSV)
export interface ExceptionItem {
  upc: string; // Also used for serial numbers in serialized mode
  scannedQuantity: number;
  firstScanned: Date;
  lastScanned: Date;
}

export interface ScanResult {
  type: 'matched' | 'exception';
  upc: string; // Also used for serial numbers in serialized mode
  itemName?: string;
  newCount: number;
  alreadyScanned?: boolean; // For serialized mode - if item was already scanned
}

// Stats work for both modes with context-appropriate meanings
export interface ReconciliationStats {
  totalExpectedItems: number;
  totalScannedItems: number;
  matchedItems: number;
  discrepancyItems: number;
  exceptionItems: number;
  totalExpectedQuantity: number;
  totalScannedQuantity: number;
  // Serialized-specific stats
  notScannedItems?: number;
}
