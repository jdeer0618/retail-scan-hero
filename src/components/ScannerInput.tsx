import { useState, useCallback, useRef, useEffect } from 'react';
import { Scan, Check, AlertTriangle } from 'lucide-react';
import { ScanResult } from '@/types/inventory';

interface ScannerInputProps {
  onScan: (upc: string) => ScanResult | null;
  lastScan: ScanResult | null;
  disabled?: boolean;
  onScanComplete?: (result: ScanResult) => void;
}

export function ScannerInput({ onScan, lastScan, disabled, onScanComplete }: ScannerInputProps) {
  const [value, setValue] = useState('');
  const [isPulsing, setIsPulsing] = useState(false);
  const inputRef = useRef<HTMLInputElement>(null);

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

  return (
    <div 
      className="stat-card cursor-text"
      onClick={handleContainerClick}
    >
      <div className="flex items-center gap-3 mb-4">
        <Scan className="w-5 h-5 text-primary" />
        <h2 className="font-semibold">Barcode Scanner</h2>
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
          placeholder={disabled ? "Load inventory CSV to begin scanning..." : "Scan barcode or enter UPC..."}
          className="w-full scanner-input rounded-lg px-4 py-4 outline-none disabled:opacity-50 disabled:cursor-not-allowed"
          autoComplete="off"
          autoCorrect="off"
          autoCapitalize="off"
          spellCheck={false}
        />
      </div>

      {lastScan && (
        <div className={`mt-4 p-3 rounded-lg border fade-in ${
          lastScan.type === 'matched' ? 'status-matched' : 'status-exception'
        }`}>
          <div className="flex items-center gap-2">
            {lastScan.type === 'matched' ? (
              <Check className="w-5 h-5" />
            ) : (
              <AlertTriangle className="w-5 h-5" />
            )}
            <span className="font-mono font-medium">{lastScan.upc}</span>
            <span className="ml-auto font-mono text-lg">×{lastScan.newCount}</span>
          </div>
          {lastScan.itemName && (
            <p className="text-sm mt-1 opacity-80 truncate">{lastScan.itemName}</p>
          )}
          {lastScan.type === 'exception' && (
            <p className="text-sm mt-1 opacity-80">Not in inventory - added to exceptions</p>
          )}
        </div>
      )}
    </div>
  );
}
