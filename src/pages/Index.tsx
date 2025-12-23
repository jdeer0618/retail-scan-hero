import { useInventory } from '@/hooks/useInventory';
import { useSettings } from '@/hooks/useSettings';
import { useScanSound } from '@/hooks/useScanSound';
import { CSVUploader } from '@/components/CSVUploader';
import { ScannerInput } from '@/components/ScannerInput';
import { StatsPanel } from '@/components/StatsPanel';
import { InventoryTable } from '@/components/InventoryTable';
import { SerializedTable } from '@/components/SerializedTable';
import { ExceptionList } from '@/components/ExceptionList';
import { SettingsDialog } from '@/components/SettingsDialog';
import { Button } from '@/components/ui/button';
import { RotateCcw, Trash2, Package, FileDown, Crosshair } from 'lucide-react';
import { generateDiscrepancyReport } from '@/utils/generatePDF';
import { ScanResult } from '@/types/inventory';

const Index = () => {
  const {
    mode,
    inventoryItems,
    serializedItems,
    exceptionItems,
    stats,
    lastScan,
    isLoaded,
    loadCSV,
    scan,
    resetScans,
    clearAll,
  } = useInventory();

  const {
    settings,
    toggleSound,
    updateNocoDB,
    clearNocoDB,
    isNocoDBConfigured,
  } = useSettings();

  const { playSuccess, playException } = useScanSound(settings.soundEnabled);

  const handleScanComplete = (result: ScanResult) => {
    if (result.type === 'matched') {
      playSuccess();
    } else {
      playException();
    }
  };

  const handleExportPDF = () => {
    generateDiscrepancyReport(inventoryItems, serializedItems, exceptionItems, mode);
  };

  const isSerialMode = mode === 'serialized';

  return (
    <div className="min-h-screen bg-background">
      {/* Header */}
      <header className="border-b border-border bg-card/50 backdrop-blur-sm sticky top-0 z-10">
        <div className="container mx-auto px-4 py-4">
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-3">
              <div className="p-2 rounded-lg bg-primary/20">
                {isSerialMode ? (
                  <Crosshair className="w-6 h-6 text-primary" />
                ) : (
                  <Package className="w-6 h-6 text-primary" />
                )}
              </div>
              <div>
                <h1 className="text-xl font-bold">
                  {isSerialMode ? 'Serialized Inventory' : 'Inventory Reconciliation'}
                </h1>
                <p className="text-sm text-muted-foreground">
                  {isSerialMode 
                    ? 'Scan & verify serial numbers' 
                    : 'Scan & compare physical inventory'
                  }
                </p>
              </div>
              {isLoaded && (
                <span className={`ml-3 px-2 py-1 rounded-full text-xs font-medium ${
                  isSerialMode 
                    ? 'bg-accent/20 text-accent' 
                    : 'bg-primary/20 text-primary'
                }`}>
                  {isSerialMode ? 'Serial Mode' : 'UPC Mode'}
                </span>
              )}
            </div>

            <div className="flex gap-2">
              <SettingsDialog
                settings={settings}
                onToggleSound={toggleSound}
                onUpdateNocoDB={updateNocoDB}
                onClearNocoDB={clearNocoDB}
                isNocoDBConfigured={isNocoDBConfigured}
              />

              {isLoaded && (
                <>
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={handleExportPDF}
                    title="Export report to PDF"
                  >
                    <FileDown className="w-4 h-4 mr-2" />
                    Export PDF
                  </Button>
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={resetScans}
                    title="Reset all scan counts"
                  >
                    <RotateCcw className="w-4 h-4 mr-2" />
                    Reset Scans
                  </Button>
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={clearAll}
                    title="Clear inventory and start over"
                  >
                    <Trash2 className="w-4 h-4 mr-2" />
                    Clear All
                  </Button>
                </>
              )}
            </div>
          </div>
        </div>
      </header>

      {/* Main Content */}
      <main className="container mx-auto px-4 py-6 space-y-6">
        {/* Top Section: CSV Upload and Scanner */}
        <div className="grid lg:grid-cols-2 gap-6">
          <CSVUploader
            onUpload={loadCSV}
            isLoaded={isLoaded}
            itemCount={stats.totalExpectedItems}
          />
          <ScannerInput
            onScan={scan}
            lastScan={lastScan}
            disabled={!isLoaded}
            onScanComplete={handleScanComplete}
            mode={mode}
          />
        </div>

        {/* Stats Panel */}
        {isLoaded && <StatsPanel stats={stats} mode={mode} />}

        {/* Exception List */}
        <ExceptionList items={exceptionItems} mode={mode} />

        {/* Inventory Table - show appropriate table based on mode */}
        {isLoaded && (
          isSerialMode 
            ? <SerializedTable items={serializedItems} />
            : <InventoryTable items={inventoryItems} />
        )}
      </main>

      {/* Footer */}
      <footer className="border-t border-border mt-8 py-4">
        <div className="container mx-auto px-4 text-center text-sm text-muted-foreground">
          Inventory Reconciliation Tool • {isSerialMode ? 'Serial Number Mode' : 'UPC Scanning Mode'}
          {!isNocoDBConfigured && (
            <span className="ml-2 text-amber-400">• Using browser session storage</span>
          )}
        </div>
      </footer>
    </div>
  );
};

export default Index;
