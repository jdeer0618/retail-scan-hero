import { useState } from 'react';
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Settings, Volume2, VolumeX, Database, Trash2 } from 'lucide-react';
import { AppSettings } from '@/types/settings';

interface SettingsDialogProps {
  settings: AppSettings;
  onToggleSound: () => void;
  onUpdateNocoDB: (config: AppSettings['nocodb']) => void;
  onClearNocoDB: () => void;
  isNocoDBConfigured: boolean;
}

export function SettingsDialog({
  settings,
  onToggleSound,
  onUpdateNocoDB,
  onClearNocoDB,
  isNocoDBConfigured,
}: SettingsDialogProps) {
  const [open, setOpen] = useState(false);
  const [baseUrl, setBaseUrl] = useState(settings.nocodb?.baseUrl || '');
  const [apiToken, setApiToken] = useState(settings.nocodb?.apiToken || '');
  const [tableId, setTableId] = useState(settings.nocodb?.tableId || '');

  const handleSaveNocoDB = () => {
    if (baseUrl.trim() && apiToken.trim()) {
      onUpdateNocoDB({
        baseUrl: baseUrl.trim(),
        apiToken: apiToken.trim(),
        tableId: tableId.trim(),
      });
    }
  };

  const handleClearNocoDB = () => {
    onClearNocoDB();
    setBaseUrl('');
    setApiToken('');
    setTableId('');
  };

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>
        <Button variant="outline" size="sm">
          <Settings className="w-4 h-4 mr-2" />
          Settings
        </Button>
      </DialogTrigger>
      <DialogContent className="sm:max-w-[500px]">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <Settings className="w-5 h-5" />
            Application Settings
          </DialogTitle>
        </DialogHeader>

        <div className="space-y-6 py-4">
          {/* Sound Settings */}
          <div className="space-y-3">
            <h3 className="text-sm font-medium flex items-center gap-2">
              {settings.soundEnabled ? (
                <Volume2 className="w-4 h-4 text-primary" />
              ) : (
                <VolumeX className="w-4 h-4 text-muted-foreground" />
              )}
              Scan Sounds
            </h3>
            <div className="flex items-center justify-between p-3 rounded-lg bg-muted/50 border border-border">
              <div>
                <p className="text-sm font-medium">Enable scan sounds</p>
                <p className="text-xs text-muted-foreground">
                  Play audio feedback when scanning barcodes
                </p>
              </div>
              <Switch
                checked={settings.soundEnabled}
                onCheckedChange={onToggleSound}
              />
            </div>
          </div>

          {/* NocoDB Settings */}
          <div className="space-y-3">
            <h3 className="text-sm font-medium flex items-center gap-2">
              <Database className="w-4 h-4 text-primary" />
              NocoDB Connection
              {isNocoDBConfigured && (
                <span className="text-xs bg-green-500/20 text-green-400 px-2 py-0.5 rounded">
                  Connected
                </span>
              )}
            </h3>
            <p className="text-xs text-muted-foreground">
              Connect to your locally hosted NocoDB instance. If not configured, data will be stored in browser session.
            </p>

            <div className="space-y-3 p-3 rounded-lg bg-muted/50 border border-border">
              <div className="space-y-2">
                <Label htmlFor="baseUrl">Base URL</Label>
                <Input
                  id="baseUrl"
                  placeholder="http://localhost:8080"
                  value={baseUrl}
                  onChange={(e) => setBaseUrl(e.target.value)}
                />
              </div>

              <div className="space-y-2">
                <Label htmlFor="apiToken">API Token</Label>
                <Input
                  id="apiToken"
                  type="password"
                  placeholder="Enter your NocoDB API token"
                  value={apiToken}
                  onChange={(e) => setApiToken(e.target.value)}
                />
              </div>

              <div className="space-y-2">
                <Label htmlFor="tableId">Table ID (optional)</Label>
                <Input
                  id="tableId"
                  placeholder="Table identifier"
                  value={tableId}
                  onChange={(e) => setTableId(e.target.value)}
                />
              </div>

              <div className="flex gap-2 pt-2">
                <Button onClick={handleSaveNocoDB} size="sm" className="flex-1">
                  Save Connection
                </Button>
                {isNocoDBConfigured && (
                  <Button
                    onClick={handleClearNocoDB}
                    variant="outline"
                    size="sm"
                  >
                    <Trash2 className="w-4 h-4" />
                  </Button>
                )}
              </div>
            </div>

            {!isNocoDBConfigured && (
              <p className="text-xs text-amber-400 flex items-center gap-1">
                ⚠️ No database configured - using browser session storage
              </p>
            )}
          </div>
        </div>
      </DialogContent>
    </Dialog>
  );
}
