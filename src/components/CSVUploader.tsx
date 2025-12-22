import { useCallback, useRef } from 'react';
import { Upload, FileSpreadsheet } from 'lucide-react';
import { Button } from '@/components/ui/button';

interface CSVUploaderProps {
  onUpload: (content: string) => void;
  isLoaded: boolean;
  itemCount: number;
}

export function CSVUploader({ onUpload, isLoaded, itemCount }: CSVUploaderProps) {
  const fileInputRef = useRef<HTMLInputElement>(null);

  const handleFileChange = useCallback((e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = (event) => {
      const content = event.target?.result as string;
      onUpload(content);
    };
    reader.readAsText(file);
    
    // Reset input so same file can be selected again
    e.target.value = '';
  }, [onUpload]);

  const handleDrop = useCallback((e: React.DragEvent) => {
    e.preventDefault();
    const file = e.dataTransfer.files[0];
    if (!file || !file.name.endsWith('.csv')) return;

    const reader = new FileReader();
    reader.onload = (event) => {
      const content = event.target?.result as string;
      onUpload(content);
    };
    reader.readAsText(file);
  }, [onUpload]);

  const handleDragOver = useCallback((e: React.DragEvent) => {
    e.preventDefault();
  }, []);

  if (isLoaded) {
    return (
      <div className="stat-card flex items-center gap-4">
        <div className="p-3 rounded-lg bg-primary/20">
          <FileSpreadsheet className="w-6 h-6 text-primary" />
        </div>
        <div className="flex-1">
          <p className="text-sm text-muted-foreground">Inventory Loaded</p>
          <p className="text-2xl font-semibold font-mono">{itemCount.toLocaleString()} items</p>
        </div>
        <Button
          variant="outline"
          size="sm"
          onClick={() => fileInputRef.current?.click()}
        >
          Replace
        </Button>
        <input
          ref={fileInputRef}
          type="file"
          accept=".csv"
          onChange={handleFileChange}
          className="hidden"
        />
      </div>
    );
  }

  return (
    <div
      onDrop={handleDrop}
      onDragOver={handleDragOver}
      className="border-2 border-dashed border-border rounded-lg p-8 text-center hover:border-primary/50 transition-colors cursor-pointer"
      onClick={() => fileInputRef.current?.click()}
    >
      <Upload className="w-12 h-12 mx-auto mb-4 text-muted-foreground" />
      <h3 className="text-lg font-medium mb-2">Upload Inventory CSV</h3>
      <p className="text-muted-foreground text-sm mb-4">
        Drag and drop your CSV file here, or click to browse
      </p>
      <Button variant="secondary">
        Select CSV File
      </Button>
      <input
        ref={fileInputRef}
        type="file"
        accept=".csv"
        onChange={handleFileChange}
        className="hidden"
      />
    </div>
  );
}
