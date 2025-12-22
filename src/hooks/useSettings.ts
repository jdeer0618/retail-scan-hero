import { useState, useCallback, useEffect } from 'react';
import { AppSettings, defaultSettings } from '@/types/settings';

const STORAGE_KEY = 'inventory-reconciliation-settings';

export function useSettings() {
  const [settings, setSettings] = useState<AppSettings>(() => {
    try {
      const stored = localStorage.getItem(STORAGE_KEY);
      if (stored) {
        return { ...defaultSettings, ...JSON.parse(stored) };
      }
    } catch (e) {
      console.error('Failed to load settings:', e);
    }
    return defaultSettings;
  });

  useEffect(() => {
    try {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(settings));
    } catch (e) {
      console.error('Failed to save settings:', e);
    }
  }, [settings]);

  const updateSettings = useCallback((updates: Partial<AppSettings>) => {
    setSettings(prev => ({ ...prev, ...updates }));
  }, []);

  const toggleSound = useCallback(() => {
    setSettings(prev => ({ ...prev, soundEnabled: !prev.soundEnabled }));
  }, []);

  const updateNocoDB = useCallback((config: AppSettings['nocodb']) => {
    setSettings(prev => ({ ...prev, nocodb: config }));
  }, []);

  const clearNocoDB = useCallback(() => {
    setSettings(prev => ({ ...prev, nocodb: null }));
  }, []);

  const isNocoDBConfigured = settings.nocodb !== null && 
    settings.nocodb.baseUrl.trim() !== '' && 
    settings.nocodb.apiToken.trim() !== '';

  return {
    settings,
    updateSettings,
    toggleSound,
    updateNocoDB,
    clearNocoDB,
    isNocoDBConfigured,
  };
}
