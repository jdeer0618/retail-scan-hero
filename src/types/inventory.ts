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

export interface ExceptionItem {
  upc: string;
  scannedQuantity: number;
  firstScanned: Date;
  lastScanned: Date;
}

export interface ScanResult {
  type: 'matched' | 'exception';
  upc: string;
  itemName?: string;
  newCount: number;
}

export interface ReconciliationStats {
  totalExpectedItems: number;
  totalScannedItems: number;
  matchedItems: number;
  discrepancyItems: number;
  exceptionItems: number;
  totalExpectedQuantity: number;
  totalScannedQuantity: number;
}
