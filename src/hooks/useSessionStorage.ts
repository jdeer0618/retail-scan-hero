import { useCallback } from 'react';
import { InventoryItem, ExceptionItem } from '@/types/inventory';

const INVENTORY_KEY = 'inventory-reconciliation-inventory';
const EXCEPTIONS_KEY = 'inventory-reconciliation-exceptions';

export function useSessionStorage() {
  const saveInventory = useCallback((items: InventoryItem[]) => {
    try {
      sessionStorage.setItem(INVENTORY_KEY, JSON.stringify(items));
    } catch (e) {
      console.error('Failed to save inventory to session:', e);
    }
  }, []);

  const loadInventory = useCallback((): InventoryItem[] | null => {
    try {
      const stored = sessionStorage.getItem(INVENTORY_KEY);
      if (stored) {
        return JSON.parse(stored);
      }
    } catch (e) {
      console.error('Failed to load inventory from session:', e);
    }
    return null;
  }, []);

  const saveExceptions = useCallback((items: ExceptionItem[]) => {
    try {
      sessionStorage.setItem(EXCEPTIONS_KEY, JSON.stringify(items));
    } catch (e) {
      console.error('Failed to save exceptions to session:', e);
    }
  }, []);

  const loadExceptions = useCallback((): ExceptionItem[] | null => {
    try {
      const stored = sessionStorage.getItem(EXCEPTIONS_KEY);
      if (stored) {
        return JSON.parse(stored);
      }
    } catch (e) {
      console.error('Failed to load exceptions from session:', e);
    }
    return null;
  }, []);

  const clearSession = useCallback(() => {
    sessionStorage.removeItem(INVENTORY_KEY);
    sessionStorage.removeItem(EXCEPTIONS_KEY);
  }, []);

  return {
    saveInventory,
    loadInventory,
    saveExceptions,
    loadExceptions,
    clearSession,
  };
}
