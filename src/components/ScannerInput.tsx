import { useState, useCallback, useRef, useEffect } from 'react';
import { Scan, Check, AlertTriangle, RefreshCw, Upload } from 'lucide-react';
import { ScanResult, InventoryMode } from '@/types/inventory';
import { Button } from '@/components/ui/button';

interface ScannerInputProps {
  onScan: (value: string) => ScanResult | null;
  lastScan: ScanResult | null;
  disabled?: boolean;
  onScanComplete?: (result: ScanResult) => void;
  mode: InventoryMode;
}

interface BulkScanSummary {
  total: number;
  matched: number;
  exceptions: number;
  duplicates: number;
}

export function ScannerInput({ onScan, lastScan, disabled, onScanComplete, mode }: ScannerInputProps) {
  const [value, setValue] = useState('');
  const [isPulsing, setIsPulsing] = useState(false);
  const [bulkSummary, setBulkSummary] = useState<BulkScanSummary | null>(null);
  const [isProcessing, setIsProcessing] = useState(false);
  const textareaRef = useRef<HTMLTextAreaElement>(null);

  const isSerialMode = mode === 'serialized';
  const placeholderText = disabled 
    ? "Load inventory CSV to begin scanning..." 
    : isSerialMode 
      ? "Paste or scan serial numbers (one per line)..." 
      : "Paste or scan barcodes/UPCs (one per line)...";

  // Keep input focused
  useEffect(() => {
    if (!disabled) {
      textareaRef.current?.focus();
    }
  }, [disabled, lastScan]);

  // Re-focus on click anywhere in the scanner area
  const handleContainerClick = useCallback(() => {
    textareaRef.current?.focus();
  }, []);

  const processBulkScans = useCallback(() => {
    if (!value.trim() || isProcessing) return;

    setIsProcessing(true);
    setBulkSummary(null);

    const lines = value.split('\n').map(line => line.trim()).filter(line => line.length > 0);
    
    const summary: BulkScanSummary = {
      total: lines.length,
      matched: 0,
      exceptions: 0,
      duplicates: 0
    };

    let lastResult: ScanResult | null = null;

    for (const line of lines) {
      const result = onScan(line);
      if (result) {
        lastResult = result;
        if (result.type === 'matched') {
          if (result.alreadyScanned) {
            summary.duplicates++;
          } else {
            summary.matched++;
          }
        } else {
          summary.exceptions++;
        }
      }
    }

    setValue('');
    setIsPulsing(true);
    setTimeout(() => setIsPulsing(false), 300);
    setBulkSummary(summary);
    setIsProcessing(false);

    if (lastResult && onScanComplete) {
      onScanComplete(lastResult);
    }
  }, [value, onScan, onScanComplete, isProcessing]);

  const handleKeyDown = useCallback((e: React.KeyboardEvent<HTMLTextAreaElement>) => {
    // Process on Ctrl+Enter or Cmd+Enter
    if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
      e.preventDefault();
      processBulkScans();
    }
  }, [processBulkScans]);

  const handleChange = useCallback((e: React.ChangeEvent<HTMLTextAreaElement>) => {
    setValue(e.target.value);
    // Clear bulk summary when user starts typing again
    if (bulkSummary) {
      setBulkSummary(null);
    }
  }, [bulkSummary]);

  const lineCount = value.split('\n').filter(line => line.trim().length > 0).length;

  const getStatusMessage = () => {
    if (!lastScan) return null;
    
    if (lastScan.type === 'exception') {
      return isSerialMode 
        ? 'Serial not in inventory - added to exceptions' 
        : 'Not in inventory - added to exceptions';
    }
    
    if (isSerialMode && lastScan.alreadyScanned) {
      return 'Already scanned - duplicate scan';
    }
    
    return null;
  };

  return (
    <div 
      className="stat-card cursor-text"
      onClick={handleContainerClick}
    >
      <div className="flex items-center gap-3 mb-4">
        <Scan className="w-5 h-5 text-primary" />
        <h2 className="font-semibold">
          {isSerialMode ? 'Serial Scanner' : 'Barcode Scanner'}
        </h2>
        {disabled && (
          <span className="text-xs text-muted-foreground ml-auto">Load CSV first</span>
        )}
      </div>

      <div className={`relative ${isPulsing ? 'pulse-scan' : ''}`}>
        <textarea
          ref={textareaRef}
          value={value}
          onChange={handleChange}
          onKeyDown={handleKeyDown}
          disabled={disabled || isProcessing}
          placeholder={placeholderText}
          rows={4}
          className="w-full scanner-input rounded-lg px-4 py-3 outline-none disabled:opacity-50 disabled:cursor-not-allowed resize-none font-mono text-sm"
          autoComplete="off"
          autoCorrect="off"
          autoCapitalize="off"
          spellCheck={false}
        />
        {lineCount > 0 && (
          <div className="absolute bottom-2 right-2 flex items-center gap-2">
            <span className="text-xs text-muted-foreground bg-background/80 px-2 py-1 rounded">
              {lineCount} item{lineCount !== 1 ? 's' : ''}
            </span>
          </div>
        )}
      </div>

      {lineCount > 0 && (
        <div className="mt-3 flex items-center justify-between">
          <span className="text-xs text-muted-foreground">
            Press <kbd className="px-1.5 py-0.5 bg-muted rounded text-xs font-mono">Ctrl</kbd> + <kbd className="px-1.5 py-0.5 bg-muted rounded text-xs font-mono">Enter</kbd> or click button to process
          </span>
          <Button 
            onClick={processBulkScans} 
            disabled={isProcessing}
            size="sm"
            className="gap-2"
          >
            <Upload className="w-4 h-4" />
            Process {lineCount} Scan{lineCount !== 1 ? 's' : ''}
          </Button>
        </div>
      )}

      {bulkSummary && (
        <div className="mt-4 p-3 rounded-lg border bg-muted/50 fade-in">
          <div className="flex items-center gap-2 mb-2">
            <Check className="w-5 h-5 text-primary" />
            <span className="font-semibold">Bulk Scan Complete</span>
          </div>
          <div className="grid grid-cols-2 gap-2 text-sm">
            <div>Total processed: <span className="font-mono font-medium">{bulkSummary.total}</span></div>
            <div className="text-green-600 dark:text-green-400">Matched: <span className="font-mono font-medium">{bulkSummary.matched}</span></div>
            {bulkSummary.duplicates > 0 && (
              <div className="text-amber-600 dark:text-amber-400">Duplicates: <span className="font-mono font-medium">{bulkSummary.duplicates}</span></div>
            )}
            {bulkSummary.exceptions > 0 && (
              <div className="text-red-600 dark:text-red-400">Exceptions: <span className="font-mono font-medium">{bulkSummary.exceptions}</span></div>
            )}
          </div>
        </div>
      )}

      {lastScan && (
        <div className={`mt-4 p-3 rounded-lg border fade-in ${
          lastScan.type === 'matched' 
            ? lastScan.alreadyScanned 
              ? 'status-warning' 
              : 'status-matched' 
            : 'status-exception'
        }`}>
          <div className="flex items-center gap-2">
            {lastScan.type === 'matched' ? (
              lastScan.alreadyScanned ? (
                <RefreshCw className="w-5 h-5" />
              ) : (
                <Check className="w-5 h-5" />
              )
            ) : (
              <AlertTriangle className="w-5 h-5" />
            )}
            <span className="font-mono font-medium">{lastScan.upc}</span>
            {!isSerialMode && (
              <span className="ml-auto font-mono text-lg">×{lastScan.newCount}</span>
            )}
          </div>
          {lastScan.itemName && (
            <p className="text-sm mt-1 opacity-80 truncate">{lastScan.itemName}</p>
          )}
          {getStatusMessage() && (
            <p className="text-sm mt-1 opacity-80">{getStatusMessage()}</p>
          )}
        </div>
      )}
    </div>
  );
}
