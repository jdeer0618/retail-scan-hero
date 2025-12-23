import { useState, useCallback, useRef, useEffect } from 'react';
import { Scan, Check, AlertTriangle, RefreshCw } from 'lucide-react';
import { ScanResult, InventoryMode } from '@/types/inventory';

interface ScannerInputProps {
  onScan: (value: string) => ScanResult | null;
  lastScan: ScanResult | null;
  disabled?: boolean;
  onScanComplete?: (result: ScanResult) => void;
  mode: InventoryMode;
}

export function ScannerInput({ onScan, lastScan, disabled, onScanComplete, mode }: ScannerInputProps) {
  const [value, setValue] = useState('');
  const [isPulsing, setIsPulsing] = useState(false);
  const inputRef = useRef<HTMLInputElement>(null);

  const isSerialMode = mode === 'serialized';
  const placeholderText = disabled 
    ? "Load inventory CSV to begin scanning..." 
    : isSerialMode 
      ? "Scan serial number..." 
      : "Scan barcode or enter UPC...";

  // Keep input focused
  useEffect(() => {
    if (!disabled) {
      inputRef.current?.focus();
    }
  }, [disabled, lastScan]);

  // Re-focus on click anywhere in the scanner area
  const handleContainerClick = useCallback(() => {
    inputRef.current?.focus();
  }, []);

  const handleKeyDown = useCallback((e: React.KeyboardEvent<HTMLInputElement>) => {
    if (e.key === 'Enter' && value.trim()) {
      const result = onScan(value.trim());
      setValue('');
      setIsPulsing(true);
      setTimeout(() => setIsPulsing(false), 300);
      if (result && onScanComplete) {
        onScanComplete(result);
      }
    }
  }, [value, onScan, onScanComplete]);

  const handleChange = useCallback((e: React.ChangeEvent<HTMLInputElement>) => {
    setValue(e.target.value);
  }, []);

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
        <input
          ref={inputRef}
          type="text"
          value={value}
          onChange={handleChange}
          onKeyDown={handleKeyDown}
          disabled={disabled}
          placeholder={placeholderText}
          className="w-full scanner-input rounded-lg px-4 py-4 outline-none disabled:opacity-50 disabled:cursor-not-allowed"
          autoComplete="off"
          autoCorrect="off"
          autoCapitalize="off"
          spellCheck={false}
        />
      </div>

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
